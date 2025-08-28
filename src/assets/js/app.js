/**
 * ScrapperLeads Pro - JavaScript Principal
 * Maneja la interfaz dinámica y comunicación con la API
 */

class ScrapperLeadsApp {
    constructor() {
        this.currentSessionId = null;
        this.progressInterval = null;
        this.results = [];
        
        this.initializeEventListeners();
        this.initializeTooltips();
    }
    
    /**
     * Inicializa los event listeners
     */
    initializeEventListeners() {
        // Formulario de búsqueda
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => this.handleSearchSubmit(e));
        }
        
        // Botón de descarga CSV
        const downloadBtn = document.getElementById('downloadCSV');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => this.downloadCSV());
        }
        
        // Validación en tiempo real
        const keywordsInput = document.getElementById('keywords');
        if (keywordsInput) {
            keywordsInput.addEventListener('input', () => this.validateForm());
        }
        
        // Auto-guardar configuración
        this.setupAutoSave();
    }
    
    /**
     * Maneja el envío del formulario de búsqueda
     */
    async handleSearchSubmit(event) {
        event.preventDefault();
        
        if (!this.validateForm()) {
            this.showAlert('Por favor, completa todos los campos requeridos.', 'warning');
            return;
        }
        
        const formData = this.getFormData();
        
        try {
            this.showLoadingState();
            await this.startScraping(formData);
        } catch (error) {
            this.hideLoadingState();
            this.showAlert('Error al iniciar la búsqueda: ' + error.message, 'danger');
            console.error('Error:', error);
        }
    }
    
    /**
     * Obtiene los datos del formulario
     */
    getFormData() {
        return {
            keywords: document.getElementById('keywords').value.trim(),
            sector: document.getElementById('sector').value,
            provincia: document.getElementById('provincia').value,
            region: document.getElementById('region').value,
            empleados: document.getElementById('empleados').value,
            facturacion: document.getElementById('facturacion').value,
            numResults: parseInt(document.getElementById('numResults').value) || 20
        };
    }
    
    /**
     * Valida el formulario
     */
    validateForm() {
        const keywords = document.getElementById('keywords').value.trim();
        const numResults = parseInt(document.getElementById('numResults').value);
        
        // Validar palabras clave
        if (!keywords || keywords.length < 3) {
            this.highlightField('keywords', false);
            return false;
        }
        this.highlightField('keywords', true);
        
        // Validar número de resultados
        if (!numResults || numResults < 1 || numResults > 100) {
            this.highlightField('numResults', false);
            return false;
        }
        this.highlightField('numResults', true);
        
        return true;
    }
    
    /**
     * Resalta campos válidos/inválidos
     */
    highlightField(fieldId, isValid) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.classList.remove('is-valid', 'is-invalid');
            field.classList.add(isValid ? 'is-valid' : 'is-invalid');
        }
    }
    
    /**
     * Inicia el proceso de scraping
     */
    async startScraping(formData) {
        try {
            const response = await fetch('/api/scraper.php/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Error desconocido');
            }
            
            this.currentSessionId = data.sessionId;
            this.results = data.results || [];
            
            // Mostrar resultados inmediatos si los hay
            if (this.results.length > 0) {
                this.displayResults();
                this.hideLoadingState();
                this.showAlert(`¡Búsqueda completada! Se encontraron ${this.results.length} leads.`, 'success');
            } else {
                // Iniciar monitoreo de progreso
                this.startProgressMonitoring();
            }
            
        } catch (error) {
            console.error('Error en startScraping:', error);
            throw error;
        }
    }
    
    /**
     * Inicia el monitoreo de progreso
     */
    startProgressMonitoring() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
        }
        
        this.progressInterval = setInterval(async () => {
            try {
                await this.checkProgress();
            } catch (error) {
                console.error('Error checking progress:', error);
                this.stopProgressMonitoring();
                this.hideLoadingState();
                this.showAlert('Error al monitorear el progreso.', 'warning');
            }
        }, 2000); // Verificar cada 2 segundos
    }
    
    /**
     * Verifica el progreso del scraping
     */
    async checkProgress() {
        if (!this.currentSessionId) return;
        
        const response = await fetch(`/api/scraper.php/progress?sessionId=${this.currentSessionId}`);
        const data = await response.json();
        
        if (data.success && data.progress) {
            this.updateProgressBar(data.progress.percentage, data.progress.message);
            
            // Si está completado, obtener resultados finales
            if (data.progress.percentage >= 100) {
                this.stopProgressMonitoring();
                await this.getResults();
            }
        }
    }
    
    /**
     * Obtiene los resultados finales
     */
    async getResults() {
        // En este caso, los resultados ya se obtuvieron en startScraping
        // Pero aquí se podría implementar una llamada adicional si fuera necesario
        this.hideLoadingState();
        this.displayResults();
        this.showAlert(`¡Búsqueda completada! Se encontraron ${this.results.length} leads.`, 'success');
    }
    
    /**
     * Detiene el monitoreo de progreso
     */
    stopProgressMonitoring() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
    }
    
    /**
     * Muestra el estado de carga
     */
    showLoadingState() {
        document.getElementById('initialState').style.display = 'none';
        document.getElementById('loadingIndicator').style.display = 'block';
        document.getElementById('progressContainer').style.display = 'block';
        document.getElementById('resultsTable').style.display = 'none';
        document.getElementById('resultSummary').style.display = 'none';
        
        // Deshabilitar formulario
        const form = document.getElementById('searchForm');
        const inputs = form.querySelectorAll('input, select, button');
        inputs.forEach(input => input.disabled = true);
    }
    
    /**
     * Oculta el estado de carga
     */
    hideLoadingState() {
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('progressContainer').style.display = 'none';
        
        // Rehabilitar formulario
        const form = document.getElementById('searchForm');
        const inputs = form.querySelectorAll('input, select, button');
        inputs.forEach(input => input.disabled = false);
    }
    
    /**
     * Actualiza la barra de progreso
     */
    updateProgressBar(percentage, message) {
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.style.width = percentage + '%';
            progressBar.textContent = Math.round(percentage) + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
        }
        
        // Actualizar mensaje si existe un elemento para ello
        const messageElement = document.querySelector('#loadingIndicator p');
        if (messageElement && message) {
            messageElement.textContent = message;
        }
    }
    
    /**
     * Muestra los resultados en la tabla
     */
    displayResults() {
        if (!this.results || this.results.length === 0) {
            this.showAlert('No se encontraron resultados para los criterios especificados.', 'info');
            return;
        }
        
        // Mostrar resumen
        document.getElementById('totalLeads').textContent = this.results.length;
        document.getElementById('resultSummary').style.display = 'block';
        
        // Llenar tabla
        const tbody = document.getElementById('resultsBody');
        tbody.innerHTML = '';
        
        this.results.forEach((lead, index) => {
            const row = this.createResultRow(lead, index);
            tbody.appendChild(row);
        });
        
        document.getElementById('resultsTable').style.display = 'block';
        document.getElementById('initialState').style.display = 'none';
        
        // Animar entrada de resultados
        this.animateResults();
    }
    
    /**
     * Crea una fila de resultado
     */
    createResultRow(lead, index) {
        const row = document.createElement('tr');
        row.className = 'fade-in';
        row.style.animationDelay = (index * 0.1) + 's';
        
        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    <div>
                        <strong>${this.escapeHtml(lead.empresa)}</strong>
                        ${lead.url ? `<br><small class="text-muted"><a href="${this.escapeHtml(lead.url)}" target="_blank" class="text-decoration-none">${this.truncateUrl(lead.url)}</a></small>` : ''}
                    </div>
                </div>
            </td>
            <td>
                ${lead.direccion ? `<i class="fas fa-map-marker-alt text-primary me-1"></i>${this.escapeHtml(lead.direccion)}` : '<span class="text-muted">No disponible</span>'}
            </td>
            <td>
                <div>
                    ${lead.telefono ? `<div><i class="fas fa-phone text-success me-1"></i>${this.escapeHtml(lead.telefono)}</div>` : ''}
                    ${lead.email ? `<div><i class="fas fa-envelope text-info me-1"></i><small>${this.escapeHtml(lead.email)}</small></div>` : ''}
                    ${!lead.telefono && !lead.email ? '<span class="text-muted">No disponible</span>' : ''}
                </div>
            </td>
            <td>
                <span class="badge bg-secondary">${this.escapeHtml(lead.empleados)}</span>
            </td>
            <td>
                <span class="badge bg-success">${this.escapeHtml(lead.facturacion)}</span>
            </td>
        `;
        
        return row;
    }
    
    /**
     * Anima la entrada de resultados
     */
    animateResults() {
        const rows = document.querySelectorAll('#resultsTable tbody tr');
        rows.forEach((row, index) => {
            setTimeout(() => {
                row.classList.add('show');
            }, index * 100);
        });
    }
    
    /**
     * Descarga los resultados en formato CSV
     */
    downloadCSV() {
        if (!this.results || this.results.length === 0) {
            this.showAlert('No hay resultados para descargar.', 'warning');
            return;
        }
        
        const csv = this.generateCSV();
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `leads_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        this.showAlert('Archivo CSV descargado correctamente.', 'success');
    }
    
    /**
     * Genera el contenido CSV
     */
    generateCSV() {
        const headers = [
            'Empresa',
            'URL',
            'Dirección',
            'Teléfono',
            'Email',
            'Empleados',
            'Facturación',
            'Sector',
            'Fecha Captura',
            'Fuente'
        ];
        
        let csv = headers.join(',') + '\n';
        
        this.results.forEach(lead => {
            const row = [
                this.csvEscape(lead.empresa),
                this.csvEscape(lead.url),
                this.csvEscape(lead.direccion),
                this.csvEscape(lead.telefono),
                this.csvEscape(lead.email),
                this.csvEscape(lead.empleados),
                this.csvEscape(lead.facturacion),
                this.csvEscape(lead.sector),
                this.csvEscape(lead.fecha_captura),
                this.csvEscape(lead.fuente)
            ];
            csv += row.join(',') + '\n';
        });
        
        return csv;
    }
    
    /**
     * Escapa valores para CSV
     */
    csvEscape(value) {
        if (!value) return '""';
        const stringValue = String(value);
        if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
            return '"' + stringValue.replace(/"/g, '""') + '"';
        }
        return '"' + stringValue + '"';
    }
    
    /**
     * Muestra alertas al usuario
     */
    showAlert(message, type = 'info') {
        // Crear elemento de alerta
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    /**
     * Configura auto-guardado de configuración
     */
    setupAutoSave() {
        const formElements = document.querySelectorAll('#searchForm input, #searchForm select');
        formElements.forEach(element => {
            element.addEventListener('change', () => {
                this.saveFormState();
            });
        });
        
        // Cargar estado guardado
        this.loadFormState();
    }
    
    /**
     * Guarda el estado del formulario
     */
    saveFormState() {
        const formData = this.getFormData();
        localStorage.setItem('scrapperLeadsFormState', JSON.stringify(formData));
    }
    
    /**
     * Carga el estado del formulario
     */
    loadFormState() {
        const savedState = localStorage.getItem('scrapperLeadsFormState');
        if (savedState) {
            try {
                const formData = JSON.parse(savedState);
                Object.keys(formData).forEach(key => {
                    const element = document.getElementById(key);
                    if (element && formData[key]) {
                        element.value = formData[key];
                    }
                });
            } catch (error) {
                console.error('Error loading form state:', error);
            }
        }
    }
    
    /**
     * Inicializa tooltips de Bootstrap
     */
    initializeTooltips() {
        // Inicializar tooltips si Bootstrap está disponible
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }
    
    /**
     * Utilidades de escape HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    /**
     * Trunca URLs largas
     */
    truncateUrl(url, maxLength = 50) {
        if (!url || url.length <= maxLength) return url;
        return url.substring(0, maxLength) + '...';
    }
}

// Inicializar la aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    window.scrapperApp = new ScrapperLeadsApp();
    
    // Verificar estado del sistema
    fetch('/api/scraper.php/health')
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'ok') {
                console.warn('System health check warning:', data);
            }
        })
        .catch(error => {
            console.error('Health check failed:', error);
        });
});

