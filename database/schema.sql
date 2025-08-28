-- =====================================================
-- ESQUEMA DE BASE DE DATOS - SCRAPPERLEADS PRO
-- Sistema profesional de captura de leads empresariales
-- Hosting: Dinahosting MariaDB 11.4
-- =====================================================

-- Configuración de la base de datos
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- TABLA: searches
-- Almacena las búsquedas realizadas por los usuarios
-- =====================================================
CREATE TABLE IF NOT EXISTS `searches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `search_uuid` varchar(36) NOT NULL UNIQUE,
  `keywords` varchar(500) NOT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `employees_range` varchar(50) DEFAULT NULL,
  `revenue_range` varchar(50) DEFAULT NULL,
  `max_results` int(11) DEFAULT 100,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `progress` int(11) DEFAULT 0,
  `total_found` int(11) DEFAULT 0,
  `results_processed` int(11) DEFAULT 0,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_uuid` (`search_uuid`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: leads
-- Almacena los leads empresariales capturados
-- =====================================================
CREATE TABLE IF NOT EXISTS `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `search_id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `employees_count` varchar(50) DEFAULT NULL,
  `revenue` varchar(100) DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `founded_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT 0.00,
  `data_quality` enum('high','medium','low') DEFAULT 'medium',
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_id` (`search_id`),
  KEY `idx_company_name` (`company_name`),
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_sector` (`sector`),
  KEY `idx_province` (`province`),
  KEY `idx_confidence_score` (`confidence_score`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_leads_search` FOREIGN KEY (`search_id`) REFERENCES `searches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: search_logs
-- Registra eventos y logs del proceso de scraping
-- =====================================================
CREATE TABLE IF NOT EXISTS `search_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `search_id` int(11) NOT NULL,
  `level` enum('info','warning','error','debug') DEFAULT 'info',
  `message` text NOT NULL,
  `context` json DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_id` (`search_id`),
  KEY `idx_level` (`level`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_logs_search` FOREIGN KEY (`search_id`) REFERENCES `searches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: system_config
-- Configuración del sistema y parámetros globales
-- =====================================================
CREATE TABLE IF NOT EXISTS `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL UNIQUE,
  `config_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: export_history
-- Historial de exportaciones CSV realizadas
-- =====================================================
CREATE TABLE IF NOT EXISTS `export_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `search_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `total_records` int(11) DEFAULT 0,
  `file_size` int(11) DEFAULT 0,
  `format` enum('csv','xlsx','json') DEFAULT 'csv',
  `download_count` int(11) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_id` (`search_id`),
  KEY `idx_filename` (`filename`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_export_search` FOREIGN KEY (`search_id`) REFERENCES `searches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DATOS INICIALES DEL SISTEMA
-- =====================================================

-- Configuración inicial del sistema
INSERT INTO `system_config` (`config_key`, `config_value`, `description`) VALUES
('app_name', 'ScrapperLeads Pro', 'Nombre de la aplicación'),
('app_version', '1.0.0', 'Versión actual de la aplicación'),
('max_results_per_search', '100', 'Máximo número de resultados por búsqueda'),
('scraping_delay', '2', 'Delay en segundos entre requests de scraping'),
('default_timeout', '30', 'Timeout por defecto para requests HTTP'),
('enable_logging', '1', 'Habilitar sistema de logging'),
('log_level', 'info', 'Nivel de logging (debug, info, warning, error)'),
('data_retention_days', '90', 'Días de retención de datos de búsquedas'),
('export_expiry_hours', '24', 'Horas de expiración de archivos exportados'),
('enable_email_notifications', '0', 'Habilitar notificaciones por email')
ON DUPLICATE KEY UPDATE 
  `config_value` = VALUES(`config_value`),
  `updated_at` = CURRENT_TIMESTAMP;

-- =====================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- =====================================================

-- Índices compuestos para consultas frecuentes
CREATE INDEX `idx_leads_search_sector` ON `leads` (`search_id`, `sector`);
CREATE INDEX `idx_leads_search_province` ON `leads` (`search_id`, `province`);
CREATE INDEX `idx_leads_quality_confidence` ON `leads` (`data_quality`, `confidence_score`);
CREATE INDEX `idx_searches_status_created` ON `searches` (`status`, `created_at`);

-- =====================================================
-- VISTAS PARA REPORTES Y ESTADÍSTICAS
-- =====================================================

-- Vista para estadísticas de búsquedas
CREATE OR REPLACE VIEW `v_search_stats` AS
SELECT 
    s.id,
    s.search_uuid,
    s.keywords,
    s.sector,
    s.province,
    s.region,
    s.status,
    s.total_found,
    s.results_processed,
    COUNT(l.id) as leads_captured,
    AVG(l.confidence_score) as avg_confidence,
    s.created_at,
    s.completed_at,
    TIMESTAMPDIFF(SECOND, s.started_at, s.completed_at) as duration_seconds
FROM `searches` s
LEFT JOIN `leads` l ON s.id = l.search_id
GROUP BY s.id;

-- Vista para leads de alta calidad
CREATE OR REPLACE VIEW `v_quality_leads` AS
SELECT 
    l.*,
    s.keywords,
    s.sector as search_sector,
    s.province as search_province
FROM `leads` l
INNER JOIN `searches` s ON l.search_id = s.id
WHERE l.data_quality = 'high' 
  AND l.confidence_score >= 0.70
  AND (l.email IS NOT NULL OR l.phone IS NOT NULL);

-- =====================================================
-- PROCEDIMIENTOS ALMACENADOS
-- =====================================================

DELIMITER //

-- Procedimiento para limpiar datos antiguos
CREATE PROCEDURE `CleanOldData`(IN retention_days INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Eliminar búsquedas antiguas (cascada eliminará leads y logs relacionados)
    DELETE FROM `searches` 
    WHERE `created_at` < DATE_SUB(NOW(), INTERVAL retention_days DAY)
      AND `status` IN ('completed', 'failed');
    
    -- Eliminar archivos de exportación expirados
    DELETE FROM `export_history` 
    WHERE `expires_at` < NOW();
    
    COMMIT;
    
    SELECT ROW_COUNT() as affected_rows;
END //

-- Procedimiento para obtener estadísticas del sistema
CREATE PROCEDURE `GetSystemStats`()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM `searches`) as total_searches,
        (SELECT COUNT(*) FROM `searches` WHERE `status` = 'completed') as completed_searches,
        (SELECT COUNT(*) FROM `leads`) as total_leads,
        (SELECT COUNT(*) FROM `leads` WHERE `data_quality` = 'high') as high_quality_leads,
        (SELECT AVG(`confidence_score`) FROM `leads`) as avg_confidence_score,
        (SELECT COUNT(*) FROM `export_history`) as total_exports,
        (SELECT MAX(`created_at`) FROM `searches`) as last_search_date;
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS PARA AUDITORÍA Y AUTOMATIZACIÓN
-- =====================================================

DELIMITER //

-- Trigger para actualizar progreso de búsqueda
CREATE TRIGGER `tr_update_search_progress` 
AFTER INSERT ON `leads`
FOR EACH ROW
BEGIN
    UPDATE `searches` 
    SET `results_processed` = (
        SELECT COUNT(*) FROM `leads` WHERE `search_id` = NEW.search_id
    ),
    `updated_at` = CURRENT_TIMESTAMP
    WHERE `id` = NEW.search_id;
END //

-- Trigger para logging automático
CREATE TRIGGER `tr_search_status_log` 
AFTER UPDATE ON `searches`
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO `search_logs` (`search_id`, `level`, `message`, `context`)
        VALUES (
            NEW.id, 
            'info', 
            CONCAT('Estado cambiado de ', OLD.status, ' a ', NEW.status),
            JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status)
        );
    END IF;
END //

DELIMITER ;

-- =====================================================
-- CONFIGURACIÓN FINAL
-- =====================================================

SET FOREIGN_KEY_CHECKS = 1;

-- Mensaje de confirmación
SELECT 'Base de datos ScrapperLeads configurada exitosamente' as status,
       NOW() as created_at,
       'MariaDB 11.4' as database_version;

