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

-- Nombre a mostrar en el certificado (el usuario lo puede personalizar desde
-- "Mis reconocimientos", ej. su nombre completo en vez del username) — si
-- queda NULL/vacío, certificado.php cae de vuelta a username_cache.
ALTER TABLE certificados
  ADD COLUMN IF NOT EXISTS nombre_certificado VARCHAR(255) DEFAULT NULL AFTER tipo;

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

-- CHK05 — grupo de WhatsApp asociado al curso. URL vacía/NULL = sin grupo,
-- el bloque de invitación no se muestra en ningún lado (checklist.txt).
ALTER TABLE cursos ADD COLUMN IF NOT EXISTS whatsapp_grupo_url VARCHAR(500) NULL AFTER foro_url;
ALTER TABLE cursos ADD COLUMN IF NOT EXISTS whatsapp_grupo_texto VARCHAR(255) NULL AFTER whatsapp_grupo_url;

-- Sin datos de contenido aquí a propósito — este archivo solo trae
-- estructura + cuentas admin esenciales (ver usuarios_perfil más abajo). El
-- contenido real (cursos/eventos/productos/etc.) vive únicamente en cada
-- entorno, nunca en el repo.

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

-- Origen "membresía regular": inscripción automática mientras el usuario
-- tenga membresía activa/gracia y el evento sea es_regular=1. Nunca se
-- reetiqueta una fila 'propio' hacia este valor ni viceversa — ver
-- backend/eventos_regulares.php. MODIFY (no ADD COLUMN) porque solo se
-- amplía un enum ya existente — es idempotente, agregar un valor que ya
-- está en el enum no falla ni duplica nada.
ALTER TABLE evento_inscripciones
  MODIFY COLUMN metodo ENUM('propio','manual','membresia_regular') NOT NULL DEFAULT 'propio';

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

-- CHK05 — mismo criterio que cursos arriba.
ALTER TABLE eventos ADD COLUMN IF NOT EXISTS whatsapp_grupo_url VARCHAR(500) NULL AFTER foro_url;
ALTER TABLE eventos ADD COLUMN IF NOT EXISTS whatsapp_grupo_texto VARCHAR(255) NULL AFTER whatsapp_grupo_url;

-- "Evento regular" (inscripción automática de miembros activos, no
-- promocional) — implica incluido_membresia=1 siempre (forzado también en
-- contenido_form.php), nunca cobra a un miembro. Ver
-- backend/eventos_regulares.php para la sincronización real.
ALTER TABLE eventos ADD COLUMN IF NOT EXISTS es_regular TINYINT(1) NOT NULL DEFAULT 0 AFTER incluido_membresia;

-- Sin datos aquí — ver nota junto a `cursos`.

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

-- Sin datos aquí — ver nota junto a `cursos`.

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

-- Jerarquía real padre→hijo entre respuestas (ver tema.php: indentación +
-- conector visual + "Citar") — antes de esto foro_respuestas era plana, sin
-- ninguna columna que dijera "esta respuesta contesta a esta otra". NULL =
-- respuesta de primer nivel (directa al tema). ON DELETE SET NULL, no
-- CASCADE: si se borra la respuesta padre, la hija no debe desaparecer en
-- cascada, solo pierde la referencia visual y pasa a verse como de primer
-- nivel (mismo criterio que editado_por/eliminado_por de esta misma tabla).
ALTER TABLE foro_respuestas
  ADD COLUMN IF NOT EXISTS respuesta_padre_id INT UNSIGNED NULL AFTER tema_id;

ALTER TABLE foro_respuestas DROP FOREIGN KEY IF EXISTS fk_foro_respuestas_padre;
ALTER TABLE foro_respuestas ADD CONSTRAINT fk_foro_respuestas_padre FOREIGN KEY (respuesta_padre_id) REFERENCES foro_respuestas (id) ON DELETE SET NULL;

-- Sin datos aquí — ver nota junto a `cursos`.

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

-- Sin datos aquí — ver nota junto a `cursos`.
--

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
-- Sin datos aquí — ver nota junto a `cursos`.
--


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
-- Sin datos aquí — ver nota junto a `cursos`.
--


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

-- Sin datos aquí — ver nota junto a `cursos`.
--


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

-- Sin datos aquí — ver nota junto a `cursos`.
--


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
-- Sin datos aquí — ver nota junto a `cursos`.
--


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

-- Sin datos aquí — ver nota junto a `cursos`.
--


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

-- Sin datos aquí — ver nota junto a `cursos`.
--


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
INSERT  IGNORE INTO `usuarios_perfil` VALUES (9,'admin',0,NULL,NULL,NULL,1,0,NULL,1,'EKALA','desarrollo@retoarjuna.org','2026-07-30 02:16:32','2026-09-03 16:00:32',NULL),(62,'admin',0,NULL,NULL,NULL,1,0,NULL,1,'Srivas','srivasthakur@gmail.com','2026-04-08 00:15:28','2026-09-03 16:00:32',NULL),(67,'admin',0,NULL,NULL,NULL,1,0,NULL,1,'Eka','caiman.mistico@gmail.com','2026-08-02 13:20:40','2026-09-03 16:00:32',NULL);
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

-- Rediseño "Regalar descuento" (varios tipos de % configurables, en tabla) +
-- "Regalar acceso" (sección propia, no un caso especial de descuento=100) —
-- regalo_configuracion pasa a ser solo el contenedor con los interruptores y
-- los límites de "Regalar acceso"; descuento_pct/max_usuarios_habilitados/
-- enlaces_por_usuario/vigencia_dias de ARRIBA quedan sin uso (no se borran:
-- filas viejas de `regalos` en producción todavía los referencian para
-- historial) y se sustituyen por las columnas de acceso de abajo +
-- regalo_tipos_descuento para el descuento.
ALTER TABLE regalo_configuracion MODIFY COLUMN descuento_pct decimal(5,2) NULL COMMENT 'Obsoleto — ver regalo_tipos_descuento. Se conserva solo por compatibilidad con filas antiguas.';
ALTER TABLE regalo_configuracion ADD COLUMN IF NOT EXISTS activo_descuento tinyint(1) NOT NULL DEFAULT 0 AFTER activo;
ALTER TABLE regalo_configuracion ADD COLUMN IF NOT EXISTS activo_acceso tinyint(1) NOT NULL DEFAULT 0 AFTER activo_descuento;
ALTER TABLE regalo_configuracion ADD COLUMN IF NOT EXISTS acceso_max_usuarios_habilitados int(10) unsigned DEFAULT NULL COMMENT 'NULL = sin tope de cuentas que pueden regalar acceso completo' AFTER activo_acceso;
ALTER TABLE regalo_configuracion ADD COLUMN IF NOT EXISTS acceso_enlaces_por_usuario int(10) unsigned NOT NULL DEFAULT 1 AFTER acceso_max_usuarios_habilitados;
ALTER TABLE regalo_configuracion ADD COLUMN IF NOT EXISTS acceso_vigencia_dias int(10) unsigned DEFAULT NULL COMMENT 'NULL = los enlaces de acceso completo no vencen' AFTER acceso_enlaces_por_usuario;
-- `activo` (el interruptor original, previo a este rediseño) ya no se lee en
-- código nuevo — se conserva la columna para no romper el UNIQUE KEY
-- histórico, pero activo_descuento/activo_acceso son ahora la fuente real.

CREATE TABLE IF NOT EXISTS `regalo_tipos_descuento` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `configuracion_id` int(10) unsigned NOT NULL,
  `descuento_pct` decimal(5,2) NOT NULL COMMENT 'Descuento parcial — nunca 100 (eso es "Regalar acceso", ver regalo_configuracion.activo_acceso)',
  `max_usuarios_habilitados` int(10) unsigned DEFAULT NULL COMMENT 'NULL = sin tope de cuentas que pueden regalar este tipo de descuento',
  `enlaces_por_usuario` int(10) unsigned NOT NULL DEFAULT 1,
  `vigencia_dias` int(10) unsigned DEFAULT NULL COMMENT 'NULL = los enlaces generados con este tipo no vencen',
  `orden` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_regalo_tipos_descuento_config` (`configuracion_id`),
  CONSTRAINT `fk_regalo_tipos_descuento_config` FOREIGN KEY (`configuracion_id`) REFERENCES `regalo_configuracion` (`id`) ON DELETE CASCADE
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

-- NULL = este regalo fue de "acceso completo" (regalo_configuracion.activo_acceso);
-- con valor = referencia al tipo de descuento parcial usado (regalo_tipos_descuento,
-- que puede haberse borrado desde entonces — ON DELETE SET NULL conserva el
-- historial del regalo aunque el admin ya haya quitado ese tipo de descuento).
ALTER TABLE regalos ADD COLUMN IF NOT EXISTS tipo_descuento_id int(10) unsigned DEFAULT NULL AFTER configuracion_id;
ALTER TABLE regalos ADD KEY IF NOT EXISTS idx_regalos_tipo_descuento (tipo_descuento_id);
ALTER TABLE regalos DROP FOREIGN KEY IF EXISTS fk_regalos_tipo_descuento;
ALTER TABLE regalos ADD CONSTRAINT fk_regalos_tipo_descuento FOREIGN KEY (tipo_descuento_id) REFERENCES regalo_tipos_descuento (id) ON DELETE SET NULL;

-- Migración de datos (idempotente vía NOT EXISTS): cada regalo_configuracion
-- vieja tenía exactamente un descuento_pct — se traduce a una fila nueva de
-- regalo_tipos_descuento (100 = "Regalar acceso", <100 = "Regalar
-- descuento") para que ninguna configuración ya activa en producción se
-- desactive silenciosamente con este cambio de esquema.
UPDATE regalo_configuracion
SET activo_acceso = 1, acceso_max_usuarios_habilitados = max_usuarios_habilitados,
    acceso_enlaces_por_usuario = enlaces_por_usuario, acceso_vigencia_dias = vigencia_dias
WHERE activo = 1 AND descuento_pct >= 100 AND activo_descuento = 0 AND activo_acceso = 0;

INSERT INTO regalo_tipos_descuento (configuracion_id, descuento_pct, max_usuarios_habilitados, enlaces_por_usuario, vigencia_dias)
SELECT rc.id, rc.descuento_pct, rc.max_usuarios_habilitados, rc.enlaces_por_usuario, rc.vigencia_dias
FROM regalo_configuracion rc
WHERE rc.activo = 1 AND rc.descuento_pct < 100 AND rc.activo_descuento = 0
  AND NOT EXISTS (SELECT 1 FROM regalo_tipos_descuento t WHERE t.configuracion_id = rc.id);

UPDATE regalo_configuracion SET activo_descuento = 1 WHERE activo = 1 AND descuento_pct < 100 AND activo_descuento = 0;

-- Cada regalos.id viejo apuntaba implícitamente al único descuento de su
-- configuración — se enlaza ahora de forma explícita al tipo recién migrado
-- (NULL se deja tal cual para los que ya eran de acceso completo).
UPDATE regalos r
JOIN regalo_configuracion rc ON rc.id = r.configuracion_id
JOIN regalo_tipos_descuento t ON t.configuracion_id = rc.id
SET r.tipo_descuento_id = t.id
WHERE r.tipo_descuento_id IS NULL AND rc.descuento_pct < 100;

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

-- =====================================================================
-- Lista de espera del próximo Reto Arjuna (evento) — página
-- content/proximo_evento.php, mostrada cuando no hay ningún evento próximo
-- agendado. A diferencia de un formulario suelto de correo, requiere cuenta
-- real (nunca un email sin sesión): el botón "Avísenme" manda a
-- ?action=registro&volver=<esta misma página> — al volver ya con sesión
-- activa, la propia página inserta esta fila. Un admin, al crear/publicar un
-- evento nuevo, revisa esta tabla a mano para avisarles (sin envío
-- automático todavía, ver notificaciones_difusiones si algún día se agrega).
-- =====================================================================
CREATE TABLE IF NOT EXISTS `evento_lista_espera` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `notificado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evento_lista_espera_usuario` (`usuario_id`),
  CONSTRAINT `fk_evento_lista_espera_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios_perfil` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Campañas de correo masivo, segmentadas — panel/admin/email_campanas.php.
-- Cada fila es un envío ya disparado (no hay borradores: se redacta y se
-- manda en el mismo formulario) a un segmento de usuarios definido por
-- `segmento`. El envío real usa enviar_email() de mailer.php una vez por
-- destinatario — cada intento individual ya queda registrado en
-- notificaciones_log (tipo = 'campana_' + id de esta tabla), así que aquí
-- solo se guarda el resumen de la campaña, no destinatario por destinatario.
-- =====================================================================
CREATE TABLE IF NOT EXISTS `email_campanas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `asunto` varchar(200) NOT NULL,
  `cuerpo_html` text NOT NULL,
  `segmento` enum('todos','inactivos','miembros','sin_compra') NOT NULL,
  `creado_por` int(10) unsigned DEFAULT NULL,
  `total_destinatarios` int(10) unsigned NOT NULL DEFAULT 0,
  `total_enviados` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_campanas_creado_por` (`creado_por`),
  CONSTRAINT `fk_email_campanas_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios_perfil` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Plantillas personalizables de los correos transaccionales automáticos
-- (bienvenida, inscripción confirmada, membresía activada, recuperar
-- contraseña, certificado emitido) — panel/admin/email_plantillas.php.
-- `tipo` calza 1 a 1 con el mismo string que ya usa notificaciones_log.tipo
-- (ver mailer.php). Sin fila para un tipo (o con activo=0), mailer.php cae
-- de vuelta al texto default hardcodeado — esta tabla nunca es la única
-- fuente de verdad, solo un override opcional.
-- =====================================================================
CREATE TABLE IF NOT EXISTS `email_plantillas` (
  `tipo` enum('bienvenida','inscripcion','membresia','recuperar_contrasena','finalizacion') NOT NULL,
  `asunto` varchar(200) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje_html` text NOT NULL,
  `boton_texto` varchar(80) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Vigencia de cupones (panel/admin/cupon_form.php) — reemplaza la edición
-- manual de fecha_inicio/fecha_fin por 3 modalidades explícitas:
--   'siempre'  — sin fecha_fin, sin límite de meses (fecha_inicio/fin en NULL).
--   'meses'    — para membresías: el descuento se aplica como Stripe Coupon
--                duration='repeating' + duration_in_months=vigencia_meses, a
--                partir de la suscripción de cada usuario (no una fecha fija
--                del cupón) — ver membresia_iniciar.php. Para pagos únicos
--                (curso/evento/producto) no hay mensualidad que repetir, así
--                que ahí se comporta igual que 'siempre'.
--   'una_vez'  — usos_totales se fuerza a 1 al guardar (mismo mecanismo que
--                ya existía, solo expuesto como opción explícita del selector).
-- fecha_inicio/fecha_fin de `cupones` se conservan (se siguen leyendo en
-- ofertas.php) pero ya no se editan a mano en el form — cupon_form.php las
-- calcula a partir de esta vigencia al guardar.
-- =====================================================================
ALTER TABLE cupones
  ADD COLUMN IF NOT EXISTS vigencia_tipo ENUM('siempre','meses','una_vez') NOT NULL DEFAULT 'siempre' AFTER usos_totales,
  ADD COLUMN IF NOT EXISTS vigencia_meses INT UNSIGNED DEFAULT NULL AFTER vigencia_tipo;

-- =====================================================================
-- Membresía pagada con OXXO — Stripe no soporta OXXO (ni ningún voucher
-- en efectivo) como método de pago para cobros recurrentes automáticos
-- (a diferencia de tarjeta, no hay forma de "volver a cargar" un OXXO sin
-- que el usuario vaya de nuevo a la tienda). Se simula la recurrencia a
-- mano: cada mes, un cron genera un PaymentIntent OXXO nuevo por el precio
-- de un mes, el usuario lo paga en tienda, y payment_intent.succeeded
-- extiende periodo_actual_fin otro mes — ver
-- backend/membresia_oxxo_generar_vouchers.php (cron) y
-- backend/pagos/membresia_oxxo_iniciar.php (alta inicial).
--
-- 'oxxo_recurrente' en metodo distingue esta membresía de una 'transferencia'
-- (esa es 100% manual, confirmada por un admin) o 'stripe' (tarjeta, cobro
-- automático real de Stripe) — aquí el cobro se automatiza por nuestro propio
-- cron, no por Stripe.
-- 'gracia' en estado: el voucher del mes venció sin pagarse, pero el acceso
-- se mantiene 2 días más por si alcanza a pagar tarde — pasado ese plazo el
-- cron la pasa a 'vencida' (pierde acceso, igual que hoy hace un cobro de
-- Stripe fallido).
-- =====================================================================
ALTER TABLE membresia_suscripciones
  MODIFY COLUMN metodo ENUM('stripe','transferencia','manual','oxxo_recurrente') NOT NULL DEFAULT 'stripe',
  MODIFY COLUMN estado ENUM('pendiente','activa','gracia','cancelada','vencida') NOT NULL DEFAULT 'activa';

CREATE TABLE IF NOT EXISTS `membresia_vouchers_oxxo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `suscripcion_id` int(10) unsigned NOT NULL,
  `payment_intent_id` varchar(191) NOT NULL,
  `numero` varchar(64) DEFAULT NULL,
  `url_voucher` varchar(500) DEFAULT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fin` date NOT NULL,
  `vence_en` datetime DEFAULT NULL,
  `estado` enum('pendiente','pagado','vencido') NOT NULL DEFAULT 'pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_membresia_voucher_intent` (`payment_intent_id`),
  KEY `idx_membresia_voucher_suscripcion` (`suscripcion_id`, `estado`),
  CONSTRAINT `fk_membresia_voucher_suscripcion` FOREIGN KEY (`suscripcion_id`) REFERENCES `membresia_suscripciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- notificaciones.tipo necesita un valor propio para "tu voucher del mes está
-- listo" (aviso 1-a-1, distinto de los 4 tipos de difusión masiva que ya
-- existían) — ver notificacion_crear() en backend/notificaciones.php.
ALTER TABLE notificaciones
  MODIFY COLUMN tipo ENUM('respuesta','mencion','nuevo_curso','nuevo_evento','nuevo_producto','nueva_noticia','aviso_admin','voucher_membresia') NOT NULL;

-- Aviso 1-a-1 de "tu comprobante de transferencia/ventanilla fue confirmado"
-- (panel/admin/_pagos_acciones.php, junto al correo que ya se mandaba) —
-- mismo motivo que voucher_membresia arriba: un ENUM nuevo por cada tipo de
-- notificación 1-a-1 que se agregue, la difusión masiva no aplica aquí.
ALTER TABLE notificaciones
  MODIFY COLUMN tipo ENUM('respuesta','mencion','nuevo_curso','nuevo_evento','nuevo_producto','nueva_noticia','aviso_admin','voucher_membresia','pago_confirmado') NOT NULL;

-- Mapeo tipo de notificación -> plantilla de WhatsApp Cloud API (ver
-- backend/whatsapp.php) — el NOMBRE de una plantilla solo existe una vez
-- dada de alta y aprobada en Meta Business Manager, así que no puede vivir
-- hardcodeado en PHP; se edita desde el panel (notificaciones_config.php,
-- sección visible solo para es_super_admin()). Sin fila para un tipo, o con
-- activo=0, ese tipo simplemente no manda WhatsApp (sigue mandando correo +
-- notificación en plataforma normal).
CREATE TABLE IF NOT EXISTS `whatsapp_plantillas` (
  `tipo` varchar(40) NOT NULL,
  `nombre_plantilla` varchar(100) NOT NULL DEFAULT '',
  `idioma` varchar(10) NOT NULL DEFAULT 'es_MX',
  `activo` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `whatsapp_plantillas` (`tipo`, `nombre_plantilla`, `idioma`, `activo`) VALUES
  ('pago_confirmado', '', 'es_MX', 0),
  ('voucher_membresia', '', 'es_MX', 0);

-- Teléfono capturado junto al comprobante de transferencia/ventanilla (ver
-- checkout.php) — puede no coincidir con usuarios_perfil.telefono, así que
-- vive aparte, no reemplaza esa columna. Al confirmar el pago (ver
-- panel/admin/_pagos_acciones.php), este número tiene prioridad sobre el
-- del perfil para el aviso de WhatsApp de "tu comprobante fue confirmado".
ALTER TABLE pagos
  ADD COLUMN IF NOT EXISTS `whatsapp_telefono` varchar(20) DEFAULT NULL AFTER `comprobante_url`;

-- Un evento que ya pasó y tiene grabación (video_grabado_url) se consume
-- igual que un curso — bajo demanda, sin fecha fija. Este flag lo hace
-- aparecer TAMBIÉN en el catálogo de Cursos (content/cursos_catalogo.php,
-- vía UNION con eventos marcados) sin dejar de ser un evento real: sigue
-- viviendo en `eventos`, con su propia inscripción/acceso — no se duplica
-- ni se migra nada a `cursos`. Deliberadamente NO existe el checkbox
-- inverso (curso→evento): un curso no tiene fecha, no encaja como evento.
ALTER TABLE eventos
  ADD COLUMN IF NOT EXISTS `mostrar_en_cursos` tinyint(1) NOT NULL DEFAULT 0 AFTER `video_grabado_url`;
