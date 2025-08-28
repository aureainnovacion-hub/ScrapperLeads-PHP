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
    
    <!-- Select2 para selección múltiple -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- CSS personalizado -->
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-search-dollar me-2"></i>
                ScrapperLeads Pro
            </a>
            <span class="navbar-text">
                <i class="fas fa-shield-alt me-1"></i>
                Sistema Profesional
            </span>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container my-4">
        <!-- Alerta de Bienvenida -->
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
                                    Palabras Clave
                                </label>
                                <input type="text" class="form-control" id="keywords" 
                                       placeholder="ej: software, marketing digital, consultoría">
                                <div class="form-text">Términos principales de búsqueda (opcional, para afinar resultados)</div>
                            </div>

                            <!-- Sector Empresarial -->
                            <div class="mb-3">
                                <label for="sector" class="form-label">
                                    <i class="fas fa-industry me-1"></i>
                                    Sectores Empresariales (CNAE-2025)
                                </label>
                                <select class="form-select" id="sector" multiple>
                                    <optgroup label="Agricultura y Ganadería">
                                        <option value="01">Agricultura, ganadería, caza y servicios relacionados</option>
                                        <option value="02">Silvicultura y explotación forestal</option>
                                        <option value="03">Pesca y acuicultura</option>
                                    </optgroup>
                                    <optgroup label="Industria">
                                        <option value="05">Extracción de antracita, hulla y lignito</option>
                                        <option value="06">Extracción de crudo de petróleo y gas natural</option>
                                        <option value="07">Extracción de minerales metálicos</option>
                                        <option value="08">Otras industrias extractivas</option>
                                        <option value="09">Actividades de apoyo a las industrias extractivas</option>
                                        <option value="10">Industria de la alimentación</option>
                                        <option value="11">Fabricación de bebidas</option>
                                        <option value="12">Industria del tabaco</option>
                                        <option value="13">Industria textil</option>
                                        <option value="14">Confección de prendas de vestir</option>
                                        <option value="15">Industria del cuero y del calzado</option>
                                        <option value="16">Industria de la madera y del corcho</option>
                                        <option value="17">Industria del papel</option>
                                        <option value="18">Artes gráficas y reproducción de soportes grabados</option>
                                        <option value="19">Coquerías y refino de petróleo</option>
                                        <option value="20">Industria química</option>
                                        <option value="21">Fabricación de productos farmacéuticos</option>
                                        <option value="22">Fabricación de productos de caucho y plásticos</option>
                                        <option value="23">Fabricación de otros productos minerales no metálicos</option>
                                        <option value="24">Metalurgia; fabricación de productos de hierro, acero y ferroaleaciones</option>
                                        <option value="25">Fabricación de productos metálicos</option>
                                        <option value="26">Fabricación de productos informáticos, electrónicos y ópticos</option>
                                        <option value="27">Fabricación de material y equipo eléctrico</option>
                                        <option value="28">Fabricación de maquinaria y equipo n.c.o.p.</option>
                                        <option value="29">Fabricación de vehículos de motor</option>
                                        <option value="30">Fabricación de otro material de transporte</option>
                                        <option value="31">Fabricación de muebles</option>
                                        <option value="32">Otras industrias manufactureras</option>
                                        <option value="33">Reparación e instalación de maquinaria y equipo</option>
                                    </optgroup>
                                    <optgroup label="Energía y Agua">
                                        <option value="35">Suministro de energía eléctrica, gas, vapor y aire acondicionado</option>
                                        <option value="36">Captación, depuración y distribución de agua</option>
                                        <option value="37">Recogida y tratamiento de aguas residuales</option>
                                        <option value="38">Valorización</option>
                                        <option value="39">Descontaminación y otros servicios de gestión de residuos</option>
                                    </optgroup>
                                    <optgroup label="Construcción">
                                        <option value="41">Construcción de edificios</option>
                                        <option value="42">Ingeniería civil</option>
                                        <option value="43">Actividades de construcción especializada</option>
                                    </optgroup>
                                    <optgroup label="Comercio">
                                        <option value="45">Venta y reparación de vehículos de motor y motocicletas</option>
                                        <option value="46">Comercio al por mayor e intermediarios del comercio</option>
                                        <option value="47">Comercio al por menor</option>
                                    </optgroup>
                                    <optgroup label="Transporte y Almacenamiento">
                                        <option value="49">Transporte terrestre y por tubería</option>
                                        <option value="50">Transporte marítimo y por vías navegables interiores</option>
                                        <option value="51">Transporte aéreo</option>
                                        <option value="52">Almacenamiento y actividades anexas al transporte</option>
                                        <option value="53">Actividades postales y de correos</option>
                                    </optgroup>
                                    <optgroup label="Hostelería">
                                        <option value="55">Servicios de alojamiento</option>
                                        <option value="56">Servicios de comidas y bebidas</option>
                                    </optgroup>
                                    <optgroup label="Información y Comunicaciones">
                                        <option value="58">Edición</option>
                                        <option value="59">Actividades cinematográficas, de vídeo y de televisión</option>
                                        <option value="60">Actividades de programación y emisión de radio y televisión</option>
                                        <option value="61">Telecomunicaciones</option>
                                        <option value="62">Programación, consultoría y otras actividades relacionadas con la informática</option>
                                        <option value="63">Servicios de información</option>
                                    </optgroup>
                                    <optgroup label="Actividades Financieras y de Seguros">
                                        <option value="64">Servicios financieros</option>
                                        <option value="65">Seguros, reaseguros y fondos de pensiones</option>
                                        <option value="66">Actividades auxiliares a los servicios financieros y a los seguros</option>
                                    </optgroup>
                                    <optgroup label="Actividades Inmobiliarias">
                                        <option value="68">Actividades inmobiliarias</option>
                                    </optgroup>
                                    <optgroup label="Actividades Profesionales, Científicas y Técnicas">
                                        <option value="69">Actividades jurídicas y de contabilidad</option>
                                        <option value="70">Actividades de las sedes centrales; actividades de consultoría de gestión empresarial</option>
                                        <option value="71">Servicios técnicos de arquitectura e ingeniería</option>
                                        <option value="72">Investigación y desarrollo</option>
                                        <option value="73">Publicidad y estudios de mercado</option>
                                        <option value="74">Otras actividades profesionales, científicas y técnicas</option>
                                        <option value="75">Actividades veterinarias</option>
                                    </optgroup>
                                    <optgroup label="Actividades Administrativas y Servicios Auxiliares">
                                        <option value="77">Actividades de alquiler</option>
                                        <option value="78">Actividades relacionadas con el empleo</option>
                                        <option value="79">Actividades de agencias de viajes</option>
                                        <option value="80">Actividades de seguridad e investigación</option>
                                        <option value="81">Servicios a edificios y actividades de jardinería</option>
                                        <option value="82">Actividades administrativas de oficina y otras actividades auxiliares</option>
                                    </optgroup>
                                    <optgroup label="Administración Pública">
                                        <option value="84">Administración Pública y defensa</option>
                                    </optgroup>
                                    <optgroup label="Educación">
                                        <option value="85">Educación</option>
                                    </optgroup>
                                    <optgroup label="Actividades Sanitarias y de Servicios Sociales">
                                        <option value="86">Actividades sanitarias</option>
                                        <option value="87">Asistencia en establecimientos residenciales</option>
                                        <option value="88">Actividades de servicios sociales sin alojamiento</option>
                                    </optgroup>
                                    <optgroup label="Actividades Artísticas, Recreativas y de Entretenimiento">
                                        <option value="90">Actividades de creación, artísticas y espectáculos</option>
                                        <option value="91">Actividades de bibliotecas, archivos, museos</option>
                                        <option value="92">Actividades de juegos de azar y apuestas</option>
                                        <option value="93">Actividades deportivas, recreativas y de entretenimiento</option>
                                    </optgroup>
                                    <optgroup label="Otros Servicios">
                                        <option value="94">Actividades asociativas</option>
                                        <option value="95">Reparación de ordenadores, efectos personales y artículos de uso doméstico</option>
                                        <option value="96">Otros servicios personales</option>
                                    </optgroup>
                                </select>
                                <div class="form-text">Selecciona uno o varios sectores (Ctrl+Click para múltiple)</div>
                            </div>

                            <!-- Ubicación -->
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="provincia" class="form-label">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        Provincia
                                    </label>
                                    <select class="form-select" id="provincia" multiple>
                                        <option value="alava">Álava</option>
                                        <option value="albacete">Albacete</option>
                                        <option value="alicante">Alicante</option>
                                        <option value="almeria">Almería</option>
                                        <option value="avila">Ávila</option>
                                        <option value="badajoz">Badajoz</option>
                                        <option value="baleares">Baleares</option>
                                        <option value="barcelona">Barcelona</option>
                                        <option value="burgos">Burgos</option>
                                        <option value="caceres">Cáceres</option>
                                        <option value="cadiz">Cádiz</option>
                                        <option value="castellon">Castellón</option>
                                        <option value="ciudadreal">Ciudad Real</option>
                                        <option value="cordoba">Córdoba</option>
                                        <option value="coruna">A Coruña</option>
                                        <option value="cuenca">Cuenca</option>
                                        <option value="girona">Girona</option>
                                        <option value="granada">Granada</option>
                                        <option value="guadalajara">Guadalajara</option>
                                        <option value="guipuzcoa">Guipúzcoa</option>
                                        <option value="huelva">Huelva</option>
                                        <option value="huesca">Huesca</option>
                                        <option value="jaen">Jaén</option>
                                        <option value="leon">León</option>
                                        <option value="lleida">Lleida</option>
                                        <option value="lugo">Lugo</option>
                                        <option value="madrid">Madrid</option>
                                        <option value="malaga">Málaga</option>
                                        <option value="murcia">Murcia</option>
                                        <option value="navarra">Navarra</option>
                                        <option value="ourense">Ourense</option>
                                        <option value="asturias">Asturias</option>
                                        <option value="palencia">Palencia</option>
                                        <option value="laspalmas">Las Palmas</option>
                                        <option value="pontevedra">Pontevedra</option>
                                        <option value="larioja">La Rioja</option>
                                        <option value="salamanca">Salamanca</option>
                                        <option value="santacruz">Santa Cruz de Tenerife</option>
                                        <option value="cantabria">Cantabria</option>
                                        <option value="segovia">Segovia</option>
                                        <option value="sevilla">Sevilla</option>
                                        <option value="soria">Soria</option>
                                        <option value="tarragona">Tarragona</option>
                                        <option value="teruel">Teruel</option>
                                        <option value="toledo">Toledo</option>
                                        <option value="valencia">Valencia</option>
                                        <option value="valladolid">Valladolid</option>
                                        <option value="vizcaya">Vizcaya</option>
                                        <option value="zamora">Zamora</option>
                                        <option value="zaragoza">Zaragoza</option>
                                        <option value="ceuta">Ceuta</option>
                                        <option value="melilla">Melilla</option>
                                    </select>
                                    <div class="form-text">Selecciona una o varias provincias</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="region" class="form-label">
                                        <i class="fas fa-globe-europe me-1"></i>
                                        Comunidad Autónoma
                                    </label>
                                    <select class="form-select" id="region" multiple>
                                        <option value="andalucia">Andalucía</option>
                                        <option value="aragon">Aragón</option>
                                        <option value="asturias">Principado de Asturias</option>
                                        <option value="baleares">Islas Baleares</option>
                                        <option value="canarias">Canarias</option>
                                        <option value="cantabria">Cantabria</option>
                                        <option value="castillalamancha">Castilla-La Mancha</option>
                                        <option value="castillaleon">Castilla y León</option>
                                        <option value="cataluna">Cataluña</option>
                                        <option value="extremadura">Extremadura</option>
                                        <option value="galicia">Galicia</option>
                                        <option value="madrid">Comunidad de Madrid</option>
                                        <option value="murcia">Región de Murcia</option>
                                        <option value="navarra">Comunidad Foral de Navarra</option>
                                        <option value="paisvasco">País Vasco</option>
                                        <option value="larioja">La Rioja</option>
                                        <option value="valencia">Comunidad Valenciana</option>
                                        <option value="ceuta">Ceuta</option>
                                        <option value="melilla">Melilla</option>
                                    </select>
                                    <div class="form-text">Selecciona una o varias comunidades autónomas</div>
                                </div>
                            </div>

                            <!-- Facturación -->
                            <div class="mb-3">
                                <label for="facturacion" class="form-label">
                                    <i class="fas fa-euro-sign me-1"></i>
                                    Facturación
                                </label>
                                <select class="form-select" id="facturacion">
                                    <option value="">Cualquiera</option>
                                    <option value="0-100K">0-100K €</option>
                                    <option value="100K-500K">100K-500K €</option>
                                    <option value="500K-1M">500K-1M €</option>
                                    <option value="1M-5M">1M-5M €</option>
                                    <option value="5M-10M">5M-10M €</option>
                                    <option value="10M-50M">10M-50M €</option>
                                    <option value="50M+">+50M €</option>
                                </select>
                            </div>

                            <!-- Número de Resultados -->
                            <div class="mb-3">
                                <label for="numResults" class="form-label">
                                    <i class="fas fa-list-ol me-1"></i>
                                    Número de Resultados
                                </label>
                                <input type="number" class="form-control" id="numResults" 
                                       value="20" min="1" max="1000">
                                <div class="form-text">Máximo: 1000 resultados</div>
                            </div>

                            <!-- Botón de Búsqueda -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg" id="startSearch">
                                    <i class="fas fa-search me-2"></i>
                                    Iniciar Captura de Leads
                                </button>
                            </div>
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
                        <div id="searchResults" class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">Configura los filtros y inicia la búsqueda</h4>
                            <p class="text-muted">Los resultados aparecerán aquí una vez iniciada la captura.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-5">
        <div class="container text-center">
            <p class="mb-0">
                <strong>ScrapperLeads Pro</strong> - Sistema profesional de captura de leads empresariales
                <span class="ms-3">© 2025 AUREA INNOVACIÓN</span>
            </p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>

