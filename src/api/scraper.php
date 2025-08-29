<?php

/**
 * API Endpoint para el Scraper de Leads
 * Maneja las peticiones de scraping y devuelve resultados en JSON
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

    switch ($method) {
        case 'POST':
            $action = $_GET['action'] ?? 'start';
            if ($action === 'start' || !isset($_GET['action'])) {
                handleStartScraping($config, $logger);
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
                handleHealthCheck($config);
            } else {
                throw new Exception('Acción no válida');
            }
            break;

        default:
            throw new Exception('Método no permitido');
    }
} catch (Exception $e) {
    if (!isset($logger)) {
        $logger = new Logger();
    }
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
function handleStartScraping(Config $config, Logger $logger): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Datos de entrada inválidos');
    }

    $hasFilters = !empty($input['keywords']) ||
                  !empty($input['sectors']) ||
                  !empty($input['provinces']) ||
                  !empty($input['regions']);

    if (!$hasFilters) {
        throw new Exception('Debe especificar al menos un filtro de búsqueda');
    }

    $params = [
        'keywords' => sanitizeString($input['keywords'] ?? ''),
        'sectors' => is_array($input['sectors']) ? $input['sectors'] : [],
        'provinces' => is_array($input['provinces']) ? $input['provinces'] : [],
        'regions' => is_array($input['regions']) ? $input['regions'] : [],
        'revenue' => sanitizeString($input['revenue'] ?? ''),
        'maxResults' => min((int)($input['maxResults'] ?? 20), $config->get('scraper.max_results', 1000))
    ];

    $searchId = uniqid('scrape_', true);
    $logger->info("Generado searchId: {$searchId} con parámetros: " . json_encode($params));

    // Desactivar el aborto del script si el usuario se desconecta
    ignore_user_abort(true);
    set_time_limit(0); // Sin límite de tiempo de ejecución

    // Iniciar el buffer de salida
    ob_start();

    // Enviar respuesta inmediata al cliente
    echo json_encode([
        'success' => true,
        'searchId' => $searchId,
        'message' => 'Scraping iniciado. El proceso se ejecutará en segundo plano.'
    ]);

    // Enviar cabeceras para cerrar la conexión
    header('Connection: close');
    header('Content-Length: ' . ob_get_length());
    ob_end_flush();
    @ob_flush();
    flush();

    // Finalizar la solicitud si es posible (para FPM)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    // --- El script continúa ejecutándose aquí después de que el cliente se haya ido ---

    try {
        $scraper = new GoogleScraper($config);
        $scraper->searchLeads($params, $searchId);
    } catch (Exception $e) {
        $logger->error("Scraping en segundo plano falló para searchId {$searchId}: " . $e->getMessage());
        // Actualizar el estado a 'failed'
        $progressFile = sys_get_temp_dir() . "/scraper_progress_{$searchId}.json";
        $progressData = [
            'sessionId' => $searchId,
            'progress' => 0,
            'message' => 'Error crítico durante el scraping: ' . $e->getMessage(),
            'status' => 'failed',
            'timestamp' => date('Y-m-d H:i:s'),
            'stats' => []
        ];
        file_put_contents($progressFile, json_encode($progressData));
    }
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
    $searchId = $_GET['searchId'] ?? '';

    if (empty($searchId)) {
        throw new Exception('Search ID requerido');
    }

    $progressFile = sys_get_temp_dir() . "/scraper_progress_{$searchId}.json";

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
function handleHealthCheck(Config $config): void
{
    $status = [
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => $config->get('app.env'),
        'version' => '1.0.0',
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'disk_space' => disk_free_space('.'),
    ];

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
