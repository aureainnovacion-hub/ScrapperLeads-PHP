<?php
/**
 * ScrapperLeads PHP - Bootstrap
 *
 * Este fichero maneja la inicialización de la aplicación, incluyendo
 * la carga de dependencias, configuración y manejo de errores.
 *
 * @author AUREA INNOVACION
 * @version 1.0.0
 */

// Cargar el autoloader de Composer para tener acceso a las librerías
require_once __DIR__ . '/../vendor/autoload.php';

use ScrapperLeads\Config\Config;

// Configuración de errores según el entorno
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    $config = Config::getInstance();

    date_default_timezone_set($config->get('app.timezone', 'Europe/Madrid'));

    if ($config->isDevelopment()) {
        ini_set('display_errors', 1);
    }

    return $config;
} catch (Exception $e) {
    // Fallback si no se puede cargar la configuración
    error_log("Fatal Error: Could not load configuration. " . $e->getMessage());
    // Terminar de forma segura si la configuración falla
    http_response_code(503);
    echo "Service Unavailable. Please check the logs.";
    exit;
}
