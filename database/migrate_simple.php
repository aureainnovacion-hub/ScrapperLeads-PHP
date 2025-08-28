<?php
/**
 * Script de Migración Simple - ScrapperLeads Pro
 * Ejecuta directamente en el hosting de Dinahosting
 */

// Configuración directa para Dinahosting
$host = 'localhost';
$dbname = 'scrapperleads';
$username = 'eduai_';
$password = 'Mm492557**';

echo "<h1>Migración de Base de Datos - ScrapperLeads Pro</h1>\n";
echo "<p><strong>Hosting:</strong> Dinahosting MariaDB 11.4</p>\n";
echo "<p><strong>Base de datos:</strong> $dbname</p>\n";

try {
    // Conectar a la base de datos
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    echo "<p style='color: green;'>✅ Conexión a MariaDB establecida</p>\n";
    
    // Verificar versión de la base de datos
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch()['version'];
    echo "<p><strong>Versión MariaDB:</strong> $version</p>\n";
    
    // Crear tablas principales
    createTables($pdo);
    
    // Insertar datos iniciales
    insertInitialData($pdo);
    
    // Crear datos de ejemplo si se solicita
    if (isset($_GET['sample'])) {
        createSampleData($pdo);
    }
    
    echo "<h2 style='color: green;'>🎉 Migración Completada Exitosamente</h2>\n";
    echo "<p><a href='/'>← Volver a ScrapperLeads Pro</a></p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

function createTables($pdo) {
    echo "<h3>📋 Creando Tablas</h3>\n";
    
    // Tabla searches
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `searches` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `search_uuid` varchar(36) NOT NULL UNIQUE,
        `keywords` varchar(500) NOT NULL,
        `sector` varchar(100) DEFAULT NULL,
        `province` varchar(100) DEFAULT NULL,
        `region` varchar(100) DEFAULT NULL,
        `employees_range` varchar(50) DEFAULT NULL,
        `revenue_range` varchar(50) DEFAULT NULL,
        `max_results` int(11) DEFAULT 100,
        `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
        `progress` int(11) DEFAULT 0,
        `total_found` int(11) DEFAULT 0,
        `results_processed` int(11) DEFAULT 0,
        `started_at` timestamp NULL DEFAULT NULL,
        `completed_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_search_uuid` (`search_uuid`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ Tabla 'searches' creada</p>\n";
    
    // Tabla leads
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `leads` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `search_id` int(11) NOT NULL,
        `company_name` varchar(255) NOT NULL,
        `contact_name` varchar(255) DEFAULT NULL,
        `address` text DEFAULT NULL,
        `phone` varchar(50) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `website` varchar(255) DEFAULT NULL,
        `employees_count` varchar(50) DEFAULT NULL,
        `revenue` varchar(100) DEFAULT NULL,
        `sector` varchar(100) DEFAULT NULL,
        `province` varchar(100) DEFAULT NULL,
        `region` varchar(100) DEFAULT NULL,
        `founded_date` date DEFAULT NULL,
        `description` text DEFAULT NULL,
        `source_url` varchar(500) DEFAULT NULL,
        `confidence_score` decimal(3,2) DEFAULT 0.00,
        `data_quality` enum('high','medium','low') DEFAULT 'medium',
        `is_verified` tinyint(1) DEFAULT 0,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_search_id` (`search_id`),
        KEY `idx_company_name` (`company_name`),
        KEY `idx_email` (`email`),
        KEY `idx_phone` (`phone`),
        KEY `idx_sector` (`sector`),
        KEY `idx_province` (`province`),
        KEY `idx_confidence_score` (`confidence_score`),
        KEY `idx_created_at` (`created_at`),
        CONSTRAINT `fk_leads_search` FOREIGN KEY (`search_id`) REFERENCES `searches` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ Tabla 'leads' creada</p>\n";
    
    // Tabla search_logs
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `search_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `search_id` int(11) NOT NULL,
        `level` enum('info','warning','error','debug') DEFAULT 'info',
        `message` text NOT NULL,
        `context` json DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_search_id` (`search_id`),
        KEY `idx_level` (`level`),
        KEY `idx_created_at` (`created_at`),
        CONSTRAINT `fk_logs_search` FOREIGN KEY (`search_id`) REFERENCES `searches` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ Tabla 'search_logs' creada</p>\n";
    
    // Tabla system_config
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS `system_config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `config_key` varchar(100) NOT NULL UNIQUE,
        `config_value` text DEFAULT NULL,
        `description` varchar(255) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_config_key` (`config_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✅ Tabla 'system_config' creada</p>\n";
}

function insertInitialData($pdo) {
    echo "<h3>⚙️ Insertando Configuración Inicial</h3>\n";
    
    $configs = [
        ['app_name', 'ScrapperLeads Pro', 'Nombre de la aplicación'],
        ['app_version', '1.0.0', 'Versión actual de la aplicación'],
        ['max_results_per_search', '100', 'Máximo número de resultados por búsqueda'],
        ['scraping_delay', '2', 'Delay en segundos entre requests de scraping'],
        ['default_timeout', '30', 'Timeout por defecto para requests HTTP'],
        ['enable_logging', '1', 'Habilitar sistema de logging'],
        ['log_level', 'info', 'Nivel de logging (debug, info, warning, error)']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO system_config (config_key, config_value, description) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        config_value = VALUES(config_value),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    foreach ($configs as $config) {
        $stmt->execute($config);
    }
    
    echo "<p>✅ Configuración inicial insertada (" . count($configs) . " elementos)</p>\n";
}

function createSampleData($pdo) {
    echo "<h3>📝 Creando Datos de Ejemplo</h3>\n";
    
    try {
        // Crear búsqueda de ejemplo
        $searchUuid = uniqid('search_', true);
        $stmt = $pdo->prepare("
            INSERT INTO searches (search_uuid, keywords, sector, province, max_results, status, total_found, results_processed) 
            VALUES (?, 'consultoría tecnológica', 'Tecnología', 'Madrid', 10, 'completed', 2, 2)
        ");
        $stmt->execute([$searchUuid]);
        $searchId = $pdo->lastInsertId();
        
        // Crear leads de ejemplo
        $sampleLeads = [
            [
                'TechConsult Madrid SL',
                'Juan Pérez',
                'Calle Gran Vía 123, 28013 Madrid',
                '+34 91 123 4567',
                'info@techconsult.es',
                'https://techconsult.es',
                '11-50',
                '1M-5M €',
                'Tecnología',
                'Madrid',
                'Centro',
                '2015-03-15'
            ],
            [
                'Innovación Digital SA',
                'María García',
                'Paseo de la Castellana 456, 28046 Madrid',
                '+34 91 987 6543',
                'contacto@innovaciondigital.com',
                'https://innovaciondigital.com',
                '51-200',
                '5M-10M €',
                'Tecnología',
                'Madrid',
                'Centro',
                '2012-07-22'
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO leads (
                search_id, company_name, contact_name, address, phone, email, 
                website, employees_count, revenue, sector, province, region, 
                founded_date, confidence_score, data_quality
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.85, 'high')
        ");
        
        foreach ($sampleLeads as $lead) {
            $stmt->execute(array_merge([$searchId], $lead));
        }
        
        echo "<p>✅ Datos de ejemplo creados: 1 búsqueda, " . count($sampleLeads) . " leads</p>\n";
        
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠️ Error creando datos de ejemplo: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
}
?>

