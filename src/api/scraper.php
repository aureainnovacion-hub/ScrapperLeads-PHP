<?php

/**
 * API Endpoint para el Scraper de Leads
 * Maneja las peticiones de scraping y devuelve resultados en JSON
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/GoogleScraper.php';
require_once __DIR__ . '/../utils/Logger.php';

use ScrapperLeads\Config\Config;
use ScrapperLeads\Utils\GoogleScraper;
use ScrapperLeads\Utils\Logger;

try {
    $config = Config::getInstance();
    $logger = new Logger();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // Routing básico
    switch ($method) {
        case 'POST':
            if (end($pathParts) === 'start') {
                handleStartScraping();
            } else {
                throw new Exception('Endpoint no encontrado');
            }
            break;
            
        case 'GET':
            if (end($pathParts) === 'progress') {
                handleGetProgress();
            } elseif (end($pathParts) === 'health') {
                handleHealthCheck();
            } else {
                throw new Exception('Endpoint no encontrado');
            }
            break;
            
        default:
            throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    $logger->error('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Inicia el proceso de scraping
 */
function handleStartScraping(): void
{
    global $config, $logger;
    
    // Validar datos de entrada
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['keywords'])) {
        throw new Exception('Parámetros requeridos faltantes');
    }
    
    // Validar y sanitizar parámetros
    $params = [
        'keywords' => sanitizeString($input['keywords']),
        'sector' => sanitizeString($input['sector'] ?? ''),
        'provincia' => sanitizeString($input['provincia'] ?? ''),
        'region' => sanitizeString($input['region'] ?? ''),
        'empleados' => sanitizeString($input['empleados'] ?? ''),
        'facturacion' => sanitizeString($input['facturacion'] ?? ''),
        'numResults' => min((int)($input['numResults'] ?? 20), $config->get('scraper.max_results', 100))
    ];
    
    $logger->info('Iniciando scraping con parámetros: ' . json_encode($params));
    
    // Crear instancia del scraper
    $scraper = new GoogleScraper($config);
    
    // Generar ID único para esta sesión
    $sessionId = uniqid('scrape_', true);
    
    // Iniciar scraping (en un proceso separado para evitar timeouts)
    $results = $scraper->searchLeads($params, $sessionId);
    
    echo json_encode([
        'success' => true,
        'sessionId' => $sessionId,
        'message' => 'Scraping iniciado correctamente',
        'results' => $results,
        'totalFound' => count($results)
    ]);
}

/**
 * Obtiene el progreso del scraping
 */
function handleGetProgress(): void
{
    $sessionId = $_GET['sessionId'] ?? '';
    
    if (empty($sessionId)) {
        throw new Exception('Session ID requerido');
    }
    
    // Leer progreso desde archivo temporal
    $progressFile = sys_get_temp_dir() . "/scraper_progress_{$sessionId}.json";
    
    if (!file_exists($progressFile)) {
        echo json_encode([
            'success' => false,
            'error' => 'Sesión no encontrada'
        ]);
        return;
    }
    
    $progress = json_decode(file_get_contents($progressFile), true);
    
    echo json_encode([
        'success' => true,
        'progress' => $progress
    ]);
}

/**
 * Health check del sistema
 */
function handleHealthCheck(): void
{
    global $config;
    
    $status = [
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => $config->get('app.env'),
        'version' => '1.0.0',
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'disk_space' => disk_free_space('.'),
    ];
    
    // Verificar conexión a base de datos
    try {
        $db = new PDO(
            "mysql:host=" . $config->get('database.host') . ";dbname=" . $config->get('database.name'),
            $config->get('database.user'),
            $config->get('database.password')
        );
        $status['database'] = 'connected';
    } catch (Exception $e) {
        $status['database'] = 'error: ' . $e->getMessage();
        $status['status'] = 'warning';
    }
    
    echo json_encode($status);
}

/**
 * Sanitiza strings de entrada
 */
function sanitizeString(string $input): string
{
    return trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
}

