-- Secciones "Actividades" (programas/clases en curso de la comunidad) y "Noticias"
-- (avisos con fecha). Ambas administrables desde el panel, mismo patrón que
-- cupones/foro_categorias. Ejecutar una sola vez contra retoarju_platform.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS actividades (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(150) NOT NULL,
  descripcion TEXT NULL,
  icono VARCHAR(10) NOT NULL DEFAULT '📌',
  enlace_url VARCHAR(500) NULL,
  orden INT NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO actividades (titulo, descripcion, icono, orden) VALUES
  ('Clases de Gita en línea', 'Estudio semanal del Bhagavad-gītā, abierto a todos los niveles.', '📖', 1),
  ('Clases de Bhagavatam en línea', 'Lectura y comentario del Śrīmad-Bhāgavatam.', '📚', 2),
  ('Lectura matutina de domingo', 'Lectura matutina los domingos del libro de Krishna.', '🌅', 3),
  ('Clases de sánscrito', 'Introducción y práctica del idioma sánscrito.', '🕉️', 4),
  ('App de sánscrito (en desarrollo)', 'Nuevo desarrollo: una app para aprender sánscrito.', '📱', 5),
  ('Árbol genealógico de los Vedas (en desarrollo)', 'Nuevo desarrollo: un árbol genealógico interactivo de los Vedas.', '🌳', 6);

CREATE TABLE IF NOT EXISTS noticias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  resumen VARCHAR(300) NULL,
  contenido MEDIUMTEXT NOT NULL,
  imagen VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  publicada_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_noticias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
