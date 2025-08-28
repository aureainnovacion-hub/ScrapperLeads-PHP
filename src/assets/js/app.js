/**
 * ScrapperLeads Pro - JavaScript Principal
 * Sistema profesional de captura de leads empresariales
 */

class ScrapperLeads {
    constructor() {
        this.searchInProgress = false;
        this.currentSearchId = null;
        this.progressInterval = null;
        
        this.init();
    }

    init() {
        console.log('🚀 ScrapperLeads Pro iniciado');
        
        // Inicializar Select2 para selección múltiple
        this.initializeSelect2();
        
        // Configurar eventos
        this.setupEventListeners();
        
        // Configurar notificaciones
        this.setupNotifications();
    }

    initializeSelect2() {
        // Inicializar Select2 para sectores
        $('#sector').select2({
            theme: 'bootstrap-5',
            placeholder: 'Selecciona sectores empresariales',
            allowClear: true,
            closeOnSelect: false,
            width: '100%'
        });

        // Inicializar Select2 para provincias
        $('#provincia').select2({
            theme: 'bootstrap-5',
            placeholder: 'Selecciona provincias',
            allowClear: true,
            closeOnSelect: false,
            width: '100%'
        });

        // Inicializar Select2 para regiones
        $('#region').select2({
            theme: 'bootstrap-5',
            placeholder: 'Selecciona comunidades autónomas',
            allowClear: true,
            closeOnSelect: false,
            width: '100%'
        });
    }

    setupEventListeners() {
        // Formulario de búsqueda
        $('#searchForm').on('submit', (e) => {
            e.preventDefault();
            this.startSearch();
        });

        // Validación en tiempo real
        $('#keywords').on('input', this.validateForm.bind(this));
        $('#numResults').on('input', this.validateNumResults.bind(this));
        $('#sector').on('change', this.validateForm.bind(this));
        $('#provincia').on('change', this.validateForm.bind(this));
        $('#region').on('change', this.validateForm.bind(this));
    }

    setupNotifications() {
        // Sistema de notificaciones personalizado
        this.showNotification = (message, type = 'info', duration = 5000) => {
            const alertClass = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            }[type] || 'alert-info';

            const notification = $(`
                <div class="alert ${alertClass} alert-dismissible fade show notification-custom" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);

            $('body').prepend(notification);

            if (duration > 0) {
                setTimeout(() => {
                    notification.fadeOut(() => notification.remove());
                }, duration);
            }
        };
    }

    validateForm() {
        const sectors = $('#sector').val();
        const provinces = $('#provincia').val();
        const regions = $('#region').val();
        const keywords = $('#keywords').val().trim();
        
        // Al menos debe haber un filtro seleccionado
        const hasFilters = (sectors && sectors.length > 0) || 
                          (provinces && provinces.length > 0) || 
                          (regions && regions.length > 0) || 
                          keywords.length > 0;
        
        $('#startSearch').prop('disabled', !hasFilters);
        
        return hasFilters;
    }

    validateNumResults() {
        const numResults = parseInt($('#numResults').val());
        
        if (numResults < 1) {
            $('#numResults').val(1);
        } else if (numResults > 1000) {
            $('#numResults').val(1000);
            this.showNotification('Máximo 1000 resultados permitidos', 'warning');
        }
    }

    async startSearch() {
        if (this.searchInProgress) {
            this.showNotification('Ya hay una búsqueda en progreso', 'warning');
            return;
        }

        if (!this.validateForm()) {
            this.showNotification('Debes seleccionar al menos un filtro de búsqueda', 'error');
            return;
        }

        this.searchInProgress = true;
        $('#startSearch').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Iniciando...');

        try {
            // Recopilar datos del formulario
            const searchData = this.getFormData();
            
            // Mostrar panel de progreso
            this.showProgressPanel();
            
            // Iniciar búsqueda
            const response = await this.makeRequest('/api/scraper.php', {
                method: 'POST',
                body: JSON.stringify(searchData),
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (response.success) {
                this.currentSearchId = response.searchId;
                this.showNotification('Búsqueda iniciada correctamente', 'success');
                this.startProgressMonitoring();
            } else {
                throw new Error(response.message || 'Error al iniciar la búsqueda');
            }

        } catch (error) {
            console.error('Error en búsqueda:', error);
            this.showNotification(`Error: ${error.message}`, 'error');
            this.resetSearchState();
        }
    }

    getFormData() {
        return {
            keywords: $('#keywords').val().trim(),
            sectors: $('#sector').val() || [],
            provinces: $('#provincia').val() || [],
            regions: $('#region').val() || [],
            revenue: $('#facturacion').val(),
            maxResults: parseInt($('#numResults').val()) || 20
        };
    }

    showProgressPanel() {
        const progressHtml = `
            <div class="progress-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-search me-2"></i>
                        Capturando Leads...
                    </h5>
                    <button class="btn btn-outline-danger btn-sm" onclick="scrapperLeads.stopSearch()">
                        <i class="fas fa-stop me-1"></i>
                        Detener
                    </button>
                </div>
                
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%" id="searchProgress">
                        0%
                    </div>
                </div>
                
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <h6 class="card-title mb-1">Encontrados</h6>
                                <span class="h4 text-primary" id="totalFound">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <h6 class="card-title mb-1">Procesados</h6>
                                <span class="h4 text-success" id="totalProcessed">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <h6 class="card-title mb-1">Calidad</h6>
                                <span class="h4 text-warning" id="avgQuality">-</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted" id="progressMessage">Iniciando búsqueda...</small>
                </div>
            </div>
        `;

        $('#searchResults').html(progressHtml);
    }

    async startProgressMonitoring() {
        this.progressInterval = setInterval(async () => {
            try {
                const response = await this.makeRequest(`/api/scraper.php?action=progress&searchId=${this.currentSearchId}`);
                
                if (response.success) {
                    this.updateProgress(response.data);
                    
                    if (response.data.status === 'completed' || response.data.status === 'failed') {
                        this.stopProgressMonitoring();
                        this.handleSearchComplete(response.data);
                    }
                }
            } catch (error) {
                console.error('Error monitoring progress:', error);
            }
        }, 2000);
    }

    updateProgress(data) {
        const progress = Math.round(data.progress || 0);
        
        $('#searchProgress').css('width', `${progress}%`).text(`${progress}%`);
        $('#totalFound').text(data.totalFound || 0);
        $('#totalProcessed').text(data.resultsProcessed || 0);
        $('#avgQuality').text(data.avgQuality ? `${Math.round(data.avgQuality * 100)}%` : '-');
        $('#progressMessage').text(data.message || 'Procesando...');
    }

    stopProgressMonitoring() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
    }

    async handleSearchComplete(data) {
        this.resetSearchState();
        
        if (data.status === 'completed') {
            this.showNotification('¡Búsqueda completada exitosamente!', 'success');
            this.showResults(data);
        } else {
            this.showNotification('La búsqueda falló. Revisa los logs para más detalles.', 'error');
        }
    }

    showResults(data) {
        const resultsHtml = `
            <div class="results-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Búsqueda Completada
                    </h5>
                    <div>
                        <button class="btn btn-outline-primary btn-sm me-2" onclick="scrapperLeads.viewResults()">
                            <i class="fas fa-eye me-1"></i>
                            Ver Resultados
                        </button>
                        <button class="btn btn-success btn-sm" onclick="scrapperLeads.exportResults()">
                            <i class="fas fa-download me-1"></i>
                            Exportar CSV
                        </button>
                    </div>
                </div>
                
                <div class="alert alert-success">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <strong>${data.totalFound || 0}</strong><br>
                            <small>Leads Encontrados</small>
                        </div>
                        <div class="col-md-3">
                            <strong>${data.resultsProcessed || 0}</strong><br>
                            <small>Procesados</small>
                        </div>
                        <div class="col-md-3">
                            <strong>${data.avgQuality ? Math.round(data.avgQuality * 100) + '%' : 'N/A'}</strong><br>
                            <small>Calidad Promedio</small>
                        </div>
                        <div class="col-md-3">
                            <strong>${this.formatDuration(data.duration)}</strong><br>
                            <small>Duración</small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <button class="btn btn-primary" onclick="scrapperLeads.newSearch()">
                        <i class="fas fa-plus me-2"></i>
                        Nueva Búsqueda
                    </button>
                </div>
            </div>
        `;

        $('#searchResults').html(resultsHtml);
    }

    async stopSearch() {
        if (!this.currentSearchId) return;

        try {
            await this.makeRequest(`/api/scraper.php?action=stop&searchId=${this.currentSearchId}`, {
                method: 'POST'
            });
            
            this.showNotification('Búsqueda detenida', 'warning');
            this.resetSearchState();
            this.stopProgressMonitoring();
            
        } catch (error) {
            console.error('Error stopping search:', error);
        }
    }

    async exportResults() {
        if (!this.currentSearchId) return;

        try {
            this.showNotification('Generando archivo CSV...', 'info');
            
            const response = await this.makeRequest(`/api/export.php?searchId=${this.currentSearchId}&format=csv`);
            
            if (response.success) {
                // Descargar archivo
                const link = document.createElement('a');
                link.href = response.downloadUrl;
                link.download = response.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                this.showNotification('Archivo CSV descargado correctamente', 'success');
            } else {
                throw new Error(response.message);
            }
            
        } catch (error) {
            console.error('Error exporting results:', error);
            this.showNotification(`Error al exportar: ${error.message}`, 'error');
        }
    }

    async viewResults() {
        if (!this.currentSearchId) return;
        
        // Abrir resultados en nueva ventana
        window.open(`/results.php?searchId=${this.currentSearchId}`, '_blank');
    }

    newSearch() {
        this.resetSearchState();
        $('#searchResults').html(`
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">Configura los filtros y inicia la búsqueda</h4>
                <p class="text-muted">Los resultados aparecerán aquí una vez iniciada la captura.</p>
            </div>
        `);
    }

    resetSearchState() {
        this.searchInProgress = false;
        this.currentSearchId = null;
        $('#startSearch').prop('disabled', false).html('<i class="fas fa-search me-2"></i>Iniciar Captura de Leads');
    }

    formatDuration(seconds) {
        if (!seconds) return 'N/A';
        
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        
        return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
    }

    async makeRequest(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }
}

// Inicializar aplicación cuando el DOM esté listo
$(document).ready(() => {
    window.scrapperLeads = new ScrapperLeads();
});

