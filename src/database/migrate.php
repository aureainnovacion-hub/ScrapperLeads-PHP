<?php

/**
 * Script de Migración de Base de Datos - ScrapperLeads Pro
 * Crea las tablas necesarias para el funcionamiento de la aplicación
 * Hosting: Dinahosting MariaDB 11.4
 */

require_once __DIR__ . '/../config/config.php';

use ScrapperLeads\Config\Config;

class DatabaseMigration
{
    private $pdo;
    private $config;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->connectDatabase();
    }

    private function connectDatabase()
    {
        try {
            $dbConfig = $this->config->get('database');

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['name'],
                $dbConfig['charset']
            );

            $this->pdo = new PDO(
                $dsn,
                $dbConfig['user'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_TIMEOUT => 30
                ]
            );

            echo "✅ Conexión a base de datos MariaDB establecida con configuración de entorno.\n";
        } catch (PDOException $e) {
            die("❌ Error de conexión: " . $e->getMessage() . "\n");
        }
    }

    public function migrate()
    {
        echo "🚀 Iniciando migración de base de datos ScrapperLeads...\n";
        echo "📊 Base de datos: eduai_scrapperleads (MariaDB 11.4)\n";
        echo "🏠 Hosting: Dinahosting\n\n";

        try {
            // Ejecutar el esquema SQL completo
            $this->executeSchemaFile();
            
            echo "\n🎉 Migración completada exitosamente\n";
            echo "📈 Estadísticas de la base de datos:\n";
            $this->showDatabaseStats();
            
        } catch (Exception $e) {
            echo "❌ Error durante la migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function executeSchemaFile()
    {
        $schemaPath = __DIR__ . '/../../database/schema.sql';
        
        if (!file_exists($schemaPath)) {
            throw new Exception("Archivo de esquema no encontrado: $schemaPath");
        }

        $sql = file_get_contents($schemaPath);
        
        if ($sql === false) {
            throw new Exception("No se pudo leer el archivo de esquema");
        }

        echo "📄 Ejecutando esquema SQL completo...\n";

        // Dividir el SQL en statements individuales
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && 
                       !preg_match('/^--/', $stmt) && 
                       !preg_match('/^\/\*/', $stmt);
            }
        );

        $executed = 0;
        foreach ($statements as $statement) {
            if (trim($statement)) {
                try {
                    $this->pdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // Ignorar errores de "ya existe" para permitir re-ejecuciones
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        throw $e;
                    }
                }
            }
        }

        echo "✅ Ejecutados $executed statements SQL\n";
    }

    private function showDatabaseStats()
    {
        try {
            // Obtener estadísticas de tablas
            $stmt = $this->pdo->query("
                SELECT 
                    TABLE_NAME as tabla,
                    TABLE_ROWS as filas,
                    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as tamaño_mb
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = 'eduai_scrapperleads'
                ORDER BY TABLE_NAME
            ");
            
            $tables = $stmt->fetchAll();
            
            foreach ($tables as $table) {
                echo "  📋 {$table['tabla']}: {$table['filas']} filas ({$table['tamaño_mb']} MB)\n";
            }

            // Verificar configuración inicial
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM system_config");
            $configCount = $stmt->fetch()['total'];
            echo "  ⚙️  Configuraciones del sistema: $configCount\n";

        } catch (PDOException $e) {
            echo "  ⚠️  No se pudieron obtener estadísticas: " . $e->getMessage() . "\n";
        }
    }

    public function testConnection()
    {
        try {
            $stmt = $this->pdo->query("SELECT VERSION() as version, NOW() as current_time");
            $result = $stmt->fetch();
            
            echo "🔍 Test de conexión:\n";
            echo "  📊 Versión MariaDB: " . $result['version'] . "\n";
            echo "  🕐 Hora del servidor: " . $result['current_time'] . "\n";
            echo "  ✅ Conexión exitosa\n";
            
            return true;
        } catch (PDOException $e) {
            echo "❌ Test de conexión falló: " . $e->getMessage() . "\n";
            return false;
        }
    }

    public function createSampleData()
    {
        echo "\n📝 Creando datos de ejemplo...\n";
        
        try {
            // Crear una búsqueda de ejemplo
            $searchUuid = uniqid('search_', true);
            $stmt = $this->pdo->prepare("
                INSERT INTO searches (search_uuid, keywords, sector, province, max_results, status) 
                VALUES (?, 'consultoría tecnológica', 'Tecnología', 'Madrid', 10, 'completed')
            ");
            $stmt->execute([$searchUuid]);
            $searchId = $this->pdo->lastInsertId();

            // Crear algunos leads de ejemplo
            $sampleLeads = [
                [
                    'TechConsult Madrid SL',
                    'Juan Pérez',
                    'Calle Gran Vía 123, Madrid',
                    '+34 91 123 4567',
                    'info@techconsult.es',
                    'https://techconsult.es',
                    '25',
                    '1M-5M €',
                    'Tecnología',
                    'Madrid',
                    'Centro',
                    '2015-03-15'
                ],
                [
                    'Innovación Digital SA',
                    'María García',
                    'Paseo de la Castellana 456, Madrid',
                    '+34 91 987 6543',
                    'contacto@innovaciondigital.com',
                    'https://innovaciondigital.com',
                    '50',
                    '5M-10M €',
                    'Tecnología',
                    'Madrid',
                    'Centro',
                    '2012-07-22'
                ]
            ];

            $stmt = $this->pdo->prepare("
                INSERT INTO leads (
                    search_id, company_name, contact_name, address, phone, email, 
                    website, employees_count, revenue, sector, province, region, 
                    founded_date, confidence_score, data_quality
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.85, 'high')
            ");

            foreach ($sampleLeads as $lead) {
                $stmt->execute(array_merge([$searchId], $lead));
            }

            echo "✅ Datos de ejemplo creados: 1 búsqueda, " . count($sampleLeads) . " leads\n";
            
        } catch (PDOException $e) {
            echo "⚠️  Error creando datos de ejemplo: " . $e->getMessage() . "\n";
        }
    }
}

// Ejecutar migración si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "=== MIGRACIÓN DE BASE DE DATOS SCRAPPERLEADS PRO ===\n\n";
    
    $migration = new DatabaseMigration();
    
    // Test de conexión
    if ($migration->testConnection()) {
        // Ejecutar migración
        $migration->migrate();
        
        // Crear datos de ejemplo (opcional)
        if (isset($_GET['sample']) || (isset($argv[1]) && $argv[1] === '--sample')) {
            $migration->createSampleData();
        }
        
        echo "\n🎯 Migración completada. La base de datos está lista para ScrapperLeads Pro.\n";
        echo "🌐 Accede a la aplicación en: http://eduaify.es\n";
    }
}

