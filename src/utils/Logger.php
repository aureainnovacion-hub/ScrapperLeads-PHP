<?php

namespace ScrapperLeads\Utils;

/**
 * Sistema de logging simple y eficiente
 */
class Logger
{
    private $logPath;
    private $logLevel;
    
    const LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    
    public function __construct(string $logPath = null, string $logLevel = 'INFO')
    {
        $this->logPath = $logPath ?: dirname(__DIR__, 2) . '/logs/app.log';
        $this->logLevel = strtoupper($logLevel);
        
        // Crear directorio de logs si no existe
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Log de debug
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }
    
    /**
     * Log de información
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Log de advertencia
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * Log de error
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Log crítico
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }
    
    /**
     * Método principal de logging
     */
    private function log(string $level, string $message, array $context = []): void
    {
        // Verificar si el nivel es suficiente para logear
        if (self::LEVELS[$level] < self::LEVELS[$this->logLevel]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        
        // Escribir al archivo de log
        file_put_contents($this->logPath, $logEntry, FILE_APPEND | LOCK_EX);
        
        // En desarrollo, también mostrar en pantalla
        if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development') {
            echo $logEntry;
        }
    }
    
    /**
     * Limpia logs antiguos
     */
    public function cleanOldLogs(int $daysToKeep = 30): void
    {
        $logDir = dirname($this->logPath);
        $files = glob($logDir . '/*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < time() - ($daysToKeep * 24 * 60 * 60)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Obtiene las últimas líneas del log
     */
    public function getRecentLogs(int $lines = 100): array
    {
        if (!file_exists($this->logPath)) {
            return [];
        }
        
        $file = file($this->logPath);
        return array_slice($file, -$lines);
    }
}

