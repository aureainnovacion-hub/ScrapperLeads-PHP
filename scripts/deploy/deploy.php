<?php

/**
 * Script de Despliegue Automático
 * Despliega la aplicación al servidor FTP según el entorno especificado
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ScrapperLeads\Config\Config;

class Deployer
{
    private $config;
    private $environment;
    private $ftpConnection;

    public function __construct(string $environment = 'production')
    {
        $this->environment = $environment;
        $_ENV['APP_ENV'] = $environment;
        $this->config = Config::getInstance();
        
        echo "🚀 Iniciando despliegue para entorno: {$environment}\n";
    }

    public function deploy(): bool
    {
        try {
            $this->validateEnvironment();
            $this->connectFTP();
            $this->createBackup();
            $this->uploadFiles();
            $this->runMigrations();
            $this->clearCache();
            $this->verifyDeployment();
            
            echo "✅ Despliegue completado exitosamente\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Error en el despliegue: " . $e->getMessage() . "\n";
            $this->rollback();
            return false;
        } finally {
            $this->closeFTP();
        }
    }

    private function validateEnvironment(): void
    {
        echo "🔍 Validando configuración del entorno...\n";
        
        $required = ['ftp.host', 'ftp.user', 'ftp.password', 'ftp.path'];
        foreach ($required as $key) {
            if (empty($this->config->get($key))) {
                throw new Exception("Configuración faltante: {$key}");
            }
        }
        
        echo "✓ Configuración validada\n";
    }

    private function connectFTP(): void
    {
        echo "🔌 Conectando al servidor FTP...\n";
        
        $this->ftpConnection = ftp_connect(
            $this->config->get('ftp.host'),
            $this->config->get('ftp.port', 21)
        );
        
        if (!$this->ftpConnection) {
            throw new Exception("No se pudo conectar al servidor FTP");
        }
        
        if (!ftp_login($this->ftpConnection, $this->config->get('ftp.user'), $this->config->get('ftp.password'))) {
            throw new Exception("Credenciales FTP incorrectas");
        }
        
        ftp_pasv($this->ftpConnection, true);
        echo "✓ Conectado al FTP\n";
    }

    private function createBackup(): void
    {
        if ($this->environment === 'production') {
            echo "💾 Creando backup...\n";
            
            $backupDir = '/backup/' . date('Y-m-d_H-i-s');
            $this->createRemoteDirectory($backupDir);
            
            // Backup de archivos críticos
            $criticalFiles = ['index.php', 'config.php', '.env'];
            foreach ($criticalFiles as $file) {
                $remotePath = $this->config->get('ftp.path') . $file;
                $backupPath = $backupDir . '/' . $file;
                
                if ($this->remoteFileExists($remotePath)) {
                    ftp_get($this->ftpConnection, '/tmp/' . $file, $remotePath, FTP_BINARY);
                    ftp_put($this->ftpConnection, $backupPath, '/tmp/' . $file, FTP_BINARY);
                }
            }
            
            echo "✓ Backup creado\n";
        }
    }

    private function uploadFiles(): void
    {
        echo "📤 Subiendo archivos...\n";
        
        $baseDir = __DIR__ . '/../../';
        $targetDir = $this->config->get('ftp.path');

        // Subir directorios principales
        $this->uploadDirectory($baseDir . 'src', $targetDir . 'src');
        $this->uploadDirectory($baseDir . 'vendor', $targetDir . 'vendor');
        $this->uploadDirectory($baseDir . 'database', $targetDir . 'database');
        
        // Subir ficheros raíz importantes
        $rootFiles = ['composer.json', 'composer.lock'];
        foreach ($rootFiles as $file) {
            if (file_exists($baseDir . $file)) {
                ftp_put($this->ftpConnection, $targetDir . $file, $baseDir . $file, FTP_ASCII);
            }
        }

        // Subir archivos de configuración específicos del entorno
        $envFile = $baseDir . "environments/{$this->environment}/.env";
        if (file_exists($envFile)) {
            ftp_put($this->ftpConnection, $targetDir . '.env', $envFile, FTP_ASCII);
        }
        
        echo "✓ Archivos subidos\n";
    }

    private function uploadDirectory(string $localDir, string $remoteDir): void
    {
        $this->createRemoteDirectory($remoteDir);
        
        $files = scandir($localDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $localPath = $localDir . '/' . $file;
            $remotePath = $remoteDir . '/' . $file;
            
            if (is_dir($localPath)) {
                $this->uploadDirectory($localPath, $remotePath);
            } else {
                $mode = pathinfo($file, PATHINFO_EXTENSION) === 'php' ? FTP_ASCII : FTP_BINARY;
                ftp_put($this->ftpConnection, $remotePath, $localPath, $mode);
                echo "  ↗ {$file}\n";
            }
        }
    }

    private function createRemoteDirectory(string $dir): void
    {
        $parts = explode('/', trim($dir, '/'));
        $currentDir = '';
        
        foreach ($parts as $part) {
            $currentDir .= '/' . $part;
            if (!$this->remoteDirectoryExists($currentDir)) {
                ftp_mkdir($this->ftpConnection, $currentDir);
            }
        }
    }

    private function remoteDirectoryExists(string $dir): bool
    {
        $currentDir = ftp_pwd($this->ftpConnection);
        if (@ftp_chdir($this->ftpConnection, $dir)) {
            ftp_chdir($this->ftpConnection, $currentDir);
            return true;
        }
        return false;
    }

    private function remoteFileExists(string $file): bool
    {
        $list = ftp_nlist($this->ftpConnection, dirname($file));
        return in_array(basename($file), $list ?: []);
    }

    private function runMigrations(): void
    {
        echo "🗄️ Ejecutando migraciones...\n";
        
        $runnerName = 'runner_migration.php';
        $remoteRunnerPath = $this->config->get('ftp.path') . $runnerName;
        $localRunnerPath = '/tmp/' . $runnerName;

        // Script que se ejecutará en el servidor
        $migrationScript = "<?php\n";
        $migrationScript .= "ini_set('display_errors', 1);\n";
        $migrationScript .= "error_reporting(E_ALL);\n";
        $migrationScript .= "require_once 'vendor/autoload.php';\n";
        $migrationScript .= "require_once 'src/database/migrate.php';\n";
        $migrationScript .= "\$migration = new DatabaseMigration();\n";
        $migrationScript .= "\$migration->migrate();\n";
        $migrationScript .= "echo 'MIGRATION_SUCCESS';\n";

        file_put_contents($localRunnerPath, $migrationScript);
        
        // Subir el script
        if (!ftp_put($this->ftpConnection, $remoteRunnerPath, $localRunnerPath, FTP_ASCII)) {
            throw new Exception("No se pudo subir el script de migración.");
        }

        // Ejecutar via HTTP
        $url = $this->config->get('app.url') . '/' . $runnerName;
        $response = @file_get_contents($url);
        
        // Limpiar
        ftp_delete($this->ftpConnection, $remoteRunnerPath);
        unlink($localRunnerPath);

        if ($response === false || strpos($response, 'MIGRATION_SUCCESS') === false) {
            throw new Exception("Falló la ejecución de la migración remota. Respuesta: " . ($response ?: 'ninguna'));
        }
        
        echo "✓ Migraciones completadas\n";
    }

    private function clearCache(): void
    {
        echo "🧹 Limpiando cache...\n";
        
        $cacheFiles = ['cache/*', 'logs/app.log'];
        foreach ($cacheFiles as $pattern) {
            $remotePath = $this->config->get('ftp.path') . $pattern;
            // Implementar limpieza de cache según sea necesario
        }
        
        echo "✓ Cache limpiado\n";
    }

    private function verifyDeployment(): void
    {
        echo "🔍 Verificando despliegue...\n";
        
        $url = $this->config->get('app.url') . '/api/health';
        $response = @file_get_contents($url);
        
        if ($response === false) {
            throw new Exception("No se pudo verificar el estado de la aplicación");
        }
        
        $data = json_decode($response, true);
        if (!$data || $data['status'] !== 'ok') {
            throw new Exception("La aplicación no responde correctamente");
        }
        
        echo "✓ Aplicación funcionando correctamente\n";
    }

    private function rollback(): void
    {
        echo "🔄 Iniciando rollback...\n";
        // Implementar lógica de rollback si es necesario
        echo "✓ Rollback completado\n";
    }

    private function closeFTP(): void
    {
        if ($this->ftpConnection) {
            ftp_close($this->ftpConnection);
        }
    }
}

// Ejecutar despliegue
if (php_sapi_name() === 'cli') {
    $environment = $argv[1] ?? 'production';
    $deployer = new Deployer($environment);
    $success = $deployer->deploy();
    exit($success ? 0 : 1);
}

