<?php

namespace ScrapperLeads\Utils;

use ScrapperLeads\Config\Config;

/**
 * Clase principal para el scraping de leads desde Google
 * Implementa búsquedas inteligentes y extracción de datos empresariales
 */
class GoogleScraper
{
    private $config;
    private $logger;
    private $userAgents;
    private $proxyList;
    
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->logger = new Logger();
        $this->initializeUserAgents();
        $this->initializeProxies();
    }
    
    /**
     * Busca leads empresariales según los parámetros especificados
     */
    public function searchLeads(array $params, string $sessionId): array
    {
        $this->logger->info("Iniciando búsqueda de leads para sesión: {$sessionId}");
        
        // Construir query de búsqueda
        $searchQuery = $this->buildSearchQuery($params);
        $this->logger->info("Query de búsqueda: {$searchQuery}");
        
        // Inicializar progreso
        $this->updateProgress($sessionId, 0, 'Iniciando búsqueda...');
        
        $results = [];
        $maxResults = $params['numResults'];
        $currentPage = 0;
        $resultsPerPage = 10;
        
        while (count($results) < $maxResults && $currentPage < 10) {
            $this->updateProgress($sessionId, 
                (count($results) / $maxResults) * 100, 
                "Procesando página " . ($currentPage + 1) . "..."
            );
            
            // Realizar búsqueda en Google
            $pageResults = $this->searchGooglePage($searchQuery, $currentPage);
            
            if (empty($pageResults)) {
                $this->logger->info("No se encontraron más resultados en la página {$currentPage}");
                break;
            }
            
            // Procesar cada resultado
            foreach ($pageResults as $result) {
                if (count($results) >= $maxResults) break;
                
                $leadData = $this->extractLeadData($result, $params);
                if ($leadData) {
                    $results[] = $leadData;
                }
                
                // Delay entre requests para evitar bloqueos
                usleep($this->config->get('scraper.delay_between_requests', 1) * 1000000);
            }
            
            $currentPage++;
            
            // Delay entre páginas
            sleep(2);
        }
        
        $this->updateProgress($sessionId, 100, 'Búsqueda completada');
        $this->logger->info("Búsqueda completada. Total de leads encontrados: " . count($results));
        
        return $results;
    }
    
    /**
     * Construye la query de búsqueda optimizada
     */
    private function buildSearchQuery(array $params): string
    {
        $query = $params['keywords'];
        
        // Añadir términos específicos para empresas
        $businessTerms = ['empresa', 'compañía', 'sociedad', 'S.L.', 'S.A.', 'contacto', 'teléfono'];
        $query .= ' ' . implode(' OR ', $businessTerms);
        
        // Filtros geográficos
        if (!empty($params['provincia'])) {
            $query .= ' "' . $params['provincia'] . '"';
        }
        
        if (!empty($params['region'])) {
            $query .= ' "' . $params['region'] . '"';
        }
        
        // Filtros de sector
        if (!empty($params['sector'])) {
            $sectorTerms = $this->getSectorTerms($params['sector']);
            $query .= ' (' . implode(' OR ', $sectorTerms) . ')';
        }
        
        // Excluir sitios no relevantes
        $excludeSites = ['-site:linkedin.com', '-site:facebook.com', '-site:twitter.com', 
                        '-site:instagram.com', '-site:youtube.com', '-site:wikipedia.org'];
        $query .= ' ' . implode(' ', $excludeSites);
        
        return $query;
    }
    
    /**
     * Realiza búsqueda en una página específica de Google
     */
    private function searchGooglePage(string $query, int $page): array
    {
        $start = $page * 10;
        $url = "https://www.google.com/search?" . http_build_query([
            'q' => $query,
            'start' => $start,
            'num' => 10,
            'hl' => 'es',
            'gl' => 'es'
        ]);
        
        $html = $this->makeRequest($url);
        
        if (!$html) {
            $this->logger->warning("No se pudo obtener contenido de la página {$page}");
            return [];
        }
        
        return $this->parseGoogleResults($html);
    }
    
    /**
     * Realiza una petición HTTP con rotación de user agents
     */
    private function makeRequest(string $url): ?string
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->config->get('scraper.timeout', 30),
            CURLOPT_USERAGENT => $this->getRandomUserAgent(),
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.8,en;q=0.6',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => 'gzip',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            $this->logger->error('cURL Error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->logger->warning("HTTP Error {$httpCode} para URL: {$url}");
            return null;
        }
        
        return $response;
    }
    
    /**
     * Parsea los resultados de Google
     */
    private function parseGoogleResults(string $html): array
    {
        $results = [];
        
        // Usar DOMDocument para parsear HTML
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        
        // Buscar elementos de resultados de búsqueda
        $resultNodes = $xpath->query('//div[@class="g"]');
        
        foreach ($resultNodes as $node) {
            $result = [];
            
            // Extraer título
            $titleNode = $xpath->query('.//h3', $node)->item(0);
            if ($titleNode) {
                $result['title'] = trim($titleNode->textContent);
            }
            
            // Extraer URL
            $linkNode = $xpath->query('.//a[@href]', $node)->item(0);
            if ($linkNode) {
                $result['url'] = $linkNode->getAttribute('href');
            }
            
            // Extraer descripción
            $descNode = $xpath->query('.//span[contains(@class, "st")]', $node)->item(0);
            if (!$descNode) {
                $descNode = $xpath->query('.//div[contains(@class, "s")]//span', $node)->item(0);
            }
            if ($descNode) {
                $result['description'] = trim($descNode->textContent);
            }
            
            if (!empty($result['title']) && !empty($result['url'])) {
                $results[] = $result;
            }
        }
        
        return $results;
    }
    
    /**
     * Extrae datos de lead de un resultado de búsqueda
     */
    private function extractLeadData(array $result, array $params): ?array
    {
        // Filtrar resultados no relevantes
        if (!$this->isRelevantResult($result, $params)) {
            return null;
        }
        
        $leadData = [
            'empresa' => $this->extractCompanyName($result),
            'url' => $result['url'],
            'descripcion' => $result['description'] ?? '',
            'telefono' => $this->extractPhone($result),
            'email' => $this->extractEmail($result),
            'direccion' => $this->extractAddress($result),
            'empleados' => $this->estimateEmployees($result, $params),
            'facturacion' => $this->estimateRevenue($result, $params),
            'sector' => $params['sector'] ?? 'No especificado',
            'fecha_captura' => date('Y-m-d H:i:s'),
            'fuente' => 'Google Search'
        ];
        
        // Intentar obtener más datos de la página web
        $this->enrichLeadData($leadData);
        
        return $leadData;
    }
    
    /**
     * Verifica si un resultado es relevante
     */
    private function isRelevantResult(array $result, array $params): bool
    {
        $title = strtolower($result['title'] ?? '');
        $description = strtolower($result['description'] ?? '');
        $content = $title . ' ' . $description;
        
        // Palabras clave que indican empresas
        $businessKeywords = ['empresa', 'compañía', 'sociedad', 'sl', 'sa', 'contacto', 'servicios'];
        
        foreach ($businessKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return true;
            }
        }
        
        // Verificar si contiene las palabras clave de búsqueda
        $searchKeywords = explode(' ', strtolower($params['keywords']));
        foreach ($searchKeywords as $keyword) {
            if (strlen($keyword) > 3 && strpos($content, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extrae el nombre de la empresa
     */
    private function extractCompanyName(array $result): string
    {
        $title = $result['title'] ?? '';
        
        // Limpiar título
        $title = preg_replace('/\s*-\s*.*$/', '', $title); // Remover texto después de guión
        $title = preg_replace('/\s*\|\s*.*$/', '', $title); // Remover texto después de pipe
        
        return trim($title) ?: 'Empresa no identificada';
    }
    
    /**
     * Extrae teléfono del contenido
     */
    private function extractPhone(array $result): string
    {
        $content = ($result['title'] ?? '') . ' ' . ($result['description'] ?? '');
        
        // Patrones de teléfono españoles
        $patterns = [
            '/(\+34\s?)?[6-9]\d{2}\s?\d{3}\s?\d{3}/',
            '/(\+34\s?)?[6-9]\d{8}/',
            '/(\+34\s?)?\d{3}\s?\d{3}\s?\d{3}/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return trim($matches[0]);
            }
        }
        
        return '';
    }
    
    /**
     * Extrae email del contenido
     */
    private function extractEmail(array $result): string
    {
        $content = ($result['title'] ?? '') . ' ' . ($result['description'] ?? '');
        
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $matches)) {
            return $matches[0];
        }
        
        return '';
    }
    
    /**
     * Extrae dirección del contenido
     */
    private function extractAddress(array $result): string
    {
        $content = ($result['description'] ?? '');
        
        // Buscar patrones de dirección
        $addressPatterns = [
            '/Calle\s+[^,]+,\s*\d+/',
            '/Avenida\s+[^,]+,\s*\d+/',
            '/Plaza\s+[^,]+,\s*\d+/',
            '/C\/\s*[^,]+,\s*\d+/',
        ];
        
        foreach ($addressPatterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return trim($matches[0]);
            }
        }
        
        return '';
    }
    
    /**
     * Estima el número de empleados
     */
    private function estimateEmployees(array $result, array $params): string
    {
        if (!empty($params['empleados'])) {
            return $params['empleados'];
        }
        
        // Lógica básica de estimación basada en contenido
        $content = strtolower(($result['title'] ?? '') . ' ' . ($result['description'] ?? ''));
        
        if (strpos($content, 'multinacional') !== false || strpos($content, 'corporación') !== false) {
            return '500+';
        } elseif (strpos($content, 'mediana empresa') !== false) {
            return '51-200';
        } elseif (strpos($content, 'pequeña empresa') !== false || strpos($content, 'pyme') !== false) {
            return '11-50';
        }
        
        return 'No especificado';
    }
    
    /**
     * Estima la facturación
     */
    private function estimateRevenue(array $result, array $params): string
    {
        if (!empty($params['facturacion'])) {
            return $params['facturacion'];
        }
        
        return 'No especificado';
    }
    
    /**
     * Enriquece los datos del lead con información adicional
     */
    private function enrichLeadData(array &$leadData): void
    {
        // Intentar obtener más información de la página web
        if (!empty($leadData['url'])) {
            $pageContent = $this->makeRequest($leadData['url']);
            if ($pageContent) {
                // Extraer información adicional de la página
                if (empty($leadData['telefono'])) {
                    $leadData['telefono'] = $this->extractPhone(['description' => $pageContent]);
                }
                if (empty($leadData['email'])) {
                    $leadData['email'] = $this->extractEmail(['description' => $pageContent]);
                }
            }
        }
    }
    
    /**
     * Obtiene términos relacionados con un sector
     */
    private function getSectorTerms(string $sector): array
    {
        $sectorTerms = [
            'tecnologia' => ['software', 'desarrollo', 'IT', 'tecnología', 'informática', 'digital'],
            'construccion' => ['construcción', 'obra', 'edificación', 'arquitectura', 'ingeniería'],
            'salud' => ['salud', 'médico', 'clínica', 'hospital', 'sanitario', 'farmacia'],
            'educacion' => ['educación', 'formación', 'academia', 'colegio', 'universidad'],
            'finanzas' => ['finanzas', 'banco', 'seguros', 'inversión', 'financiero'],
            'retail' => ['comercio', 'tienda', 'retail', 'venta', 'distribución'],
            'industria' => ['industria', 'fabricación', 'producción', 'manufactura'],
            'servicios' => ['servicios', 'consultoría', 'asesoría', 'gestión']
        ];
        
        return $sectorTerms[$sector] ?? [$sector];
    }
    
    /**
     * Actualiza el progreso de la sesión
     */
    private function updateProgress(string $sessionId, float $percentage, string $message): void
    {
        $progress = [
            'percentage' => round($percentage, 2),
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $progressFile = sys_get_temp_dir() . "/scraper_progress_{$sessionId}.json";
        file_put_contents($progressFile, json_encode($progress));
    }
    
    /**
     * Inicializa la lista de user agents
     */
    private function initializeUserAgents(): void
    {
        $this->userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ];
    }
    
    /**
     * Inicializa la lista de proxies (opcional)
     */
    private function initializeProxies(): void
    {
        $this->proxyList = [];
        // Aquí se pueden añadir proxies si es necesario
    }
    
    /**
     * Obtiene un user agent aleatorio
     */
    private function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }
}

