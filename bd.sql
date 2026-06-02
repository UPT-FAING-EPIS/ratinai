-- --------------------------------------------------------
-- Host:                         kodama.proxy.rlwy.net
-- Versión del servidor:         9.4.0 - MySQL Community Server - GPL
-- SO del servidor:              Linux
-- HeidiSQL Versión:             12.15.0.7171
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para railway
CREATE DATABASE IF NOT EXISTS `railway` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `railway`;

-- Volcando estructura para tabla railway.establecimientos
CREATE TABLE IF NOT EXISTS `establecimientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `tipo` enum('publico','privado') DEFAULT NULL,
  `ruc` varchar(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla railway.establecimientos: ~2 rows (aproximadamente)
INSERT INTO `establecimientos` (`id`, `nombre`, `direccion`, `tipo`, `ruc`) VALUES
	(1, 'Hospital de la Solidaridad', 'Av. Ejercito 123', NULL, NULL),
	(2, 'Clínica CheviVision', 'Avenida Bolognesi 1954, frente a Mercadillo Bolognesi', 'privado', '88888888888');

-- Volcando estructura para tabla railway.maestro
CREATE TABLE IF NOT EXISTS `maestro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `descripcion` varchar(100) NOT NULL,
  `orden` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla railway.maestro: ~11 rows (aproximadamente)
INSERT INTO `maestro` (`id`, `tipo`, `codigo`, `descripcion`, `orden`) VALUES
	(1, 'ROL_SISTEMA', 'SAD', 'Super Administrador', 1),
	(2, 'ROL_SISTEMA', 'ADM', 'Admin Establecimiento', 2),
	(3, 'ROL_SISTEMA', 'MED', 'Médico Oftalmólogo', 3),
	(4, 'TIPO_ESPECIALIDAD', 'OFT', 'Oftalmología', 1),
	(5, 'TIPO_ESPECIALIDAD', 'RET', 'Retinología', 2),
	(6, 'TIPO_ESPECIALIDAD', 'GLA', 'Glaucoma', 3),
	(7, 'TIPO_ESPECIALIDAD', 'COR', 'Córnea', 4),
	(8, 'TIPO_ESPECIALIDAD', 'OOR', 'Órbita y Oculoplástica', 5),
	(9, 'TIPO_ESPECIALIDAD', 'EST', 'Estrabismo', 6),
	(10, 'TIPO_ESPECIALIDAD', 'UVE', 'Uveítis', 7),
	(11, 'TIPO_ESPECIALIDAD', 'OTR', 'Otro', 99);

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
  `evidencia_1` mediumtext,
  `evidencia_1_nombre` varchar(255) DEFAULT NULL,
  `evidencia_2` mediumtext,
  `evidencia_2_nombre` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `fecha_solicitud` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla railway.solicitudes_establecimiento: ~1 rows (aproximadamente)
INSERT INTO `solicitudes_establecimiento` (`id`, `nombre_centro`, `direccion`, `tipo`, `ruc`, `dni_titular`, `nombres_titular`, `apellidos_titular`, `telefono`, `correo_contacto`, `evidencia_1`, `evidencia_1_nombre`, `evidencia_2`, `evidencia_2_nombre`, `estado`, `fecha_solicitud`) VALUES
(1, 'Clínica CheviVision', 'Avenida Bolognesi 1954, frente a Mercadillo Bolognesi', 'privado', '88888888888', '76352379', 'Sebastian', 'Fuentes', '946143071', 'gichevichin2020@gmail.com', 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ.....Y','','','','pendiente','2026-06-02 00:04:50');
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla railway.usuarios: ~14 rows (aproximadamente)
INSERT INTO `usuarios` (`id`, `rol_codigo`, `establecimiento_id`, `nombre`, `correo`, `password`, `cmp`, `especialidad`, `es_password_temporal`, `activo`, `ultimo_acceso`) VALUES
	(1, 'SAD', NULL, 'Super Administrador', 'superadmin@ratinai.com', '$2y$10$Wwk04DqoCefNnKTCcrajjuYktY3q2saxD6.BvH/eB2KhY7RR5vpzK', NULL, NULL, 0, 1, '2026-06-02 00:06:59'),
	(2, 'ADM', 1, 'Admin Hospital', 'admin@hospital.com', '$2y$10$Wwk04DqoCefNnKTCcrajjuYktY3q2saxD6.BvH/eB2KhY7RR5vpzK', NULL, NULL, 0, 1, '2026-06-01 18:13:20'),
	(3, 'MED', 1, 'Dr. Juan Perez', 'medico@hospital.com', '$2y$10$Wwk04DqoCefNnKTCcrajjuYktY3q2saxD6.BvH/eB2KhY7RR5vpzK', '123456', 'Retina', 1, 1, NULL),
	(4, 'MED', 1, 'Dr. Editado 1779761091098', 'cypress_doc_1779490411501@hospital.com', '$2y$10$Kr7TWLfv0FNd3VutUXcqAeEHgNDifZakdqoqnM5R4R0fS5rHvOEMa', 'CMP4931', 'Oftalmología', 1, 0, NULL),
	(5, 'MED', 1, 'Dr. Kalid Practicante', 'gg2022074263@virtual.upt.pe', '$2y$10$TYV9JJeR4BIzKOiFkUTa2.EPbwYO9m//E9cXqyHMfWHHiFmagi8Hm', '123457', 'Oftalmología', 1, 1, '2026-05-26 00:26:55'),
	(6, 'MED', 1, 'Dr. Cypress Test', 'cypress_doc_1779754955361@hospital.com', '$2y$10$pZ8s5mC.lK0WUrw7QhOumu4WVng2DCIp5pnDHOnsUEXVGmrjMxiNS', 'CMP4557', 'Oftalmología', 1, 0, NULL),
	(7, 'MED', 1, 'Dr. Editado 1779762112378', 'cypress_doc_1779757118078@hospital.com', '$2y$10$/jH.91UBg0E8gcVfBx1x5eS9uqvUVUzybz0iA3TmKX6lNcZEoCAji', 'CMP6231', 'Oftalmología', 1, 0, NULL),
	(8, 'MED', 1, 'Dr. Cypress Test', 'cypress_doc_1779759594621@hospital.com', '$2y$10$osjwHMA8TZ.q0wrJfAe7Te0bAIWyiHO17K9DFEthTGJjNtDiur9S.', 'CMP2859', 'Oftalmología', 1, 0, NULL),
	(9, 'MED', 1, 'Dr. Cypress Test', 'cypress_doc_1779760179946@hospital.com', '$2y$10$uTOnANaOwdPsmmI8scGu6.p/JeWf/qXME7AXG6SAHcloiCWJ.4Mim', 'CMP1625', 'Oftalmología', 1, 0, NULL),
	(10, 'MED', 1, 'Dr. Cypress Test', 'cypress_doc_1779760401847@hospital.com', '$2y$10$0gBWx672UHy06FKArKrtXuQ/bZRpL73pZzSFupKK/DEqTYJFh9Uhu', 'CMP7439', 'Oftalmología', 1, 0, NULL),
	(11, 'MED', 1, 'Dr. Editado 1779761653438', 'cypress_doc_1779761623147@hospital.com', '$2y$10$1klTVknvVD54pbx./TQhwerBHTuN8tIUyWSDXO2XOY2r6q1DSdXm.', 'CMP4664', 'Oftalmología', 1, 0, NULL),
	(12, 'MED', 1, 'Dr. Editado 1779763128083', 'cypress_doc_1779763095773@hospital.com', '$2y$10$KTD3xfTRVA69s.qKBvJwtOurrAjVvqbfyAYJzPkEcX3hOWNtBwEb6', 'CMP2172', 'Oftalmología', 1, 0, NULL),
	(13, 'ADM', 2, 'Sebastian Fuentes', 'gichevichin2020@gmail.com', '$2y$10$7nnV.sbbSBum796CP.a7y.G04wj4VxvOODk41FAdZ6BKwQcxBsV/G', NULL, NULL, 0, 1, '2026-06-02 00:10:16'),
	(14, 'MED', 1, 'Dr. Editado 1780337591127', 'cypress_doc_1780337559241@hospital.com', '$2y$10$ozpkxPVpeCZkQ03nKyn9h.qBao94Ggpxrv3Mm6EzeiOKqRaPVCuiq', 'CMP7934', 'Oftalmología', 1, 1, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
