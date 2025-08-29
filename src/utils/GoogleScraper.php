<?php

namespace ScrapperLeads\Utils;

use ScrapperLeads\Config\Config;
use Exception;

/**
 * Clase principal para el scraping de leads usando Google Places API
 * Implementa búsquedas profesionales y extracción de datos empresariales
 */
class GoogleScraper
{
    private $config;
    private $logger;
    private $apiKey;
    private $baseUrl = 'https://maps.googleapis.com/maps/api/place';

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->logger = new Logger();
        $this->apiKey = $config->get('google.places_api_key', 'AIzaSyBvOkBwAqITJ5ohHXbQmk2dEwKI2cU_-dM');
    }

    /**
     * Busca leads empresariales usando Google Places API
     */
    public function searchLeads(array $params, string $sessionId): array
    {
        $this->logger->info("Iniciando búsqueda de leads con Google Places API para sesión: {$sessionId}");

        $searchQuery = $this->buildSearchQuery($params);
        $this->logger->info("Query de búsqueda: {$searchQuery}");

        $this->updateProgress($sessionId, 0, 'Iniciando búsqueda con Google Places API...');

        $results = [];
        $maxResults = min($params['maxResults'] ?? 10, 60); // Google Places API limit
        $nextPageToken = null;
        $currentPage = 0;

        try {
            do {
                $this->updateProgress(
                    $sessionId,
                    (count($results) / $maxResults) * 50,
                    "Buscando en Google Places - Página " . ($currentPage + 1) . "..."
                );

                $apiResults = $this->searchPlaces($searchQuery, $nextPageToken, $params);

                if (!$apiResults || !isset($apiResults['results'])) {
                    break;
                }

                foreach ($apiResults['results'] as $index => $place) {
                    if (count($results) >= $maxResults) {
                        break 2;
                    }

                    $progress = ((count($results) + 1) / $maxResults) * 80;
                    $this->updateProgress(
                        $sessionId,
                        $progress,
                        "Procesando empresa " . (count($results) + 1) . " de {$maxResults}..."
                    );

                    $placeDetails = $this->getPlaceDetails($place['place_id']);
                    $lead = $this->convertPlaceToLead($place, $placeDetails, $params);

                    if ($lead) {
                        $results[] = $lead;
                    }

                    usleep(100000); // 0.1 segundos
                }

                $nextPageToken = $apiResults['next_page_token'] ?? null;
                $currentPage++;

                if ($nextPageToken) {
                    sleep(2);
                }
            } while ($nextPageToken && count($results) < $maxResults && $currentPage < 3);

            $this->updateProgress(
                $sessionId,
                100,
                'Búsqueda completada exitosamente',
                'completed',
                [
                    'totalFound' => count($results),
                    'resultsProcessed' => count($results),
                    'avgQuality' => $this->calculateAverageQuality($results),
                    'duration' => time() - strtotime('now')
                ]
            );

            $this->logger->info("Búsqueda completada. Encontrados: " . count($results) . " leads");
        } catch (Exception $e) {
            $this->logger->error("Error en búsqueda: " . $e->getMessage());
            $this->updateProgress($sessionId, 0, 'Error: ' . $e->getMessage(), 'error');
            throw $e;
        }

        return $results;
    }

    /**
     * Realiza búsqueda en Google Places API
     */
    private function searchPlaces(string $query, ?string $pageToken = null, array $params = []): ?array
    {
        $url = $this->baseUrl . '/textsearch/json';

        $queryParams = [
            'query' => $query,
            'key' => $this->apiKey,
            'language' => 'es',
            'region' => 'es'
        ];

        if (!empty($params['provinces']) || !empty($params['regions'])) {
            $location = $this->buildLocationFilter($params);
            if ($location) {
                $queryParams['location'] = $location['lat'] . ',' . $location['lng'];
                $queryParams['radius'] = $location['radius'] ?? 50000;
            }
        }

        if ($pageToken) {
            $queryParams['pagetoken'] = $pageToken;
        }

        $fullUrl = $url . '?' . http_build_query($queryParams);
        $this->logger->info("Consultando Google Places API: " . $fullUrl);

        $response = $this->makeApiRequest($fullUrl);

        if (!$response) {
            throw new Exception('Error al conectar con Google Places API');
        }

        $data = json_decode($response, true);

        if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
            $this->logger->error("Error en API: " . $data['status'] . " - " . ($data['error_message'] ?? ''));
            throw new Exception('Error en Google Places API: ' . $data['status']);
        }

        return $data;
    }

    /**
     * Obtiene detalles completos de un lugar
     */
    private function getPlaceDetails(string $placeId): ?array
    {
        $url = $this->baseUrl . '/details/json';

        $queryParams = [
            'place_id' => $placeId,
            'key' => $this->apiKey,
            'language' => 'es',
            'fields' => 'name,formatted_address,formatted_phone_number,website,business_status,opening_hours,' .
                'rating,user_ratings_total,types,geometry,plus_code'
        ];

        $fullUrl = $url . '?' . http_build_query($queryParams);

        $response = $this->makeApiRequest($fullUrl);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);

        if ($data['status'] !== 'OK') {
            $this->logger->warning("No se pudieron obtener detalles para place_id: {$placeId}");
            return null;
        }

        return $data['result'] ?? null;
    }

    /**
     * Convierte un lugar de Google Places a formato de lead
     */
    private function convertPlaceToLead(array $place, ?array $details, array $params): ?array
    {
        if (!empty($params['sectors']) && !$this->matchesSectors($place, $params['sectors'])) {
            return null;
        }

        return [
            'id' => uniqid('lead_'),
            'company_name' => $place['name'] ?? 'N/A',
            'address' => $place['formatted_address'] ?? ($details['formatted_address'] ?? 'N/A'),
            'phone' => $details['formatted_phone_number'] ?? 'N/A',
            'website' => $details['website'] ?? 'N/A',
            'email' => $this->extractEmailFromWebsite($details['website'] ?? ''),
            'business_status' => $details['business_status'] ?? 'OPERATIONAL',
            'rating' => $place['rating'] ?? ($details['rating'] ?? 0),
            'total_ratings' => $details['user_ratings_total'] ?? 0,
            'types' => implode(', ', $place['types'] ?? []),
            'latitude' => $place['geometry']['location']['lat'] ?? 0,
            'longitude' => $place['geometry']['location']['lng'] ?? 0,
            'employees' => $this->estimateEmployees($place, $details),
            'revenue' => $this->estimateRevenue($place, $details),
            'founded_date' => 'N/A',
            'quality_score' => $this->calculateQualityScore($place, $details),
            'source' => 'Google Places API',
            'found_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Construye query de búsqueda basado en parámetros
     */
    private function buildSearchQuery(array $params): string
    {
        $queryParts = [];

        if (!empty($params['keywords'])) {
            $queryParts[] = $params['keywords'];
        }

        if (!empty($params['sectors']) && is_array($params['sectors'])) {
            $sectorTerms = [];
            foreach ($params['sectors'] as $sector) {
                $sectorTerms[] = $this->translateSectorToSearchTerm($sector);
            }
            if (!empty($sectorTerms)) {
                $queryParts[] = implode(' OR ', $sectorTerms);
            }
        }

        $locationParts = [];
        if (!empty($params['provinces']) && is_array($params['provinces'])) {
            $locationParts = array_merge($locationParts, $params['provinces']);
        }
        if (!empty($params['regions']) && is_array($params['regions'])) {
            $locationParts = array_merge($locationParts, $params['regions']);
        }

        if (!empty($locationParts)) {
            $queryParts[] = implode(' OR ', $locationParts);
        }

        if (empty($queryParts)) {
            $queryParts[] = 'empresa negocio';
        }

        return implode(' ', $queryParts);
    }

    /**
     * Construye filtro de ubicación para la API
     */
    private function buildLocationFilter(array $params): ?array
    {
        $locations = [
            'Barcelona' => ['lat' => 41.3851, 'lng' => 2.1734, 'radius' => 30000],
            'Madrid' => ['lat' => 40.4168, 'lng' => -3.7038, 'radius' => 40000],
            'Valencia' => ['lat' => 39.4699, 'lng' => -0.3763, 'radius' => 25000],
            'Sevilla' => ['lat' => 37.3891, 'lng' => -5.9845, 'radius' => 20000],
            'Bilbao' => ['lat' => 43.2627, 'lng' => -2.9253, 'radius' => 15000],
        ];

        $allLocations = array_merge($params['provinces'] ?? [], $params['regions'] ?? []);

        foreach ($allLocations as $location) {
            foreach ($locations as $city => $coords) {
                if (stripos($location, $city) !== false || stripos($city, $location) !== false) {
                    return $coords;
                }
            }
        }

        return null;
    }

    /**
     * Realiza petición HTTP a la API
     */
    private function makeApiRequest(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ScrapperLeads-PHP/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 30
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->logger->error("Error al realizar petición HTTP: {$url}");
            return null;
        }

        return $response;
    }

    /**
     * Verifica si un lugar coincide con los sectores especificados
     */
    private function matchesSectors(array $place, array $sectors): bool
    {
        if (empty($sectors)) {
            return true;
        }

        $placeTypes = $place['types'] ?? [];
        $placeName = strtolower($place['name'] ?? '');

        foreach ($sectors as $sector) {
            $sectorKeywords = $this->getSectorKeywords($sector);

            foreach ($placeTypes as $type) {
                if (in_array($type, $sectorKeywords)) {
                    return true;
                }
            }

            foreach ($sectorKeywords as $keyword) {
                if (stripos($placeName, $keyword) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Obtiene palabras clave para un sector
     */
    private function getSectorKeywords(string $sector): array
    {
        $keywords = [
            'Servicios financieros' => ['bank', 'finance', 'financial_services', 'insurance_agency', 'accounting'],
            'Tecnología' => ['electronics_store', 'computer_repair', 'software', 'technology'],
            'Restauración' => ['restaurant', 'food', 'meal_takeaway', 'bakery', 'cafe'],
            'Retail' => ['store', 'shopping_mall', 'clothing_store', 'shoe_store'],
            'Salud' => ['hospital', 'doctor', 'pharmacy', 'health', 'medical'],
            'Educación' => ['school', 'university', 'education', 'training'],
            'Construcción' => ['construction', 'contractor', 'plumber', 'electrician'],
            'Transporte' => ['transportation', 'logistics', 'moving_company', 'taxi'],
        ];

        return $keywords[$sector] ?? [strtolower($sector)];
    }

    /**
     * Traduce sector CNAE a términos de búsqueda
     */
    private function translateSectorToSearchTerm(string $sector): string
    {
        $translations = [
            'Servicios financieros' => 'bank finance insurance',
            'Actividades auxiliares a los servicios financieros y a los seguros' => 'financial services insurance',
            'Tecnología' => 'technology software computer',
            'Restauración' => 'restaurant food catering',
            'Retail' => 'store shop retail',
            'Salud' => 'health medical hospital',
            'Educación' => 'education school training',
            'Construcción' => 'construction building contractor',
            'Transporte' => 'transport logistics delivery',
        ];

        return $translations[$sector] ?? $sector;
    }

    /**
     * Estima número de empleados basado en datos disponibles
     */
    private function estimateEmployees(array $place, ?array $details): string
    {
        $totalRatings = $details['user_ratings_total'] ?? 0;

        if ($totalRatings > 1000) {
            return '50-200';
        }
        if ($totalRatings > 500) {
            return '20-50';
        }
        if ($totalRatings > 100) {
            return '10-20';
        }
        if ($totalRatings > 20) {
            return '5-10';
        }
        return '1-5';
    }

    /**
     * Estima facturación basada en datos disponibles
     */
    private function estimateRevenue(array $place, ?array $details): string
    {
        $totalRatings = $details['user_ratings_total'] ?? 0;
        $types = $place['types'] ?? [];

        $highRevenueTypes = ['bank', 'insurance_agency', 'hospital', 'shopping_mall'];
        $isHighRevenue = !empty(array_intersect($types, $highRevenueTypes));

        if ($isHighRevenue && $totalRatings > 500) {
            return '5M-10M €';
        }
        if ($isHighRevenue && $totalRatings > 100) {
            return '1M-5M €';
        }
        if ($totalRatings > 1000) {
            return '1M-5M €';
        }
        if ($totalRatings > 500) {
            return '500K-1M €';
        }
        if ($totalRatings > 100) {
            return '100K-500K €';
        }
        return '0-100K €';
    }

    /**
     * Calcula puntuación de calidad del lead
     */
    private function calculateQualityScore(array $place, ?array $details): float
    {
        $score = 0.5;

        if (!empty($details['formatted_phone_number'])) {
            $score += 0.2;
        }
        if (!empty($details['website'])) {
            $score += 0.2;
        }
        if (($place['rating'] ?? 0) >= 4.0) {
            $score += 0.1;
        }
        if (($details['user_ratings_total'] ?? 0) > 50) {
            $score += 0.1;
        }

        return min($score, 1.0);
    }

    /**
     * Calcula calidad promedio de todos los leads
     */
    private function calculateAverageQuality(array $results): float
    {
        if (empty($results)) {
            return 0.0;
        }

        $totalQuality = array_sum(array_column($results, 'quality_score'));
        return round($totalQuality / count($results), 2);
    }

    /**
     * Intenta extraer email de un website (simplificado)
     */
    private function extractEmailFromWebsite(string $website): string
    {
        if (empty($website)) {
            return 'N/A';
        }

        $domain = parse_url($website, PHP_URL_HOST);
        if ($domain) {
            return 'info@' . str_replace('www.', '', $domain);
        }

        return 'N/A';
    }

    /**
     * Actualiza el progreso de la búsqueda
     */
    private function updateProgress(
        string $sessionId,
        float $progress,
        string $message,
        string $status = 'running',
        array $stats = []
    ): void {
        $progressData = [
            'sessionId' => $sessionId,
            'progress' => round($progress, 1),
            'message' => $message,
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'stats' => $stats
        ];

        $progressFile = sys_get_temp_dir() . "/scraper_progress_{$sessionId}.json";
        file_put_contents($progressFile, json_encode($progressData));

        $this->logger->info("Progreso actualizado: {$progress}% - {$message}");
    }
}
