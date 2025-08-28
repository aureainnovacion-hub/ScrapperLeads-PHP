<?php

namespace ScrapperLeads\Config;

use Dotenv\Dotenv;

class Config
{
    private static $instance = null;
    private $config = [];

    private function __construct()
    {
        $this->loadEnvironment();
        $this->loadConfig();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnvironment(): void
    {
        $envPath = dirname(__DIR__, 2);
        
        // Detectar entorno
        $environment = $_ENV['APP_ENV'] ?? 'development';
        
        // Cargar archivo .env específico del entorno si existe
        $envFile = $envPath . "/environments/{$environment}/.env";
        if (file_exists($envFile)) {
            $dotenv = Dotenv::createImmutable(dirname($envFile));
            $dotenv->load();
        }
        
        // Cargar .env principal como fallback
        if (file_exists($envPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($envPath);
            $dotenv->load();
        }
    }

    private function loadConfig(): void
    {
        $this->config = [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'ScrapperLeads',
                'env' => $_ENV['APP_ENV'] ?? 'development',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Madrid',
            ],
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? '3306',
                'name' => $_ENV['DB_NAME'] ?? 'scrapper_leads',
                'user' => $_ENV['DB_USER'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'prefix' => $_ENV['DB_PREFIX'] ?? 'sl_',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            ],
            'google' => [
                'places_api_key' => $_ENV['GOOGLE_PLACES_API_KEY'] ?? 'AIzaSyBvOkBwAqITJ5ohHXbQmk2dEwKI2cU_-dM'
            ],
            'scraper' => [
                'max_results' => (int)($_ENV['SCRAPER_MAX_RESULTS'] ?? 100),
                'timeout' => (int)($_ENV['SCRAPER_TIMEOUT'] ?? 30),
                'user_agent' => $_ENV['SCRAPER_USER_AGENT'] ?? 'Mozilla/5.0 (compatible; ScrapperLeads/1.0)',
                'delay_between_requests' => (int)($_ENV['SCRAPER_DELAY'] ?? 1),
            ],
            'ftp' => [
                'host' => $_ENV['FTP_HOST'] ?? '',
                'user' => $_ENV['FTP_USER'] ?? '',
                'password' => $_ENV['FTP_PASSWORD'] ?? '',
                'path' => $_ENV['FTP_PATH'] ?? '/www/',
                'port' => (int)($_ENV['FTP_PORT'] ?? 21),
            ],
            'logging' => [
                'level' => $_ENV['LOG_LEVEL'] ?? 'info',
                'path' => $_ENV['LOG_PATH'] ?? dirname(__DIR__, 2) . '/logs',
            ],
        ];
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function isProduction(): bool
    {
        return $this->get('app.env') === 'production';
    }

    public function isDevelopment(): bool
    {
        return $this->get('app.env') === 'development';
    }

    public function isStaging(): bool
    {
        return $this->get('app.env') === 'staging';
    }
}

