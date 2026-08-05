-- Eventos + Tienda (productos), y generalización de pagos/certificados para
-- que cubran curso, evento o producto (exactamente uno por fila, regla de
-- aplicación, no constraint de DB). Ejecutar una sola vez contra retoarju_platform.

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Eventos (online o presenciales)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS eventos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  descripcion TEXT NULL,
  tipo ENUM('online','presencial') NOT NULL DEFAULT 'online',
  ubicacion VARCHAR(255) NULL COMMENT 'Dirección física, o "Zoom"/liga si es online',
  fecha_inicio DATETIME NOT NULL,
  fecha_fin DATETIME NULL,
  cupo_maximo INT UNSIGNED NULL,
  imagen_portada VARCHAR(255) NULL,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0,
  gratuito TINYINT(1) NOT NULL DEFAULT 0,
  foro_url VARCHAR(500) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_eventos_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Inscripciones a eventos (RSVP / asistencia)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS evento_inscripciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  evento_id INT UNSIGNED NOT NULL,
  estado ENUM('inscrito','asistio','cancelado') NOT NULL DEFAULT 'inscrito',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evento_inscripcion (usuario_id, evento_id),
  CONSTRAINT fk_evento_inscripciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE,
  CONSTRAINT fk_evento_inscripciones_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Productos de la tienda (físicos o digitales/infoproductos)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo ENUM('fisico','digital') NOT NULL DEFAULT 'fisico',
  nombre VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  descripcion TEXT NULL,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0,
  imagen VARCHAR(255) NULL,
  stock INT UNSIGNED NULL COMMENT 'NULL = ilimitado (típico en digital)',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_productos_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Generalizar pagos: además de curso, ahora también evento o producto
-- --------------------------------------------------------
ALTER TABLE pagos
  MODIFY curso_id INT UNSIGNED NULL,
  ADD COLUMN evento_id INT UNSIGNED NULL AFTER curso_id,
  ADD COLUMN producto_id INT UNSIGNED NULL AFTER evento_id,
  ADD COLUMN cantidad INT UNSIGNED NOT NULL DEFAULT 1 AFTER producto_id,
  ADD COLUMN direccion_envio TEXT NULL AFTER comprobante_url;

ALTER TABLE pagos
  ADD CONSTRAINT fk_pagos_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_pagos_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE;

-- --------------------------------------------------------
-- Generalizar certificados: "reconocimientos" de curso o de evento
-- --------------------------------------------------------
-- La UNIQUE (usuario_id, curso_id) ya existente se conserva tal cual: con
-- curso_id NULL-able, MySQL trata cada NULL como distinto, así que las filas de
-- tipo "evento" (curso_id NULL) no chocan entre sí por esa llave.
ALTER TABLE certificados
  MODIFY curso_id INT UNSIGNED NULL,
  ADD COLUMN evento_id INT UNSIGNED NULL AFTER curso_id,
  ADD COLUMN tipo ENUM('curso','evento') NOT NULL DEFAULT 'curso' AFTER evento_id;

ALTER TABLE certificados
  ADD CONSTRAINT fk_certificados_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
  ADD UNIQUE KEY uq_certificado_usuario_evento (usuario_id, evento_id);

-- --------------------------------------------------------
-- Datos de ejemplo
-- --------------------------------------------------------
INSERT INTO eventos (titulo, slug, descripcion, tipo, ubicacion, fecha_inicio, precio, gratuito, activo)
VALUES (
  'Encuentro mensual en línea',
  'encuentro-mensual-online',
  'Sesión abierta de preguntas y práctica en vivo por Zoom.',
  'online', 'Zoom', DATE_ADD(NOW(), INTERVAL 14 DAY), 0, 1, 1
)
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo);

INSERT INTO productos (tipo, nombre, slug, descripcion, precio, stock, activo)
VALUES
  ('fisico', 'Playera Reto Arjuna', 'playera-reto-arjuna', 'Playera de algodón con el logo del Reto Arjuna.', 350, 20, 1),
  ('digital', 'Guía práctica en PDF', 'guia-practica-pdf', 'Resumen descargable de los 8 retos del programa.', 150, NULL, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);
