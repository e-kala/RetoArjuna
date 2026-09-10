-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: retoarju_platform
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `retoarju_platform`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `retoarju_platform` /*!40100 DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci */;

USE `retoarju_platform`;

--
-- Table structure for table `actividades`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `actividades` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(10) NOT NULL DEFAULT '?',
  `enlace_url` varchar(500) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_actividades_titulo` (`titulo`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `actividades`
--

LOCK TABLES `actividades` WRITE;
/*!40000 ALTER TABLE `actividades` DISABLE KEYS */;
INSERT  IGNORE INTO `actividades` VALUES (1,'App de sánscrito (en desarrollo)','Nuevo desarrollo: una app para aprender sánscrito.','📱',NULL,5,1,'2026-08-02 15:05:40'),(2,'Árbol genealógico de los Vedas (en desarrollo)','Nuevo desarrollo: un árbol genealógico interactivo de los Vedas.','🌳',NULL,6,1,'2026-08-02 15:05:40'),(3,'Clases de Bhagavatam en línea','Lectura y comentario del Śrīmad-Bhāgavatam.','📚',NULL,2,1,'2026-08-02 15:05:40'),(4,'Clases de Gita en línea','Estudio semanal del Bhagavad-gītā, abierto a todos los niveles.','📖',NULL,1,1,'2026-08-02 15:05:40'),(5,'Clases de sánscrito','Introducción y práctica del idioma sánscrito.','🕉️',NULL,4,1,'2026-08-02 15:05:40'),(6,'Lectura matutina de domingo','Lectura matutina los domingos del libro de Krishna.','🌅',NULL,3,1,'2026-08-02 15:05:40');
/*!40000 ALTER TABLE `actividades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificados`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `certificados` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `curso_id` int(10) unsigned DEFAULT NULL,
  `evento_id` int(10) unsigned DEFAULT NULL,
  `tipo` enum('curso','evento') NOT NULL DEFAULT 'curso',
  `codigo` varchar(40) NOT NULL,
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certificado_codigo` (`codigo`),
  UNIQUE KEY `uq_certificado_usuario_curso` (`usuario_id`,`curso_id`),
  UNIQUE KEY `uq_certificado_usuario_evento` (`usuario_id`,`evento_id`),
  KEY `fk_certificados_curso` (`curso_id`),
  KEY `fk_certificados_evento` (`evento_id`),
  CONSTRAINT `fk_certificados_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certificados_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certificados_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificados`
--

LOCK TABLES `certificados` WRITE;
/*!40000 ALTER TABLE `certificados` DISABLE KEYS */;
/*!40000 ALTER TABLE `certificados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curso_inscripciones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `curso_inscripciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `curso_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_curso_inscripcion` (`usuario_id`,`curso_id`),
  KEY `fk_curso_inscripciones_curso` (`curso_id`),
  CONSTRAINT `fk_curso_inscripciones_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_curso_inscripciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--

ALTER TABLE curso_inscripciones
  ADD COLUMN IF NOT EXISTS metodo ENUM('propio','manual') NOT NULL DEFAULT 'propio' AFTER curso_id,
  ADD COLUMN IF NOT EXISTS activada_por INT UNSIGNED NULL AFTER metodo;

ALTER TABLE curso_inscripciones DROP FOREIGN KEY IF EXISTS fk_curso_inscripciones_activada_por;
ALTER TABLE curso_inscripciones ADD CONSTRAINT fk_curso_inscripciones_activada_por FOREIGN KEY (activada_por) REFERENCES usuarios_perfil (id) ON DELETE SET NULL;

ALTER TABLE curso_inscripciones ADD KEY IF NOT EXISTS idx_curso_inscripciones_activada_por (activada_por);

-- Ver "Prestaciones y regalos" más abajo — la FK a regalos se agrega
-- después en este archivo, tras crear esa tabla.
ALTER TABLE curso_inscripciones ADD COLUMN IF NOT EXISTS regalo_id INT UNSIGNED NULL AFTER curso_id;
ALTER TABLE curso_inscripciones ADD KEY IF NOT EXISTS idx_curso_inscripciones_regalo (regalo_id);

-- Dumping data for table `curso_inscripciones`
--

LOCK TABLES `curso_inscripciones` WRITE;
/*!40000 ALTER TABLE `curso_inscripciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `curso_inscripciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cursos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `cursos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `nivel` enum('principiante','intermedio','avanzado') NOT NULL DEFAULT 'principiante',
  `duracion_horas` decimal(5,1) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `imagen_portada` varchar(255) DEFAULT NULL,
  `video_intro` varchar(255) DEFAULT NULL,
  `foro_url` varchar(500) DEFAULT NULL COMMENT 'Enlace al hilo/tag del Flarum real para discutir el curso',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_codigo_promocion` tinyint(1) NOT NULL DEFAULT 0,
  `gratuito` tinyint(1) NOT NULL DEFAULT 0,
  `incluido_membresia` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cursos_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--

ALTER TABLE cursos
  ADD COLUMN IF NOT EXISTS mostrar_codigo_promocion TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;

-- "Precio especial de miembro" (OF10) — no existía, solo había
-- incluido_membresia (todo o nada). NULL = sin descuento configurado.
ALTER TABLE cursos
  ADD COLUMN IF NOT EXISTS descuento_miembro_pct DECIMAL(5,2) NULL AFTER incluido_membresia;

-- landing_page_id (flujo de venta curso→landing) — la FK a landing_pages se
-- agrega más abajo en este archivo, tras crear esa tabla.
ALTER TABLE cursos ADD COLUMN IF NOT EXISTS landing_page_id INT UNSIGNED NULL AFTER slug;

-- Dumping data for table `cursos`
--

LOCK TABLES `cursos` WRITE;
/*!40000 ALTER TABLE `cursos` DISABLE KEYS */;
INSERT  IGNORE INTO `cursos` VALUES (1,'Introducción al Reto Arjuna','introduccion-reto-arjuna',NULL,'Curso introductorio gratuito para conocer la filosofía y estructura del Reto Arjuna.','principiante',2.5,0.00,NULL,NULL,'plataforma/foro/index.php',1,0,1,0,NULL,'2026-07-30 00:12:40','2026-08-18 09:52:42');
/*!40000 ALTER TABLE `cursos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evento_inscripciones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `evento_inscripciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `evento_id` int(10) unsigned NOT NULL,
  `estado` enum('inscrito','asistio','cancelado') NOT NULL DEFAULT 'inscrito',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evento_inscripcion` (`usuario_id`,`evento_id`),
  KEY `fk_evento_inscripciones_evento` (`evento_id`),
  CONSTRAINT `fk_evento_inscripciones_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evento_inscripciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--

ALTER TABLE evento_inscripciones
  ADD COLUMN IF NOT EXISTS metodo ENUM('propio','manual') NOT NULL DEFAULT 'propio' AFTER estado,
  ADD COLUMN IF NOT EXISTS activada_por INT UNSIGNED NULL AFTER metodo;

ALTER TABLE evento_inscripciones DROP FOREIGN KEY IF EXISTS fk_evento_inscripciones_activada_por;
ALTER TABLE evento_inscripciones ADD CONSTRAINT fk_evento_inscripciones_activada_por FOREIGN KEY (activada_por) REFERENCES usuarios_perfil (id) ON DELETE SET NULL;

ALTER TABLE evento_inscripciones ADD KEY IF NOT EXISTS idx_evento_inscripciones_activada_por (activada_por);

-- Ver "Prestaciones y regalos" más abajo — la FK a regalos se agrega
-- después en este archivo, tras crear esa tabla.
ALTER TABLE evento_inscripciones ADD COLUMN IF NOT EXISTS regalo_id INT UNSIGNED NULL AFTER evento_id;
ALTER TABLE evento_inscripciones ADD KEY IF NOT EXISTS idx_evento_inscripciones_regalo (regalo_id);

-- Dumping data for table `evento_inscripciones`
--

LOCK TABLES `evento_inscripciones` WRITE;
/*!40000 ALTER TABLE `evento_inscripciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `evento_inscripciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eventos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `eventos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('online','presencial') NOT NULL DEFAULT 'online',
  `ubicacion` varchar(255) DEFAULT NULL COMMENT 'Dirección física, o "Zoom"/liga si es online',
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `cupo_maximo` int(10) unsigned DEFAULT NULL,
  `imagen_portada` varchar(255) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gratuito` tinyint(1) NOT NULL DEFAULT 0,
  `solo_miembros` tinyint(1) NOT NULL DEFAULT 0,
  `incluido_membresia` tinyint(1) NOT NULL DEFAULT 0,
  `foro_url` varchar(500) DEFAULT NULL,
  `video_grabado_url` varchar(500) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_codigo_promocion` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eventos_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--

ALTER TABLE eventos
  ADD COLUMN IF NOT EXISTS mostrar_codigo_promocion TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;

-- "Precio especial de miembro" (OF10) — mismo criterio que cursos arriba.
ALTER TABLE eventos
  ADD COLUMN IF NOT EXISTS descuento_miembro_pct DECIMAL(5,2) NULL AFTER incluido_membresia;

-- landing_page_id (flujo de venta evento→landing) — la FK a landing_pages se
-- agrega más abajo en este archivo, tras crear esa tabla.
ALTER TABLE eventos ADD COLUMN IF NOT EXISTS landing_page_id INT UNSIGNED NULL AFTER slug;

-- Dumping data for table `eventos`
--

LOCK TABLES `eventos` WRITE;
/*!40000 ALTER TABLE `eventos` DISABLE KEYS */;
INSERT  IGNORE INTO `eventos` VALUES (9,'Reto Arjuna Noviembre 2025','reto-arjuna-noviembre-2025',NULL,'Vive el reto en 9 días','online','','2026-08-02 07:30:00',NULL,NULL,'uploads/cursos/407a736bb1c8c813451227cb8a03add5.webp',500.00,0,0,0,NULL,'','',1,0,'2026-08-14 15:15:02','2026-08-14 15:19:12'),(15,'prueba solo miembros','prueba1',NULL,'','online','','2026-08-21 01:33:00',NULL,NULL,'',0.00,0,1,1,NULL,'','',1,0,'2026-08-16 06:32:59','2026-08-24 06:27:34');
/*!40000 ALTER TABLE `eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `foro_categorias`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `foro_categorias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `aprobada` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` int(10) unsigned DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_foro_categorias_slug` (`slug`),
  KEY `idx_foro_categorias_parent` (`parent_id`),
  KEY `fk_foro_categorias_creado_por` (`creado_por`),
  CONSTRAINT `fk_foro_categorias_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foro_categorias_parent` FOREIGN KEY (`parent_id`) REFERENCES `foro_categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--

ALTER TABLE foro_categorias
  ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL AFTER id;

ALTER TABLE foro_categorias DROP FOREIGN KEY IF EXISTS fk_foro_categorias_parent;
ALTER TABLE foro_categorias ADD CONSTRAINT fk_foro_categorias_parent FOREIGN KEY (parent_id) REFERENCES foro_categorias (id) ON DELETE CASCADE;

ALTER TABLE foro_categorias ADD KEY IF NOT EXISTS idx_foro_categorias_parent (parent_id);

-- "Categorías" pasaron a ser el vocabulario de ETIQUETAS de un tema (relación
-- muchos-a-muchos vía foro_tema_etiquetas, ver más abajo) — aprobada=1 por
-- defecto para no afectar las ya existentes (creadas por admin); una nueva
-- propuesta por un usuario normal al crear un tema queda en aprobada=0 hasta
-- que un admin la aprueba (panel/admin/foro.php (pestaña "Etiquetas")).
ALTER TABLE foro_categorias
  ADD COLUMN IF NOT EXISTS aprobada TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion,
  ADD COLUMN IF NOT EXISTS creado_por INT UNSIGNED NULL AFTER aprobada;

ALTER TABLE foro_categorias DROP FOREIGN KEY IF EXISTS fk_foro_categorias_creado_por;
ALTER TABLE foro_categorias ADD CONSTRAINT fk_foro_categorias_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios_perfil (id) ON DELETE SET NULL;

-- Dumping data for table `foro_categorias`
--

LOCK TABLES `foro_categorias` WRITE;
/*!40000 ALTER TABLE `foro_categorias` DISABLE KEYS */;
INSERT  IGNORE INTO `foro_categorias` VALUES (3,NULL,'Guías y Actualizaciones','gu-as-y-actualizaciones','',1,NULL,0,'2026-08-02 10:57:03'),(4,NULL,'Preguntas','preguntas','Pregunta y responde a preguntas sobre la vida.',1,NULL,1,'2026-08-02 10:57:03'),(5,NULL,'Experiencias','experiencias','Compartir vivencias personales aceptando el Reto Arjuna',1,NULL,4,'2026-08-02 10:57:03'),(6,NULL,'Testimonio','testimonio','',1,NULL,0,'2026-08-02 10:57:03'),(7,NULL,'Recursos','recursos','Materiales PDF, Libros, etc.',1,NULL,3,'2026-08-02 10:57:03'),(8,NULL,'Curiosidadades','curiosidadades','Recreación, memes, videos',1,NULL,2,'2026-08-02 10:57:03'),(9,NULL,'Preguntas Frecuentes','preguntas-frecuentes','',1,NULL,0,'2026-08-02 10:57:03'),(10,NULL,'La Identidad ','la-identidad','Dudas, experiencias, sobre la identidad.',1,NULL,0,'2026-08-02 10:57:03'),(11,NULL,'Artículos','art-culos','Reflexiones y ensayos aplicados del Gītā y la práctica del Reto Arjuna.',1,NULL,5,'2026-08-02 10:57:03'),(12,NULL,'La mente','la-mente','',1,NULL,0,'2026-08-02 10:57:03'),(13,NULL,'Emociones y desapego','emociones-y-desapego','',1,NULL,0,'2026-08-02 10:57:03'),(14,NULL,'Amor y gratitud (Bhakti)','amor-y-gratitud-bhakti','',1,NULL,0,'2026-08-02 10:57:03'),(15,NULL,'Decisiones con coherencia','decisiones-con-coherencia','',1,NULL,0,'2026-08-02 10:57:03'),(16,NULL,'Ejercicio','ejercicio','',1,NULL,6,'2026-08-02 10:57:03');
/*!40000 ALTER TABLE `foro_categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `foro_likes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `foro_likes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `tema_id` int(10) unsigned DEFAULT NULL,
  `respuesta_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_foro_likes` (`usuario_id`,`tema_id`,`respuesta_id`),
  KEY `fk_foro_likes_tema` (`tema_id`),
  KEY `fk_foro_likes_respuesta` (`respuesta_id`),
  CONSTRAINT `fk_foro_likes_respuesta` FOREIGN KEY (`respuesta_id`) REFERENCES `foro_respuestas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_likes_tema` FOREIGN KEY (`tema_id`) REFERENCES `foro_temas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_likes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=184 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `foro_likes`
--

LOCK TABLES `foro_likes` WRITE;
/*!40000 ALTER TABLE `foro_likes` DISABLE KEYS */;
INSERT  IGNORE INTO `foro_likes` VALUES (1,23,NULL,1,'2026-08-02 10:57:03'),(2,23,2,NULL,'2026-08-02 10:57:03'),(3,23,3,NULL,'2026-08-02 10:57:03'),(4,23,NULL,12,'2026-08-02 10:57:03'),(5,23,NULL,13,'2026-08-02 10:57:03'),(6,23,NULL,14,'2026-08-02 10:57:03'),(7,23,NULL,2,'2026-08-02 10:57:03'),(8,23,NULL,10,'2026-08-02 10:57:03'),(9,23,NULL,15,'2026-08-02 10:57:03'),(10,23,NULL,23,'2026-08-02 10:57:03'),(11,23,NULL,19,'2026-08-02 10:57:03'),(12,23,NULL,27,'2026-08-02 10:57:03'),(13,23,9,NULL,'2026-08-02 10:57:03'),(14,23,NULL,37,'2026-08-02 10:57:03'),(15,23,NULL,32,'2026-08-02 10:57:03'),(16,23,NULL,28,'2026-08-02 10:57:03'),(17,23,NULL,33,'2026-08-02 10:57:03'),(18,23,11,NULL,'2026-08-02 10:57:03'),(19,23,12,NULL,'2026-08-02 10:57:03'),(20,23,13,NULL,'2026-08-02 10:57:03'),(21,23,14,NULL,'2026-08-02 10:57:03'),(22,23,15,NULL,'2026-08-02 10:57:03'),(23,23,NULL,50,'2026-08-02 10:57:03'),(24,23,16,NULL,'2026-08-02 10:57:03'),(25,23,NULL,24,'2026-08-02 10:57:03'),(26,23,NULL,51,'2026-08-02 10:57:03'),(27,23,NULL,38,'2026-08-02 10:57:03'),(28,23,NULL,20,'2026-08-02 10:57:03'),(29,23,NULL,29,'2026-08-02 10:57:03'),(30,23,NULL,52,'2026-08-02 10:57:03'),(31,23,NULL,53,'2026-08-02 10:57:03'),(32,23,NULL,34,'2026-08-02 10:57:03'),(33,23,NULL,54,'2026-08-02 10:57:03'),(34,23,NULL,35,'2026-08-02 10:57:03'),(35,23,NULL,30,'2026-08-02 10:57:03'),(36,23,NULL,55,'2026-08-02 10:57:03'),(37,23,17,NULL,'2026-08-02 10:57:03'),(38,23,NULL,56,'2026-08-02 10:57:03'),(39,23,NULL,57,'2026-08-02 10:57:03'),(40,23,NULL,31,'2026-08-02 10:57:03'),(41,23,NULL,36,'2026-08-02 10:57:03'),(42,23,NULL,21,'2026-08-02 10:57:03'),(43,23,NULL,39,'2026-08-02 10:57:03'),(44,23,NULL,25,'2026-08-02 10:57:03'),(45,23,NULL,59,'2026-08-02 10:57:03'),(46,23,NULL,44,'2026-08-02 10:57:03'),(47,23,NULL,43,'2026-08-02 10:57:03'),(48,23,NULL,42,'2026-08-02 10:57:03'),(49,23,NULL,16,'2026-08-02 10:57:03'),(50,23,NULL,17,'2026-08-02 10:57:03'),(51,23,NULL,11,'2026-08-02 10:57:03'),(52,23,NULL,3,'2026-08-02 10:57:03'),(53,23,NULL,8,'2026-08-02 10:57:03'),(54,23,NULL,18,'2026-08-02 10:57:03'),(55,23,NULL,26,'2026-08-02 10:57:03'),(56,23,NULL,4,'2026-08-02 10:57:03'),(57,23,NULL,45,'2026-08-02 10:57:03'),(58,23,NULL,46,'2026-08-02 10:57:03'),(59,23,NULL,47,'2026-08-02 10:57:03'),(60,23,NULL,67,'2026-08-02 10:57:03'),(61,23,NULL,68,'2026-08-02 10:57:03'),(62,23,NULL,60,'2026-08-02 10:57:03'),(63,23,NULL,58,'2026-08-02 10:57:03'),(64,23,NULL,69,'2026-08-02 10:57:03'),(65,23,NULL,70,'2026-08-02 10:57:03'),(66,23,NULL,71,'2026-08-02 10:57:03'),(67,23,NULL,41,'2026-08-02 10:57:03'),(68,23,NULL,72,'2026-08-02 10:57:03'),(69,23,27,NULL,'2026-08-02 10:57:03'),(70,23,28,NULL,'2026-08-02 10:57:03'),(71,23,29,NULL,'2026-08-02 10:57:03'),(72,23,30,NULL,'2026-08-02 10:57:03'),(73,23,33,NULL,'2026-08-02 10:57:03'),(74,24,1,NULL,'2026-08-02 10:57:03'),(75,26,1,NULL,'2026-08-02 10:57:03'),(76,29,1,NULL,'2026-08-02 10:57:03'),(77,32,1,NULL,'2026-08-02 10:57:03'),(78,33,26,NULL,'2026-08-02 10:57:03'),(79,34,10,NULL,'2026-08-02 10:57:03'),(80,34,33,NULL,'2026-08-02 10:57:03'),(81,38,3,NULL,'2026-08-02 10:57:03'),(82,38,NULL,27,'2026-08-02 10:57:03'),(83,38,10,NULL,'2026-08-02 10:57:03'),(84,38,NULL,50,'2026-08-02 10:57:03'),(85,38,NULL,40,'2026-08-02 10:57:03'),(86,38,NULL,73,'2026-08-02 10:57:03'),(87,40,4,NULL,'2026-08-02 10:57:04'),(88,40,NULL,12,'2026-08-02 10:57:04'),(89,40,NULL,13,'2026-08-02 10:57:04'),(90,40,NULL,14,'2026-08-02 10:57:04'),(91,40,NULL,15,'2026-08-02 10:57:04'),(92,40,5,NULL,'2026-08-02 10:57:04'),(93,40,10,NULL,'2026-08-02 10:57:04'),(94,40,11,NULL,'2026-08-02 10:57:04'),(95,40,12,NULL,'2026-08-02 10:57:04'),(96,40,13,NULL,'2026-08-02 10:57:04'),(97,43,1,NULL,'2026-08-02 10:57:04'),(98,43,7,NULL,'2026-08-02 10:57:04'),(99,43,10,NULL,'2026-08-02 10:57:04'),(100,43,15,NULL,'2026-08-02 10:57:04'),(101,43,NULL,50,'2026-08-02 10:57:04'),(102,43,16,NULL,'2026-08-02 10:57:04'),(103,43,NULL,51,'2026-08-02 10:57:04'),(104,43,25,NULL,'2026-08-02 10:57:04'),(105,43,27,NULL,'2026-08-02 10:57:04'),(106,44,1,NULL,'2026-08-02 10:57:04'),(107,44,2,NULL,'2026-08-02 10:57:04'),(108,44,NULL,7,'2026-08-02 10:57:04'),(109,44,NULL,27,'2026-08-02 10:57:04'),(110,44,NULL,32,'2026-08-02 10:57:04'),(111,44,NULL,28,'2026-08-02 10:57:04'),(112,44,NULL,33,'2026-08-02 10:57:04'),(113,44,10,NULL,'2026-08-02 10:57:04'),(114,44,15,NULL,'2026-08-02 10:57:04'),(115,44,NULL,50,'2026-08-02 10:57:04'),(116,44,NULL,51,'2026-08-02 10:57:04'),(117,44,NULL,29,'2026-08-02 10:57:04'),(118,44,NULL,52,'2026-08-02 10:57:04'),(119,44,NULL,53,'2026-08-02 10:57:04'),(120,44,NULL,34,'2026-08-02 10:57:04'),(121,44,NULL,54,'2026-08-02 10:57:04'),(122,44,NULL,55,'2026-08-02 10:57:04'),(123,44,NULL,56,'2026-08-02 10:57:04'),(124,44,NULL,57,'2026-08-02 10:57:04'),(125,44,NULL,36,'2026-08-02 10:57:04'),(126,44,NULL,58,'2026-08-02 10:57:04'),(127,44,26,NULL,'2026-08-02 10:57:04'),(128,44,27,NULL,'2026-08-02 10:57:04'),(129,44,28,NULL,'2026-08-02 10:57:04'),(130,44,29,NULL,'2026-08-02 10:57:04'),(131,44,39,NULL,'2026-08-02 10:57:04'),(132,44,NULL,84,'2026-08-02 10:57:04'),(133,44,NULL,85,'2026-08-02 10:57:04'),(134,44,NULL,86,'2026-08-02 10:57:04'),(135,45,1,NULL,'2026-08-02 10:57:04'),(136,45,NULL,7,'2026-08-02 10:57:04'),(137,45,NULL,9,'2026-08-02 10:57:04'),(138,45,NULL,10,'2026-08-02 10:57:04'),(139,45,10,NULL,'2026-08-02 10:57:04'),(140,45,11,NULL,'2026-08-02 10:57:04'),(141,45,12,NULL,'2026-08-02 10:57:04'),(142,45,13,NULL,'2026-08-02 10:57:04'),(143,45,14,NULL,'2026-08-02 10:57:04'),(144,45,16,NULL,'2026-08-02 10:57:04'),(145,45,NULL,30,'2026-08-02 10:57:04'),(146,45,NULL,58,'2026-08-02 10:57:04'),(147,47,26,NULL,'2026-08-02 10:57:04'),(148,47,27,NULL,'2026-08-02 10:57:04'),(149,47,28,NULL,'2026-08-02 10:57:04'),(150,48,1,NULL,'2026-08-02 10:57:04'),(151,50,1,NULL,'2026-08-02 10:57:04'),(152,50,NULL,7,'2026-08-02 10:57:04'),(153,50,15,NULL,'2026-08-02 10:57:04'),(154,62,28,NULL,'2026-08-02 10:57:04'),(155,62,29,NULL,'2026-08-02 10:57:04'),(156,62,30,NULL,'2026-08-02 10:57:04'),(157,62,NULL,78,'2026-08-02 10:57:04'),(158,62,32,NULL,'2026-08-02 10:57:04'),(159,62,33,NULL,'2026-08-02 10:57:04'),(160,62,35,NULL,'2026-08-02 10:57:04'),(161,62,36,NULL,'2026-08-02 10:57:04'),(162,62,37,NULL,'2026-08-02 10:57:04'),(163,62,42,NULL,'2026-08-02 10:57:04'),(164,62,NULL,99,'2026-08-02 10:57:04'),(165,62,NULL,97,'2026-08-02 10:57:04'),(166,62,NULL,95,'2026-08-02 10:57:04'),(167,62,NULL,93,'2026-08-02 10:57:04'),(168,62,NULL,91,'2026-08-02 10:57:04'),(169,62,NULL,89,'2026-08-02 10:57:04'),(170,62,NULL,83,'2026-08-02 10:57:04'),(171,62,48,NULL,'2026-08-02 10:57:04'),(172,62,NULL,84,'2026-08-02 10:57:04'),(173,62,NULL,90,'2026-08-02 10:57:04'),(174,62,NULL,92,'2026-08-02 10:57:04'),(175,62,NULL,94,'2026-08-02 10:57:04'),(176,62,NULL,96,'2026-08-02 10:57:04'),(177,62,NULL,98,'2026-08-02 10:57:04'),(178,62,NULL,100,'2026-08-02 10:57:04'),(179,62,NULL,79,'2026-08-02 10:57:04'),(180,62,NULL,81,'2026-08-02 10:57:04'),(182,9,39,NULL,'2026-08-02 11:32:41');
/*!40000 ALTER TABLE `foro_likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `foro_notificaciones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `foro_notificaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL COMMENT 'destinatario',
  `tipo` enum('respuesta','mencion') NOT NULL,
  `tema_id` int(10) unsigned NOT NULL,
  `respuesta_id` int(10) unsigned DEFAULT NULL,
  `actor_usuario_id` int(10) unsigned NOT NULL COMMENT 'quién generó la notificación',
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_foro_notif_usuario` (`usuario_id`,`leida`),
  KEY `fk_foro_notif_tema` (`tema_id`),
  KEY `fk_foro_notif_respuesta` (`respuesta_id`),
  KEY `fk_foro_notif_actor` (`actor_usuario_id`),
  CONSTRAINT `fk_foro_notif_actor` FOREIGN KEY (`actor_usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_notif_respuesta` FOREIGN KEY (`respuesta_id`) REFERENCES `foro_respuestas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_notif_tema` FOREIGN KEY (`tema_id`) REFERENCES `foro_temas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_notif_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `foro_notificaciones`
--

LOCK TABLES `foro_notificaciones` WRITE;
/*!40000 ALTER TABLE `foro_notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `foro_notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Notificaciones — sistema unificado para toda la plataforma (no solo foro).
-- Reemplaza a foro_notificaciones como mecanismo activo (esa tabla se deja
-- sin borrar por si quedó algo histórico, pero ya no se le escribe — ver
-- foro_crear_notificacion() en foro_helpers.php, reimplementada para
-- escribir aquí). A diferencia de foro_notificaciones, `enlace`/`titulo` se
-- guardan ya resueltos al crear la notificación (no vía JOIN a tema_id) para
-- no atar la tabla a ninguna entidad en particular — así una notificación de
-- "curso nuevo" no necesita ninguna fila de foro_temas.
--

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL COMMENT 'destinatario',
  `tipo` enum('respuesta','mencion','nuevo_curso','nuevo_evento','nuevo_producto','nueva_noticia','aviso_admin') NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` varchar(500) DEFAULT NULL,
  `enlace` varchar(300) NOT NULL COMMENT 'relativo a BASE_URL',
  `actor_usuario_id` int(10) unsigned DEFAULT NULL COMMENT 'quién generó la notificación; NULL si es del sistema (contenido nuevo)',
  `difusion_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL si es 1-a-1 (respuesta/mención); si no, referencia la campaña masiva que la creó',
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notificaciones_usuario` (`usuario_id`,`leida`),
  KEY `fk_notificaciones_dest_actor` (`actor_usuario_id`),
  KEY `idx_notificaciones_difusion` (`difusion_id`),
  CONSTRAINT `fk_notificaciones_dest_actor` FOREIGN KEY (`actor_usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notificaciones_dest_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Instalación vieja que ya tenía `notificaciones` antes de difusion_id/aviso_admin.
ALTER TABLE notificaciones MODIFY COLUMN tipo enum('respuesta','mencion','nuevo_curso','nuevo_evento','nuevo_producto','nueva_noticia','aviso_admin') NOT NULL;
ALTER TABLE notificaciones ADD COLUMN IF NOT EXISTS difusion_id int(10) unsigned DEFAULT NULL AFTER actor_usuario_id;
ALTER TABLE notificaciones ADD KEY IF NOT EXISTS idx_notificaciones_difusion (difusion_id);

--
-- Qué tipos de notificación "de difusión" (contenido nuevo, no personales
-- como respuesta/mención) están activos — panel/admin/notificaciones_config.php.
-- Solo los 4 tipos de difusión tienen fila aquí; respuesta/mención siempre
-- están activas (son 1-a-1, disparadas por una acción directa del usuario,
-- no un aviso masivo que un admin necesite poder apagar). aviso_admin
-- tampoco tiene fila — es una acción explícita del admin, no un disparador
-- automático que convenga poder apagar de antemano.
--

CREATE TABLE IF NOT EXISTS `notificacion_config` (
  `tipo` enum('nuevo_curso','nuevo_evento','nuevo_producto','nueva_noticia') NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notificacion_config`
--

INSERT IGNORE INTO `notificacion_config` (`tipo`, `activo`) VALUES
('nuevo_curso',1),('nuevo_evento',1),('nuevo_producto',1),('nueva_noticia',1);

--
-- Difusiones masivas de notificaciones — una fila por campaña (contenido
-- nuevo publicado, o un aviso personalizado que un admin compone a mano
-- desde panel/admin/notificaciones_config.php). Cada usuario recibe su
-- propia fila en `notificaciones` con este id en `difusion_id` — borrar la
-- difusión (ON DELETE CASCADE) la quita de la bandeja de todos a la vez.
--

CREATE TABLE IF NOT EXISTS `notificaciones_difusiones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('nuevo_curso','nuevo_evento','nuevo_producto','nueva_noticia','aviso_admin') NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` varchar(500) DEFAULT NULL,
  `enlace` varchar(300) NOT NULL,
  `creado_por` int(10) unsigned DEFAULT NULL COMMENT 'NULL si fue un disparador automático (contenido nuevo), no una acción manual del admin',
  `total_destinatarios` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_difusion_creado_por` (`creado_por`),
  CONSTRAINT `fk_difusion_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La FK de notificaciones.difusion_id espera a que esta tabla ya exista.
ALTER TABLE notificaciones DROP FOREIGN KEY IF EXISTS fk_notificaciones_difusion;
ALTER TABLE notificaciones ADD CONSTRAINT fk_notificaciones_difusion FOREIGN KEY (difusion_id) REFERENCES notificaciones_difusiones (id) ON DELETE CASCADE;

--
-- Table structure for table `foro_respuestas`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `foro_respuestas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tema_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `contenido` mediumtext NOT NULL,
  `editado_en` datetime DEFAULT NULL,
  `editado_por` int(10) unsigned DEFAULT NULL,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_foro_respuestas_tema` (`tema_id`),
  KEY `fk_foro_respuestas_usuario` (`usuario_id`),
  KEY `fk_foro_respuestas_editado_por` (`editado_por`),
  KEY `fk_foro_respuestas_eliminado_por` (`eliminado_por`),
  FULLTEXT KEY `ft_foro_respuestas` (`contenido`),
  CONSTRAINT `fk_foro_respuestas_editado_por` FOREIGN KEY (`editado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foro_respuestas_eliminado_por` FOREIGN KEY (`eliminado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foro_respuestas_tema` FOREIGN KEY (`tema_id`) REFERENCES `foro_temas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_respuestas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--

ALTER TABLE foro_respuestas
  ADD COLUMN IF NOT EXISTS editado_en DATETIME NULL AFTER contenido,
  ADD COLUMN IF NOT EXISTS editado_por INT UNSIGNED NULL AFTER editado_en;

ALTER TABLE foro_respuestas DROP FOREIGN KEY IF EXISTS fk_foro_respuestas_editado_por;
ALTER TABLE foro_respuestas ADD CONSTRAINT fk_foro_respuestas_editado_por FOREIGN KEY (editado_por) REFERENCES usuarios_perfil (id) ON DELETE SET NULL;

-- Papelera de respuestas: un autor puede "borrar" su propia respuesta (o un admin
-- cualquiera), pero no se elimina de inmediato — queda oculta del público
-- (eliminado_en IS NOT NULL) para que un admin la revise en
-- panel/admin/foro.php (pestaña "Moderación"), y se purga sola a los 15 días si nadie le da
-- atención (ver foro_purgar_papelera_vencida() en foro_helpers.php).
ALTER TABLE foro_respuestas
  ADD COLUMN IF NOT EXISTS eliminado_en DATETIME NULL AFTER editado_por,
  ADD COLUMN IF NOT EXISTS eliminado_por INT UNSIGNED NULL AFTER eliminado_en;

ALTER TABLE foro_respuestas DROP FOREIGN KEY IF EXISTS fk_foro_respuestas_eliminado_por;
ALTER TABLE foro_respuestas ADD CONSTRAINT fk_foro_respuestas_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios_perfil (id) ON DELETE SET NULL;

-- Dumping data for table `foro_respuestas`
--

LOCK TABLES `foro_respuestas` WRITE;
/*!40000 ALTER TABLE `foro_respuestas` DISABLE KEYS */;
INSERT  IGNORE INTO `foro_respuestas` VALUES (1,1,32,'<p>Muchas gracias, Hare Krishna</p>',NULL,NULL,NULL,NULL,'2025-09-26 18:35:29','2026-08-02 16:49:17'),(2,1,38,'<p>Garcia, gracias, gracias,  Hare Krishna.</p>',NULL,NULL,NULL,NULL,'2025-11-13 15:28:30','2026-08-02 16:49:17'),(3,1,45,'<p>El foro me encantó, escribir nos abre más y los pensamientos fluyen con más claridad.<br>\nCuando hablo, se me van las ideas o las palabras correctas. Gracias.</p>',NULL,NULL,NULL,NULL,'2025-11-24 05:05:57','2026-08-02 16:49:17'),(4,1,43,'<p>Gracias por esta gran labor del Reto Arjuna.<br>\nHere Krishna</p>',NULL,NULL,NULL,NULL,'2025-11-24 10:10:55','2026-08-02 16:49:17'),(5,1,42,'<p>Gracias por implementar este proceso encaminado a descubrir nuestra identidad trascendental</p>',NULL,NULL,NULL,NULL,'2025-11-26 11:33:25','2026-08-02 16:49:17'),(6,1,50,'<p>Gracias haré krishna</p>',NULL,NULL,NULL,NULL,'2025-11-27 04:52:23','2026-08-02 16:49:17'),(7,2,23,'<p>¡Buen día, Gaby! gracias por la pregunta🙏</p>\n\n<p>Krishna explica Om Tat Sat (Bg 17.23–27) como un “nombre triple” del Absoluto para realizar sacrificios -&gt; sacralizar nuestros oficios o lo que hacemos. Es como una brújula que se activa con la conciencia: te recuerda el norte correcto y limpia la intención.<br>\nEs decir, se emplea para dirigir y clarificar la intención trascendental antes de decidir o actuar donde nos percibimos en control, o aceptar situaciones fuera de nuestro control.</p>\n\n<p><strong>De un vistazo:</strong></p>\n\n<p>OM → Recuerdo el centro: Tú eres la meta.</p>\n\n<p>TAT → Lo ofrezco para Ti, no para mi ego.</p>\n\n<p>SAT → Que sea verdadero, bueno y para Tu satisfacción.</p>\n\n<p><strong>En la práctica</strong></p>\n\n<p>Antes de cualquier esfuerzo (trabajo, servicio, conversación difícil, etc.) o o recreación regulada (juego, descanso, conversación agradable, etc.): recita, suave o mentalmente, Om Tat Sat para despejar cualquier intención material o temporal.</p>\n\n<p>Después de terminar: vuelve a recitar Om Tat Sat para soltar el fruto, corregir cualquier desviación y cerrar en ofrenda.</p>\n\n<p>Nota: En nuestro camino, el Mahāmantra Hare Krishna es el japa (rezo) principal; Om Tat Sat es una invocación breve para encuadrar la mente y el corazón al inicio y al cierre de lo que hacemos. Aun así, si sientes la inclinación, también puede ser benéfico meditar en este mantra con mayor profundidad; no hay problema ni contraindicación. 🌿</p>',NULL,NULL,NULL,NULL,'2025-10-15 23:11:26','2026-08-02 16:49:17'),(8,2,45,'<p>Recuerdo este mantra cuando daba clases de Kundalini yoga al finalizar siempre la clase.</p>',NULL,NULL,NULL,NULL,'2025-11-24 05:11:47','2026-08-02 16:49:17'),(9,3,23,'<p><a href=\"https://postimg.cc/FfWBN41k\" target=\"_blank\" rel=\"noopener\"><img src=\"https://i.postimg.cc/G2dwP3CK/Generated-Image-October-16-2025-10-27-AM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"></a></p>\n\n<p>¡Gracias, Ivon, por compartirte y felicidades por los logros! Me alegra mucho leerte. Sobre el “¿cómo conectar con la compasión cuando aparece incomodidad ante una crítica a tu esfuerzo?”, coincido contigo: ya diste un acierto enorme al observar y notar que, muchas veces, lo que ocurre tiene más que ver con procesos del otro que contigo. Lo que sigue es afinar cómo responder sin caer en la lástima (que nos coloca falsamente por encima) ni en la desconexión. Te propongo visualizar un plan.</p>\n\n<p>Aqui dejo una propuesta para tu plan</p>\n\n<h5>Parte 1 — Desenganche emocional</h5>\n\n<ol><li>Pausa corporal. Si <strong>(nombre)</strong> dice algo que me molesta, exhalo largo por la boca, bajo hombros, suavizo mandíbula y llevo una mano al pecho.</li>\n<li>Ejercicio mental de Nombrar sin juicio. “Ahora siento ___ (molestia/contracción/enojo/nudo en ___).” Nombrar no justifica; me desidentifica del impulso.</li>\n<li>Seguro de <strong>Perspectiva</strong>. “Esto que siento y que veo en mi mento es transitorio.”</li>\n<li>Abrir espacio al otro (sin asumir). “¿Quiero entenderte mejor y reconocer el valor en lo que dices? ¿podrias repetirlo sin usar palabras como_________ con las que me bloqueo”</li></ol>\n\n<h5>Parte 2 — Establece límites compasivos</h5>\n\n<ul><li><p>“Veo tensión. Prefiero hablar cuando estemos más tranquilos.”</p></li>\n\n<li><p>“Agradezco el punto; me duele la forma. ¿Podemos seguir con respeto?”</p></li>\n\n<li><p>“Ahora cuidaré mi calma; retomamos luego.”</p></li></ul>\n\n<p><em>Compasión ≠ permisividad.</em> Es firmeza amorosa: cuido mi centro y desde ahí elijo la respuesta más útil.</p>\n\n<p><strong>Si la crítica te activa</strong></p>\n\n<p>Comentario rectificador: “Gracias por decirlo. Me importa mejorar y también la forma. Si lo hablamos sin ataques, te escucho.”</p>\n\n<p>Si insisten: “Así no puedo seguir. Necesito tranquilizarme. Pausamos aquí y retomamos después.”</p>\n\n<p>Si hay violencia: me retiro. Cuidarme también es compasión.</p>\n\n<p><strong>Si la crítica es válida pero la forma hiere</strong></p>\n\n<p>Rescato el contenido: “De lo que dices, me sirve ___.”</p>\n\n<p>Pongo límite a la forma: “La forma me duele.”</p>\n\n<p>Propongo siguiente paso: “¿Lo revisamos con ejemplos mañana a las 17:00?”</p>\n\n<p>Mini-prácticas (2–3 min)</p>\n\n<p>3R: Respirar – Reconocer – Responder.</p>\n\n<p>Frase ancla (previo a charla difícil): “Uso mi atención al servicio de lo esencial.”</p>\n\n<p>Autocompasión breve: “Es comprensible que esto me duela; puedo cuidarme y cuidar el vínculo.”</p>\n\n<p>Chequeo post-evento: ¿Qué sentí? ¿Qué valor cuidé? ¿Qué haré distinto la próxima?</p>\n\n<p><strong>Esto ya es compasión en construcción; ahora solo la afinas con límites.</strong></p>',NULL,NULL,NULL,NULL,'2025-10-16 22:13:12','2026-08-02 16:49:17'),(10,3,38,'<p>No engancharme, abro mis oídos,  cierro mi boca cuando necesito escuchar la verdad,  abro mi corazón y mi boca cuando necesito decir la verdad.</p>',NULL,NULL,NULL,NULL,'2025-11-13 15:39:45','2026-08-02 16:49:17'),(11,3,45,'<p>Ahora me pasa lo de Mireille.<br>\nYo fui muy broncuda en mi vida pasada. Pero muy!!! je!!!</p>',NULL,NULL,NULL,NULL,'2025-11-24 04:06:35','2026-08-02 16:49:17'),(12,4,28,'<p><strong>@Otakur</strong> necesito cambiar el apego a las cosas materiales</p>',NULL,NULL,NULL,NULL,'2025-11-13 11:57:28','2026-08-02 16:49:17'),(13,4,37,'<p><strong>@Otakur</strong> el llamado a transformar mi vida y llevarla de manera consciente.<br>\nAyudando a otros a expandir su contexto.</p>',NULL,NULL,NULL,NULL,'2025-11-13 12:06:06','2026-08-02 16:49:17'),(14,4,38,'<p>Poder controlar el ruido interno,  el dejar de juzgarme tan fuerte.  Seguir ayudando, poder escuchar los mensajes que recibo.</p>',NULL,NULL,NULL,NULL,'2025-11-13 15:25:02','2026-08-02 16:49:17'),(15,4,39,'<p><strong>@Otakur</strong> Entender mi razón de ser ante un mundo que transpira violencia, desigualdad, cinismo, hipocresía. El saber tomar decisiones difíciles ante tanta maldad desproporcionada, para no dejarme influir ni seguir fluyendo ante tanta maldad</p>',NULL,NULL,NULL,NULL,'2025-11-13 17:28:00','2026-08-02 16:49:17'),(16,4,45,'<p>A transformar mi vida, saber tomar buenas decisiones, seguir ayudando y saber vivir en paz y tranquilidad sin que lo externo me perturbe si eso no está en mis manos.</p>',NULL,NULL,NULL,NULL,'2025-11-24 03:36:30','2026-08-02 16:49:17'),(17,4,45,'<p>Amar lo que hago.</p>',NULL,NULL,NULL,NULL,'2025-11-24 03:37:05','2026-08-02 16:49:17'),(18,4,50,'<p>Mirar desde el amor, empatia hacia los seres vivos no ser impulsivo pensar antes de actuar <br>\nTolerancia, paciencia y más sed de krishna!!</p>',NULL,NULL,NULL,NULL,'2025-11-24 06:54:10','2026-08-02 16:49:17'),(19,5,40,'<p>La parte de mi vida que ya no se siente auténtica es la relación con mi familia ampliada. Siento que se mueven en dinámicas que ya no resuenan conmigo, y eso me desconecta. Sin embargo, también quiero dejar de juzgarlos y aprender a aceptarlos tal como son, sin intentar cambiarlos.</p>\n\n<p>“Voy a dejar el juicio hacia mi familia<br>\ny comenzar a aceptarlos tal como son, para vivir más ligera y más auténtica”</p>',NULL,NULL,NULL,NULL,'2025-11-15 08:13:16','2026-08-02 16:49:17'),(20,5,38,'<p>No confío en mi intuición,  no confío en lo que soy capaz de hacer, siento que no puedo salir adelante.</p>',NULL,NULL,NULL,NULL,'2025-11-23 07:56:10','2026-08-02 16:49:17'),(21,5,45,'<p>Pues mi incomodidad y mi hartazgo es vivir día a día con dolor y con más de 100 síntomas que aparecen y desaparecen y no saber cómo sentirme al otro día. Pero voy a dejar de pensar eso y que tampoco soy este cuerpo y comenzar a levantarme como si esto no me limitara, pues todavía tengo todo mi cuerpo.</p>',NULL,NULL,NULL,NULL,'2025-11-24 03:02:24','2026-08-02 16:49:17'),(22,5,50,'<p>Voy a dejar de ser egoísta, y a comenzar a ser más consciente</p>',NULL,NULL,NULL,NULL,'2025-11-27 04:58:05','2026-08-02 16:49:17'),(23,6,40,'<p><strong>@Otakur</strong> Suelo mostrar el “personaje” de quien ve las cosas rápido y quiere que todo sea coherente. Me cuesta no juzgar cuando los demás no ven lo obvio, y por dentro me frustro. Pienso más rápido de lo que hablo, y aunque sé que no tengo la verdad absoluta, muchas veces me detengo y me callo.</p>',NULL,NULL,NULL,NULL,'2025-11-15 08:08:23','2026-08-02 16:49:17'),(24,6,38,'<p><strong>@Otakur</strong> no me se explicar, pienso las cosas pero al decirlas no las digo adecuadamente. Tengo mucho ruido en la cabeza.</p>',NULL,NULL,NULL,NULL,'2025-11-23 07:53:45','2026-08-02 16:49:17'),(25,6,45,'<p>Sí, totalmente. suelo mostrar fortaleza pero por dentro estoy hecha pedazos, quiero no ver a nadie para no fingir que estoy bien, de igual manera no se nota mi enfermedad, así que la gente me dice que bien te ves, cuando en realidad yo no me siento así. Así que uso máscaras para no hacerme la víctima, además de que no me gusta. Pero a veces me gustaría que investigaran un poco para que supieran cómo es mi día a día, pero al final me rindo ante eso y es más cómodo usar máscaras.</p>',NULL,NULL,NULL,NULL,'2025-11-24 03:11:47','2026-08-02 16:49:17'),(26,6,50,'<p>Me causó el sentimiento, de actuar como si no pasara nada, cuando por dentro estoy confundido o roto<br>\nPor no expresar lo que pienso,y no incomodar a mis seres más cercanos. <br>\nNecesito claridad de pensamiento, ser más coherente, respetar pensamiento y acción de los demás, respetar la forma de creer y pensar, <br>\nHaré krishna</p>',NULL,NULL,NULL,NULL,'2025-11-24 07:45:59','2026-08-02 16:49:17'),(27,7,40,'<p><strong>@Otakur</strong> Yo misma y mi esposo. Cuando tengo dudas me pregunto si lo que deseo hacer viene desde el ego y cuál es el propósito. Me hubiese gustado que mi familia fuera más conectada a nivel conciencia. Fui educada para reaccionar sin escuchar y mucho menos observar.</p>',NULL,NULL,NULL,NULL,'2025-11-17 06:40:18','2026-08-02 16:49:17'),(28,7,43,'<p><strong>@Otakur</strong> yo tengo a mis maestros del Reto Arjuna que me ayudan a recordar lo esencial.<br>\nMe ayuda mucho el seguimiento diario del Reto Arjuna.<br>\nGracias</p>',NULL,NULL,NULL,NULL,'2025-11-21 18:58:25','2026-08-02 16:49:17'),(29,7,38,'<p>Mi esposo es quien me  a ayudado mucho en mi crecimiento espiritual,  el me mostró el camino a Krishna</p>',NULL,NULL,NULL,NULL,'2025-11-23 07:58:30','2026-08-02 16:49:17'),(30,7,44,'<p>Si, tengo un maestro que me ha guiado por estos últimos 6 años y me ha enseñado tantas cosas que me han ayudado a evitar volver a la perdición. <br>\nPor el momento estamos distanciados por alguna razón,  imagino que es hora de poner en práctica lo aprendido en éste tiempo. Pero en mi corazón aun resuenan sus palabras. 🙏🏻🩷</p>',NULL,NULL,NULL,NULL,'2025-11-23 09:33:59','2026-08-02 16:49:17'),(31,7,45,'<p>Es dificil cuando uno busca la aprovación de la familia en mi caso me canse pues nunca la tuve, fui la obeja negra que ellos dicen que tome un mal camino para ellos, pero tengo el gran honor que en el camino me encontré con muchos maestros de diferentes caminos y siempre valoraron mucho mi trabajo y siempre me quedaba para trabajar con ellos, hice mucho trabajo para las comunidades donde iba viviendo de arte, de cultura, de terapias donde recibi mucho amor de las personas, pero al mismo tiempo cuando dejaba esos lugares sentia su olvido y sentía el no reconocimento o siento y eso es como una incomodidad que no he superado del todo &quot; El reconocimiento&quot; y por muchos &quot; la traicion&quot; . Ahora mi pareja me apoya económicamente, me felicita, me da muchos halagos y estuvo en mis peores momentos. Aunque también tiempo atrás la sufrí mucho con él ya que su bipolaridad me afectó mucho, gracias a Krishna que ahora él tiene mucho tiempo estable y ahora le tocó a él apoyarme y una parte de mí se deja, como que siento, por fin puedo relajarme después de una vida ruda por muchos años. je! Pero sé que tengo que ayudar y generar de nuevo mi economía y aunque mi hija ya está en pareja a veces sé que necesita mi ayuda.</p>',NULL,NULL,NULL,NULL,'2025-11-24 02:47:51','2026-08-02 16:49:17'),(32,8,34,'<p>Lo que espero obtener con el reto Arjuna es tranquilidad, armonia, claridad, paz mental</p>',NULL,NULL,NULL,NULL,'2025-11-21 18:55:33','2026-08-02 16:49:17'),(33,8,42,'<p>Conocimiento verdadero para transitar por este mundo tan complejo y así ayudar a otros a encontrar a Dios</p>',NULL,NULL,NULL,NULL,'2025-11-21 19:03:05','2026-08-02 16:49:17'),(34,8,38,'<p>Lo que espero del reto Arjuna es seguir avanzando en mi sanación.</p>',NULL,NULL,NULL,NULL,'2025-11-23 09:14:39','2026-08-02 16:49:17'),(35,8,44,'<p>Lo que espero del Reto Arjuna es integrar aún más la consciencia de Krishna 🫶🏻</p>',NULL,NULL,NULL,NULL,'2025-11-23 09:28:25','2026-08-02 16:49:17'),(36,8,45,'<p>Lo que espero del Reto Arjuna es seguir trabajando en mi nuevo Yo trasendental y ver la vida de en este mundo material en lo trasendental en armonia, alegría y paz conmigo misma y con el exterior, ya que ahora me cuesta mucho socializar, estoy feliz con mi yo solitario encontrandome con Krishna y dandole otro valor mi familia y a mi nueva Nube y volver a ser economicamente independiente.<br>\nMi salud va paso a paso. aprendiendo a vivir con ella.</p>',NULL,NULL,NULL,NULL,'2025-11-24 02:56:03','2026-08-02 16:49:17'),(37,9,43,'<p>Qué espero recibir de este Reto Arjuna?<br>\nEspero tener más seguridad en el Supremo, más confianza, tener desapego del resultado.<br>\nCuál es mi expectativa?<br>\nQue esté reto Arjuna, me ayude a tener confianza en los procesos de la vida, en quitarme la necesidad de querer controlar todo.</p>',NULL,NULL,NULL,NULL,'2025-11-21 18:55:17','2026-08-02 16:49:17'),(38,9,38,'<p><strong>@Ramanujan</strong> primera publicación en el foro</p>',NULL,NULL,NULL,NULL,'2025-11-23 07:54:37','2026-08-02 16:49:17'),(39,9,45,'<p>Es mi tercera y estoy muy feliz. Le doy gracias a Krishna porque él me llevó a ustedes.</p>',NULL,NULL,NULL,NULL,'2025-11-24 03:04:18','2026-08-02 16:49:17'),(40,10,50,'<p>Quiero sentir el amor de krishna en mi corazón y así poder respetar a todos los seres vivos</p>',NULL,NULL,NULL,NULL,'2025-11-27 04:59:25','2026-08-02 16:49:17'),(41,11,50,'<p>Lo que espero obtener <br>\nEs conciencia, paciencia, desprendimiento <br>\nCambios de juicios y actitudes <br>\nMejores hábitos</p>',NULL,NULL,NULL,NULL,'2025-11-27 05:01:29','2026-08-02 16:49:17'),(42,12,45,'<p>Igual que Betty Gracias, gracias, gracias por ayudarnos a crecer. Eternamente agradecida. :)</p>',NULL,NULL,NULL,NULL,'2025-11-24 03:32:08','2026-08-02 16:49:18'),(43,13,45,'<p>Mi tercer reto y estoy muy feliz y agradecida. Comencé con una depresión muy fuerte y ya soy otra, con otra visión de las cosas y eso lo ha notado mi familia ahora que le mandé las preguntas a una prima y me contesto: Te noto muy bien desde que empezaste con la conciencia de Krishna. eh!!! Hari bol.  :)</p>',NULL,NULL,NULL,NULL,'2025-11-24 03:29:18','2026-08-02 16:49:18'),(44,14,45,'<p>Creo que ya lo mencioné en ejercicios anteriores.<br>\nSiento que ahora estoy en mi forma de vida,lo que siempre soñé de pensar, vivir y de actuar, solo que tengo que hacer algo para mejorar mi economía y mi dinámica de organización para poderlo lograr, para conectar con mi nueva y mejor versión desafiando la enfermedad.</p>',NULL,NULL,NULL,NULL,'2025-11-24 03:25:32','2026-08-02 16:49:18'),(45,14,34,'<p>El espejo y la brújula<br>\n“¿qué me impulsa realmente a trabajar en cambiar<br>\nesto?”<br>\nMi foco en este reto es: Inseguridad, dolor, sufrimiento (carencia y temor)<br>\n“¿Qué motivación me funciona y es más bondadosa para lo que<br>\nquiero hacer?”<br>\nEl romper con la cadena generacional de carencia e ignorancia y abrir paso al camino de la claridad, estabilidad emocional y psicológica a las generaciones que vienen tras de mi.<br>\n“Si aparece la inseguridad, entonces cantaré una alabanza al Supremo“ (porque quiero recordar que Dios siempre esta conmigo)<br>\nHoy descubrí que mis motivaciones venían de: la inseguridad y el dolor<br>\nMi para-qué más claro es: ayudar a mis futuras generaciones (familiares)<br>\nMi regla si–entonces más útil fue: cantar cuando sienta inseguridad o dolor<br>\nAlgo que quiero seguir practicando: el aprender a cambiar la perspectiva de las situaciones y las reacciones ante dichas situaciones.</p>',NULL,NULL,NULL,NULL,'2025-11-24 19:44:05','2026-08-02 16:49:18'),(46,14,34,'<p>Identidad Estable<br>\n“¿Quién soy más allá de este rol y de este momento?”<br>\nun alma eterna<br>\n ¿Desde qué identidad estoy viviendo hoy?<br>\nYo Inseguro<br>\n&quot;más allá de este rol y de este momento soy un alma eterna recordando mi verdadera identidad y proveniencia&quot;<br>\n¿ Cómo actuaria yo si mi valor no dependiera de la relación o rol que tengo con esta persona?<br>\ncon honestidad, poniendo limites si es necesario pero siempre con respeto para no herir a la persona.<br>\n¿ Cómo me definía?<br>\ncomo una persona insegura<br>\n¿ Qué recordé?<br>\nque soy un alma eterna y que las situaciones son temporales<br>\n¿ Qué cambió?<br>\nmi perspectiva<br>\n&quot;Hoy descubrí que no solo soy este cuerpo y mente, también soy un alma eterna&quot;</p>',NULL,NULL,NULL,NULL,'2025-11-24 23:15:29','2026-08-02 16:49:18'),(47,14,50,'<p>Quien soy más alla de este rol y momento?<br>\nUn ser de luz<br>\n¿desd que identidad estoy hoy?<br>\nYo material <br>\nComo actuaría yo di mi valor no dependiera del rol que tengo con esta persona?<br>\nCon tolerancia, receptivo conectar mente y lengua <br>\nPensar antes de actuar <br>\nComo me define?<br>\nCon carencia de carácter <br>\nQue recorde?<br>\nTodos llevamos con nosotros la luz de krishna <br>\nQue cambio?<br>\nMi forma de mirar a mis semejantes <br>\nHoy descubrí q puedo ser mejor, y dar mejores respuestas ,alas situaciones del día a día <br>\nGracias haré krishna</p>',NULL,NULL,NULL,NULL,'2025-11-25 07:33:41','2026-08-02 16:49:18'),(48,14,45,'<p>Hoy al escuchar el reto #6 me encantó darme cuenta de que este espíritu (alma) mío tiene este cuerpo, no el cuerpo tiene un alma o espíritu. (A mí me gusta llamarlo espíritu.) <br>\nAsí que haciendo el ejercicio con mis manos sobre el rosto me vino a mi que como servicio devo dar clases de yoga a gente con fibromialgia con las enseñanzas de Krishna y hablo de un yoga haciendo asanas enfocadas a que no somos este cuerpo y con asanas, meditacion y respiraciones donde se y hasta donde se que este cuerpo dolorido alcanza. Esté, creo que podría ser mi servicio desde casa y en línea.<br>\nY lo que no quiero ver, o no quería ver, es que mi organización debe comenzar por aprender a no dispersarme, por escribir mis objetivos y mis horas de trabajo, y, como no soy este cuerpo, por romper con la fatiga.<br>\nTambién lo que no quería ver es la honestidad con mi pareja, la necesidad de espacio, aunque ya estaba hablado, me parece que él no quiere irse, así que me tomé un momento para ver por dónde retomar esa conversación o cambiar la negociación siendo lo más honesta y sin lastimar. Ahora esto tomará un nuevo reto. OH!</p>',NULL,NULL,NULL,NULL,'2025-11-29 07:55:53','2026-08-02 16:49:18'),(49,14,34,'<p>Visión Profunda<br>\n¿Qué deseo elijo hoy que me acerca a mi mejor versión? <br>\namor y compasión<br>\n¿Cuál es mi para qué ahora? <br>\npara contribuir en la transformación de una sociedad hostil a una sociedad en armonía<br>\n¿Quién soy más allá de este rol y de este momento? <br>\nsoy un alma dichosa<br>\n¿Qué no estoy viendo aún? <br>\nmi valor y el cariño que otros sienten por mi<br>\nEscenario<br>\nMis familiares me invitaron a celebrar el cumpleaños de un integrante de la familia cuando no acostumbran a dirigirme la palabra o a ser distantes conmigo<br>\nLo que realmente está pasando es… que actué desde el temor al juicio y a sentirme insuficiente ante ellos<br>\nLo que esto me invita a cultivar es… la confianza en mi misma y la comunicación efectiva con los demás<br>\nUn paso pequeño posible es… disculparme por mi comportamiento hostil y agradecer la invitación que en su momento rechace</p>',NULL,NULL,NULL,NULL,'2025-12-08 21:21:45','2026-08-02 16:49:18'),(50,15,47,'<p>Hoy reflexioné mucho y entendí que en muchos momentos de mi vida he tomado decisiones desde el miedo.</p>',NULL,NULL,NULL,NULL,'2025-11-23 07:42:06','2026-08-02 16:49:18'),(51,15,43,'<p>Hoy descubrí que tiendo a desvelarme innecesariamente intentando aprovechar cada momento disponible para aprender lo más posible de los temas que me interesan.<br>\nLo verdaderamente posible hoy es que quiero trabajar en: dormirme diario antes de las 11 PM, hacer ejercicio diario y vivir por debajo de mis ingresos.</p>',NULL,NULL,NULL,NULL,'2025-11-23 07:54:30','2026-08-02 16:49:18'),(52,15,34,'<p>Hoy me di cuenta que no siempre se puedo salir adelante sola y reconozco que necesito ayuda, también reconozco que debo poner de mi parte y esforzarme para lograr verdaderos cambios en mi vida y mi persona.</p>',NULL,NULL,NULL,NULL,'2025-11-23 08:00:22','2026-08-02 16:49:18'),(53,15,48,'<p>Lo verdadero posible hoy es que quiero trabajar en el “compromiso” (no se cómo llamarlo) de profundidad en las cosas que hago, comenzar una lectura y terminarla en conciencia, por ejemplo, estar presente. Actuó con prisa, en la urgencia de cada día, sin dedicación o decisión de realizar algo a profundidad apartando un tiempo para comenzar y acabar algún propósito que me deje aprendizaje. Buscar una mirada más amable a mis errores</p>',NULL,NULL,NULL,NULL,'2025-11-23 08:01:04','2026-08-02 16:49:18'),(54,15,44,'<p>Hoy descubrí que aún me falta camino por recorrer, pero también me di cuenta de cuanto he avanzado gracias a mi encuentro con Krishna. <br>\nLo verdadero posible hoy es que quiero trabajar en: Creer y confiar más en mí misma, siento que de ahí partirían muchas cosas como la humildad, y el poder para enfrentar mis miedos. <br>\nHare Krishna 📿</p>',NULL,NULL,NULL,NULL,'2025-11-23 09:26:49','2026-08-02 16:49:18'),(55,15,38,'<p>No se tomar buenas desiciones, las tomo con forme al impulso. No me pongo como prioridad. Con miedos, heridas del alma, inseguridades. Conflictos emocionales</p>',NULL,NULL,NULL,NULL,'2025-11-23 11:21:06','2026-08-02 16:49:18'),(56,15,45,'<p>Hola ¡Hare Krishna!  <br>\n Es mi 3.er reto y estoy muy agradecida, ya que voy paso a paso y con grandes logros. Pero se que me falta mucho, porque me cuesta por está enfermedad que da mucha fatiga crónica, me desconcentro mucho, me disperso, quiero hacer muchas cosas a la vez y me dejo llevar día a día dependiendo como me siento, pero tome este encuento por tercera vez para centrarme y poderme organizar, tener calma y pensar sensatamente, aunque siempre me he querido comer el mundo muy rapido desde pequeña je! así que creo que la <strong>organizacion</strong> de el día a día me hará trabajar mejor paso a paso, para ir hacia donde quiero, un tabajo que me guste me llene para volver a generar mi economía que se quedo en ceros totales y es muy estresante y eso me conlleva a que me den crisis. Sí, estoy pintando y haciendo cosas lindas para lograrlo. Un proyecto de yoga para fibromialgia, di clases por más de 10 años, y sé que puedo empezar, pero no empiezo. Siento que no avanzo, me desespero y me disperso. Gracias. Hari Bol.</p>',NULL,NULL,NULL,NULL,'2025-11-24 02:30:25','2026-08-02 16:49:18'),(57,15,50,'<p>Quiero,limpiar mi mente, cuerpo alma,para poder recibir con más firmeza la bendición de krishna, igual q recibir más información sobre el ,me emociona <br>\nHoy llevo dos años sin beber, e dado el paso de no consumir res, mi trabajo me exige resistencia física, por eso no puedo dejarlo de golpe pero ay voy. <br>\nQuiero ser mejor persona, (más empatia)hacia mis semejantes, hoy se que krishna vive en todos los seres vivos, más tolerancia ser más positivo y racional.<br>\nQuiero encontrar la mejor versión de mi<br>\nTener el valor y la consciencia que arjuna recibió, <br>\nGracias por tanto <br>\nMis máximas reverencias <br>\nAgradezco por toda la sabiduría, trascendental, que emana de los pies de loto. <br>\nHaré krishna <br>\n @</p>\n\n<p>-</p>',NULL,NULL,NULL,NULL,'2025-11-24 02:45:42','2026-08-02 16:49:18'),(58,15,49,'<p>Hoy me di cuenta de que navego sin dirección o acostumbro a perder rumbo fácilmente.</p>',NULL,NULL,NULL,NULL,'2025-11-26 08:38:14','2026-08-02 16:49:18'),(59,16,45,'<p>Yo igual. je!!! por eso me hace falta organizarme. Pero es que también luego me da insomnio y me clavo en cosas.</p>',NULL,NULL,NULL,NULL,'2025-11-24 03:14:57','2026-08-02 16:49:18'),(60,17,49,'<p>Lo verdaderamente posible hoy es que quiero trabajar en evitar distractores como lo son redes sociales.</p>',NULL,NULL,NULL,NULL,'2025-11-26 08:29:00','2026-08-02 16:49:18'),(61,18,50,'<p><strong>@Otakur</strong> mañana, quiero moverme desde el respeto ala vida</p>',NULL,NULL,NULL,NULL,'2025-11-24 06:49:03','2026-08-02 16:49:18'),(62,18,38,'<p>Mañana quiero moverme desde la presencia.</p>',NULL,NULL,NULL,NULL,'2025-11-24 07:14:08','2026-08-02 16:49:18'),(63,18,47,'<p>Mañana quiero moverme desde el amor</p>',NULL,NULL,NULL,NULL,'2025-11-24 07:49:00','2026-08-02 16:49:18'),(64,18,43,'<p>Mañana quiero moverme desde la coherencia</p>',NULL,NULL,NULL,NULL,'2025-11-24 10:07:11','2026-08-02 16:49:18'),(65,18,45,'<p>Mañana quiero moverme desde la paciencia.</p>',NULL,NULL,NULL,NULL,'2025-11-26 19:51:59','2026-08-02 16:49:18'),(66,18,34,'<p>Mañana quiero moverme desde la conciencia.</p>',NULL,NULL,NULL,NULL,'2025-11-29 20:14:23','2026-08-02 16:49:18'),(67,19,34,'<p>&quot;Hoy descubrí que no solo soy este cuerpo y mente, también soy un alma eterna&quot;</p>',NULL,NULL,NULL,NULL,'2025-11-25 07:58:29','2026-08-02 16:49:18'),(68,19,38,'<p>Hoy descubrí que en mi práctica soy alguien capaz de aprender , rectificar y seguir avanzando.<br>\nEn mis emociones soy un ser que merece descanso claridad y cuidado.<br>\nY en lo místico.<br>\nSoy un alma eterna experimentando una vida temporal en este avatar, conectada con Krishna.<br>\nY quien soy más allá de este rol y de este momento.  <br>\nSoy amor.</p>',NULL,NULL,NULL,NULL,'2025-11-26 01:42:29','2026-08-02 16:49:18'),(69,19,47,'<p>Hoy recordé que también Soy un Alma Eterna recordando el amor de Krishna y el camino de regreso a el</p>',NULL,NULL,NULL,NULL,'2025-11-26 08:55:48','2026-08-02 16:49:18'),(70,19,45,'<p>Recuerdo que soy un ser de luz eterno y que en éste  mundo nada me pertenece.</p>',NULL,NULL,NULL,NULL,'2025-11-26 19:53:56','2026-08-02 16:49:18'),(71,19,50,'<p>Soy un ser de luz,trascendental</p>',NULL,NULL,NULL,NULL,'2025-11-27 04:53:15','2026-08-02 16:49:18'),(72,19,43,'<p>Hoy recordé que soy un ser eterno viviendo una experiencia temporal.</p>',NULL,NULL,NULL,NULL,'2025-11-27 06:20:00','2026-08-02 16:49:18'),(73,20,34,'<p>Escenario<br>\nMis familiares me invitaron a celebrar el cumpleaños de un integrante de la familia cuando no acostumbran a dirigirme la palabra o a ser distantes conmigo<br>\nLo que realmente está pasando es… que actué desde el temor al juicio y a sentirme insuficiente ante ellos<br>\nLo que esto me invita a cultivar es… la confianza en mi misma y la comunicación efectiva con los demás<br>\nUn paso pequeño posible es… disculparme por mi comportamiento hostil y agradecer la invitación que en su momento rechace</p>',NULL,NULL,NULL,NULL,'2025-12-08 21:22:39','2026-08-02 16:49:18'),(74,20,38,'<p>Lo que realmente estaba pasando era ver las cosas desde mis juicios.<br>\nLo que esto me invito a cultivar fue ver las cosas con conciencia y fuera del juicio.<br>\nEl paso pequeño posible que elegí fue hacer auto análisis con honestidad.</p>',NULL,NULL,NULL,NULL,'2025-12-14 08:52:39','2026-08-02 16:49:18'),(75,21,38,'<p>Última actividad: pospuse hacer ejercicio <br>\nFuego: inacción <br>\nFruto:orgullo con reserva,  me dije al rato hago ejercicio.<br>\nMi ofrenda: ofrezco este límite tal como apareció reconociendo que no soy el único factor participante.</p>',NULL,NULL,NULL,NULL,'2025-12-14 09:01:21','2026-08-02 16:49:18'),(76,22,38,'<p>Mi señal de cierre más frecuente hasta ahora es: responder con impulso.<br>\nUsando mi doble llave de la mente enfocada descubrí que: no es bueno responder impulsibamente.<br>\nMi regla si-entonces es- si observo que hoy a responder con impulso, entonces haré 4-4, verificare mi foco y descartare  o controlare con conciencia durante 90s.</p>',NULL,NULL,NULL,NULL,'2025-12-14 09:11:05','2026-08-02 16:49:18'),(77,23,38,'<p>Hecho que elegí:escuchar , analizar antes de responder en una conversación u acción. <br>\nLo que hoy entrego : responder con conciencia.<br>\nMi.micro -acción  de claridad: no responder der desde mi juicio y enviar una versión simple de 90s.<br>\nMi regla si- entonses: escucho con atención,  respeto,  doy un paso a lo que si depende de mi. <br>\nSeñal de apertura: responder con claridad.</p>',NULL,NULL,NULL,NULL,'2025-12-14 09:23:15','2026-08-02 16:49:18'),(78,31,34,'<p>Inicio de Reto Arjuna - Quiero sostener la acción en todos los ámbitos (espiritual, laboral, personal, deber...)</p>',NULL,NULL,NULL,NULL,'2026-03-29 07:03:25','2026-08-02 16:49:18'),(79,36,63,'<p>En cuanto al control, no es válido cuando queremos controlar algo que pude tener un riesgo , como cuando nuestros hijos (adolecentes andan en la calle) y quieres llevarlo para saber dónde está o que haga bien, o que llegue temprano?<br>\nO cuando queremos controlar un mejor orden, ya sea en casa con , limpieza, o incluso comportamientos y hábitos que nos llevan tener relaciones en casa separatistas o hasta de conflicto? Pero cuando si es en verdad mejor tus ideas o formas, porque generarían un mejor orden. <br>\nNo es que alguien debe tener un control en un circulo específico, casa, trabajo o algún otro<br>\n?</p>',NULL,NULL,NULL,NULL,'2026-04-12 22:49:42','2026-08-02 16:49:18'),(80,36,62,'<p><strong>@San</strong></p> \n\n<p>Gracias por dejar tu reflexión y duda.</p>\n\n<p>Es totalmente válido querer orden, cuidado y dirección.</p>\n\n<p>Eso no es el problema.</p>\n\n<p>El punto no es que “dejes de intentar controlar todo”, sino que controles solo lo que te corresponde.</p>\n\n<p>Por ejemplo:</p>\n\n<p>✔ Sí te corresponde:<br>\n– poner reglas<br>\n– dar dirección<br>\n– hablar, corregir, acompañar<br>\n– tomar decisiones en tu rol (mamá, responsable de casa, etc.)</p>\n\n<p>❌ No te corresponde:<br>\n– asegurar el resultado exacto<br>\n– garantizar cómo va a actuar el otro<br>\n– evitar cualquier riesgo completamente</p>\n\n<p>Cuando intentas controlar eso… aparece tensión, conflicto o desgaste.</p>\n\n<p>Entonces, no se trata de soltar tu responsabilidad.</p>\n\n<p>Se trata de hacer tu parte con claridad… sin intentar cargar lo que no depende de ti.</p>',NULL,NULL,NULL,NULL,'2026-04-14 19:44:53','2026-08-02 16:49:18'),(81,37,63,'<p>A cerca de hacer buscando un resultado. Pero cuando estamos realizando algo, algún proyecto, invertimos suponiendo un cambio, es decir sabiendo que tiene que resultar algo, no una inversión monetaria justamente, sino inversión por ejemplo de autoconfianza. O entonces puede ser que solo no se espere a que salga exactamente así? Que el resultado ya está arreglado por el creador sea no tan aceptable por nosotros?<br>\nDe alguna forma está bien buscar resultados? O es mejor solo dejarlo o dirigirlo al creador ?</p>',NULL,NULL,NULL,NULL,'2026-04-12 22:52:42','2026-08-02 16:49:18'),(82,37,62,'<p><strong>@San</strong> <br>\nGracias por tu pregunta Sandra</p> \n\n<p>Esta es una confusión común.</p>\n\n<p>En realidad aquí hay dos cerraduras distintas que vale la pena separar:</p>\n\n<p>👉 Antes de actuar: La cerradura de Intención Más Clara<br>\n👉 Después de actuar: La cerradura de Ofrenda Cotidiana</p>\n\n<p><strong>Primero: Intención Más Clara</strong></p>\n\n<p>No está mal tener un resultado en mente.<br>\nEso es esencial para orientar la acción.</p>\n\n<p>Pero no es lo mismo actuar “para que salga bien”, &quot;para estar tranquilo&quot;, &quot;para sentirme más seguro&quot; etc, que actuar con un para-qué que puedes sostener aunque el resultado cambie.</p>\n\n<p>Lo importante es desde dónde estás actuando.</p>\n\n<p>Ejemplos más estables:<br>\n– “para hacer mi parte con claridad”<br>\n– “para cuidar la relación”<br>\n– “para responder adecuadamente a la situación”</p>\n\n<p>Eso te da dirección sin volverte dependiente del resultado.</p>\n\n<p><strong>Ahora con Ofrenda Cotidiana</strong></p>\n\n<p>Aquí ya no se corrige el para-qué.</p>\n\n<p>Se corrige otra cosa:</p>\n\n<p>👉 la relación que aparece con el resultado después de actuar</p>\n\n<p>No se trata tanto del resultado en sí, sino de cómo te lo empiezas a cargar.</p>\n\n<p>Por ejemplo, cuando aparece una relación infundada o injustificada como:</p>\n\n<p>– “esto salió bien gracias a mí”<br>\n– “esto salió mal y es totalmente mi culpa”<br>\n– “esto no tiene nada que ver conmigo”</p>\n\n<p>Lo que se entrena es reconocer esa relación y no quedarte atrapada en orgullo o culpa falsos.</p>\n\n<p>Una forma simple de verlo:</p>\n\n<p>👉 Sí para-qué sustentable (Intención Más Clara)<br>\n👉 No apropiación indebida del resultado (Ofrenda Cotidiana)</p>',NULL,NULL,NULL,NULL,'2026-04-14 19:40:47','2026-08-02 16:49:18'),(83,39,44,'<p><strong>Dia 1 - Línea de meta - Mente regulada</strong> 🏁</p>\n\n<p>Hoy me sentí mas consciente de lo que pasa en mi cabeza. <br>\nEn camino al trabajo se me vino a la mente hacer ejercicio y enseguida lo anoté en mi agenda para empezar a organizarme y comenzar el lunes 🙏🏻🤞🏻<br>\nCuando venían pensamientos feos o que me movían las emociones los dejaba pasar como hojas 🍃</p> \n\n<p>Muchas gracias.  Bonita noche ✨️</p>',NULL,NULL,NULL,NULL,'2026-04-08 22:33:23','2026-08-02 16:49:18'),(84,39,57,'<p><strong>@Srivas</strong> si</p>\n\n<p><strong>@Srivas</strong> ¿Lograste tomar mejor algún pensamiento y convertirlo en una decisión o acción clara para sostener lo que te importa? (sí/no y qué pasó)<br>\nSi, tómela decisión de realizar el ejercicio al levantarme (no tan temprano) sin generar ansiedad por no hacerlo tan temprano…</p>',NULL,NULL,NULL,NULL,'2026-04-11 12:23:49','2026-08-02 16:49:18'),(85,39,62,'<p><strong>@Luneta</strong></p> \n\n<p>Luna🙂</p>\n\n<p>Se nota que pudiste observar mejor lo que aparecía en tu mente, sin irte automáticamente con todo.<br>\nY además hubo algo muy valioso:<br>\ncuando apareció la idea de hacer ejercicio, la bajaste a algo concreto al anotarlo en tu agenda.<br>\nJusto eso es parte de lo que Krishna le enseña a Arjuna: no quedarse arrastrado por todo lo que aparece, sino recuperar claridad para actuar.</p>\n\n<p>Eso es abrir la cerradura de la Mente Regulada.<br>\nGracias por compartir tu línea de meta ✨</p>',NULL,NULL,NULL,NULL,'2026-04-12 22:50:33','2026-08-02 16:49:18'),(86,39,62,'<p><strong>@Mayela</strong> <br>\nMayela 🙂<br>\nGracias por compartirlo.<br>\nSe ve claro: ajustaste la idea para poder sostenerla sin ansiedad, y aun así actuaste.<br>\nEso es muy valioso.<br>\nAhí está justo lo que Krishna le enseña a Arjuna: no forzarse a lo perfecto, sino sostener lo importante.<br>\nGracias por cruzar esta línea de meta ✨</p>',NULL,NULL,NULL,NULL,'2026-04-12 22:58:13','2026-08-02 16:49:18'),(87,40,44,'<p><strong>Día 3 - Línea de meta - Intención más clara</strong> 🏁</p>\n\n<p>Aclarar un malentendido ➡️ para mejorar la comunicación ➡️ para ir dejando de reaccionar impulsivamente, vivir más en paz y armonía ➡️ preguntarle si tenía tiempo de platicar y expresar mis inquietudes e incomodidades. 🏁</p>',NULL,NULL,NULL,NULL,'2026-04-08 22:29:42','2026-08-02 16:49:18'),(88,40,57,'<p><strong>@Srivas</strong> ¿Lograste elevar algún para-qué hoy y convertirlo en una decisión o acción que te ayudara a sostener lo que te importa? (sí/no y qué pasó)</p>\n\n<p>Si <br>\nEl para que hacerlo es para lograr el hábito en esta semana aunque sea tarde pero sobre todo dar inicio a la rutina de los ejercicios de reto Hanuman, y la práctica de yoga como parte de mi rutina diaria…</p>',NULL,NULL,NULL,NULL,'2026-04-11 12:31:55','2026-08-02 16:49:18'),(89,41,44,'<p><strong>Dia 2 - Línea de meta - Visión Abierta</strong> 🏁</p>\n\n<p>Porque hoy tuve una fiesta infantil y quedé de pasar por mi mejor amiga a las 11:50 y a las 11:47 me mandó mensaje de que se iba a desayunar con su esposo y me empecé a molestar y a enjuiciar,  pero me acordé del reto y primero respiré profundo,  después detecté el hecho &quot;Ya era tarde&quot; después me di cuenta que empecé a hacer juicio hacia mi amiguis. Después me pregunté que botón me estaba reactivando: el del control y que las cosas no salieron como yo hubiera querido y al final dije: <em>&quot;Bueno acepto que las cosas salieron de esta manera, debe haber una razón por la cual ella despertó tarde y quiero cambiar mi forma de ser y de vivir la vida&quot;</em><br>\nY pues resulta que anoche salió de fiesta y estaba desvelada por eso se paró tarde, o sea que no fue personal para nada, jaja. Y de todos modos todo se acomodó a como tocaba ser y estuvo bien. 😄<br>\nGracias reto Arjuna 🫶🏻🙏🏻</p>',NULL,NULL,NULL,NULL,'2026-04-08 22:30:39','2026-08-02 16:49:18'),(90,41,57,'<p><strong>@Srivas</strong></p> \n\n<p><strong>@Srivas</strong> ¿Lograste notar algún juicio hoy, separar lo que pasó de lo que apareció en ti, y reconstruir un juicio que te ayudara a sostener lo que te importa? (sí/no y qué pasó)</p>\n\n<p>Si<br>\nEl no levantarme tan temprano hacer el ejercicio mi juicio empezaba a sentir culpa por no hacerlo, pero al tomar la decisión de hacerlo más tarde logré hacer mi rutina completa sin que me genere culpabilidad por hacerlo más tarde….</p>',NULL,NULL,NULL,NULL,'2026-04-11 12:28:24','2026-08-02 16:49:18'),(91,43,44,'<p><strong>Dia 4 - Linea de meta de Confiar y Soltar</strong> 🏁</p>\n\n<p>Sí logré identificar varios intentos de control desde en la mañana con las ventas de mis productos, así como la fila de las cajas al querer pagar, el tráfico, la forma de manejar de la gente, el tiempo, las acciones y  reacciones de los demás. Si logré soltarlo aunque en la venta de en la noche me costó mas trabajo soltar, me estaba empezando a sentir enojada y frustrada por la falta de clientes, pero empecé a gestionarlo a través de la llave y la brújula.  Al final si lo solté, de repente ya estaba bien tranquila. <br>\nGracias 🏹📿</p>',NULL,NULL,NULL,NULL,'2026-04-08 22:28:22','2026-08-02 16:49:18'),(92,43,57,'<p><strong>@Srivas</strong> ¿Lograste identificar y soltar algún intento de control que no te correspondía y que estaba afectando tu capacidad de actuar? (sí/no y qué pasó)</p>\n\n<p>Si…<br>\nIdentifique el querer controlar para levantarme tan temprano en periodo de vacaciones, queriendo descansar, lo que me provocaba resistirme a hacerlo tan temprano por lo que aceptar que lo puedo hacer más tarde el ejercicio me permitió que fluyera la rutina sin generar ansiedad por el tiempo…</p>',NULL,NULL,NULL,NULL,'2026-04-11 12:37:29','2026-08-02 16:49:18'),(93,44,44,'<p><strong>Dia 5 - Línea de meta — Ofrenda Cotidiana 🏁</strong></p>\n\n<p>Al hacer un trabajo interno profundo me permitió ver la parte mas oscura de mí y eso me generó algunas culpas por el daño que pude haber causado a personas que me quisieron y me quieren mucho. <br>\nEntonces mi pasado ya no me define porque he trabajado y lo hago cada día pare ser mejor persona</p>\n\n<p>Y también ofrendé el orgullo en el tema de las ventas cuando me va bien y la culpa cuando me va no tan bien. <br>\n🙏🏻📿</p>',NULL,NULL,NULL,NULL,'2026-04-08 22:27:08','2026-08-02 16:49:18'),(94,44,57,'<p><strong>@Srivas</strong> ¿Lograste soltar la relación de orgullo o culpa que estabas cargando? (sí/no y qué pasó después)</p>\n\n<p>Si…<br>\nSolté la culpa de no levantarme temprano para hacer el ejercicio, una vez que acepté que lo iba hacer tarde y dejar que todo fluyera ofreciendo al Ser supremo logré hacer la rutina más tarde y con ligereza…</p>',NULL,NULL,NULL,NULL,'2026-04-11 12:40:14','2026-08-02 16:49:18'),(95,45,44,'<p><strong>Dia 6 - Línea de meta — Identidad Estable 🏁</strong></p>\n\n<p>En la tarde tuve una reunión con varias amigas y sentí envidia de una de ellas porque le llevaron unos regalitos.  Eso de la envidia ya lo he estado trabajando pero hoy lo detecté, lo sentí y pensé: Yo No Soy envidiosa. La envidia de ninguna manera me define. Entonces de repente me encontré sintiéndome diferente. <br>\nElijo dejar de actuar desde una versión temporal de mí. 🙏🏻📿</p>',NULL,NULL,NULL,NULL,'2026-04-08 22:25:59','2026-08-02 16:49:18'),(96,45,57,'<p><strong>@Srivas</strong> ¿Lograste no reducirte a lo que estaba pasando y actuar desde un lugar más estable? (sí/no y qué pasó)</p>\n\n<p>Si…<br>\nEl no levantarme temprano me genera ansiedad porque pensaba que el tiempo no me iba alcanzar para hacerlo pero al aceptar que solo sería más tarde y si iba a realizar la rutina, me di la oportunidad de fluir al momento de iniciar y dejar que el tiempo pasara hasta concluirla…</p>',NULL,NULL,NULL,NULL,'2026-04-11 12:43:46','2026-08-02 16:49:18'),(97,46,44,'<p><strong>Dia 7 - Línea de meta — Visión profunda 🏁</strong></p>\n\n<p>Anoche cuando llegué de la calle, me encontré que mi perro se había agarrado un gato callejero qué se metió al patio y pues lo desvivió. Me sentí muy triste,  en shock, tuve que limpiar todo y pues levantar al gatito.  Me sentía muy decepcionada. Y después ya para dormir se me ocurre meterme un hisopo al oído y se me quedó adentro el algodón.  Solo quedo el puro palito, fui a casa de una amiga y no pudo sacarlo. Me acompañaron al hospital porque el algodón estaba ya dentro del canal. Afortunadamente con un lavado salió pero hoy amanecí con esa sensación de un mundo gris, dormí toda la mañana y me pregunté muchas veces ¿por qué pasó lo del perro? ¿Por qué me metí el cotonete, jaja? <br>\nPero después de esta sesión me pregunté ¿Y si confío en que hay un orden divino que busca mi crecimiento? ¿Y si todo eso que pasó anoche es un regalo?<br>\nEntonces entendí que lo que había pasado no definia mi realidad. Que no era bueno ni malo, simplemente fue. <br>\nEl gato se metió.  El perro y su instinto. Y yo no debí meterme el hisopo tan profundo jaja.<br>\nGracias 🙏🏻📿</p>',NULL,NULL,NULL,NULL,'2026-04-08 22:25:10','2026-08-02 16:49:18'),(98,46,57,'<p><strong>@Srivas</strong> ¿Lograste notar algún momento donde estabas tomando lo que pasaba como suficiente para juzgar la realidad? (sí/no y qué cambió)</p>\n\n<p>Si…<br>\nDeje de presionarme para levantarme temprano, y cuando me despertaba me levantaba hacer mi rutina, solo considerando que no importaba el momento sino que esta acción busca mi crecimiento, sin importar el momento sino solamente lograr hacerlo, lo que permitió fluir…</p>',NULL,NULL,NULL,NULL,'2026-04-11 12:50:05','2026-08-02 16:49:18'),(99,47,44,'<p><strong>Dia 8 - Línea de meta - Bhakti — Amor en acción</strong> 🏁</p>\n\n<p>Tengo un puesto de pan casero y postres en las noches, hoy en especial cuando me empezaba a afanar porque había muy poca gente en la calle entonces le ofrecí a Krishna todo ese afán de querer tener el control de las ventas y de la gente cantando unos Maha Mantra para volver a  tomar las riendas de la mente.  Y enseguida hacía consciencia de soltar, fluir y confiar.. 📿🫶🏻</p>',NULL,NULL,NULL,NULL,'2026-04-08 22:24:14','2026-08-02 16:49:18'),(100,47,57,'<p><strong>@Srivas</strong> ¿Lograste ofrecer alguna acción quedando libre del condicionamiento del resultado? (sí/no y qué pasó)</p>\n\n<p>Si…<br>\nOfreciendo al Ser supremo el levantarme al despertarme para llevar a cabo mi rutina de ejercicios dejándome fluir…</p>',NULL,NULL,NULL,NULL,'2026-04-11 12:56:21','2026-08-02 16:49:18');
/*!40000 ALTER TABLE `foro_respuestas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `foro_respuestas_historial`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `foro_respuestas_historial` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `respuesta_id` int(10) unsigned NOT NULL,
  `contenido_anterior` mediumtext NOT NULL,
  `editado_por` int(10) unsigned NOT NULL,
  `editado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_foro_respuestas_historial_respuesta` (`respuesta_id`),
  KEY `fk_foro_respuestas_historial_usuario` (`editado_por`),
  CONSTRAINT `fk_foro_respuestas_historial_respuesta` FOREIGN KEY (`respuesta_id`) REFERENCES `foro_respuestas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_respuestas_historial_usuario` FOREIGN KEY (`editado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `foro_respuestas_historial`
--

LOCK TABLES `foro_respuestas_historial` WRITE;
/*!40000 ALTER TABLE `foro_respuestas_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `foro_respuestas_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `foro_tema_etiquetas`
--
-- Relación muchos-a-muchos tema<->etiqueta: reemplaza la antigua columna única
-- `foro_temas.categoria_id`. Sin datos semilla propios a propósito — se puebla
-- por la corrección de migración que vive en la sección de `foro_temas` más
-- abajo (copia `categoria_id` de cada tema existente antes de borrar esa
-- columna), así funciona igual para una base nueva que para una que ya tenía
-- temas con categoría asignada.
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `foro_tema_etiquetas` (
  `tema_id` int(10) unsigned NOT NULL,
  `categoria_id` int(10) unsigned NOT NULL,
  `creado_por` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tema_id`,`categoria_id`),
  KEY `idx_foro_tema_etiquetas_categoria` (`categoria_id`),
  KEY `fk_foro_tema_etiquetas_usuario` (`creado_por`),
  CONSTRAINT `fk_foro_tema_etiquetas_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `foro_categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_tema_etiquetas_tema` FOREIGN KEY (`tema_id`) REFERENCES `foro_temas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_tema_etiquetas_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `foro_tema_etiquetas`
--

LOCK TABLES `foro_tema_etiquetas` WRITE;
/*!40000 ALTER TABLE `foro_tema_etiquetas` DISABLE KEYS */;
/*!40000 ALTER TABLE `foro_tema_etiquetas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `foro_temas`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `foro_temas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `curso_id` int(10) unsigned DEFAULT NULL,
  `evento_id` int(10) unsigned DEFAULT NULL,
  `leccion_id` int(10) unsigned DEFAULT NULL,
  `visibilidad` enum('publico','privado','compartido') NOT NULL DEFAULT 'publico',
  `compartido_con_usuario_id` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `contenido` mediumtext NOT NULL,
  `editado_en` datetime DEFAULT NULL,
  `editado_por` int(10) unsigned DEFAULT NULL,
  `fijado` tinyint(1) NOT NULL DEFAULT 0,
  `cerrado` tinyint(1) NOT NULL DEFAULT 0,
  `respuestas_count` int(10) unsigned NOT NULL DEFAULT 0,
  `vistas` int(10) unsigned NOT NULL DEFAULT 0,
  `ultima_respuesta_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_foro_temas_slug` (`slug`),
  KEY `fk_foro_temas_usuario` (`usuario_id`),
  KEY `idx_foro_temas_curso` (`curso_id`),
  KEY `idx_foro_temas_leccion` (`leccion_id`),
  KEY `fk_foro_temas_editado_por` (`editado_por`),
  KEY `idx_foro_temas_evento` (`evento_id`),
  KEY `fk_foro_temas_compartido_con` (`compartido_con_usuario_id`),
  KEY `idx_foro_temas_visibilidad` (`visibilidad`),
  FULLTEXT KEY `ft_foro_temas` (`titulo`,`contenido`),
  CONSTRAINT `fk_foro_temas_compartido_con` FOREIGN KEY (`compartido_con_usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foro_temas_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foro_temas_editado_por` FOREIGN KEY (`editado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foro_temas_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foro_temas_leccion` FOREIGN KEY (`leccion_id`) REFERENCES `lecciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_foro_temas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--

ALTER TABLE foro_temas
  ADD COLUMN IF NOT EXISTS editado_en DATETIME NULL AFTER contenido,
  ADD COLUMN IF NOT EXISTS editado_por INT UNSIGNED NULL AFTER editado_en;

ALTER TABLE foro_temas DROP FOREIGN KEY IF EXISTS fk_foro_temas_editado_por;
ALTER TABLE foro_temas ADD CONSTRAINT fk_foro_temas_editado_por FOREIGN KEY (editado_por) REFERENCES usuarios_perfil (id) ON DELETE SET NULL;

ALTER TABLE foro_temas
  ADD COLUMN IF NOT EXISTS evento_id INT UNSIGNED NULL AFTER curso_id,
  ADD COLUMN IF NOT EXISTS visibilidad ENUM('publico','privado','compartido') NOT NULL DEFAULT 'publico' AFTER leccion_id,
  ADD COLUMN IF NOT EXISTS compartido_con_usuario_id INT UNSIGNED NULL AFTER visibilidad;

ALTER TABLE foro_temas DROP FOREIGN KEY IF EXISTS fk_foro_temas_evento;
ALTER TABLE foro_temas ADD CONSTRAINT fk_foro_temas_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE SET NULL;
ALTER TABLE foro_temas ADD KEY IF NOT EXISTS idx_foro_temas_evento (evento_id);

ALTER TABLE foro_temas DROP FOREIGN KEY IF EXISTS fk_foro_temas_compartido_con;
ALTER TABLE foro_temas ADD CONSTRAINT fk_foro_temas_compartido_con FOREIGN KEY (compartido_con_usuario_id) REFERENCES usuarios_perfil (id) ON DELETE SET NULL;
ALTER TABLE foro_temas ADD KEY IF NOT EXISTS idx_foro_temas_visibilidad (visibilidad);

-- `categoria_id` (única, obligatoria por tema) pasó a ser una relación
-- muchos-a-muchos vía `foro_tema_etiquetas` (ver más abajo) — cada tema
-- puede llevar 0 o más etiquetas en vez de exactamente una categoría. Esta
-- corrección copia primero cada categoria_id existente a la tabla nueva
-- (INSERT IGNORE — no hace nada si ya se corrió antes) y solo después quita
-- la columna vieja, para no perder datos en una producción que todavía no
-- se había actualizado. El SELECT va detrás de una comprobación en
-- information_schema porque en una base nueva (creada ya con este mismo
-- archivo) `categoria_id` nunca llega a existir, y referenciar una columna
-- inexistente rompe la importación completa.
SET @foro_temas_tiene_categoria_id = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'foro_temas' AND column_name = 'categoria_id'
);
SET @foro_migrar_categoria_id_sql = IF(
  @foro_temas_tiene_categoria_id > 0,
  'INSERT IGNORE INTO foro_tema_etiquetas (tema_id, categoria_id, creado_por, created_at) SELECT id, categoria_id, usuario_id, created_at FROM foro_temas WHERE categoria_id IS NOT NULL',
  'SELECT 1'
);
PREPARE foro_migrar_categoria_id_stmt FROM @foro_migrar_categoria_id_sql;
EXECUTE foro_migrar_categoria_id_stmt;
DEALLOCATE PREPARE foro_migrar_categoria_id_stmt;

ALTER TABLE foro_temas DROP FOREIGN KEY IF EXISTS fk_foro_temas_categoria;
ALTER TABLE foro_temas DROP COLUMN IF EXISTS categoria_id;

-- "Categoría libre" (2026-08-31) — tercera taxonomía, aparte de curso/evento
-- y de las etiquetas (foro_categorias): cualquier usuario elige una existente
-- o escribe una nueva al publicar, SIN aprobación de admin (a diferencia de
-- las etiquetas). Una sola por tema (no es N:M como las etiquetas). La FK a
-- foro_categorias_libres se agrega más abajo, una vez que esa tabla ya existe
-- (mismo criterio que pagos/promociones).
ALTER TABLE foro_temas
  ADD COLUMN IF NOT EXISTS categoria_libre_id INT UNSIGNED NULL AFTER evento_id;

-- "Ocultar" un tema (2026-08-31) — a diferencia de "cerrado" (ya no acepta
-- respuestas, pero se sigue viendo) esto lo quita del foro por completo,
-- solo un admin lo ve. DEFAULT 1 A PROPÓSITO, aunque el objetivo final es
-- que un tema nuevo se publique visible: MySQL usa el DEFAULT de la columna
-- para rellenar las filas YA EXISTENTES al agregarla (así es como se cumple
-- el pedido "por ahora oculta todas las que ya hay" en una base de
-- producción real, que no vuelve a correr el INSERT de abajo — a diferencia
-- de una base nueva creada con este archivo, done el INSERT sí trae su
-- propio valor explícito para cada fila). crear_tema.php inserta oculto=0
-- explícitamente en cada tema nuevo, así que el DEFAULT de la columna nunca
-- le aplica a partir de aquí en adelante.
ALTER TABLE foro_temas
  ADD COLUMN IF NOT EXISTS oculto TINYINT(1) NOT NULL DEFAULT 1 AFTER cerrado;

-- Dumping data for table `foro_temas`
--

LOCK TABLES `foro_temas` WRITE;
/*!40000 ALTER TABLE `foro_temas` DISABLE KEYS */;
INSERT  IGNORE INTO `foro_temas` VALUES (1,NULL,NULL,NULL,NULL,'publico',NULL,23,'Bienvenido/a al Foro del Reto Arjuna','bienvenidoa-al-foro-del-reto-arjuna-3','<p>Este foro es tu espacio central durante el reto: aquí compartimos reflexiones, respondemos preguntas y nos acompañamos como comunidad. El grupo de WhatsApp servirá solo para enviar avisos y enlaces directos a las secciones del foro.</p>\n\n<h5>Cómo usar este foro.</h5>\n\n<p><strong>Vive el reto paso a paso</strong><br>\nPara cada parte del reto encontrarás un hilo con la pregunta detonadora y el material de apoyo. Dedica unos 20–30 minutos a leer, reflexionar y escribir tu respuesta en el foro.</p>\n\n<p><strong>Sigue el orden de los retos</strong><br>\nEmpieza por el Día 1 y continúa en secuencia. Cada paso está diseñado para guiarte en un proceso progresivo hacia mayor claridad y conexión con tu identidad trascendental.</p>\n\n<p><strong>Publica y participa</strong></p>\n\n<ul><li><p>Comparte tus respuestas personales en el hilo de cada reto.</p></li>\n\n<li><p>Lee lo que otros comparten y apóyalos con un comentario o reacción.</p></li></ul>\n\n<p>Usa las secciones delimitadas por las etiquetas de “Preguntas” para expresar tus dudas y “Experiencias” para contar cómo vives el proceso.</p>\n\n<p><strong>Accede fácilmente al contenido</strong><br>\nCada etiqueta del foro organiza el material:</p>\n\n<ul><li><p>Anuncios: solo el staff publica avisos y calendario.</p></li>\n\n<li><p>Artículos: textos breves de inspiración y filosofía aplicada.</p></li>\n\n<li><p>Preguntas: espacio para preguntas técnicas o conceptuales.</p></li>\n\n<li><p>Experiencias: comparte tu vivencia del reto.</p></li>\n\n<li><p>Curiosidades: inspira y enriquece con aportes ligeros.</p></li></ul>\n\n<p><strong>Repite el ciclo cuando lo necesites</strong><br>\nEl reto es cíclico. Puedes reiniciarlo cuando quieras o profundizar en un día específico que resuene contigo. La idea es que cada ejercicio se vuelva parte de tu vida cotidiana.</p>\n\n<p><strong>Aprovecha la comunidad</strong><br>\nEste foro vive de tu participación. Comparte con sinceridad y respeto. Aquí no buscamos perfección, sino autenticidad y acompañamiento mutuo.</p>',NULL,NULL,1,0,1,6,3,'2025-11-26 22:52:23','2025-09-22 22:36:59'),(2,NULL,NULL,NULL,NULL,'publico',NULL,35,'El mantra Om tat sat','el-mantra-om-tat-sat-5','<p>Hola, mi pregunta es: se recomienda meditar con el mantra Om tat sat? Gracias!</p>',NULL,NULL,0,0,1,2,0,'2025-11-23 23:11:47','2025-10-09 03:09:07'),(3,NULL,NULL,NULL,NULL,'publico',NULL,36,'Como conectar con la compasion genuina cuando \"otro\" te refleja violencia ','como-conectar-con-la-compasion-genuina-cuando-otro-te-refleja-violencia-6','<p>Hola a todos ✨</p>\n\n<p>Yo quiero compartir-me <br>\nResulta que desde que empezamos con el reto arjuna pues el trabajo consciente de ser una mejor versión de mi misma siguiendo las enseñanzas de nuestro maestro Krishna<br>\nPues... no la domino Jajaja</p> \n\n<p>Por ejemplo, el parar y  ser &quot;observador&quot; <br>\nAl principio pasaba alguna situacion  y reaccionaba  = despues observaba que había pasado, analizaba de dónde venía y entendía porque era un suceso intemporal y soltaba</p>\n\n<p>Ahora de forma muy natural, puedo ser observadora, y ver de dónde viene ese cúmulo de emociones perturbadoras del otro y no reaccionar viseralmente y tratar conectar con la compasión genuina</p>  \n\n<p>Pero ahí es el gran <em>PEROOO</em><br>\nAún no se como ? <br>\nComo conectar con la compasión cuando lo que siento es molestia por alguna situación violenta o incómoda por alguna situación de crítica a mi esfuerzo o alguna injusticia... y</p> \n\n<p>Veo que el origen ni siquiera tiene que ver conmigo es lo que carga el otro</p>\n\n<p>El observar y darme cuenta en el aquí y el ahora de forma natural y automática lo veo como una gran acierto y crecimiento personal</p> \n\n<p>Pero siento que me falta tanto para terminar de gestionar en el amor y compasión al otro sin sentir lástima, o ignorarlo o siendo condecendiente, soberbia etc</p>',NULL,NULL,0,0,1,3,0,'2025-11-23 22:06:35','2025-10-16 00:09:47'),(4,NULL,NULL,NULL,NULL,'publico',NULL,23,'¿Qué está susurrando dentro de ti?','que-esta-susurrando-dentro-de-ti-7','<p><img src=\"https://i.postimg.cc/Dy87F1r4/Generated-Image-November-12-2025-11-01PM-1.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\n“A veces no es un grito; es un susurro: ya no puedo seguir igual.”<br>\nNo siempre sabemos qué cambiar, pero algo adentro pide un nuevo comienzo.<br>\nSi estás leyendo esto, quizá ya escuchaste ese llamado.<br>\nEn 2–3 renglones: ¿cuál es el llamado que te trae al Reto Arjuna? Escribe tu susurro.</p>',NULL,NULL,0,0,1,7,0,'2025-11-24 00:54:10','2025-11-13 11:03:27'),(5,NULL,NULL,NULL,NULL,'publico',NULL,23,'El hartazgo como puerta','el-hartazgo-como-puerta-8','<p><img src=\"https://i.postimg.cc/Y09QTZgc/Generated-Image-November-13-2025-8-52AM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\n El hartazgo a veces es la señal de que creciste por dentro y tu vida externa ya no te representa. Hartarse de lo superficial puede ser el inicio de lo profundo. ¿Qué parte de tu vida ya no se siente auténtica?</p>\n\n<p><strong>Completa esta frase: “Voy a dejar ____ y comenzar ____ (por hoy)&quot;</strong></p>',NULL,NULL,0,0,1,4,0,'2025-11-26 22:58:05','2025-11-13 20:13:32'),(6,NULL,NULL,NULL,NULL,'publico',NULL,23,'Detrás del personaje','detras-del-personaje-9','<p><img src=\"https://i.postimg.cc/7ZTQtK0z/Chat-GPT-Image-14-nov-2025-07-08-35-p-m.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"></p>\n<h5>Por fuera todo ok; por dentro, ruido.</h5>\n<p><strong>Arjuna sostenía una máscara de perfección mientras lidiaba con insatisfacción y conflicto interno.<br>\n¿Te ha pasado?</strong><br>\nEn 3 líneas: ¿qué “personaje” sueles mostrar y qué sientes detrás?<br>\nComparte tu “doble visión” (tu personaje / lo que hay detrás).</p>',NULL,NULL,0,0,1,4,0,'2025-11-24 01:45:59','2025-11-15 07:16:17'),(7,NULL,NULL,NULL,NULL,'publico',NULL,23,'La voz amiga','la-voz-amiga-10','<p><img src=\"https://i.postimg.cc/cCJJyhpf/Chat-GPT-Image-15-nov-2025-09-55-18-p-m.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\n<strong>A veces olvidamos quiénes somos y el ruido nos arrastra.</strong><br>\nA Arjuna también le pasó: estuvo a punto de perderse, hasta que Krishna le recordó lo esencial antes de actuar. A veces hace falta una voz amiga.</p>\n\n<p><strong>¿Tienes a alguien que te recuerde lo esencial?</strong></p>\n\n<p>Si sí: ¿quién es y qué te dice?</p>\n\n<p>Si no: ¿quién te habría gustado tener y qué crees que te habría dicho que es lo esencial?</p>\n\n<p><strong>Publica tu respuesta en 1–2 líneas y da 👍 a 2 comentarios que te inspiren.</strong></p>',NULL,NULL,0,0,1,5,1,'2025-11-23 20:47:51','2025-11-16 09:58:45'),(8,NULL,NULL,NULL,NULL,'publico',NULL,34,'mi inicio en el reto Arjuna','mi-inicio-en-el-reto-arjuna-11','<p>Elba</p>',NULL,NULL,0,0,1,5,0,'2025-11-23 20:56:03','2025-11-21 18:47:24'),(9,NULL,NULL,NULL,NULL,'publico',NULL,43,'Mi inicio en el Reto Arjuna ','mi-inicio-en-el-reto-arjuna-12','<p>Esta es mi primera publicación en el foro.<br>\nGracias por esta experiencia.</p>',NULL,NULL,0,0,1,3,0,'2025-11-23 21:04:18','2025-11-21 18:50:25'),(10,NULL,NULL,NULL,NULL,'publico',NULL,23,'Mi Inicio en el Reto Arjuna Noviembre 2025 (Srivas)','mi-inicio-en-el-reto-arjuna-noviembre-2025-srivas-13','<p>Espero obtener una forma de servir, ayudar a millones de personas</p>',NULL,NULL,0,0,1,1,0,'2025-11-26 22:59:25','2025-11-21 19:25:18'),(11,NULL,NULL,NULL,NULL,'publico',NULL,33,'Mi renovación en el reto Arjuna','mi-renovacion-en-el-reto-arjuna-14','<p>Lo que espero obtener con el reto Arjuna es: Mantener en cada acción mi capacidad de introspección y reflexión profunda, y así, ofrecer mi servicio de manera amorosa.</p>',NULL,NULL,0,0,1,1,0,'2025-11-26 23:01:29','2025-11-22 00:14:36'),(12,NULL,NULL,NULL,NULL,'publico',NULL,46,'Entro al Reto Arjuna','entro-al-reto-arjuna-15','<p>Bendecida noche, deseo esten bien, con firmeza en su versión autentica, Divina de ustedes. Gracias a los Maestros por ayudarnos a crecer. Mi respuesta sigue siendo la misma, para conocerme,  conocer más mi Divinidad, conocer más al Supremo y asi, dar entregas a los demás humanos, plantas y animales sintiéndome más completa.</p>',NULL,NULL,0,0,1,1,0,'2025-11-23 21:32:08','2025-11-22 11:41:00'),(13,NULL,NULL,NULL,NULL,'publico',NULL,47,'Mi tercer reto Arjuna ','mi-tercer-reto-arjuna-16','<p>Esta es mi tercer reto Arjuna. Los primeros 2 fueron realmente valiosos para mí. Espero que nuevamente sea una experiencia transformadora</p>',NULL,NULL,0,0,1,1,0,'2025-11-23 21:29:18','2025-11-22 18:02:30'),(14,NULL,NULL,NULL,NULL,'publico',NULL,34,'El espejo, honestidad sin juicios','el-espejo-honestidad-sin-juicios-17','<p>Usando el proceso de apertura con la doble llave, escribe una lista respondiendo a la pregunta<br>\n&quot;¿Qué aspectos de mi forma de vivir, pensar o actuar me alejan de mi mejor versión?&quot;<br>\nDivide tu reflexión en tres áreas:</p>\n<ol><li><p>Forma de vivir<br>\n    la pereza<br>\n    la depresión<br>\n    la desmotivación<br>\n    la desidia<br>\n    mi alimentación</p></li>\n\n<li><p>Forma de pensar<br>\n    el victimismo<br>\n    la carencia<br>\n    la duda<br>\n    el miedo<br>\n    la paranoia<br>\n    la comparación</p></li>\n\n<li><p>Forma de actuar<br>\n    el impulso<br>\n    el enojo<br>\n    la incongruencia<br>\n    la inacción</p></li></ol>\n\n<p>Usando el proceso de apertura con la llave del cuerpo y preguntándote ¿Estoy listo para escuchar<br>\ncon humildad y sin defensa.? como llave de la conciencia Pide a 1–2 personas de confianza que te<br>\nrespondan:</p>\n\n<ol><li><p>¿Qué observas en mi forma de vivir que podría mejorar, cultivar o soltar?<br>\nTienes una visión clara del sentido de la vida y la espiritualidad, pero aun debes trabajar más en soltar todos los obstáculos materiales que te impiden crecer.</p></li>\n\n<li><p>¿Qué observas en mi forma de pensar que podría mejorar, cultivar o soltar?<br>\nTienes mucha visión de las cosas que quieres hacer y cambiar pero te hace falta dar ese gran salto sin miedo al fracaso y de ser así aprender de los errores.</p></li>\n\n<li><p>¿Qué observas en mi forma de actuar que podría mejorar, cultivar o soltar?<br>\nNoto muchos sentimientos encontrados y situaciones que debes aprender a trabajar para no ser aprensiva y dejar que todo fluya, ya que la solución no siempre depende de ti, tienes que afrontarlo y aprender a superar las adeversidades</p></li></ol>\n\n<p>Escribe sin defenderte, usando lenguaje funcional:</p>\n\n<p>Tiendo a dejarme llevar por la pereza, la depresión y la desmotivación, tengo el hábito de descuidar mi alimentación, suelo procrastinar.</p>\n\n<p>Tiendo a sobreprensar de manera carente. con temor, paranoia y suelo tener pensamientos que me hacen sentir y creer que soy victima, también suelo tener pensamientos que me hacen creer que no merezco ningún tipo de bienestar, sobre todo material.</p>\n\n<p>Suelo reaccionar de forma hostil ante situaciones que hacen sentir vulnerable, también suelo reaccionar con enojo, tristeza e incongruencia.</p>\n\n<p>Selecciona el aspecto en el que te quieres enfocar y escríbelos en este espació. <br>\n“Lo verdadero posible hoy es que quiero trabajar en: el desapego y la confianza personal</p>',NULL,NULL,0,0,1,6,0,'2025-12-08 15:21:45','2025-11-23 04:30:25'),(15,NULL,NULL,NULL,NULL,'publico',NULL,23,'🏁 Línea de Meta del Primer Día – Reto Arjuna Noviembre 2025','linea-de-meta-del-primer-dia-reto-arjuna-noviembre-2025-18','<p><img src=\"https://i.postimg.cc/JnJc6Y6x/Generated-Image-November-22-2025-7-17PM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\nHoy trabajamos con la Cerradura de Más Honestidad. No para corregirnos, sino para vernos con más claridad y sin juicio.<br>\n<strong>Cuando termines tu reto del día (a tu ritmo)</strong>, puedes pasar por aquí y dejar una sola verdad que hayas reconocido sobre ti. No tiene que ser profunda, ni extensa. Solo real.</p>\n\n<p>Puedes comenzar con:</p>\n\n<p>“Hoy me di cuenta de que…”<br>\n“Estoy viendo que suelo…”<br>\n“Algo que me cuesta reconocer es…”</p>\n\n<p>Este gesto es voluntario, pero significativo. <strong>Es como marcar una pequeña bandera al final del primer tramo del viaje</strong> 🪷<br>\nGracias por tu presencia y tu honestidad.</p>',NULL,NULL,1,0,1,9,0,'2025-11-26 02:38:14','2025-11-23 07:24:02'),(16,NULL,NULL,NULL,NULL,'publico',NULL,43,'Día 1','dia-1-19','<p>Hoy descubrí que tiendo a desvelarme innecesariamente intentando aprovechar cada momento disponible para aprender lo más posible de los temas que me interesan.<br>\nLo verdaderamente posible hoy es que quiero trabajar en: dormirme diario antes de las 11 PM, hacer ejercicio diario y vivir por debajo de mis ingresos.</p>',NULL,NULL,0,0,1,1,0,'2025-11-23 21:14:57','2025-11-23 07:51:52'),(17,NULL,NULL,NULL,NULL,'publico',NULL,49,'Mi inicio en el Reto Arjuna - VÍKTOR','mi-inicio-en-el-reto-arjuna-viktor-20','<p>Lo más honesto que puedo decir hoy sobre por qué estoy aquí es por reconciliación.</p>',NULL,NULL,0,0,1,1,0,'2025-11-26 02:29:00','2025-11-23 12:49:01'),(18,NULL,NULL,NULL,NULL,'publico',NULL,23,'🏁 Línea de Meta del Segundo Día – Reto Arjuna Noviembre 2025','linea-de-meta-del-segundo-dia-reto-arjuna-noviembre-2025-21','<p><img src=\"https://i.postimg.cc/bNXsP54H/Generated-Image-November-23-2025-5-14PM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\nQuerida comunidad del Reto Arjuna 🤍</p>\n\n<p>Hoy entrenamos la segunda cerradura del sistema:<br>\nIntención Más Clara — la brújula que orienta nuestros pasos hacia lo que sí queremos cultivar.</p>\n\n<p>Para cerrar este día sin carga y con belleza funcional, te dejamos una práctica breve de recierre vespertino (90 segundos).<br>\nEs opcional, pero muchos la sienten transformadora.</p>\n\n<p>🌙 Ritual breve “Brújula Interior” (90 s)</p>\n\n<p>Coloca una mano en el esternón<br>\n(el mismo gesto que usamos esta mañana).</p>\n\n<p>Exhala largo una vez.<br>\nSuave, sin forzar.</p>\n\n<p>Pregunta en silencio:<br>\n“¿Qué motivación quiero recordar mañana al despertar?”</p>\n\n<p>Deja que llegue una palabra.<br>\nNo una frase; solo una palabra.<br>\n(Ejemplos: claridad, cuidado, presencia, orden, servicio, calma, coherencia.)</p>\n\n<p>Registra esa palabra en una línea:<br>\n“Mañana quiero moverme desde: ________.”</p>\n\n<p>Puedes escribirlo aquí en el foro para cerrar el día junto a la comunidad,<br>\no guardarlo sólo para ti como un pequeño hilo de continuidad.</p>',NULL,NULL,0,0,1,6,0,'2025-11-29 14:14:23','2025-11-24 05:18:15'),(19,NULL,NULL,NULL,NULL,'publico',NULL,23,'🏁 Línea de Meta del Tercer Día – Reto Arjuna Noviembre 2025','linea-de-meta-del-tercer-dia-reto-arjuna-noviembre-2025-22','<p><img src=\"https://i.postimg.cc/xdWLBXbt/Generated-Image-November-24-2025-7-39PM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\nQuerida comunidad 💜<br>\nCerramos este día volviendo al Centro.</p>\n\n<p>90 segundos:</p>\n\n<p>Mano al pecho.</p>\n\n<p>Pregunta suave:<br>\n“¿Quién soy más allá de este rol y de este momento?”</p>\n\n<p>Inhala lento y deja que llegue una frase verdadera.</p>\n\n<p>Completa la línea:<br>\n“Hoy recordé que también soy ______.”</p>\n\n<p>Si quieres, compártela aquí 🙏<br>\nQue descanses en tu identidad estable. 🌙</p>',NULL,NULL,0,0,1,6,0,'2025-11-27 00:20:00','2025-11-25 07:42:29'),(20,NULL,NULL,NULL,NULL,'publico',NULL,23,'🏁 Línea de Meta del 4° Reto del RA Online — Visión Profunda Edición Nov25','linea-de-meta-del-4deg-reto-del-ra-online-vision-profunda-edicion-nov25-23','<p><img src=\"https://i.postimg.cc/KzVgSJFC/Generated-Image-December-06-2025-1-15PM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\nArjuna,<br>\nAquí tienes la línea de meta del 4° Reto: <strong>Visión Profunda</strong> — la práctica de ver los hechos con más amplitud y menos lectura reactiva, apoyándote en una identidad más estable.</p>\n\n<h4><strong>📍Te invitamos a compartir las tres líneas de reescritura de alguno de tus ejercicios en este reto.</strong></h4>\n<h5>Ejercicio</h5>\n<p><strong>Elije</strong> una experiencia real —de tu entrenamiento o de una aplicación en vivo— donde reconociste una señal de cierre y aplicaste la Doble Llave.<br>\n<em>(Publica todo en un solo mensaje.)</em><br>\n1️⃣ <strong>Lo que realmente estaba pasando era…</strong><br>\n   <em>(hecho breve, sin interpretación)</em><br>\n2️⃣ <strong>Lo que esto me invitó a cultivar fue…</strong><br>\n   <em>(dirección interna: más claridad, más paciencia, más presencia…)</em><br>\n3️⃣ <strong>El paso pequeño posible que elegí fue…</strong><br>\n   <em>(micro-acción de 90 s que encarnó la nueva visión)</em></p>\n\n<hr>\n\n<h5>Ejemplo breve (formato guía):</h5>\n<p>Lo que realmente estaba pasando era que la respuesta tardó más de lo esperado.<br>\nLo que esto me invitó a cultivar fue más amplitud y menos suposición.<br>\nEl paso pequeño que elegí fue enviar un mensaje claro y amable pidiendo actualización.</p>\n\n<hr>\n\n<h5>Indicaciones finales</h5>\n<ul><li>Mantén cada línea en 1–2 frases.</li>\n<li>No interpretes emociones; describe hechos y dirección.</li>\n<li>Busca una visión un poco más amplia que la inicial, no perfección.</li>\n<li><strong>Puedes publicar en cualquier momento; te acompañamos y orientamos cuando es útil.</strong></li></ul>',NULL,NULL,0,0,1,2,0,'2025-12-14 02:52:39','2025-12-07 02:02:00'),(21,NULL,NULL,NULL,NULL,'publico',NULL,23,'🏁 Línea de Meta del 5° Reto del RA Online — Ofrenda Cotidiana Edición Nov25','linea-de-meta-del-5deg-reto-del-ra-online-ofrenda-cotidiana-edicion-nov25-24','<p><img src=\"https://i.postimg.cc/8zdRzy62/Generated-Image-December-07-2025-4-16AM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\nArjuna,<br>\nAquí cruzas la Línea de Meta del 5º Reto: Ofrendar lo Cotidiano.<br>\nEn este reto aprendiste a convertir una percepción ordinaria en una ofrenda consciente.<br>\nA reconocer lo que aparece y ofrecerlo con honestidad.</p>\n\n<hr>\n\n<h4>📍 <strong>Te invitamos a realizar una vez más el ejercicio para marcar tu paso por este reto</strong></h4>\n\n<p>1️⃣ Identifica tu última actividad</p>\n\n<p>Una intención consumada, una abstinencia lograda, una intención frustrada o un impulso desbordado.</p>\n\n<p>2️⃣ Aplica la secuencia de la Doble Llave de la Ofrenda Cotidiana</p>\n\n<p>🤔 ¿Qué percibo ahora que puedo ofrecer y en dónde?<br>\n→ 🤲 palmas hacia arriba a la altura del torso<br>\n→ 🫁💭 permite que surja qué se ofrece y desde qué fuego<br>\n→ 🗣 expresa la ofrenda en una frase breve<br>\n→ ♻ realiza pequeños círculos con la mano derecha como entregando<br>\n→ 👁️‍🗨️ verifica qué recibes: ¿claridad? · ¿ligereza? · ¿ánimo? · ¿impulso amable?</p>\n\n<p>3️⃣ Recuerda los elementos de la práctica*</p>\n\n<p>Fuegos posibles: Acción · Abstinencia · Inacción · Inercia<br>\nFrutos posibles: Orgullo (con o sin reserva) · Humildad (con o sin reserva)<br>\nPlantilla breve de ofrenda:<br>\n“Ofrezco este/a ______ tal como apareció, reconociendo que no soy el único factor participante.”</p>\n\n<hr>\n\n<h5><strong>Compártelo en los comentarios así:</strong></h5>\n\n<p>• Última actividad: ______<br>\n• Fuego: ______<br>\n• Fruto: ______<br>\n• Mi ofrenda: ______</p>\n\n<hr>\n\n<h5><strong>Ejemplo breve (solo como referencia de formato)</strong></h5>\n\n<p>• Última actividad: pospuse responder un mensaje.<br>\n• Fuego: inacción.<br>\n• Fruto: orgullo con reserva (me dije “luego lo hago”).<br>\n• Mi ofrenda: “Ofrezco este límite tal como apareció, reconociendo que no soy el único factor participante.”</p>\n\n<hr>\n\n<p>La intención es que reconozcas lo que sí estuvo presente y permitas claridad.<br>\nTe leemos. 🙏✨</p>',NULL,NULL,0,0,1,1,0,'2025-12-14 03:01:21','2025-12-07 16:04:28'),(22,NULL,NULL,NULL,NULL,'publico',NULL,23,'🏁 Línea de Meta del 6° Reto del RA Online — Mente Enfocada · Edición Nov25','linea-de-meta-del-6deg-reto-del-ra-online-mente-enfocada-edicion-nov25-26','<p><img src=\"https://i.postimg.cc/SKK6kR28/Generated-Image-December-07-2025-7-13AM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\n<strong>Arjuna,</strong><br>\nAquí cruzas la Línea de Meta del <strong>6º Reto: La Mente Enfocada</strong>.</p>\n\n<p>Superando este Reto accedemos a la posibilidad de usar el instrumento de la mente con más dirección y menos dispersión, evitando el drama y sosteniendo claridad cuando estamos en conciencia.</p>\n\n<hr>\n\n<h4><strong>📍 Te invitamos a compartir una de tus experiencias abriendo la Cerradura de la Mente Enfocada y una de tus reglas si–entonces:</strong></h4>\n\n<h5>Comenta abajo completando la plantilla y siguiendo el ejemplo dado:</h5>\n\n<p>1️⃣“Mi señal de cierre más frecuente hasta ahora es: _________ ”</p>\n\n<p>2️⃣“Usando mi Doble Llave de la Mente Enfocada descubrí que _________ ”<br>\n<em>(Puedes aplicar la Doble Llave ahora para repasar)</em></p>\n\n<p>🤔 ¿Cuál es el foco que quiero ahora? → 🫁⏳ respiración 4×4 para activación y pausa → 💭 permitir que surja el siguiente paso claro → 🗣 expresar el ancla o dirección breve → 👉🔵 dirigir suavemente la atención a un punto fijo → 👁️‍🗨️ verificar: ¿estabilidad? · ¿claridad? · ¿continuidad 90 s?</p>\n\n<p>3️⃣Mi regla si–entonces para continuar el entrenamiento es:<br>\n“Si aparece ________, entonces aplicaré ________ durante 90 s.”</p>\n\n<hr>\n\n<h5>Ejemplo breve (como referencia de formato):</h5>\n\n<p>Mi señal de cierre más frecuente hasta ahora es: <em>saltos de tarea constantes.</em><br>\nUsando mi Doble Llave de la Mente Enfocada descubrí que: <em>un solo paso claro reduce la dispersión.</em><br>\nMi regla si–entonces es: <em>Si observo saltos de tarea constantes, entonces haré 4–4, verificaré mi foco y descartaré o postergaré cualquier tarea que no corresponde al foco durante 90 s.</em></p>\n\n<p>Continuamos caminando juntos.</p>',NULL,NULL,0,0,1,1,0,'2025-12-14 03:11:05','2025-12-07 18:54:39'),(23,NULL,NULL,NULL,NULL,'publico',NULL,23,'🏁 Línea de Meta del 7° Reto del RA Online — Confiar y Soltar · Edición Nov25','linea-de-meta-del-7deg-reto-del-ra-online-confiar-y-soltar-edicion-nov25-27','<p><img src=\"https://i.postimg.cc/7LhpzzYM/Generated-Image-December-07-2025-10-48AM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\n<strong>Arjuna,</strong></p>\n\n<p>Aquí cruzas la <strong>Línea de Meta del 7º Reto: Confiar y Soltar</strong>.<br>\nSuperando este Reto  accedemos a la posibilidad de reconocer lo que no depende de nosotros,<br>\na disminuir la tensión que aparece con el impulso de controlar,<br>\ny a actuar con más firmeza, dirección y claridad.</p>\n\n<hr>\n\n<h4>📍 <strong>Te invitamos a realizar una vez más el ejercicio para marcar tu paso por este reto</strong></h4>\n\n<p><em>(y preparar una regla si–entonces para continuar el entrenamiento)</em></p>\n\n<hr>\n\n<p><strong>1️⃣ Identifica un hecho reciente</strong></p>\n\n<p>Algo donde apareció tensión, urgencia, duda o revisión innecesaria.<br>\nPuede ser una conversación, una expectativa, un pendiente o un impulso de controlar.</p>\n\n<p><strong>2️⃣ Aplica la secuencia de la Doble Llave de Confiar y Soltar</strong></p>\n\n<p>🤛🤜 empuñar suave → 🤔 <em>¿Qué no depende de mí y hoy puedo entregar con confianza?</em> → 🫁💭 permitir que surja una respuesta breve → 🗣 expresar esa respuesta en 3–7 palabras → 🙌 abrir las palmas y soltar → 👁️‍🗨️ verificar: <em>¿alivio? · ¿claridad? · ¿respiración más amplia? · ¿impulso sereno?</em></p>\n\n<p><strong>3️⃣ Elige una micro–acción de 90 segundos</strong></p>\n\n<p>Un paso pequeño y posible que <strong>sí depende de ti</strong> y que exprese la claridad recuperada.</p>\n\n<hr>\n\n<p><strong>4️⃣ Crea tu regla si–entonces para continuar el entrenamiento</strong></p>\n\n<p>“<strong>Si aparece ________, entonces aplicaré la Doble Llave de Confiar y Soltar<br>\ny tomaré una micro–acción de 90 segundos que sí dependa de mí.</strong>”</p>\n\n<p>Esta regla convierte la comprensión de hoy en continuidad práctica.</p>\n\n<hr>\n\n<h4>📌 <strong>Compártelo en los comentarios así:</strong></h4>\n\n<p>• Hecho que elegí: ______<br>\n• Lo que hoy entrego: ______<br>\n• Mi micro-acción de claridad: ______<br>\n• Mi regla si–entonces: ______<br>\n• Señal de apertura observada: ¿alivio? · ¿claridad? · ¿respiración más amplia? · ¿impulso sereno?</p>\n\n<hr>\n\n<h5><strong>Ejemplo breve (solo como referencia de formato)</strong></h5>\n\n<p>• Hecho que elegí: revisar repetidamente un mensaje antes de enviarlo.<br>\n• Lo que hoy entrego: <em>el resultado de la conversación.</em><br>\n• Mi micro-acción de claridad: <em>enviar una versión simple en 90 s.</em><br>\n• Mi regla si–entonces: <em>Si observo revisión innecesaria, empuño y suelto, y doy un paso directo que sí depende de mí.</em><br>\n• Señal de apertura observada: <em>respiración más amplia y un impulso sereno a actuar.</em></p>\n\n<hr>\n\n<p>La intención es que reconozcan lo que sí depende de ti,<br>\nsueltes lo que no,<br>\ny sigas caminando con más claridad y menos peso.<br>\n<strong>Te leemos. 🙏✨</strong></p>',NULL,NULL,0,0,1,1,0,'2025-12-14 03:23:15','2025-12-07 22:28:29'),(24,NULL,NULL,NULL,NULL,'publico',NULL,23,'🏁 Línea de Meta del 8° Reto del RA Online — Amor en acción · Edición Nov25','linea-de-meta-del-8deg-reto-del-ra-online-amor-en-accion-edicion-nov25-28','<p><img src=\"https://i.postimg.cc/pVGbWxQK/Generated-Image-December-07-2025-12-33PM.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"></p>\n\n<p><strong>Arjuna,</strong></p>\n\n<p>Aquí cruzas la <strong>Línea de Meta del 8º Reto: Bhakti — Amor en Acción</strong>.<br>\nSuperar este reto abre la posibilidad de expresar una <strong>disposición amable y relacional</strong>,<br>\nde reducir la auto–referencia que estrecha la percepción,<br>\ny de convertir claridad interna en <strong>un gesto pequeño y concreto hacia alguien real</strong>.</p>\n\n<hr>\n\n<h4>📍 <strong>Te invitamos a realizar una vez más el ejercicio para marcar tu paso por este reto</strong></h4>\n\n<p><em>(y preparar una regla si–entonces para sostener la práctica durante los próximos días)</em></p>\n\n<hr>\n\n<p>1️⃣ Identifica un hecho reciente</p>\n\n<p>Un momento donde apareció alguna señal de cierre de Bhakti:<br>\n• aislamiento por inercia,<br>\n• servicio automático sin presencia,<br>\n• olvido espontáneo de la gratitud,<br>\n• evitar un gesto amable que sí era posible.</p>\n\n<hr>\n\n<p>2️⃣ Aplica la secuencia de la Doble Llave de Bhakti</p>\n\n<p>🤔 <em>¿Con quién pongo amor en acción ahora?</em><br>\n→ ✋❤️ mano al corazón (activación)<br>\n→ 🫁💭 permitir que surja claramente a quién y cómo servir<br>\n→ 🗣 expresar la dirección en 3–7 palabras (“hoy doy X a Y”)<br>\n→ 🤲 gesto de ofrenda / entrega consciente<br>\n→ 👁️‍🗨️ verificar: <em>¿calidez? · ¿dirección? · ¿impulso amable?</em></p>\n\n<hr>\n\n<p>3️⃣ Realiza una micro–acción de 90 segundos</p>\n\n<p>Un gesto simple y real que exprese la disposición abierta:<br>\n• agradecer algo puntual,<br>\n• reconocer un detalle,<br>\n• ofrecer ayuda breve,<br>\n• enviar un mensaje amable,<br>\n• facilitar algo pequeño y posible.</p>\n\n<hr>\n\n<p>4️⃣ Crea tu regla si–entonces para continuar el entrenamiento</p>\n\n<p>Usa esta estructura:</p>\n\n<p><strong>“Si aparece ________ (del hecho que elegí),<br>\nentonces aplicaré la Doble Llave de Bhakti<br>\ny entonces __________ (un gesto concreto de 90 segundos hacia quien corresponda).”</strong></p>\n\n<p>Esta regla convierte la claridad de hoy en continuidad práctica.</p>\n\n<hr>\n\n<p>📌 <strong>Compártelo en los comentarios así:</strong></p>\n\n<p>• Hecho que elegí: ______<br>\n• Persona hacia quien puse amor en acción: ______<br>\n• Mi micro–acción de 90 s: ______<br>\n• Mi regla si–entonces: ______<br>\n• Señal de apertura observada: <em>¿calidez? · ¿dirección? · ¿impulso amable?</em></p>\n\n<hr>\n\n<h5><strong>Ejemplo breve (solo como referencia de formato)</strong></h5>\n\n<p>• Hecho que elegí: evitar expresar gratitud aunque sí era posible.<br>\n• Persona hacia quien puse amor en acción: <em>mi hermana.</em><br>\n• Mi micro–acción de 90 s: <em>enviar un mensaje simple agradeciendo un gesto concreto.</em><br>\n• Mi regla si–entonces:<br>\n<em>“Si aparece la tendencia a postergar un gesto amable,<br>\nentonces aplicaré la Doble Llave de Bhakti<br>\ny entonces enviaré un mensaje breve de gratitud o apoyo en 90 segundos.”</em><br>\n• Señal de apertura observada: <em>claridad puntual e impulso amable.</em></p>',NULL,NULL,0,0,1,0,0,'2025-12-07 18:00:44','2025-12-08 00:00:44'),(25,NULL,NULL,NULL,NULL,'publico',NULL,23,'🌺 Línea de Meta Final del Reto Arjuna Online — Edición Noviembre 2025','linea-de-meta-final-del-reto-arjuna-online-edicion-noviembre-2025-29','<p><img src=\"https://i.postimg.cc/gj4XDLH2/C3op-Gf7p82Fmv-Sr-Dq2J3z.png\" alt=\"\" style=\"max-width:100%;border-radius:8px;\"><br>\n<strong>Arjuna,</strong><br>\nAquí cruzas la <strong>Línea de Meta Final del Reto Arjuna — Bhakti</strong>.<br>\nEste cierre no es un final del camino, sino un punto donde reconocemos <strong>lo que fue posible caminar</strong>,<br>\nlo ofrecemos con sinceridad<br>\ny abrimos espacio para la relación que sostiene y acompaña cada paso.<br>\nDespués de ocho cerraduras, ocho disposiciones y ocho micro–acciones,<br>\nllegamos al gesto más simple y profundo:<br>\n<strong>ofrecer el proceso tal como está y elegir cómo queremos seguir caminando.</strong></p>\n\n<hr>\n<h4>📍 <strong>Te invitamos a realizar esta dinámica de cierre para marcar tu paso por todo el reto</strong> <em>(y abrir el ciclo de continuidad con claridad y aprecio interno)</em></h4>\n\n<p><strong>1️⃣ Recupera el aspecto temporal que elegiste en el 1er Reto</strong><br>\nEse rasgo, impulso o reacción de tu identidad temporal que decidiste trabajar.<br>\nPuedes recordarlo como:<br>\n• exigencia,<br>\n• miedo a fallar,<br>\n• necesidad de aprobación,<br>\n• autoimagen limitada,<br>\n• o cualquier otro aspecto elegido.<br>\nEscríbelo con honestidad amable.</p>\n\n<p><strong>2️⃣ Observa cómo cambió tu relación con ese aspecto</strong><br>\nSin dramatizar ni idealizar:<br>\n¿Lo juzgas menos?<br>\n¿Apareció más ternura o comprensión?<br>\n¿Se suavizó alguna lectura?<br>\n¿Hubo una pequeña transformación real?<br>\n¿Pudiste caminar al menos un paso con más claridad?<br>\nDescribe solo el cambio.</p>\n\n<p><strong>3️⃣ Elige qué quieres hacer con ese aspecto hoy</strong><br>\nMarca tu opción:<br>\n<strong>1.</strong> <em>Lo ofrezco tal como está</em>, como gesto sincero de conexión, y elijo otro aspecto que ahora requiere mi atención.<br>\n<strong>2.</strong> Me comprometo a seguir cultivándolo con amor y constancia.<br>\n<strong>3.</strong> Ambas: lo ofrezco hoy y seguiré caminando con él.<br>\nEste paso expresa <strong>libertad, dirección y aprecio hacia el propio proceso</strong>.</p>\n\n<p><strong>4️⃣ Usa la Doble Llave de la Ofrenda Cotidiana (5º Reto)</strong><br>\nEjecuta la secuencia exacta:<br>\n<strong>🤔 ¿Qué percibo ahora que puedo ofrecer?</strong> → 🤲 palmas hacia arriba a la altura del torso → 🫁💭 permitir que surja qué se ofrece y cómo→ 🗣 expresar brevemente la ofrenda (3–7 palabras) → ♻ gesto circular suave con la mano derecha (entregar / devolver) → 👁️‍🗨️ verificar: <em>¿ligereza? · ¿claridad? · ¿impulso amable?</em><br>\nRecuerda:<br>\n<strong>Así como un niño entrega su dibujo —imperfecto pero sincero— tú puedes ofrecer lo que fue posible caminar.</strong><br>\nSin exigencia. Con autenticidad.</p>\n\n<p><strong>5️⃣ Comparte tu significado actual de la palabra “ofrenda”</strong><br>\nNo buscamos definición teórica ni poética.<br>\nBuscamos <strong>tu lectura inmediata</strong>, la que nació de caminar estos ocho retos.</p>\n\n<hr>\n<h4>📌 <strong>Compártelo en los comentarios así:</strong></h4>\n<ol><li>Aspecto temporal que elegí en el 1er reto: ______</li>\n<li>Cómo cambió mi relación con él: ______</li>\n<li>Opción que elijo hoy (1, 2 o 3): ______</li>\n<li>Mi ofrenda utilizando la Doble Llave: ______</li>\n<li>Qué significa hoy para mí “ofrenda”: ______<br>\n---\n<h5>⭐ <strong>Ejemplo breve (solo como referencia de formato)</strong></h5></li>\n<li>Aspecto temporal: miedo a fallar.</li>\n<li>Cambio observado: ahora lo veo con más amplitud y menos juicio.</li>\n<li>Opción elegida: ambas.</li>\n<li>Ofrenda (Doble Llave): “ofrezco mi proceso tal como está”.</li>\n<li>Significado de “ofrenda”: entregar con sinceridad lo que pude caminar.<br>\n---\n<h4>🌱 <strong>Cierre</strong></h4>\nLo que ofrendamos no es perfección, sino <strong>la dirección que elegimos sostener</strong>.<br>\nY esa dirección, cuando es sincera, se vuelve una forma de Bhakti:<br>\nsimple, amable y posible.<br>\n<strong>Gracias por caminar todo este ciclo, Arjuna.<br>\nTu ofrenda marca el inicio del siguiente paso.</strong> 🙏✨</li></ol>',NULL,NULL,0,0,1,0,0,'2025-12-08 00:46:52','2025-12-08 06:46:52'),(26,NULL,NULL,NULL,NULL,'publico',NULL,23,'\"Mi inicio en el Reto Arjuna – Srivas\"','mi-inicio-en-el-reto-arjuna-srivas-30','<p>Quiero sostener mi decisión de no gritarles a mis hijos.</p>',NULL,NULL,0,0,1,0,0,'2026-03-27 12:48:01','2026-03-27 18:48:01'),(27,NULL,NULL,NULL,NULL,'publico',NULL,33,'Mi inicio en el Reto Arjuna Marisol ','mi-inicio-en-el-reto-arjuna-marisol-31','<p>Quiero que mis hijas sean independientes de mí, no quiero seguirles dando codependencia</p>',NULL,NULL,0,0,1,0,0,'2026-03-27 12:58:07','2026-03-27 18:58:07'),(28,NULL,NULL,NULL,NULL,'publico',NULL,43,'Mi inicio en el Reto Arjuna - Jorge Luis ','mi-inicio-en-el-reto-arjuna-jorge-luis-32','<p>Tomar las mejores decisiones en cada momento, dejar de procrastinar.</p>',NULL,NULL,0,0,1,0,0,'2026-03-27 13:06:39','2026-03-27 19:06:39'),(29,NULL,NULL,NULL,NULL,'publico',NULL,47,'Mi inicio en el Reto Arjuna - Esme','mi-inicio-en-el-reto-arjuna-esme-34','<p>En esta ocasión, quiero trabajar sobre las decisiones que tomo con respecto a mi responsabilidad con mis hijos; pero además, cada vez con más frecuencia, comparto lo que he aprendido en los retos previos con las personas que están en mi entorno.</p>',NULL,NULL,0,0,1,0,0,'2026-03-27 23:12:16','2026-03-28 05:12:16'),(30,NULL,NULL,NULL,NULL,'publico',NULL,44,'Mi inicio en el reto Arjuna - Luna','mi-inicio-en-el-reto-arjuna-luna-35','<p>Durante el zoom había puesto mi comentario pero no recuerdo que fue (porque no se guardó, jaja), sin embargo durante el día me doy cuenta lo que realmente ocupo trabajar es mi reactividad, dejar de reaccionar impulsivamente, la tolerancia a la frustración y mejorar la conexión con mi intuición. <br>\nGracias. Haré Krishna 🙏🏻✨️📿</p>',NULL,NULL,0,0,1,0,0,'2026-03-28 00:24:16','2026-03-28 06:24:16'),(31,NULL,NULL,NULL,NULL,'publico',NULL,34,'Mi inicio en el reto Arjuna - Elba','mi-inicio-en-el-reto-arjuna-elba-36','',NULL,NULL,0,0,1,1,0,'2026-03-29 01:03:25','2026-03-29 07:00:49'),(32,NULL,NULL,NULL,NULL,'publico',NULL,34,'Llave de la mente regulada','llave-de-la-mente-regulada-37','<p>Hoy detecte que me estaba dejando arrastrar por pensamientos recurrentes que me estaban haciendo entrar en un estado de drama y estres por no saber que hacer, así que recorde la pregunta sobre lo que quería hacer con esos pensamientos y decidí dejarlos pasar (omitirlos como los anuncios de you tube y otras plataformas) y aunque me costo trabajo cada vez que volvían me repetía la pregunta hasta que logre dejarlos ir, redirigiendo mi mente y mi cuerpo hacia otros asuntos de interes verdadero.</p>',NULL,NULL,0,0,1,0,0,'2026-03-29 01:14:31','2026-03-29 07:14:31'),(33,NULL,NULL,NULL,NULL,'publico',NULL,34,'Visión abierta','vision-abierta-38','<p>Ayer llegue al juicio de que mi pareja es groseo y malo, <br>\nllegue a este a este juicio porque la mayor parte del día<br>\nestuvo alejado de mi haciendo otras actividades, después<br>\nle pedí un favor y no lo hizo, lo que hizo aparecer en mi<br>\nenojo y tristeza. Analizando el hecho me dÍ cuenta que la<br>\nincomodidad que apareció en mi fue debido a que no me<br>\nagrada sentirme ignorada ni invalidada, sin embargo también<br>\nentendi que en ocaciones todos necesitamos espacio para<br>\nnosotros mismos y que tal vez él necesitaba estar solo para<br>\nreflexionar sobre algo que le esta inquietando y no lo externa<br>\ntal vez por temor al que diran.</p>',NULL,NULL,0,0,1,0,0,'2026-03-30 12:24:46','2026-03-30 18:24:46'),(34,NULL,NULL,NULL,NULL,'publico',NULL,34,'Intención más clara','intencion-mas-clara-39','<p>La desición que tome al iniciar el reto fue accionar en todos los aspectos de mi vida, ya que he estado en un <br>\nlargo periodo de inactividad en varios aspectos.</p> \n\n<p>Acción laboral: <br>\nPara qué reactivo<br>\nPara adquirir cosas materiales que necesito y cubrir mi manutención</p>\n\n<p>Para qué elevado<br>\nPara mejorar mi calidad de vida de forma digna, poder apoyar a mi hija y sobre todo para ofrecer los resultados<br>\nde mi trabajo a Krishna</p>\n\n<p>Acción espiritual:<br>\nPara qué reactivo<br>\nPara sentirme tranquila y protegida</p> \n\n<p>Para qué elevado<br>\nPara aprender a servir correctamente con base en la enseñanza de los gurus y satisfacer a Krishna</p>',NULL,NULL,0,0,1,0,1,'2026-03-30 18:22:44','2026-03-31 00:22:44'),(35,NULL,NULL,NULL,NULL,'publico',NULL,42,'Inicio en el Reto Arjuna (José Luis)','inicio-en-el-reto-arjuna-jose-luis-40','<p>Quiero mantener mi decisión de enfrentar con tranquilidad los distintos problemas que me presenta la vida</p>',NULL,NULL,0,0,1,0,0,'2026-04-01 05:10:30','2026-04-01 11:10:30'),(36,NULL,NULL,NULL,NULL,'publico',NULL,34,'Sostener, confiar y soltar','sostener-confiar-y-soltar-41','<p>Hace algunos días me postule a varias ofertas de trabajo en línea y me llego notificación de que una empresa estaba interesada en mi perfil pero no se comunicaron conmigo, estuve como 3 días revisando el correo y los mensajes en la bolsa de trabajo y nada, me frustre mucho así que ayer lo solte confiando en que el trabajo que me corresponde llegará en el momento adecuado ya que me sigo postulando.</p>',NULL,NULL,0,0,1,2,0,'2026-04-14 13:44:53','2026-04-02 08:40:32'),(37,NULL,NULL,NULL,NULL,'publico',NULL,34,'Ofrenda Cotidiana.','ofrenda-cotidiana-42','<p>Hoy ofrendo al fuego de la acción, la culpa que he sentido por haber renunciado a un buen trabajo estable hace años, ya que después de ese trabajo no he podido tener estabilidad laboral.</p>',NULL,NULL,0,0,1,2,0,'2026-04-14 13:40:47','2026-04-02 08:48:51'),(38,NULL,NULL,NULL,NULL,'publico',NULL,34,'Identidad estable','identidad-estable-43','<p>Ayer quería ponerme una cadenita con un dije de cristal que me regalo mi hija en mi cumpleaños y le pedí ayuda a mi pareja para que me lo pusiera, pero hizo algunos comentarios que me incomodaron, así que decidí hacerlo por mi misma, en el intento el dije se safó de la cadena y cayo al suelo rompiendose en pedacitos, tuve mucho enojo y tristeza y culpe a mi pareja, justo en ese momento recorde la llave, respire profundamente y acepte que no fue culpa de él, pues fue a mi a quien se le cayó, recorde que esa situación no me define que soy más que ese momento desagradable y lo deje ir, ya estando más tranquila le pedí disculpa a mi pareja y ahí se terminó el tema.</p>',NULL,NULL,0,0,1,0,0,'2026-04-03 22:17:01','2026-04-04 04:17:01'),(39,NULL,NULL,NULL,NULL,'publico',NULL,62,'🏁 Línea de Meta 1  — Reto Mente Regulada Marzo-Abril 2026','linea-de-meta-1-reto-mente-regulada-marzo-abril-2026-44','<h5>¿Lograste tomar mejor algún pensamiento y convertirlo en una decisión o acción clara para sostener lo que te importa? (sí/no y qué pasó)</h5>',NULL,NULL,0,0,1,4,3,'2026-04-12 16:58:13','2026-04-08 07:41:00'),(40,NULL,NULL,NULL,NULL,'publico',NULL,62,'🏁 Línea de Meta 3 — Intención Más Clara Marzo-Abril 2026','linea-de-meta-3-intencion-mas-clara-marzo-abril-2026-45','<h5>¿Lograste elevar algún para-qué hoy y convertirlo en una decisión o acción que te ayudara a sostener lo que te importa? (sí/no y qué pasó)</h5>',NULL,NULL,1,0,1,2,0,'2026-04-11 06:31:55','2026-04-08 07:54:07'),(41,NULL,NULL,NULL,NULL,'publico',NULL,62,'🏁 Línea de Meta 2 — Visión Abierta Marzo-Abril 2026','linea-de-meta-2-vision-abierta-marzo-abril-2026-46','<h5>¿Lograste notar algún juicio hoy, separar lo que pasó de lo que apareció en ti, y reconstruir un juicio que te ayudara a sostener lo que te importa? (sí/no y qué pasó)</h5>',NULL,NULL,1,0,1,2,0,'2026-04-11 06:28:24','2026-04-08 08:02:49'),(42,NULL,NULL,NULL,NULL,'publico',NULL,34,'Visión profunda','vision-profunda-47','<p>Hoy recibí la llamada de una de mis hermanas, quien me comentó sobre las acciones de otra de mis hermanas, inmediatamente se me vinó a la mente que no podía ser de otra forma porque así es ella ya &quot;la conozco&quot;, en ese momento que identifique el cierre, rapidamente revise en whats app que debía hacer, la pregunta era  ¿Qué haría si confío en un orden que busca mi crecimiento? en ese momento supe que debía confiar en la Suprema Personalidad de Dios lo cual apaciguo mi mente y a diferencia de otras ocaciones en las que se ha presentado esta situación esta vez no hice ningún comentario negativo sobre mi hermana y tampoco juicios, me senti muy contenta, creo esto tenía que pasar para poder cumplir el reto de visión profunda</p>',NULL,NULL,0,0,1,0,0,'2026-04-08 03:19:22','2026-04-08 09:19:22'),(43,NULL,NULL,NULL,NULL,'publico',NULL,62,'🏁 Línea de meta 4 — de Confiar y Soltar Marzo-Abril 2026','linea-de-meta-4-de-confiar-y-soltar-marzo-abril-2026-48','<h5>¿Lograste identificar y soltar algún intento de control que no te correspondía y que estaba afectando tu capacidad de actuar? (sí/no y qué pasó)</h5>',NULL,NULL,1,0,1,2,0,'2026-04-11 06:37:29','2026-04-08 16:54:22'),(44,NULL,NULL,NULL,NULL,'publico',NULL,62,'🏁 Línea de meta 5 — Ofrenda Cotidiana Marzo-Abril 2026','linea-de-meta-5-ofrenda-cotidiana-marzo-abril-2026-49','<h5>¿Lograste soltar la relación de orgullo o culpa que estabas cargando? (sí/no y qué pasó después)</h5>',NULL,NULL,1,0,1,2,0,'2026-04-11 06:40:14','2026-04-08 17:12:03'),(45,NULL,NULL,NULL,NULL,'publico',NULL,62,'🏁 Línea de meta 6 — Identidad Estable Marzo-Abril 2026','linea-de-meta-6-identidad-estable-marzo-abril-2026-50','<h5>¿Lograste no reducirte a lo que estaba pasando y actuar desde un lugar más estable? (sí/no y qué pasó)</h5>',NULL,NULL,1,0,1,2,0,'2026-04-11 06:43:46','2026-04-08 17:15:47'),(46,NULL,NULL,NULL,NULL,'publico',NULL,62,'🏁 Línea de meta 7 — Visión Profunda Marzo-Abril 2026','linea-de-meta-7-vision-profunda-marzo-abril-2026-51','<h5>¿Lograste notar algún momento donde estabas tomando lo que pasaba como suficiente para juzgar la realidad? (sí/no y qué cambió)</h5>',NULL,NULL,1,0,1,2,0,'2026-04-11 06:50:05','2026-04-08 17:20:08'),(47,NULL,NULL,NULL,NULL,'publico',NULL,62,'🏁 Línea de meta 8 — Bhakti Marzo-Abril 2026','linea-de-meta-8-bhakti-marzo-abril-2026-52','<h5>¿Lograste ofrecer alguna acción quedando libre del condicionamiento del resultado? (sí/no y qué pasó)</h5>',NULL,NULL,1,0,1,2,2,'2026-04-11 06:56:21','2026-04-08 17:46:17'),(48,NULL,NULL,NULL,NULL,'publico',NULL,44,'🏁🏹Integración - Testimonios Marzo-Abril 2026','integracion-testimonios-marzo-abril-2026-53','<p>Miles de millones de gracias a todo el equipo que hizo posible este reto Arjuna renovado, fresco, claro y profundo, por todo lo que dejaron en mí 🙏🏻 <br>\nDespués de 3 retos por fin pude hacerlo completo, cumpliendo cada día con cada uno.🥰<br>\nY gracias también a todos los participantes por su presencia, sus historias, sus comentarios, su sinergia que también me ayudaron a crecer un poquito más 🙏🏻🫶🏻✨️🍀<br>\nQue El Supremo los siga llenando de salud, protección y bendiciones a cada uno de ustedes y a sus familias! 📿 Hare Krishna 📿<br>\n#todossomosarjuna🏹</p>',NULL,NULL,1,0,1,0,0,'2026-04-08 16:41:50','2026-04-08 22:41:50'),(49,NULL,NULL,NULL,NULL,'publico',NULL,62,'Como el fuego viene con humo, toda acción trae defectos','como-el-fuego-viene-con-humo-toda-accion-trae-defectos-57','<p>Arjuna no eligió el escenario.</p>\n\n<p>Le tocó actuar en uno de los más duros posibles:<br>\nfrente a sus maestros,<br>\nfrente a sus mayores,<br>\nfrente a su propia familia.</p>\n\n<p>Nada de eso era ideal.<br>\nNada de eso estaba limpio.</p>\n\n<p>Y aun así, tenía que actuar.</p>\n\n<p>Krishna lo expresa con una analogía muy precisa:<br>\nasí como el fuego está cubierto por humo, toda acción trae defectos.</p>\n\n<p>Eso significa que no siempre podrás actuar en condiciones perfectas.<br>\nHabrá duda, incomodidad, emociones encontradas y presión por elegir.</p>\n\n<p>Ese también es el humo.</p>\n\n<p>El punto no es esperar a que el humo desaparezca.<br>\nEl punto es no detenerte por completo a causa de él.</p>\n\n<p>Arjuna no esperó una batalla sin conflicto.<br>\nTuvo que hacer su parte en medio de una situación defectuosa por naturaleza.</p>\n\n<p>En nuestra vida pasa igual.<br>\nMuchas veces no avanzamos porque seguimos esperando el momento limpio, la emoción correcta o las condiciones ideales.</p>\n\n<p>Pero no toda acción llega así.</p>\n\n<p>La pregunta no es si todo ya está despejado.</p>\n\n<p>La pregunta es:<br>\n¿vas a hacer tu mejor esfuerzo con el humo presente?</p>\n\n<p><strong>Para compartir:</strong><br>\n<strong>¿Cuál es una decisión concreta que has estado postergando porque “no están dadas las condiciones”?</strong><br>\n<strong>¿Y cuál sería el siguiente paso que sí podrías sostener hoy, aun con ese humo presente?</strong></p>',NULL,NULL,0,0,1,0,1,'2026-04-14 20:27:30','2026-04-15 02:27:30');
/*!40000 ALTER TABLE `foro_temas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `foro_temas_historial`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `foro_temas_historial` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tema_id` int(10) unsigned NOT NULL,
  `titulo_anterior` varchar(200) NOT NULL,
  `contenido_anterior` mediumtext NOT NULL,
  `editado_por` int(10) unsigned NOT NULL,
  `editado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_foro_temas_historial_tema` (`tema_id`),
  KEY `fk_foro_temas_historial_usuario` (`editado_por`),
  CONSTRAINT `fk_foro_temas_historial_tema` FOREIGN KEY (`tema_id`) REFERENCES `foro_temas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foro_temas_historial_usuario` FOREIGN KEY (`editado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `foro_temas_historial`
--

LOCK TABLES `foro_temas_historial` WRITE;
/*!40000 ALTER TABLE `foro_temas_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `foro_temas_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leccion_materiales`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `leccion_materiales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `leccion_id` int(10) unsigned NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `url` varchar(500) NOT NULL,
  `orden` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leccion_materiales_leccion` (`leccion_id`),
  CONSTRAINT `fk_leccion_materiales_leccion` FOREIGN KEY (`leccion_id`) REFERENCES `lecciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leccion_materiales`
--

LOCK TABLES `leccion_materiales` WRITE;
/*!40000 ALTER TABLE `leccion_materiales` DISABLE KEYS */;
INSERT  IGNORE INTO `leccion_materiales` VALUES (7,4,'Imprimible','https://docs.google.com/document/d/10WxzI06k-liBaiPeWo5-i5JM8WuqVI228uPiMviweHE/edit?usp=sharing',0,'2026-08-03 16:12:35'),(8,4,'Versión Digital','https://drive.google.com/file/d/1Fx9nZMTMEOry1WHCZdbK8OtLQLfbeCe8/view?usp=drive_link',1,'2026-08-03 16:12:35'),(11,5,'PDF','https://drive.google.com/file/d/1awmfu2rjEQjkmJ8JqTSHxjZO1NW6i13u/view?usp=drive_link',0,'2026-08-03 16:14:22'),(12,5,'Google Doc','https://docs.google.com/document/d/1wYWCv7q5wR1XbV1vz5uJo65xxeCla4fetCenzyxy3o0/edit?usp=drive_link',1,'2026-08-03 16:14:22'),(13,7,'Presentación','https://bienvenidoa-al--4ez298c.gamma.site/',0,'2026-08-03 16:23:32'),(14,9,'Diapositivas','https://bienvenidao-al-5to-lrpexs7.gamma.site/',0,'2026-08-03 16:24:18'),(15,10,'Diapositivas','https://bienvenida-al-6to-reto-d-fqp90y4.gamma.site/',0,'2026-08-03 16:25:17'),(16,11,'Diapositivas 1','https://bienvenidoa-al-7o-reto-d-aavyu44.gamma.site/',0,'2026-08-03 16:26:28'),(17,11,'Diapositivas 2','https://sesion-de-refuerzo-7-ret-kftk54b.gamma.site/',1,'2026-08-03 16:26:28'),(18,12,'Diapositivas','https://bienvenidoa-al-8-reto-de-2hz4243.gamma.site/',0,'2026-08-03 16:27:13');
/*!40000 ALTER TABLE `leccion_materiales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leccion_videos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `leccion_videos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `leccion_id` int(10) unsigned NOT NULL,
  `url` varchar(500) NOT NULL,
  `orden` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leccion_videos_leccion` (`leccion_id`),
  CONSTRAINT `fk_leccion_videos_leccion` FOREIGN KEY (`leccion_id`) REFERENCES `lecciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leccion_videos`
--

LOCK TABLES `leccion_videos` WRITE;
/*!40000 ALTER TABLE `leccion_videos` DISABLE KEYS */;
INSERT  IGNORE INTO `leccion_videos` VALUES (4,3,'https://www.youtube.com/embed/UUJSFYBs-d4',0,'2026-08-02 17:21:14'),(9,4,'https://www.youtube.com/embed/l-4DjcC9K9o',0,'2026-08-03 16:12:35'),(10,4,'https://www.youtube.com/embed/mPZSn_m8w00',1,'2026-08-03 16:12:35'),(13,5,'https://www.youtube.com/embed/qifaVnzBOvo',0,'2026-08-03 16:14:22'),(14,5,'https://www.youtube.com/embed/Ws_0viqs9Do',1,'2026-08-03 16:14:22'),(15,6,'https://www.youtube.com/embed/l9QPRtXCt4g',0,'2026-08-03 16:18:14'),(16,6,'https://www.youtube.com/embed/I6J8QoKaTjs',1,'2026-08-03 16:18:14'),(17,6,'https://www.youtube.com/embed/QmksaayGhsM',2,'2026-08-03 16:18:14'),(18,6,'https://www.youtube.com/embed/cA6Vu83N9uw',3,'2026-08-03 16:18:14'),(20,7,'https://www.youtube.com/embed/RJE7pSseTO0',0,'2026-08-03 16:23:32'),(21,7,'https://www.youtube.com/embed/KdgkdZQZmIU',1,'2026-08-03 16:23:32'),(22,7,'https://www.youtube.com/embed/-9tcM5k40Zs',2,'2026-08-03 16:23:32'),(23,9,'https://www.youtube.com/embed/sogGE0F8wCg',0,'2026-08-03 16:24:18'),(24,9,'https://www.youtube.com/embed/me-fdn87b3A',1,'2026-08-03 16:24:18'),(25,9,'https://www.youtube.com/embed/WECi4ZtfMgQ',2,'2026-08-03 16:24:18'),(29,10,'https://www.youtube.com/embed/syC-3iOQmhc',0,'2026-08-03 16:25:17'),(30,10,'https://www.youtube.com/embed/9OuRRPNqsfk',1,'2026-08-03 16:25:17'),(31,10,'https://www.youtube.com/embed/b4sOV-AZq04',2,'2026-08-03 16:25:17'),(32,11,'https://www.youtube.com/embed/P6AvtLunLAE',0,'2026-08-03 16:26:28'),(33,11,'https://www.youtube.com/embed/P6AvtLunLAE',1,'2026-08-03 16:26:28'),(34,11,'https://www.youtube.com/embed/QeCsjfqwGxY',2,'2026-08-03 16:26:28'),(35,12,'https://www.youtube.com/embed/H3ufgWoCU2Y',0,'2026-08-03 16:27:13'),(36,12,'https://www.youtube.com/embed/RzAuAWf5700',1,'2026-08-03 16:27:13');
/*!40000 ALTER TABLE `leccion_videos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Landing pages subidas por el admin como HTML (checklist: "subir html a la
-- plataforma") — se guarda ya extraído (estilos + body, ver
-- backend/landing_pages.php: landing_extraer_contenido()) para que al
-- servirla solo haga falta envolverla en el navbar/footer de siempre, sin
-- construir un editor de landing pages completo.
--

CREATE TABLE IF NOT EXISTS `landing_pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL COMMENT 'solo para identificarla en el admin y como <title>',
  `slug` varchar(220) NOT NULL,
  `contenido_html` mediumtext NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_landing_pages_slug` (`slug`),
  KEY `fk_landing_pages_creado_por` (`creado_por`),
  CONSTRAINT `fk_landing_pages_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Flujo de venta: un curso/evento puede enlazar la landing comercial a la
-- que se redirige a un visitante sin acceso (ver curso_detalle.php/
-- evento_detalle.php) — ON DELETE SET NULL porque borrar la landing no debe
-- borrar el curso/evento, solo desligarlos.
ALTER TABLE cursos DROP FOREIGN KEY IF EXISTS fk_cursos_landing_page;
ALTER TABLE cursos ADD CONSTRAINT fk_cursos_landing_page FOREIGN KEY (landing_page_id) REFERENCES landing_pages (id) ON DELETE SET NULL;
ALTER TABLE cursos ADD KEY IF NOT EXISTS idx_cursos_landing_page (landing_page_id);

ALTER TABLE eventos DROP FOREIGN KEY IF EXISTS fk_eventos_landing_page;
ALTER TABLE eventos ADD CONSTRAINT fk_eventos_landing_page FOREIGN KEY (landing_page_id) REFERENCES landing_pages (id) ON DELETE SET NULL;
ALTER TABLE eventos ADD KEY IF NOT EXISTS idx_eventos_landing_page (landing_page_id);

--
-- Table structure for table `lecciones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `lecciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `curso_id` int(10) unsigned DEFAULT NULL,
  `evento_id` int(10) unsigned DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_contenido` enum('contenido','quiz') NOT NULL DEFAULT 'contenido',
  `contenido_url` varchar(500) DEFAULT NULL,
  `contenido_texto` mediumtext DEFAULT NULL,
  `orden` int(10) unsigned NOT NULL DEFAULT 0,
  `duracion_min` int(10) unsigned DEFAULT NULL,
  `vista_previa` tinyint(1) NOT NULL DEFAULT 0,
  `estado_publicacion` enum('borrador','publicado') NOT NULL DEFAULT 'publicado',
  `foro_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leccion_orden` (`curso_id`,`orden`),
  UNIQUE KEY `uq_leccion_orden_evento` (`evento_id`,`orden`),
  CONSTRAINT `fk_lecciones_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lecciones_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Editor unificado (panel/admin/leccion_form.php): todo el contenido que no
-- es quiz vive en un solo campo HTML (contenido_texto), con video/audio/
-- archivos insertados desde ahí mismo — ya no hay tipo_contenido por
-- separado para cada uno. Instalación vieja: primero se agrega 'contenido'
-- al enum sin quitar los viejos, se retiquetan las filas, y HASTA ENTONCES
-- se reduce el enum a solo los dos valores finales (si se hiciera en un
-- solo paso, cualquier fila con un valor viejo truncaría a '').
ALTER TABLE lecciones MODIFY COLUMN tipo_contenido enum('video','pdf','texto','quiz','audio','contenido') NOT NULL DEFAULT 'contenido';
UPDATE lecciones SET tipo_contenido = 'contenido' WHERE tipo_contenido <> 'quiz';
ALTER TABLE lecciones MODIFY COLUMN tipo_contenido enum('contenido','quiz') NOT NULL DEFAULT 'contenido';
ALTER TABLE lecciones ADD COLUMN IF NOT EXISTS estado_publicacion enum('borrador','publicado') NOT NULL DEFAULT 'publicado' AFTER vista_previa;
-- IMPORTANTE — esto SOLO ajusta el esquema. Una instalación vieja con
-- videos/audios/materiales ya guardados en sus tablas separadas (leccion_videos/
-- leccion_materiales/contenido_url) todavía necesita, una sola vez, el mismo
-- script que se corrió aquí para juntar todo eso dentro de contenido_texto
-- antes de que el editor nuevo los pueda mostrar — no es un ALTER, no se
-- aplica solo con deploy_auto.php, hay que correrlo a mano revisando el diff.

-- Dumping data for table `lecciones`
--

LOCK TABLES `lecciones` WRITE;
/*!40000 ALTER TABLE `lecciones` DISABLE KEYS */;
INSERT  IGNORE INTO `lecciones` VALUES (1,1,NULL,'Bienvenida','','contenido','','<p>Bienvenido al Reto Arjuna. En esta lección conocerás el propósito del programa.</p>',0,NULL,1,'publicado','','2026-07-30 00:12:40'),(2,1,NULL,'Cuestionario inicial',NULL,'quiz',NULL,NULL,2,NULL,0,'publicado',NULL,'2026-07-30 00:12:41'),(3,NULL,9,'Introducción','En esta sesión encontraras las dinámicas y procesos para seguir este curso en vivo','contenido','','<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/UUJSFYBs-d4\"></iframe>',1,65,1,'publicado',NULL,'2026-08-02 13:31:02'),(4,NULL,9,'Reto 1: Cerradura “Más Honestidad”','Sesión matutina del primer día del Reto','contenido','','<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/l-4DjcC9K9o\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/mPZSn_m8w00\"></iframe>\n<p><a href=\"https://docs.google.com/document/d/10WxzI06k-liBaiPeWo5-i5JM8WuqVI228uPiMviweHE/edit?usp=sharing\">📎 Imprimible</a></p>\n<p><a href=\"https://drive.google.com/file/d/1Fx9nZMTMEOry1WHCZdbK8OtLQLfbeCe8/view?usp=drive_link\">📎 Versión Digital</a></p>',2,65,0,'publicado',NULL,'2026-08-02 13:44:40'),(5,NULL,9,'Reto 2: “Intención Más Clara”','Sesión donde Presentamos el Segundo Reto','contenido','','Este es el contenido\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/qifaVnzBOvo\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/Ws_0viqs9Do\"></iframe>\n<p><a href=\"https://drive.google.com/file/d/1awmfu2rjEQjkmJ8JqTSHxjZO1NW6i13u/view?usp=drive_link\">📎 PDF</a></p>\n<p><a href=\"https://docs.google.com/document/d/1wYWCv7q5wR1XbV1vz5uJo65xxeCla4fetCenzyxy3o0/edit?usp=drive_link\">📎 Google Doc</a></p>',3,65,0,'publicado',NULL,'2026-08-03 16:14:12'),(6,NULL,9,'Reto 3: Identidad Estable','','contenido','','<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/l9QPRtXCt4g\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/I6J8QoKaTjs\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/QmksaayGhsM\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/cA6Vu83N9uw\"></iframe>',4,NULL,0,'publicado',NULL,'2026-08-03 16:18:14'),(7,NULL,9,'Reto 4: Cerradura de la “Visión Profunda”','','contenido','','<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/RJE7pSseTO0\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/KdgkdZQZmIU\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/-9tcM5k40Zs\"></iframe>\n<p><a href=\"https://bienvenidoa-al--4ez298c.gamma.site/\">📎 Presentación</a></p>',5,NULL,0,'publicado',NULL,'2026-08-03 16:18:50'),(9,NULL,9,'Reto 5: La Ofrenda Cotidiana','','contenido','','<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/sogGE0F8wCg\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/me-fdn87b3A\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/WECi4ZtfMgQ\"></iframe>\n<p><a href=\"https://bienvenidao-al-5to-lrpexs7.gamma.site/\">📎 Diapositivas</a></p>',6,NULL,0,'publicado',NULL,'2026-08-03 16:19:48'),(10,NULL,9,'Reto 6: Cerradura de la “Mente Enfocada”','','contenido','','<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/syC-3iOQmhc\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/9OuRRPNqsfk\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/b4sOV-AZq04\"></iframe>\n<p><a href=\"https://bienvenida-al-6to-reto-d-fqp90y4.gamma.site/\">📎 Diapositivas</a></p>',7,NULL,0,'publicado',NULL,'2026-08-03 16:20:07'),(11,NULL,9,'Reto 7: Cerradura de “Confiar y Soltar”','','contenido','','<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/P6AvtLunLAE\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/P6AvtLunLAE\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/QeCsjfqwGxY\"></iframe>\n<p><a href=\"https://bienvenidoa-al-7o-reto-d-aavyu44.gamma.site/\">📎 Diapositivas 1</a></p>\n<p><a href=\"https://sesion-de-refuerzo-7-ret-kftk54b.gamma.site/\">📎 Diapositivas 2</a></p>',8,NULL,0,'publicado',NULL,'2026-08-03 16:20:25'),(12,NULL,9,'Reto 8: Cerradura del “Bhakti, Amor en Acción”','','contenido','','<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/H3ufgWoCU2Y\"></iframe>\n<iframe class=\"ql-video\" frameborder=\"0\" allowfullscreen=\"true\" src=\"https://www.youtube.com/embed/RzAuAWf5700\"></iframe>\n<p><a href=\"https://bienvenidoa-al-8-reto-de-2hz4243.gamma.site/\">📎 Diapositivas</a></p>',9,NULL,0,'publicado',NULL,'2026-08-03 16:20:46');
/*!40000 ALTER TABLE `lecciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `membresia_suscripciones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `membresia_suscripciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `membresia_id` int(10) unsigned NOT NULL,
  `metodo` enum('stripe','transferencia','manual') NOT NULL DEFAULT 'stripe',
  `modo` enum('live','prueba') NOT NULL DEFAULT 'live',
  `renovacion_automatica` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_inicio` date DEFAULT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `stripe_subscription_id` varchar(255) DEFAULT NULL,
  `comprobante_url` varchar(500) DEFAULT NULL,
  `activada_por` int(10) unsigned DEFAULT NULL,
  `estado` enum('pendiente','activa','cancelada','vencida') NOT NULL DEFAULT 'activa',
  `periodo_actual_fin` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membresia_susc_stripe_sub` (`stripe_subscription_id`),
  KEY `idx_membresia_susc_usuario` (`usuario_id`),
  KEY `idx_membresia_susc_estado` (`estado`),
  KEY `fk_membresia_susc_membresia` (`membresia_id`),
  CONSTRAINT `fk_membresia_susc_membresia` FOREIGN KEY (`membresia_id`) REFERENCES `membresias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_membresia_susc_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- modo = 'live' por defecto a propósito, mismo criterio que pagos.modo de
-- arriba — usuario_tiene_membresia_activa() (auth.php) ya filtra modo =
-- 'live', así que una suscripción de prueba nunca otorga acceso real.
ALTER TABLE membresia_suscripciones
  ADD COLUMN IF NOT EXISTS modo ENUM('live','prueba') NOT NULL DEFAULT 'live' AFTER metodo;

-- Trazabilidad de qué promoción/cupón se usó (FK más abajo, después de que
-- promociones/cupones ya existen).
ALTER TABLE membresia_suscripciones
  ADD COLUMN IF NOT EXISTS cupon_id INT UNSIGNED NULL AFTER membresia_id,
  ADD COLUMN IF NOT EXISTS promocion_id INT UNSIGNED NULL AFTER cupon_id;

-- Dumping data for table `membresia_suscripciones`
--

LOCK TABLES `membresia_suscripciones` WRITE;
/*!40000 ALTER TABLE `membresia_suscripciones` DISABLE KEYS */;
INSERT  IGNORE INTO `membresia_suscripciones` VALUES (4,67,1,NULL,NULL,'stripe','live',0,NULL,'cus_V38cRsSzmWwtBz','sub_1U32LnKBZI0bAfydz4ZoXAh7',NULL,NULL,'activa','2026-09-11 01:11:20','2026-08-10 23:14:18','2026-08-10 23:14:18');
/*!40000 ALTER TABLE `membresia_suscripciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `membresias`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `membresias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `intervalo` enum('mensual','anual') NOT NULL DEFAULT 'mensual',
  `stripe_price_id` varchar(255) DEFAULT NULL COMMENT 'Price real creado en el Dashboard de Stripe',
  `stripe_price_id_prueba` varchar(255) DEFAULT NULL,
  `mostrar_codigo_promocion` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membresias_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Price ID de Stripe en modo prueba, paralelo al de LIVE de arriba — un
-- admin en modo prueba lo usa en vez del real (ver membresia_iniciar.php).
-- NULL hasta que un admin lo llene a mano en panel/admin/membresia_form.php.
ALTER TABLE membresias
  ADD COLUMN IF NOT EXISTS stripe_price_id_prueba VARCHAR(255) NULL AFTER stripe_price_id;

-- Igual que en cursos/eventos/productos: controla si aparece el campo de
-- código de promoción en el checkout de la suscripción (el Payment Element
-- embebido no trae uno nativo para suscripciones — ver
-- stripe_membresia_aplicar_promocion.php).
ALTER TABLE membresias
  ADD COLUMN IF NOT EXISTS mostrar_codigo_promocion TINYINT(1) NOT NULL DEFAULT 0 AFTER stripe_price_id_prueba;

-- Dumping data for table `membresias`
--

LOCK TABLES `membresias` WRITE;
/*!40000 ALTER TABLE `membresias` DISABLE KEYS */;
INSERT  IGNORE INTO `membresias` VALUES (1,'Membresía Camino Arjuna','Membresía de práctica continua: el espacio al que vuelves para reorientarte, no un curso con contenido nuevo cada semana.',108.00,'mensual','price_1U32BGKBZI0bAfydSI1N3mhd','price_1U8bwxKBZI0bAfydQFmMYZ9j',0,1,1,'2026-08-06 08:19:18');
/*!40000 ALTER TABLE `membresias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `navbar_links`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `navbar_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `texto` varchar(100) NOT NULL,
  `url` varchar(300) NOT NULL,
  `area` enum('nav','footer','ambos') NOT NULL DEFAULT 'ambos',
  `abre_nueva_pestana` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_navbar_links_url` (`url`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

ALTER TABLE navbar_links
  ADD COLUMN IF NOT EXISTS requiere_sesion TINYINT(1) NOT NULL DEFAULT 0 AFTER abre_nueva_pestana;

--
-- Dumping data for table `navbar_links`
--

LOCK TABLES `navbar_links` WRITE;
/*!40000 ALTER TABLE `navbar_links` DISABLE KEYS */;
INSERT  IGNORE INTO `navbar_links` VALUES (1,'Cursos','plataforma/index.php?action=cursos','ambos',0,0,1,1,'2026-08-06 08:19:18'),(2,'Membresía','plataforma/index.php?action=membresia','ambos',0,1,1,1,'2026-08-06 08:19:18'),(3,'Actividades','plataforma/index.php?action=actividades','ambos',0,0,0,3,'2026-08-06 08:19:18'),(4,'Noticias','plataforma/index.php?action=noticias','ambos',0,0,0,4,'2026-08-06 08:19:18'),(5,'Eventos','plataforma/index.php?action=eventos','ambos',0,0,1,5,'2026-08-06 08:19:18'),(6,'Tienda','plataforma/index.php?action=tienda','ambos',0,0,1,6,'2026-08-06 08:19:18'),(7,'Foro','plataforma/foro/','ambos',0,0,1,7,'2026-08-06 08:19:18'),(8,'Reto Arjuna','reto-arjuna.html','ambos',0,0,1,8,'2026-08-06 08:19:18');
/*!40000 ALTER TABLE `navbar_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `noticias`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `noticias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `resumen` varchar(300) DEFAULT NULL,
  `contenido` mediumtext NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `publicada_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_noticias_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `noticias`
--

LOCK TABLES `noticias` WRITE;
/*!40000 ALTER TABLE `noticias` DISABLE KEYS */;
/*!40000 ALTER TABLE `noticias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `notificaciones_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(40) NOT NULL,
  `destinatario` varchar(150) NOT NULL,
  `asunto` varchar(200) NOT NULL,
  `estado` enum('enviado','error') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notificaciones_usuario` (`usuario_id`),
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones_log`
--

LOCK TABLES `notificaciones_log` WRITE;
/*!40000 ALTER TABLE `notificaciones_log` DISABLE KEYS */;
INSERT  IGNORE INTO `notificaciones_log` VALUES (3,NULL,'inscripcion','finaltest@example.com','Inscripción confirmada','error','2026-07-30 04:47:35'),(4,NULL,'finalizacion','finaltest@example.com','Certificado emitido','error','2026-07-30 04:47:48'),(5,67,'inscripcion','caiman.mistico@gmail.com','Inscripción confirmada','enviado','2026-08-02 14:05:47'),(6,NULL,'membresia','membtest9776@example.com','Tu membresía Camino Arjuna está activa','error','2026-08-06 08:33:16'),(7,67,'inscripcion','caiman.mistico@gmail.com','Inscripción confirmada','error','2026-08-06 22:18:49'),(8,NULL,'inscripcion','test_stripe_qa@example.com','Inscripción confirmada','error','2026-08-10 16:30:16'),(10,NULL,'membresia','testnuevousuario@example.com','Tu membresía Camino Arjuna está activa','error','2026-08-14 15:33:49'),(11,NULL,'membresia','testusuariomembmodal@example.com','Tu membresía Camino Arjuna está activa','error','2026-08-14 16:09:46'),(12,NULL,'membresia','testunifyconmembresia@example.com','Tu membresía Camino Arjuna está activa','error','2026-08-14 16:15:30');
/*!40000 ALTER TABLE `notificaciones_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `pagos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `curso_id` int(10) unsigned DEFAULT NULL,
  `evento_id` int(10) unsigned DEFAULT NULL,
  `producto_id` int(10) unsigned DEFAULT NULL,
  `cupon_id` int(10) unsigned DEFAULT NULL,
  `promocion_id` int(10) unsigned DEFAULT NULL,
  `regalo_id` int(10) unsigned DEFAULT NULL,
  `cantidad` int(10) unsigned NOT NULL DEFAULT 1,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('stripe','transferencia') NOT NULL,
  `modo` enum('live','prueba') NOT NULL DEFAULT 'live',
  `transaccion_id` varchar(191) DEFAULT NULL,
  `estado` enum('pendiente','confirmado','rechazado') NOT NULL DEFAULT 'pendiente',
  `comprobante_url` varchar(500) DEFAULT NULL,
  `direccion_envio` text DEFAULT NULL,
  `fecha_pago` datetime DEFAULT NULL,
  `fecha_validacion` datetime DEFAULT NULL,
  `validado_por` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pagos_estado` (`estado`),
  KEY `fk_pagos_usuario` (`usuario_id`),
  KEY `fk_pagos_curso` (`curso_id`),
  KEY `fk_pagos_evento` (`evento_id`),
  KEY `fk_pagos_producto` (`producto_id`),
  KEY `fk_pagos_cupon` (`cupon_id`),
  KEY `fk_pagos_promocion` (`promocion_id`),
  KEY `fk_pagos_regalo` (`regalo_id`),
  CONSTRAINT `fk_pagos_cupon` FOREIGN KEY (`cupon_id`) REFERENCES `cupones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pagos_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pagos_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pagos_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pagos_promocion` FOREIGN KEY (`promocion_id`) REFERENCES `promociones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pagos_regalo` FOREIGN KEY (`regalo_id`) REFERENCES `regalos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pagos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- modo = 'live' por defecto a propósito: nunca se toca en filas ya
-- existentes (todas son ventas reales previas a este cambio). Un pago
-- creado en modo prueba de Stripe se marca 'prueba' desde
-- stripe_create_intent.php, y usuario_tiene_acceso_curso()/
-- usuario_esta_inscrito_evento()/usuario_compro_producto() (auth.php) y los
-- reportes de panel/admin/reportes.php ya filtran modo = 'live'.
ALTER TABLE pagos
  ADD COLUMN IF NOT EXISTS modo ENUM('live','prueba') NOT NULL DEFAULT 'live' AFTER metodo_pago;

-- Trazabilidad de qué promoción/cupón se usó (columnas nuevas — las FK que
-- las conectan a promociones/cupones se agregan más abajo en este archivo,
-- después de que esas dos tablas ya existen).
ALTER TABLE pagos
  ADD COLUMN IF NOT EXISTS cupon_id INT UNSIGNED NULL AFTER producto_id,
  ADD COLUMN IF NOT EXISTS promocion_id INT UNSIGNED NULL AFTER cupon_id;

-- Trazabilidad de regalo (ver "Prestaciones y regalos" más abajo) — la FK a
-- regalos se agrega después en este archivo, tras crear esa tabla.
ALTER TABLE pagos
  ADD COLUMN IF NOT EXISTS regalo_id INT UNSIGNED NULL AFTER promocion_id;

-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
INSERT  IGNORE INTO `pagos` VALUES (6,67,NULL,NULL,2,NULL,NULL,NULL,1,150.00,'stripe','live','pi_3U1ZIzCs0458QG2X1Gof7yyQ','confirmado',NULL,'','2026-08-06 16:18:49','2026-08-06 16:18:49',67,'2026-08-06 21:58:25'),(25,67,NULL,9,NULL,NULL,NULL,NULL,1,500.00,'stripe','prueba','pi_3U8qZsKBZI0bAfyd0o2pVSFx','pendiente',NULL,'',NULL,NULL,NULL,'2026-08-26 23:49:56'),(48,79,NULL,9,NULL,NULL,NULL,NULL,1,500.00,'stripe','prueba','pi_3UBoBzKBZI0bAfyd1pbYjzLB','confirmado',NULL,'','2026-09-03 21:53:58','2026-09-03 21:53:58',NULL,'2026-09-04 03:53:31');
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `productos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('fisico','digital') NOT NULL DEFAULT 'fisico',
  `nombre` varchar(150) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `imagen` varchar(255) DEFAULT NULL,
  `archivo_digital` varchar(255) DEFAULT NULL,
  `stock` int(10) unsigned DEFAULT NULL COMMENT 'NULL = ilimitado (típico en digital)',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `mostrar_codigo_promocion` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_productos_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--

ALTER TABLE productos
  ADD COLUMN IF NOT EXISTS mostrar_codigo_promocion TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;

-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT  IGNORE INTO `productos` VALUES (1,'fisico','Playera Reto Arjuna','playera-reto-arjuna','Playera de algodón con el logo del Reto Arjuna.',350.00,NULL,NULL,20,1,0,'2026-07-30 04:20:11','2026-07-30 04:20:11'),(2,'digital','Guía práctica en PDF','guia-practica-pdf','Resumen descargable de los 8 retos del programa.',150.00,NULL,NULL,NULL,1,0,'2026-07-30 04:20:11','2026-08-06 22:15:01');
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progreso`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `progreso` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `curso_id` int(10) unsigned NOT NULL,
  `leccion_id` int(10) unsigned NOT NULL,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  `tiempo_visto_seg` int(10) unsigned NOT NULL DEFAULT 0,
  `ultimo_acceso` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_progreso_usuario_leccion` (`usuario_id`,`leccion_id`),
  KEY `fk_progreso_curso` (`curso_id`),
  KEY `fk_progreso_leccion` (`leccion_id`),
  CONSTRAINT `fk_progreso_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progreso_leccion` FOREIGN KEY (`leccion_id`) REFERENCES `lecciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progreso_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progreso`
--

LOCK TABLES `progreso` WRITE;
/*!40000 ALTER TABLE `progreso` DISABLE KEYS */;
/*!40000 ALTER TABLE `progreso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_intentos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `quiz_intentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `quiz_id` int(10) unsigned NOT NULL,
  `puntaje` int(10) unsigned NOT NULL,
  `total_preguntas` int(10) unsigned NOT NULL,
  `aprobado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_quiz_intentos_usuario` (`usuario_id`),
  KEY `fk_quiz_intentos_quiz` (`quiz_id`),
  CONSTRAINT `fk_quiz_intentos_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_intentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_intentos`
--

LOCK TABLES `quiz_intentos` WRITE;
/*!40000 ALTER TABLE `quiz_intentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `quiz_intentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_opciones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `quiz_opciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pregunta_id` int(10) unsigned NOT NULL,
  `texto` varchar(255) NOT NULL,
  `es_correcta` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_quiz_opciones_pregunta` (`pregunta_id`),
  CONSTRAINT `fk_quiz_opciones_pregunta` FOREIGN KEY (`pregunta_id`) REFERENCES `quiz_preguntas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_opciones`
--

LOCK TABLES `quiz_opciones` WRITE;
/*!40000 ALTER TABLE `quiz_opciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `quiz_opciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_preguntas`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `quiz_preguntas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` int(10) unsigned NOT NULL,
  `enunciado` text NOT NULL,
  `tipo` enum('opcion_multiple','verdadero_falso') NOT NULL DEFAULT 'opcion_multiple',
  `orden` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_quiz_preguntas_quiz` (`quiz_id`),
  CONSTRAINT `fk_quiz_preguntas_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_preguntas`
--

LOCK TABLES `quiz_preguntas` WRITE;
/*!40000 ALTER TABLE `quiz_preguntas` DISABLE KEYS */;
/*!40000 ALTER TABLE `quiz_preguntas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quizzes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `leccion_id` int(10) unsigned NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `puntaje_minimo_aprobar` int(10) unsigned NOT NULL DEFAULT 70,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quizzes_leccion` (`leccion_id`),
  CONSTRAINT `fk_quizzes_leccion` FOREIGN KEY (`leccion_id`) REFERENCES `lecciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quizzes`
--

LOCK TABLES `quizzes` WRITE;
/*!40000 ALTER TABLE `quizzes` DISABLE KEYS */;
/*!40000 ALTER TABLE `quizzes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios_perfil`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `usuarios_perfil` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rol` enum('estudiante','instructor','admin') NOT NULL DEFAULT 'estudiante',
  `stripe_modo_prueba` tinyint(1) NOT NULL DEFAULT 0,
  `password_hash` varchar(255) DEFAULT NULL,
  `google_id` varchar(64) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `es_prueba` tinyint(1) NOT NULL DEFAULT 0,
  `avatar_cache` varchar(255) DEFAULT NULL,
  `perfil_publico` tinyint(1) NOT NULL DEFAULT 1,
  `username_cache` varchar(255) NOT NULL,
  `email_cache` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_perfil_username` (`username_cache`),
  UNIQUE KEY `uq_usuarios_perfil_email` (`email_cache`),
  UNIQUE KEY `uq_usuarios_perfil_google_id` (`google_id`)
) ENGINE=InnoDB AUTO_INCREMENT=159 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Modo prueba de Stripe (ver stripe_helper.php): un admin puede activarlo
-- para probar pagos sin dinero real — se guarda por usuario, nunca en
-- sesión, para que quede encendido hasta que se apague a mano.
ALTER TABLE usuarios_perfil
  ADD COLUMN IF NOT EXISTS stripe_modo_prueba TINYINT(1) NOT NULL DEFAULT 0 AFTER rol;

-- Última sesión iniciada (ver login_user() en auth.php) — para el panel de
-- inactividad de usuarios (panel/admin/inactividad.php). NULL = nunca ha
-- iniciado sesión desde que existe esta columna (incluye TODAS las cuentas
-- ya existentes al momento de este ALTER, aunque sí hayan usado la
-- plataforma antes — no hay forma de reconstruir ese historial).
ALTER TABLE usuarios_perfil
  ADD COLUMN IF NOT EXISTS ultimo_login DATETIME NULL AFTER updated_at;

-- Perfiles públicos opcionales — activado por default (1), cada usuario lo
-- puede apagar desde "Mi perfil" si prefiere que su nombre no sea un link
-- clickeable para otros (foro por ahora). Solo se aplica el DEFAULT a
-- cuentas NUEVAS creadas después de este ALTER — una instalación vieja que
-- reciba esta columna por primera vez no le toca el valor a sus cuentas ya
-- existentes (ver deploy_auto.php: nunca se corren UPDATE en automático).
ALTER TABLE usuarios_perfil
  ADD COLUMN IF NOT EXISTS perfil_publico TINYINT(1) NOT NULL DEFAULT 1 AFTER avatar_cache;
ALTER TABLE usuarios_perfil
  MODIFY COLUMN perfil_publico TINYINT(1) NOT NULL DEFAULT 1;

-- Dumping data for table `usuarios_perfil`
--

LOCK TABLES `usuarios_perfil` WRITE;
/*!40000 ALTER TABLE `usuarios_perfil` DISABLE KEYS */;
INSERT  IGNORE INTO `usuarios_perfil` VALUES (9,'admin',0,'$2y$10$QH4GQwoGf7n16nBIqOwF6ebIP80Y5edwSF4BcpJbtmzNKlGRBW8q2',NULL,NULL,1,0,NULL,1,'EKALA','desarrollo@retoarjuna.org','2026-07-30 02:16:32','2026-09-03 16:00:32',NULL),(23,'estudiante',0,'$2y$10$WHUEgnqQAPewg3g2sTGVT.EsA01k2GKl1HNe6IcA1YrjNI/nrRd6C',NULL,'',1,1,NULL,1,'Otakur','prueba@gmail.com','2026-04-07 10:23:29','2026-09-03 16:00:32','2026-09-03 09:59:19'),(24,'estudiante',0,'$2y$10$6Pm3pJAMZgDaRFyblF9Cb.0zpJ/tpH9.QdXXobUTIcAHUcwnbHkKm',NULL,NULL,1,1,NULL,1,'insecto','insecto@gmail.com','2026-04-07 10:42:34','2026-09-03 16:00:32',NULL),(25,'estudiante',0,'$2y$10$tCxr6yqu03/Ez49QTAwkI.hVDJ/.eCMYn5ogN3CWcSvYmHtoHRMT6',NULL,NULL,1,0,NULL,1,'contacto','contacto@retoarjuna.org','2026-04-07 11:12:42','2026-09-03 16:00:32',NULL),(26,'estudiante',0,'$2y$10$chs7Ei8//FZO2NumovTID.qJ/9esOJs1zGhB3vp8RqEZSfDKW1H0.',NULL,NULL,1,0,NULL,1,'Olivia5181','oliviatrejosolis@gmail.com','2025-09-25 10:49:42','2026-09-03 16:00:32',NULL),(27,'estudiante',0,'$2y$10$Ax2t.WWiv.Pz5HHGsNmXeuXSRWwJ8eN8rZmVyyl/F1kB3bcQ1mSpa',NULL,NULL,1,0,NULL,1,'Rousse','rosalbitascg@yahoo.com.mx','2025-09-25 11:03:18','2026-09-03 16:00:32',NULL),(28,'estudiante',0,'$2y$10$ui2f9HUhzTn3WFwz42..VuOGLfoyDf1PyTqQR7CsylYWlVNxU7mN.',NULL,NULL,1,0,NULL,1,'Ruth','ruthmurciac@gmail.com','2025-09-25 12:00:19','2026-09-03 16:00:32',NULL),(29,'estudiante',0,'$2y$10$3m7qBRNb2L0OSz6CnkJxcuZQcasUAe7PC/1k2jsPCyMYSsBBUFlSC',NULL,NULL,1,0,NULL,1,'LIDIA','lidiaromerosal@gmail.com','2025-09-25 18:04:37','2026-09-03 16:00:32',NULL),(30,'estudiante',0,'$2y$10$xlw6voMXurCZCV14iOA1SuWu81dLWfyZBgo5f7SHh60mceGh9hKS2',NULL,NULL,1,0,NULL,1,'Georgia','gcarvajal096@gmail.com','2025-09-25 20:27:23','2026-09-03 16:00:32',NULL),(31,'estudiante',0,'$2y$10$QgN6iTTRF82XeiFDOWSyhuuaBr9XxPD4sESE6JjL2BG8FWztPGNBO',NULL,NULL,1,0,NULL,1,'Nicandro','nick_y_sony@hotmail.com','2025-09-25 20:33:39','2026-09-03 16:00:32',NULL),(32,'estudiante',0,'$2y$10$/SyROkVOfwHqSeyGaxAOlev25JfZ8J2K7W/VDSpGsFP1.76StIyQy',NULL,NULL,1,0,NULL,1,'Krishna_Das','krishnadasyoga@gmail.com','2025-09-26 18:34:36','2026-09-03 16:00:32',NULL),(33,'estudiante',0,'$2y$10$8n9IZuJpeel1.V1swAhsau4q00MbjQwSUJVea4SWCfj2WSUSdDu/i',NULL,NULL,1,0,NULL,1,'MarisolViBa','vibmarisol1975@gmail.com','2025-09-26 21:31:31','2026-09-03 16:00:32',NULL),(34,'estudiante',0,'$2y$10$IQRsC3FRZv24lA.JWLzvKuuZwmhmf2J7c8dAQ.9LOnp5ZT53ilihi',NULL,NULL,1,0,NULL,1,'Elba','ithzes@gmail.com','2025-10-02 18:59:18','2026-09-03 16:00:32',NULL),(35,'estudiante',0,'$2y$10$V2spcFYd4vxmQz1SeFjKJ.JKOJfYs7JX1vYoQ2jPDEKJ7N8BL43pK',NULL,NULL,1,0,NULL,1,'Gaby','gabrielanavad@gmail.com','2025-10-09 03:06:00','2026-09-03 16:00:32',NULL),(36,'estudiante',0,'$2y$10$Ug55EkmZLehKyI81tP5xT.fa/sfGeMCXQxEcxNYOrCFEcWW8w0qTq',NULL,NULL,1,0,NULL,1,'Iv-','ivontellez@gmail.com','2025-10-15 23:52:55','2026-09-03 16:00:32',NULL),(37,'estudiante',0,'$2y$10$qQqFiAKe.P.wdX8XCpwOPuU6WIyTuFTAdq9/eSTMsY/y8/kOTS66i',NULL,NULL,1,0,NULL,1,'Alex','howek223@gmail.com','2025-11-13 12:02:20','2026-09-03 16:00:32',NULL),(38,'estudiante',0,'$2y$10$U/fFwXZYZ.NjQBejA55tXO5HULfgHv1ka7xeEgPVJy10xejUiYL8a',NULL,NULL,1,0,NULL,1,'Mireille','mireille.g0518@gmail.com','2025-11-13 15:20:15','2026-09-03 16:00:32',NULL),(39,'estudiante',0,'$2y$10$63vkYXjjpZu40pRdhcuPKea10WJjTlZ3FiIk/1GFwPjDSzTp7KXhi',NULL,NULL,1,0,NULL,1,'vixtor','victormanuelguzmanvazquez01@gmail.com','2025-11-13 17:22:43','2026-09-03 16:00:32',NULL),(40,'estudiante',0,'$2y$10$IzXgChAr5Mdk2BpJxxvz6.WlO5fL1752FHb5o2hRzXy3alD5RN8lS',NULL,NULL,1,0,NULL,1,'MarAvati','madelcabave@gmail.com','2025-11-13 20:11:37','2026-09-03 16:00:32',NULL),(41,'estudiante',0,'$2y$10$phALRSClLnfv0oNaOzKaQObngv3YfJPx/UJrC63mG2d5BU7CqrytS',NULL,NULL,1,0,NULL,1,'7721981212','joseluisprabhu@mail.com','2025-11-18 07:59:58','2026-09-03 16:00:32',NULL),(42,'estudiante',0,'$2y$10$nELOztzwbIBU5TOr39qqreD8jXxVgtFenvsfBrvYTpNolPPHHX2Fe',NULL,NULL,1,0,NULL,1,'ulises','jlruiz1111@gmail.com','2025-11-21 18:44:22','2026-09-03 16:00:32',NULL),(43,'estudiante',0,'$2y$10$NUkH6sYROHN3e8Txi.a3W.Oqtm0eFRDL0WelF78/ko.woJ1D9nK56',NULL,NULL,1,0,NULL,1,'Ramanujan','jorge.marquez.diaz@gmail.com','2025-11-21 18:44:29','2026-09-03 16:00:32',NULL),(44,'estudiante',0,'$2y$10$RMGXzfub8WgmZ2UzxnCDVOWWXdJW/zHj4nnIbiPvvYe2PAXuawWw.',NULL,NULL,1,0,NULL,1,'Luneta','lunita2605@gmail.com','2025-11-21 21:20:54','2026-09-03 16:00:32',NULL),(45,'estudiante',0,'$2y$10$QenU9qsvIQnnJ4TWN9tHs.jgc/JK4goTi8sCyD8jqHP05L99q6F4e',NULL,NULL,1,0,NULL,1,'Nube','nubeblanca26@outlook.com','2025-11-22 00:37:42','2026-09-03 16:00:32',NULL),(46,'estudiante',0,'$2y$10$ZnRjTjluciknIJ/CB0fO5.fokmCmcCfvYyor3PD7KnKRcMxN9CfhK',NULL,NULL,1,0,NULL,1,'BettyGarcia','bettylunamagia@gmail.com','2025-11-22 11:32:02','2026-09-03 16:00:32',NULL),(47,'estudiante',0,'$2y$10$SsF2aNROkqB8UedmdctYx.yUg1QjCvd/aBwEVlYEQYZhhQGlBOdVK',NULL,NULL,1,0,NULL,1,'ESME','esmeralda.gonzalezh@gmail.com','2025-11-22 17:56:06','2026-09-03 16:00:32',NULL),(48,'estudiante',0,'$2y$10$62h9PL8XVcLBOYcHMRo0x.07G2u4nDCmS2I7v7mHwpwummcb44Xqy',NULL,NULL,1,0,NULL,1,'Sandra','sandraraquelr@yahoo.com.mx','2025-11-23 07:55:21','2026-09-03 16:00:32',NULL),(49,'estudiante',0,'$2y$10$I1BQyls91OsXju0mf6OyLeeOSqV8KbIz7v91IJA6z9kqeRcAaPSNi',NULL,NULL,1,0,NULL,1,'VIKTOR','VICTORMANUELSOY01@GMAIL.COM','2025-11-23 12:11:15','2026-09-03 16:00:32',NULL),(50,'estudiante',0,'$2y$10$vZV2n4cilbDlBKjlsatMGOuQeK9l/q4HLtDbyJ.OW5BWP9mtoT0Cu',NULL,NULL,1,0,NULL,1,'Jorge-1988','psykomaton@gmail.com','2025-11-24 02:28:39','2026-09-03 16:00:32',NULL),(51,'estudiante',0,'$2y$10$1BJKVdxpEQO58fuCySwbeubKvISUcBRubNy1oRzjx9KlY3RobAqgu',NULL,NULL,1,0,NULL,1,'Antonio','zimeria@hotmail.com','2025-11-24 22:02:55','2026-09-03 16:00:32',NULL),(52,'estudiante',0,'$2y$10$W10sQsd87EIyTGW5bWA1uuIH3amLRt2jiWHoOx1NmaVEknGE6N8ZG',NULL,NULL,1,0,NULL,1,'Sandy','sandysrtjf4@gmail.com','2026-03-27 18:46:03','2026-09-03 16:00:32',NULL),(53,'estudiante',0,'$2y$10$RH9SH593xoGfj1YCcn0kSeNnz6/7qCifyEuSFGjuqIfrpyvkllkiO',NULL,NULL,1,0,NULL,1,'Yopas','srivas.cervantes@hotmail.com','2026-03-27 18:54:19','2026-09-03 16:00:32',NULL),(54,'estudiante',0,'$2y$10$vSdMEATnwJVf9XH.iB4sgO74EGV5U8IY8wbVTsFnhRd95i8pFXy2O',NULL,NULL,1,0,NULL,1,'Alejandra-Toledo','aleedisat@gmail.com','2026-03-27 18:54:29','2026-09-03 16:00:32',NULL),(55,'estudiante',0,'$2y$10$8tb89BfGtRfuyNIWwBnvYek6tz8hT5sTPbpQyMdVhQ7N8gei5ndyO',NULL,NULL,1,0,NULL,1,'Prishveda','prishveda@gmail.com','2026-03-27 18:54:55','2026-09-03 16:00:32',NULL),(56,'estudiante',0,'$2y$10$O.6OItILqo0ja33bI.FB9OFeOZkU0TUf/vhb994dhHfL/H75.xIhq',NULL,NULL,1,0,NULL,1,'Ivon','arq.ivondeltoro@gmail.com','2026-03-28 05:18:46','2026-09-03 16:00:32',NULL),(57,'estudiante',0,'$2y$10$WgcdQ14uHG4feBHZ5ZLqaO3jVUw2hX9Kvuj/mDQz1/T1h74GVjyOm',NULL,NULL,1,0,NULL,1,'Mayela','amayelaaltamirano@gmail.com','2026-03-28 07:25:37','2026-09-03 16:00:32',NULL),(58,'estudiante',0,'$2y$10$.8ivKFvK0EvDjjiDFKktIuVSxbBXY4JdgMOxiaTDOBl8L4GAqAxL.',NULL,NULL,1,0,NULL,1,'Lore','potenciagrados@gmail.com','2026-03-28 09:52:45','2026-09-03 16:00:32',NULL),(59,'estudiante',0,'$2y$10$iW5mxcfneJfjDjOkzevNZOWwTZObv/u0cUrHD0zDlmgzo21/1HPce',NULL,NULL,1,0,NULL,1,'Ana','anagpemojica@gmail.com','2026-03-29 02:20:21','2026-09-03 16:00:32',NULL),(60,'estudiante',0,'$2y$10$SDVogGcBH5Pci6oHzo01x.huoK8FP7S1JKctrgqkUujjhAMWihBkS',NULL,NULL,1,0,NULL,1,'bill233','BilloNali69@gmail.com','2026-04-03 15:44:29','2026-09-03 16:00:32',NULL),(61,'estudiante',0,'$2y$10$ILx9gpVlD1CHRmY0HLhW5OKUJjf/pNHGZcouW4gOHFuwLGodFLioO',NULL,NULL,1,1,NULL,1,'bimbi','respaldo1eka@gmail.com','2026-04-04 21:17:54','2026-09-03 16:00:32',NULL),(62,'admin',0,'$2y$10$X9oXAxtcvf3MBXpv07U.V.LWAetnA3wmL5zVf08T3ygnSiLR7x5ny','114319072660196025935',NULL,1,0,NULL,1,'Srivas','srivasthakur@gmail.com','2026-04-08 00:15:28','2026-09-03 16:00:32',NULL),(63,'estudiante',0,'$2y$10$xVRmS0JMJWs7FvTORR1Zw./8pCcaMsGW//fgEttUwxBTdVCebFpEO',NULL,NULL,1,0,NULL,1,'San','sandinadelcerrito@gmail.com','2026-04-10 05:00:38','2026-09-03 16:00:32',NULL),(64,'estudiante',0,'$2y$10$PkqMaiSD34PmGIDKnlfyOOHv699TlQG5Rnp0Gc4qdLP35BYXEbLeW',NULL,NULL,1,0,NULL,1,'Ximena','ximena_es@outlook.es','2026-04-10 20:43:59','2026-09-03 16:00:32',NULL),(65,'estudiante',0,'$2y$10$36nN6NxsHH6carH7Wqp1WuTQl4../iVpQq.HU6Yd4kSpXNxv5s.oy',NULL,NULL,1,0,NULL,1,'dangy6c','dingdangyc@gmail.com','2026-04-14 09:20:21','2026-09-03 16:00:32',NULL),(67,'admin',0,NULL,'118061433859966034375','',1,0,NULL,1,'Eka','caiman.mistico@gmail.com','2026-08-02 13:20:40','2026-09-03 16:00:32','2026-08-31 22:28:51'),(79,'estudiante',1,'$2y$10$yyaOck73kkY/5UMGH9AmeeKe412N4FvmKRTN/ssExhLzpR3PcOP1G',NULL,'',1,1,NULL,1,'usuario','usuario@gmail.com','2026-08-11 04:00:42','2026-09-03 16:00:32','2026-08-31 22:27:56'),(100,'estudiante',0,'$2y$10$trSB.DNRft2Gz1HCvig4Q.rQ0ylifVWsigrbIqxRa2b2lwL4GCh7G',NULL,NULL,1,0,NULL,1,'Nube Verónica','nubeblanca796@gmail.com','2026-08-14 16:21:05','2026-09-03 16:00:32',NULL);
/*!40000 ALTER TABLE `usuarios_perfil` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-16 13:22:56


-- Correcciones a filas semilla que ya pudieran existir con el texto/nombre
-- viejo (renombres hechos en sesión anterior) -- se aplican por su valor
-- identificador estable, no por id. No-op si las filas no existen todavía o
-- ya están correctas. Seguro de correr antes o después de los INSERT de
-- arriba (busca por valor, no por id).
SET NAMES utf8mb4;
UPDATE `navbar_links` SET `texto` = 'Membresía' WHERE `url` = 'plataforma/index.php?action=membresia';
UPDATE `membresias` SET `nombre` = 'Membresía Camino Arjuna' WHERE `nombre` = 'Camino Arjuna';

-- Un Visitante sin sesión nunca debe ver el link de Membresía en el navbar
-- (checklist.txt H01/H02) -- se marca por valor, no por id, para que corra
-- igual de bien sobre una instalación nueva o una vieja.
UPDATE `navbar_links` SET `requiere_sesion` = 1 WHERE `url` = 'plataforma/index.php?action=membresia';

-- Antes, gratuito=1 daba acceso automático sin inscripción explícita — quien
-- ya tenía progreso real en un curso (evidencia de que ya lo estaba
-- cursando, no del bug) recibe su inscripción para no perder acceso a algo
-- que ya había empezado. Seguro de correr más de una vez (INSERT IGNORE +
-- UNIQUE KEY en la tabla) y en cualquier momento (usa columnas, no VALUES
-- posicionales, así que no depende de en qué punto del archivo se ejecute).
INSERT IGNORE INTO curso_inscripciones (usuario_id, curso_id)
SELECT DISTINCT usuario_id, curso_id FROM progreso;

-- foro/ se movió dentro de plataforma/ (2026-08-18) — cualquier valor
-- guardado como ruta relativa a la raíz del sitio que empezara con 'foro/'
-- necesita el prefijo 'plataforma/' delante. Nunca toca valores absolutos
-- (http.../ o /...), que ya se guardan completos. Idempotente: una vez
-- corregido, el valor ya no empieza con 'foro/' y el WHERE deja de
-- coincidir, así que correrlo de nuevo es un no-op.
UPDATE navbar_links SET url = CONCAT('plataforma/', url) WHERE url LIKE 'foro/%';
UPDATE cursos SET foro_url = CONCAT('plataforma/', foro_url) WHERE foro_url LIKE 'foro/%';
UPDATE eventos SET foro_url = CONCAT('plataforma/', foro_url) WHERE foro_url LIKE 'foro/%';
UPDATE lecciones SET foro_url = CONCAT('plataforma/', foro_url) WHERE foro_url LIKE 'foro/%';

-- =====================================================================
-- Motor de ofertas: promociones públicas + cupones internos
-- (checklist.txt secciones 4/9 — P01-P04, OF04-OF10, ADM02-ADM04).
-- Ver plataforma/backend/ofertas.php para la lógica que las consume.
-- =====================================================================

-- Una promoción es 1:1 con un solo ítem (igual que `pagos`: 4 columnas FK
-- nullable, exactamente una no-nula por fila — validado en PHP, no con
-- CHECK, que no se usa en ningún otro lado del proyecto).
CREATE TABLE IF NOT EXISTS `promociones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `curso_id` int(10) unsigned DEFAULT NULL,
  `evento_id` int(10) unsigned DEFAULT NULL,
  `producto_id` int(10) unsigned DEFAULT NULL,
  `membresia_id` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(150) NOT NULL COMMENT 'Se usa tal cual en el desglose del checkout (CHK02)',
  `modalidad` enum('promocion','preventa') NOT NULL DEFAULT 'promocion',
  `tipo_descuento` enum('porcentaje','precio_fijo') NOT NULL DEFAULT 'porcentaje',
  `valor` decimal(10,2) NOT NULL COMMENT '% si tipo_descuento=porcentaje, precio final MXN si precio_fijo',
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `combinable` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_promociones_curso` (`curso_id`),
  KEY `idx_promociones_evento` (`evento_id`),
  KEY `idx_promociones_producto` (`producto_id`),
  KEY `idx_promociones_membresia` (`membresia_id`),
  KEY `idx_promociones_vigencia` (`fecha_inicio`,`fecha_fin`),
  CONSTRAINT `fk_promociones_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promociones_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promociones_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_promociones_membresia` FOREIGN KEY (`membresia_id`) REFERENCES `membresias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cupón interno (calculado en la plataforma, no vía Stripe Promotion Codes
-- — ese mecanismo sigue vivo en paralelo en stripe_aplicar_promocion.php,
-- no se toca). `codigo` único; sin filas en cupon_alcance = cupón global.
CREATE TABLE IF NOT EXISTS `cupones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `tipo_descuento` enum('porcentaje','monto') NOT NULL DEFAULT 'porcentaje',
  `valor` decimal(10,2) NOT NULL,
  `fecha_inicio` datetime DEFAULT NULL COMMENT 'NULL = sin límite inferior',
  `fecha_fin` datetime DEFAULT NULL COMMENT 'NULL = sin vencimiento',
  `usos_totales` int(10) unsigned DEFAULT NULL COMMENT 'NULL = ilimitado, mismo criterio que productos.stock',
  `combinable` tinyint(1) NOT NULL DEFAULT 0,
  `origen` enum('admin','incentivo_cuenta_nueva') NOT NULL DEFAULT 'admin' COMMENT 'incentivo_cuenta_nueva = P04/ADM04, reusa este mismo sistema',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cupones_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relación N:M cupón↔ítem — "productos aplicables" de P02/ADM03 (un cupón sí
-- puede aplicar a varios ítems, a diferencia de una promoción).
CREATE TABLE IF NOT EXISTS `cupon_alcance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cupon_id` int(10) unsigned NOT NULL,
  `curso_id` int(10) unsigned DEFAULT NULL,
  `evento_id` int(10) unsigned DEFAULT NULL,
  `producto_id` int(10) unsigned DEFAULT NULL,
  `membresia_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cupon_alcance_cupon` (`cupon_id`),
  CONSTRAINT `fk_cupon_alcance_cupon` FOREIGN KEY (`cupon_id`) REFERENCES `cupones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cupon_alcance_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cupon_alcance_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cupon_alcance_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cupon_alcance_membresia` FOREIGN KEY (`membresia_id`) REFERENCES `membresias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las columnas cupon_id/promocion_id de pagos ya se agregaron junto a su
-- propio CREATE TABLE, más arriba (antes de su INSERT de datos) — aquí solo
-- se agregan las FK, que sí pueden esperar a que cupones/promociones existan.
ALTER TABLE pagos DROP FOREIGN KEY IF EXISTS fk_pagos_cupon;
ALTER TABLE pagos ADD CONSTRAINT fk_pagos_cupon FOREIGN KEY (cupon_id) REFERENCES cupones (id) ON DELETE SET NULL;
ALTER TABLE pagos DROP FOREIGN KEY IF EXISTS fk_pagos_promocion;
ALTER TABLE pagos ADD CONSTRAINT fk_pagos_promocion FOREIGN KEY (promocion_id) REFERENCES promociones (id) ON DELETE SET NULL;

-- Mismo caso que pagos: las columnas de membresia_suscripciones ya se
-- agregaron junto a su propio CREATE TABLE, aquí solo la FK.
ALTER TABLE membresia_suscripciones DROP FOREIGN KEY IF EXISTS fk_membsusc_cupon;
ALTER TABLE membresia_suscripciones ADD CONSTRAINT fk_membsusc_cupon FOREIGN KEY (cupon_id) REFERENCES cupones (id) ON DELETE SET NULL;
ALTER TABLE membresia_suscripciones DROP FOREIGN KEY IF EXISTS fk_membsusc_promocion;
ALTER TABLE membresia_suscripciones ADD CONSTRAINT fk_membsusc_promocion FOREIGN KEY (promocion_id) REFERENCES promociones (id) ON DELETE SET NULL;

-- =====================================================================
-- Foro: "categoría libre" — tercera taxonomía de un tema, aparte de
-- curso/evento y de las etiquetas (foro_categorias). Cualquier usuario elige
-- una existente o escribe una nueva al publicar, sin aprobación de admin.
-- =====================================================================
CREATE TABLE IF NOT EXISTS `foro_categorias_libres` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `creado_por` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_foro_categorias_libres_slug` (`slug`),
  KEY `fk_foro_categorias_libres_creado_por` (`creado_por`),
  CONSTRAINT `fk_foro_categorias_libres_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La columna categoria_libre_id de foro_temas ya se agregó junto a su propio
-- CREATE TABLE, más arriba (antes de su INSERT de datos) — aquí solo se
-- agrega la FK, que sí puede esperar a que foro_categorias_libres exista.
ALTER TABLE foro_temas DROP FOREIGN KEY IF EXISTS fk_foro_temas_categoria_libre;
ALTER TABLE foro_temas ADD CONSTRAINT fk_foro_temas_categoria_libre FOREIGN KEY (categoria_libre_id) REFERENCES foro_categorias_libres (id) ON DELETE SET NULL;
ALTER TABLE foro_temas ADD KEY IF NOT EXISTS idx_foro_temas_categoria_libre (categoria_libre_id);

-- =====================================================================
-- Audio protegido en lecciones de curso. A diferencia del "entregable
-- digital" de productos (uploads.php: procesar_subida_archivo_digital,
-- nombre aleatorio como única protección), aquí el archivo se guarda en
-- uploads/audios_protegidos/ con acceso directo bloqueado por .htaccess —
-- todo el streaming pasa por backend/audio_stream.php, que revalida sesión
-- + inscripción en cada request (mismo criterio que ya usa leccion.php).
-- Nota: el ALTER que aquí agregaba 'audio' al enum de tipo_contenido quedó
-- obsoleto — el editor unificado (ver el bloque de `lecciones` más arriba)
-- ya cubre toda la migración del enum, incluido ese valor intermedio.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `leccion_audios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `leccion_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL hasta que se liga a una lección real, ver leccion_audio_embed_subir.php',
  `ruta_archivo` varchar(300) NOT NULL COMMENT 'Relativa a plataforma/, dentro de uploads/audios_protegidos/',
  `titulo` varchar(150) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leccion_audios_leccion` (`leccion_id`),
  CONSTRAINT `fk_leccion_audios_leccion` FOREIGN KEY (`leccion_id`) REFERENCES `lecciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El editor unificado sube el audio ANTES de que la lección exista (nace
-- huérfano, leccion_id NULL, hasta que se guarda el formulario) — instalación
-- vieja: se quita el NOT NULL.
ALTER TABLE leccion_audios MODIFY COLUMN leccion_id int(10) unsigned DEFAULT NULL;

-- =====================================================================
-- Calificaciones de curso/evento. Exactamente una de curso_id/evento_id no
-- nula por fila (se valida en PHP, igual que el resto del proyecto). Solo
-- se puede calificar tras terminar (certificados ya emitido — mismo
-- criterio que verificar_y_emitir_certificado()/reconocimiento de evento).
-- NULL no cuenta como duplicado en UNIQUE KEY (comportamiento MySQL), así
-- que un mismo usuario puede tener una fila de curso y otra de evento sin
-- chocar entre sí.
-- =====================================================================
CREATE TABLE IF NOT EXISTS `calificaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `curso_id` int(10) unsigned DEFAULT NULL,
  `evento_id` int(10) unsigned DEFAULT NULL,
  `puntuacion` tinyint(3) unsigned NOT NULL COMMENT '1 a 5',
  `comentario` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_calificaciones_usuario_curso` (`usuario_id`,`curso_id`),
  UNIQUE KEY `uq_calificaciones_usuario_evento` (`usuario_id`,`evento_id`),
  KEY `idx_calificaciones_curso` (`curso_id`),
  KEY `idx_calificaciones_evento` (`evento_id`),
  CONSTRAINT `fk_calificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calificaciones_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calificaciones_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Prestaciones y regalos. Quien ya tiene acceso a un curso/evento puede
-- regalar ese mismo acceso (o un descuento) a alguien más, dentro de un
-- permiso que configura el admin por curso/evento (regalo_configuracion) —
-- nunca se otorga por default. `regalos` es la ÚNICA fuente de verdad del
-- estado de cada enlace (disponibilidad/reclamación/consumo/vigencia): la
-- UI siempre lee `estado` de aquí, nunca reconstruye una copia local.
-- Deliberadamente separado de `cupones` (motor de ofertas) — un regalo
-- nunca debe presentarse ni tratarse como cupón comercial, aunque ambos
-- terminen reduciendo el precio: origen y nombre se conservan distintos en
-- toda la plataforma (checkout, pagos, admin).
-- =====================================================================
CREATE TABLE IF NOT EXISTS `regalo_configuracion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `curso_id` int(10) unsigned DEFAULT NULL,
  `evento_id` int(10) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `descuento_pct` decimal(5,2) NOT NULL COMMENT '100 = acceso completo gratis; menor = % de descuento',
  `max_usuarios_habilitados` int(10) unsigned DEFAULT NULL COMMENT 'NULL = cualquier comprador puede regalar; con valor, tope de cuentas distintas con el permiso',
  `enlaces_por_usuario` int(10) unsigned NOT NULL DEFAULT 1,
  `vigencia_dias` int(10) unsigned DEFAULT NULL COMMENT 'NULL = los enlaces generados no vencen',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_regalo_config_curso` (`curso_id`),
  UNIQUE KEY `uq_regalo_config_evento` (`evento_id`),
  CONSTRAINT `fk_regalo_config_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regalo_config_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `regalos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) NOT NULL COMMENT 'Único e irrepetible, va en el enlace público',
  `configuracion_id` int(10) unsigned NOT NULL,
  `usuario_da_id` int(10) unsigned NOT NULL,
  `usuario_recibe_id` int(10) unsigned DEFAULT NULL COMMENT 'Se llena al reclamar (abrir el enlace ya logueado)',
  `estado` enum('disponible','reclamado','aceptado','revocado') NOT NULL DEFAULT 'disponible',
  `vence_en` datetime DEFAULT NULL,
  `reclamado_en` datetime DEFAULT NULL,
  `aceptado_en` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_regalos_codigo` (`codigo`),
  KEY `idx_regalos_config_dador` (`configuracion_id`,`usuario_da_id`),
  KEY `idx_regalos_recibe` (`usuario_recibe_id`),
  CONSTRAINT `fk_regalos_config` FOREIGN KEY (`configuracion_id`) REFERENCES `regalo_configuracion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regalos_da` FOREIGN KEY (`usuario_da_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regalos_recibe` FOREIGN KEY (`usuario_recibe_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las FK de curso_inscripciones.regalo_id / evento_inscripciones.regalo_id /
-- pagos.regalo_id esperaban a que `regalos` existiera.
ALTER TABLE curso_inscripciones DROP FOREIGN KEY IF EXISTS fk_curso_inscripciones_regalo;
ALTER TABLE curso_inscripciones ADD CONSTRAINT fk_curso_inscripciones_regalo FOREIGN KEY (regalo_id) REFERENCES regalos (id) ON DELETE SET NULL;
ALTER TABLE evento_inscripciones DROP FOREIGN KEY IF EXISTS fk_evento_inscripciones_regalo;
ALTER TABLE evento_inscripciones ADD CONSTRAINT fk_evento_inscripciones_regalo FOREIGN KEY (regalo_id) REFERENCES regalos (id) ON DELETE SET NULL;
ALTER TABLE pagos DROP FOREIGN KEY IF EXISTS fk_pagos_regalo;
ALTER TABLE pagos ADD CONSTRAINT fk_pagos_regalo FOREIGN KEY (regalo_id) REFERENCES regalos (id) ON DELETE SET NULL;

-- =====================================================================
-- El progreso por lección nacía curso-only (curso_id NOT NULL) — para que
-- "Marcar como completada" también funcione en lecciones de evento, se
-- vuelve nullable y se agrega evento_id, mismo patrón mutuamente excluyente
-- que ya usa `lecciones`/`calificaciones` (exactamente uno de los dos no
-- nulo, validado en PHP en backend/progreso_back.php). La UNIQUE KEY
-- (usuario_id, leccion_id) no cambia — ya alcanza para ambos casos, porque
-- leccion_id ya es único sin importar si la lección es de curso o evento.
-- El certificado por progreso (verificar_y_emitir_certificado()) sigue
-- siendo exclusivo de curso — los eventos tienen su propio reconocimiento
-- por asistencia, sin relación con esto.
-- =====================================================================
ALTER TABLE progreso MODIFY COLUMN curso_id int(10) unsigned DEFAULT NULL;
ALTER TABLE progreso ADD COLUMN IF NOT EXISTS evento_id int(10) unsigned DEFAULT NULL AFTER curso_id;
ALTER TABLE progreso DROP FOREIGN KEY IF EXISTS fk_progreso_evento;
ALTER TABLE progreso ADD CONSTRAINT fk_progreso_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE;

-- =====================================================================
-- Recuperar contraseña ("olvidé mi contraseña"): el token nunca se guarda en
-- claro, solo su hash sha256 — igual que password_hash guarda un hash y no la
-- contraseña, así una fuga de esta tabla no permite reusar ningún enlace.
-- Un enlace vence a la hora (expira_en) y se marca usado=1 al consumirse, para
-- que no se pueda reutilizar ni siquiera dentro de esa hora. Se referencia a
-- `usuarios_perfil`, que ya existe en este punto del dump.
-- =====================================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_resets_token_hash` (`token_hash`),
  KEY `idx_password_resets_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE password_resets DROP FOREIGN KEY IF EXISTS fk_password_resets_usuario;
ALTER TABLE password_resets ADD CONSTRAINT fk_password_resets_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE;

