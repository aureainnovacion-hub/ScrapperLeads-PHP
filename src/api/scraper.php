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
            $action = $_GET['action'] ?? 'start';
            if ($action === 'start' || !isset($_GET['action'])) {
                handleStartScraping();
            } elseif ($action === 'stop') {
                handleStopScraping();
            } else {
                throw new Exception('Acción no válida');
            }
            break;
            
        case 'GET':
            $action = $_GET['action'] ?? 'health';
            if ($action === 'progress') {
                handleGetProgress();
            } elseif ($action === 'health') {
                handleHealthCheck();
            } else {
                throw new Exception('Acción no válida');
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
    
    if (!$input) {
        throw new Exception('Datos de entrada inválidos');
    }
    
    // Validar que al menos haya un filtro
    $hasFilters = !empty($input['keywords']) || 
                  !empty($input['sectors']) || 
                  !empty($input['provinces']) || 
                  !empty($input['regions']);
    
    if (!$hasFilters) {
        throw new Exception('Debe especificar al menos un filtro de búsqueda');
    }
    
    // Validar y sanitizar parámetros
    $params = [
        'keywords' => sanitizeString($input['keywords'] ?? ''),
        'sectors' => is_array($input['sectors']) ? $input['sectors'] : [],
        'provinces' => is_array($input['provinces']) ? $input['provinces'] : [],
        'regions' => is_array($input['regions']) ? $input['regions'] : [],
        'revenue' => sanitizeString($input['revenue'] ?? ''),
        'maxResults' => min((int)($input['maxResults'] ?? 20), $config->get('scraper.max_results', 1000))
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
        'searchId' => $sessionId,
        'message' => 'Scraping iniciado correctamente',
        'results' => $results,
        'totalFound' => count($results)
    ]);
}

/**
 * Detiene el proceso de scraping
 */
function handleStopScraping(): void
{
    $searchId = $_GET['searchId'] ?? '';
    
    if (empty($searchId)) {
        throw new Exception('Search ID requerido');
    }
    
    // Marcar como detenido en archivo de progreso
    $progressFile = sys_get_temp_dir() . "/scraper_progress_{$searchId}.json";
    
    if (file_exists($progressFile)) {
        $progress = json_decode(file_get_contents($progressFile), true);
        $progress['status'] = 'stopped';
        $progress['message'] = 'Búsqueda detenida por el usuario';
        file_put_contents($progressFile, json_encode($progress));
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Búsqueda detenida correctamente'
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

