<?php

/**
 * Script de Migración de Base de Datos
 * Crea las tablas necesarias para el sistema ScrapperLeads
 */

require_once __DIR__ . '/../config/config.php';

use ScrapperLeads\Config\Config;

try {
    $config = Config::getInstance();
    
    echo "🗄️ Iniciando migración de base de datos...\n";
    
    // Conectar a la base de datos
    $dsn = sprintf(
        "mysql:host=%s;port=%s;charset=%s",
        $config->get('database.host'),
        $config->get('database.port', 3306),
        $config->get('database.charset', 'utf8mb4')
    );
    
    $pdo = new PDO(
        $dsn,
        $config->get('database.user'),
        $config->get('database.password'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $config->get('database.charset', 'utf8mb4')
        ]
    );
    
    // Crear base de datos si no existe
    $dbName = $config->get('database.name');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
    
    echo "✅ Conectado a la base de datos: {$dbName}\n";
    
    // Obtener prefijo de tablas
    $prefix = $config->get('database.prefix', 'sl_');
    
    // Crear tabla de leads
    createLeadsTable($pdo, $prefix);
    
    // Crear tabla de sesiones de scraping
    createScrapingSessionsTable($pdo, $prefix);
    
    // Crear tabla de configuraciones
    createConfigTable($pdo, $prefix);
    
    // Crear tabla de logs
    createLogsTable($pdo, $prefix);
    
    // Insertar datos iniciales
    insertInitialData($pdo, $prefix);
    
    echo "🎉 Migración completada exitosamente!\n";
    
} catch (Exception $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Crea la tabla de leads
 */
function createLeadsTable(PDO $pdo, string $prefix): void
{
    echo "📋 Creando tabla de leads...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$prefix}leads` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `session_id` varchar(100) NOT NULL,
        `empresa` varchar(255) NOT NULL,
        `url` text,
        `descripcion` text,
        `telefono` varchar(50),
        `email` varchar(255),
        `direccion` text,
        `empleados` varchar(50),
        `facturacion` varchar(50),
        `sector` varchar(100),
        `provincia` varchar(100),
        `region` varchar(100),
        `fecha_captura` datetime NOT NULL,
        `fuente` varchar(100) NOT NULL DEFAULT 'Google Search',
        `estado` enum('activo','inactivo','verificado') NOT NULL DEFAULT 'activo',
        `notas` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_session_id` (`session_id`),
        KEY `idx_empresa` (`empresa`),
        KEY `idx_sector` (`sector`),
        KEY `idx_provincia` (`provincia`),
        KEY `idx_fecha_captura` (`fecha_captura`),
        KEY `idx_estado` (`estado`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "✅ Tabla de leads creada\n";
}

/**
 * Crea la tabla de sesiones de scraping
 */
function createScrapingSessionsTable(PDO $pdo, string $prefix): void
{
    echo "🔄 Creando tabla de sesiones de scraping...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$prefix}scraping_sessions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `session_id` varchar(100) NOT NULL UNIQUE,
        `parametros` json NOT NULL,
        `estado` enum('iniciado','en_progreso','completado','error') NOT NULL DEFAULT 'iniciado',
        `progreso` decimal(5,2) NOT NULL DEFAULT 0.00,
        `mensaje_progreso` varchar(255),
        `total_encontrados` int(11) NOT NULL DEFAULT 0,
        `total_procesados` int(11) NOT NULL DEFAULT 0,
        `tiempo_inicio` datetime NOT NULL,
        `tiempo_fin` datetime,
        `ip_cliente` varchar(45),
        `user_agent` text,
        `error_mensaje` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_session_id` (`session_id`),
        KEY `idx_estado` (`estado`),
        KEY `idx_tiempo_inicio` (`tiempo_inicio`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "✅ Tabla de sesiones de scraping creada\n";
}

/**
 * Crea la tabla de configuraciones
 */
function createConfigTable(PDO $pdo, string $prefix): void
{
    echo "⚙️ Creando tabla de configuraciones...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$prefix}config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `clave` varchar(100) NOT NULL UNIQUE,
        `valor` text,
        `descripcion` varchar(255),
        `tipo` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
        `categoria` varchar(50) NOT NULL DEFAULT 'general',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_clave` (`clave`),
        KEY `idx_categoria` (`categoria`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "✅ Tabla de configuraciones creada\n";
}

/**
 * Crea la tabla de logs
 */
function createLogsTable(PDO $pdo, string $prefix): void
{
    echo "📝 Creando tabla de logs...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$prefix}logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nivel` enum('DEBUG','INFO','WARNING','ERROR','CRITICAL') NOT NULL,
        `mensaje` text NOT NULL,
        `contexto` json,
        `session_id` varchar(100),
        `ip_cliente` varchar(45),
        `user_agent` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_nivel` (`nivel`),
        KEY `idx_session_id` (`session_id`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "✅ Tabla de logs creada\n";
}

/**
 * Inserta datos iniciales
 */
function insertInitialData(PDO $pdo, string $prefix): void
{
    echo "📊 Insertando datos iniciales...\n";
    
    // Configuraciones iniciales
    $configs = [
        [
            'clave' => 'app_version',
            'valor' => '1.0.0',
            'descripcion' => 'Versión de la aplicación',
            'tipo' => 'string',
            'categoria' => 'sistema'
        ],
        [
            'clave' => 'max_results_per_search',
            'valor' => '100',
            'descripcion' => 'Máximo número de resultados por búsqueda',
            'tipo' => 'integer',
            'categoria' => 'scraper'
        ],
        [
            'clave' => 'default_delay_seconds',
            'valor' => '1',
            'descripcion' => 'Delay por defecto entre requests (segundos)',
            'tipo' => 'integer',
            'categoria' => 'scraper'
        ],
        [
            'clave' => 'enable_logging',
            'valor' => 'true',
            'descripcion' => 'Habilitar sistema de logging',
            'tipo' => 'boolean',
            'categoria' => 'sistema'
        ],
        [
            'clave' => 'sectores_disponibles',
            'valor' => json_encode([
                'tecnologia' => 'Tecnología',
                'construccion' => 'Construcción',
                'salud' => 'Salud',
                'educacion' => 'Educación',
                'finanzas' => 'Finanzas',
                'retail' => 'Retail / Comercio',
                'industria' => 'Industria',
                'servicios' => 'Servicios'
            ]),
            'descripcion' => 'Sectores empresariales disponibles',
            'tipo' => 'json',
            'categoria' => 'filtros'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO `{$prefix}config` 
        (clave, valor, descripcion, tipo, categoria) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($configs as $config) {
        $stmt->execute([
            $config['clave'],
            $config['valor'],
            $config['descripcion'],
            $config['tipo'],
            $config['categoria']
        ]);
    }
    
    echo "✅ Datos iniciales insertados\n";
}

/**
 * Verifica si una tabla existe
 */
function tableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    return $stmt->rowCount() > 0;
}

