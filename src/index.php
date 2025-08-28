<?php

/**
 * ScrapperLeads PHP - Punto de entrada principal
 * Sistema profesional de captura de leads empresariales
 * 
 * @author AUREA INNOVACION
 * @version 1.0.0
 */

// Configuración de errores según el entorno
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Autoloader manual
require_once __DIR__ . '/config/config.php';

// Configuración
use ScrapperLeads\Config\Config;

try {
    $config = Config::getInstance();
    
    // Configurar zona horaria
    date_default_timezone_set($config->get('app.timezone', 'Europe/Madrid'));
    
    // Mostrar errores solo en desarrollo
    if ($config->isDevelopment()) {
        ini_set('display_errors', 1);
    }
    
} catch (Exception $e) {
    // Fallback si no se puede cargar la configuración
    error_log("Error loading configuration: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScrapperLeads Pro - Captura de Leads Empresariales</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/app.css" rel="stylesheet">
    
    <meta name="description" content="Sistema profesional de captura de leads empresariales con filtros avanzados">
    <meta name="keywords" content="leads, scraping, empresas, marketing, captación">
    <meta name="author" content="AUREA INNOVACION">
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-search-dollar me-2"></i>
                ScrapperLeads Pro
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text">
                    <i class="fas fa-shield-alt me-1"></i>
                    Sistema Profesional
                </span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Alert de información -->
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Bienvenido a ScrapperLeads Pro</strong> - Sistema profesional de captura de leads empresariales con filtros avanzados.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="row">
            <!-- Panel de Filtros -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filtros de Búsqueda
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="searchForm">
                            <!-- Palabras Clave -->
                            <div class="mb-3">
                                <label for="keywords" class="form-label">
                                    <i class="fas fa-key me-1"></i>
                                    Palabras Clave *
                                </label>
                                <input type="text" class="form-control" id="keywords" 
                                       placeholder="ej: software, marketing digital, consultoría" required>
                                <div class="form-text">Términos principales de búsqueda</div>
                            </div>

                            <!-- Sector Empresarial -->
                            <div class="mb-3">
                                <label for="sector" class="form-label">
                                    <i class="fas fa-industry me-1"></i>
                                    Sector Empresarial
                                </label>
                                <select class="form-select" id="sector">
                                    <option value="">Todos los sectores</option>
                                    <option value="tecnologia">Tecnología</option>
                                    <option value="construccion">Construcción</option>
                                    <option value="salud">Salud</option>
                                    <option value="educacion">Educación</option>
                                    <option value="finanzas">Finanzas</option>
                                    <option value="retail">Retail / Comercio</option>
                                    <option value="industria">Industria</option>
                                    <option value="servicios">Servicios</option>
                                </select>
                            </div>

                            <!-- Ubicación -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="provincia" class="form-label">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        Provincia
                                    </label>
                                    <select class="form-select" id="provincia">
                                        <option value="">Todas</option>
                                        <option value="madrid">Madrid</option>
                                        <option value="barcelona">Barcelona</option>
                                        <option value="valencia">Valencia</option>
                                        <option value="sevilla">Sevilla</option>
                                        <option value="malaga">Málaga</option>
                                        <option value="bilbao">Bilbao</option>
                                        <option value="zaragoza">Zaragoza</option>
                                        <option value="alicante">Alicante</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="region" class="form-label">
                                        <i class="fas fa-globe-europe me-1"></i>
                                        Región
                                    </label>
                                    <select class="form-select" id="region">
                                        <option value="">Todas</option>
                                        <option value="centro">Centro</option>
                                        <option value="norte">Norte</option>
                                        <option value="sur">Sur</option>
                                        <option value="este">Este</option>
                                        <option value="oeste">Oeste</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Tamaño de Empresa -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="empleados" class="form-label">
                                        <i class="fas fa-users me-1"></i>
                                        Empleados
                                    </label>
                                    <select class="form-select" id="empleados">
                                        <option value="">Cualquiera</option>
                                        <option value="1-10">1-10</option>
                                        <option value="11-50">11-50</option>
                                        <option value="51-200">51-200</option>
                                        <option value="201-500">201-500</option>
                                        <option value="500+">+500</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="facturacion" class="form-label">
                                        <i class="fas fa-euro-sign me-1"></i>
                                        Facturación
                                    </label>
                                    <select class="form-select" id="facturacion">
                                        <option value="">Cualquiera</option>
                                        <option value="0-1M">0-1M €</option>
                                        <option value="1M-5M">1M-5M €</option>
                                        <option value="5M-10M">5M-10M €</option>
                                        <option value="10M-50M">10M-50M €</option>
                                        <option value="50M+">+50M €</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Número de Resultados -->
                            <div class="mb-3">
                                <label for="numResults" class="form-label">
                                    <i class="fas fa-list-ol me-1"></i>
                                    Número de Resultados
                                </label>
                                <input type="number" class="form-control" id="numResults" 
                                       min="1" max="<?= $config->get('scraper.max_results', 100) ?>" value="20">
                                <div class="form-text">Máximo: <?= $config->get('scraper.max_results', 100) ?> resultados</div>
                            </div>

                            <!-- Botón de Búsqueda -->
                            <button type="submit" class="btn btn-success w-100" id="startScrape">
                                <i class="fas fa-play-circle me-2"></i>
                                Iniciar Captura de Leads
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Panel de Resultados -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Progreso y Resultados
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Barra de Progreso -->
                        <div class="progress mb-3" id="progressContainer" style="display: none;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 id="progressBar" role="progressbar" style="width: 0%">0%</div>
                        </div>

                        <!-- Indicador de Carga -->
                        <div class="text-center py-5" id="loadingIndicator" style="display: none;">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-3 mb-1">Capturando datos desde Google...</p>
                            <small class="text-muted">Esto puede tardar varios minutos según el número de resultados.</small>
                        </div>

                        <!-- Resumen de Resultados -->
                        <div id="resultSummary" style="display: none;">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>¡Captura completada!</strong> Se han encontrado <span id="totalLeads"></span> leads.
                            </div>
                            <div class="d-flex justify-content-end mb-3">
                                <button id="downloadCSV" class="btn btn-primary">
                                    <i class="fas fa-download me-2"></i>
                                    Descargar CSV
                                </button>
                            </div>
                        </div>

                        <!-- Tabla de Resultados -->
                        <div class="table-responsive" id="resultsTable" style="display: none;">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th><i class="fas fa-building me-1"></i>Empresa</th>
                                        <th><i class="fas fa-map-marker-alt me-1"></i>Ubicación</th>
                                        <th><i class="fas fa-phone me-1"></i>Contacto</th>
                                        <th><i class="fas fa-users me-1"></i>Empleados</th>
                                        <th><i class="fas fa-euro-sign me-1"></i>Facturación</th>
                                    </tr>
                                </thead>
                                <tbody id="resultsBody">
                                    <!-- Resultados dinámicos -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Estado Inicial -->
                        <div id="initialState" class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Configura los filtros y inicia la búsqueda</h5>
                            <p class="text-muted">Los resultados aparecerán aquí una vez iniciada la captura.</p>
                        </div>
                    </div>
                </div>

                <!-- Información Legal -->
                <div class="alert alert-warning mt-3">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Información Legal</h6>
                    <small>
                        Este sistema respeta los términos de servicio y políticas de uso de las plataformas consultadas. 
                        Se recomienda usar APIs oficiales para uso comercial intensivo. Los datos obtenidos deben usarse 
                        conforme a la normativa de protección de datos vigente.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6>ScrapperLeads Pro</h6>
                    <p class="mb-0">Sistema profesional de captura de leads empresariales</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">© 2025 AUREA INNOVACIÓN</p>
                    <small class="text-muted">Versión 1.0.0</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>

