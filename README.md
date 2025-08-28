# ScrapperLeads PHP - Sistema de Captura de Leads

## Descripción

Sistema profesional de captura de leads empresariales desde Google con filtros avanzados, desarrollado en PHP para máxima compatibilidad con hostings estándar.

## Características

- **Filtros Avanzados**: Segmentación por palabras clave, sector, ubicación, empleados y facturación
- **Interfaz Dinámica**: Visualización en tiempo real del progreso
- **Exportación CSV**: Descarga de resultados estructurados
- **Arquitectura Escalable**: Separación de entornos y automatización
- **Despliegue Automático**: CI/CD integrado con GitHub Actions

## Arquitectura del Proyecto

```
ScrapperLeads-PHP/
├── src/                          # Código fuente principal
│   ├── api/                      # Endpoints de la API
│   ├── assets/                   # Recursos estáticos
│   │   ├── css/                  # Hojas de estilo
│   │   ├── js/                   # Scripts JavaScript
│   │   └── images/               # Imágenes
│   ├── config/                   # Configuraciones
│   ├── database/                 # Scripts de base de datos
│   └── utils/                    # Utilidades y helpers
├── tests/                        # Pruebas automatizadas
│   ├── unit/                     # Pruebas unitarias
│   └── integration/              # Pruebas de integración
├── environments/                 # Configuraciones por entorno
│   ├── development/              # Desarrollo local
│   ├── staging/                  # Entorno de pruebas
│   └── production/               # Producción
├── scripts/                      # Scripts de automatización
│   ├── deploy/                   # Scripts de despliegue
│   └── backup/                   # Scripts de respaldo
├── docs/                         # Documentación
└── .github/workflows/            # GitHub Actions (CI/CD)
```

## Entornos

### Desarrollo (Local)
- Base de datos SQLite para desarrollo rápido
- Configuración de debug activada
- Datos de prueba incluidos

### Staging (Pruebas)
- Réplica del entorno de producción
- Pruebas automatizadas
- Validación antes del despliegue

### Producción (eduaify.es)
- Hosting: Dinahosting Profesional Plus Linux
- PHP 7.4 + MariaDB 11.4
- Configuración optimizada para rendimiento

## Tecnologías

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **Base de Datos**: MariaDB/MySQL
- **Estilos**: Bootstrap 5 + CSS personalizado
- **Testing**: PHPUnit
- **CI/CD**: GitHub Actions
- **Despliegue**: FTP automatizado

## Instalación

### Requisitos
- PHP 7.4+
- MySQL/MariaDB
- Composer (para dependencias)
- Git

### Configuración Local
```bash
# Clonar repositorio
git clone https://github.com/aureainnovacion-hub/ScrapperLeads-PHP.git
cd ScrapperLeads-PHP

# Instalar dependencias
composer install

# Configurar base de datos
cp environments/development/.env.example .env
# Editar .env con tus credenciales

# Ejecutar migraciones
php scripts/database/migrate.php

# Iniciar servidor de desarrollo
php -S localhost:8000 -t src/
```

## Uso

1. **Acceder a la aplicación**: `http://localhost:8000`
2. **Configurar filtros** de búsqueda
3. **Iniciar captura** de leads
4. **Monitorear progreso** en tiempo real
5. **Descargar resultados** en CSV

## Despliegue

### Automático (Recomendado)
```bash
# Push a rama main activa el despliegue automático
git push origin main
```

### Manual
```bash
# Ejecutar script de despliegue
./scripts/deploy/deploy.sh production
```

## Testing

```bash
# Ejecutar todas las pruebas
composer test

# Pruebas unitarias
composer test:unit

# Pruebas de integración
composer test:integration
```

## Configuración de Entornos

### Variables de Entorno
Cada entorno tiene su archivo `.env`:
- `environments/development/.env`
- `environments/staging/.env`
- `environments/production/.env`

### Configuración de Base de Datos
```env
DB_HOST=localhost
DB_NAME=scrapper_leads
DB_USER=usuario
DB_PASS=contraseña
DB_PREFIX=sl_
```

## API Endpoints

- `POST /api/scraper/start` - Iniciar scraping
- `GET /api/scraper/progress/{id}` - Obtener progreso
- `POST /api/export/csv` - Exportar a CSV
- `GET /api/health` - Estado del sistema

## Contribución

1. Fork del repositorio
2. Crear rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Añadir nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

## Licencia

© 2025 AUREA INNOVACIÓN. Todos los derechos reservados.

## Soporte

- **Email**: aurea@aureainnovacion.com
- **Documentación**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/aureainnovacion-hub/ScrapperLeads-PHP/issues)

