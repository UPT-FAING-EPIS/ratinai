-- --------------------------------------------------------
-- Host:                         kodama.proxy.rlwy.net
-- Versión del servidor:         9.4.0 - MySQL Community Server - GPL
-- SO del servidor:              Linux
-- HeidiSQL Versión:             12.17.0.7270
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Volcando estructura para tabla railway.establecimientos
CREATE TABLE IF NOT EXISTS `establecimientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `tipo` enum('publico','privado') DEFAULT NULL,
  `ruc` varchar(11) DEFAULT NULL,
  `id_usuario` int DEFAULT NULL COMMENT 'ID del usuario ADM propietario del establecimiento',
  PRIMARY KEY (`id`),
  KEY `idx_establecimientos_id_usuario` (`id_usuario`),
  CONSTRAINT `establecimientos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla railway.maestro
CREATE TABLE IF NOT EXISTS `maestro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `descripcion` varchar(100) NOT NULL,
  `orden` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla railway.solicitudes_establecimiento
CREATE TABLE IF NOT EXISTS `solicitudes_establecimiento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_centro` varchar(100) NOT NULL,
  `direccion` varchar(200) NOT NULL,
  `tipo` enum('publico','privado') NOT NULL,
  `ruc` varchar(11) NOT NULL,
  `dni_titular` varchar(8) NOT NULL,
  `nombres_titular` varchar(100) NOT NULL,
  `apellidos_titular` varchar(100) NOT NULL,
  `telefono` varchar(15) NOT NULL,
  `correo_contacto` varchar(100) NOT NULL,
  `id_usuario_solicitante` int DEFAULT NULL COMMENT 'ID del usuario ADM que generó esta solicitud (NULL si fue solicitud pública)',
  `evidencia_1` mediumtext,
  `evidencia_1_nombre` varchar(255) DEFAULT NULL,
  `evidencia_2` mediumtext,
  `evidencia_2_nombre` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `fecha_solicitud` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `solicitudes_ibfk_1` (`id_usuario_solicitante`),
  CONSTRAINT `solicitudes_ibfk_1` FOREIGN KEY (`id_usuario_solicitante`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla railway.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rol_codigo` varchar(20) NOT NULL,
  `establecimiento_id` int DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `cmp` varchar(20) DEFAULT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `es_password_temporal` tinyint(1) DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `ultimo_acceso` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`),
  KEY `establecimiento_id` (`establecimiento_id`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`establecimiento_id`) REFERENCES `establecimientos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
