-- --------------------------------------------------------
-- Script para crear tablas de Pacientes y Análisis Retinal
-- --------------------------------------------------------

-- Volcando estructura para tabla pacientes
CREATE TABLE IF NOT EXISTS `pacientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dni` varchar(15) NOT NULL,
  `codigo_paciente` varchar(20) NOT NULL,
  `nombres` varchar(100) DEFAULT NULL,
  `apellidos` varchar(100) DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `codigo_paciente` (`codigo_paciente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando estructura para tabla analisis_retinales
CREATE TABLE IF NOT EXISTS `analisis_retinales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_medico` int NOT NULL,
  `id_paciente` int DEFAULT NULL,
  `imagen_path` varchar(255) NOT NULL,
  `resultado_principal` varchar(50) NOT NULL,
  `probabilidad_principal` decimal(5,2) NOT NULL,
  `probabilidad_normal` decimal(5,2) NOT NULL,
  `probabilidad_diabetes` decimal(5,2) NOT NULL,
  `probabilidad_glaucoma` decimal(5,2) NOT NULL,
  `probabilidad_catarata` decimal(5,2) NOT NULL,
  `alerta_anomalia` tinyint(1) NOT NULL DEFAULT '0',
  `es_referencial` tinyint(1) NOT NULL DEFAULT '1',
  `tiempo_analisis` decimal(6,3) DEFAULT NULL,
  `fecha_analisis` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_analisis_medico` (`id_medico`),
  KEY `idx_analisis_paciente` (`id_paciente`),
  CONSTRAINT `fk_analisis_medico` FOREIGN KEY (`id_medico`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_analisis_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
