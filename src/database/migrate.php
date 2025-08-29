<?php

namespace ScrapperLeads\Database;

use ScrapperLeads\Config\Config;
use PDO;
use PDOException;
use Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class DatabaseMigration
{
    private $pdo;
    private $config;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->connectDatabase();
    }

    private function connectDatabase(): void
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

    public function migrate(): void
    {
        echo "🚀 Iniciando migración de base de datos ScrapperLeads...\n";

        try {
            $this->executeSchemaFile();
            echo "\n🎉 Migración completada exitosamente\n";
            $this->showDatabaseStats();
        } catch (Exception $e) {
            echo "❌ Error durante la migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    private function executeSchemaFile(): void
    {
        $schemaPath = dirname(__DIR__, 2) . '/database/schema.sql';

        if (!file_exists($schemaPath)) {
            throw new Exception("Archivo de esquema no encontrado: $schemaPath");
        }

        $sql = file_get_contents($schemaPath);

        if ($sql === false) {
            throw new Exception("No se pudo leer el archivo de esquema");
        }

        echo "📄 Ejecutando esquema SQL completo...\n";

        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function ($stmt) {
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
                    if (
                        strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate') === false
                    ) {
                        throw $e;
                    }
                }
            }
        }

        echo "✅ Ejecutados $executed statements SQL\n";
    }

    private function showDatabaseStats(): void
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    TABLE_NAME as tabla,
                    TABLE_ROWS as filas,
                    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as tamaño_mb
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = '{$this->config->get('database.name')}'
                ORDER BY TABLE_NAME
            ");

            $tables = $stmt->fetchAll();

            echo "📈 Estadísticas de la base de datos:\n";
            foreach ($tables as $table) {
                echo "  📋 {$table['tabla']}: {$table['filas']} filas ({$table['tamaño_mb']} MB)\n";
            }
        } catch (PDOException $e) {
            echo "  ⚠️  No se pudieron obtener estadísticas: " . $e->getMessage() . "\n";
        }
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "=== MIGRACIÓN DE BASE DE DATOS SCRAPPERLEADS PRO ===\n\n";

    $migration = new DatabaseMigration();
    $migration->migrate();

    echo "\n🎯 Migración completada. La base de datos está lista para ScrapperLeads Pro.\n";
}
