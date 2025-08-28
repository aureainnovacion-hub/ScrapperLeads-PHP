<?php

/**
 * API Endpoint para Exportación de Datos
 * Maneja la exportación de leads a diferentes formatos
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Logger.php';

use ScrapperLeads\Config\Config;
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
            if (end($pathParts) === 'csv') {
                handleCSVExport();
            } else {
                throw new Exception('Endpoint no encontrado');
            }
            break;
            
        case 'GET':
            if (end($pathParts) === 'formats') {
                handleGetFormats();
            } else {
                throw new Exception('Endpoint no encontrado');
            }
            break;
            
        default:
            throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    $logger->error('Export API Error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Maneja la exportación a CSV
 */
function handleCSVExport(): void
{
    global $logger;
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['data']) || !is_array($input['data'])) {
        throw new Exception('Datos requeridos para exportación');
    }
    
    $data = $input['data'];
    $filename = $input['filename'] ?? 'leads_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    $logger->info('Exportando ' . count($data) . ' leads a CSV');
    
    // Configurar headers para descarga
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Crear output stream
    $output = fopen('php://output', 'w');
    
    // Añadir BOM para UTF-8 (para Excel)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers del CSV
    $headers = [
        'Empresa',
        'URL',
        'Descripción',
        'Teléfono',
        'Email',
        'Dirección',
        'Empleados',
        'Facturación',
        'Sector',
        'Provincia',
        'Región',
        'Fecha Captura',
        'Fuente',
        'Estado',
        'Notas'
    ];
    
    fputcsv($output, $headers, ';'); // Usar punto y coma para compatibilidad con Excel español
    
    // Datos
    foreach ($data as $lead) {
        $row = [
            $lead['empresa'] ?? '',
            $lead['url'] ?? '',
            $lead['descripcion'] ?? '',
            $lead['telefono'] ?? '',
            $lead['email'] ?? '',
            $lead['direccion'] ?? '',
            $lead['empleados'] ?? '',
            $lead['facturacion'] ?? '',
            $lead['sector'] ?? '',
            $lead['provincia'] ?? '',
            $lead['region'] ?? '',
            $lead['fecha_captura'] ?? '',
            $lead['fuente'] ?? '',
            $lead['estado'] ?? 'activo',
            $lead['notas'] ?? ''
        ];
        
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    
    $logger->info('Exportación CSV completada: ' . $filename);
}

/**
 * Obtiene los formatos de exportación disponibles
 */
function handleGetFormats(): void
{
    header('Content-Type: application/json');
    
    $formats = [
        'csv' => [
            'name' => 'CSV (Comma Separated Values)',
            'description' => 'Formato compatible con Excel y hojas de cálculo',
            'extension' => 'csv',
            'mime_type' => 'text/csv'
        ],
        'excel' => [
            'name' => 'Excel (XLSX)',
            'description' => 'Formato nativo de Microsoft Excel',
            'extension' => 'xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'available' => false // Requiere librerías adicionales
        ],
        'json' => [
            'name' => 'JSON',
            'description' => 'Formato de intercambio de datos estructurados',
            'extension' => 'json',
            'mime_type' => 'application/json'
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'formats' => $formats
    ]);
}

