-- Permite varios videos de YouTube por lección (antes solo `contenido_url` admitía
-- uno) y materiales de apoyo (enlaces a documentos/recursos externos), sin importar
-- el tipo de contenido de la lección. Ejecutar una sola vez contra retoarju_platform.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS leccion_videos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  leccion_id INT UNSIGNED NOT NULL,
  url VARCHAR(500) NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leccion_videos_leccion (leccion_id),
  CONSTRAINT fk_leccion_videos_leccion FOREIGN KEY (leccion_id) REFERENCES lecciones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leccion_materiales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  leccion_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  url VARCHAR(500) NOT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leccion_materiales_leccion (leccion_id),
  CONSTRAINT fk_leccion_materiales_leccion FOREIGN KEY (leccion_id) REFERENCES lecciones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migra el video único que ya tuviera cada lección de tipo 'video' a la nueva tabla,
-- para no perder lo ya cargado. `contenido_url` se deja intacto (retrocompatibilidad
-- y sigue siendo la columna usada para el tipo 'pdf').
INSERT INTO leccion_videos (leccion_id, url, orden)
SELECT id, contenido_url, 0 FROM lecciones
WHERE tipo_contenido = 'video' AND contenido_url IS NOT NULL AND contenido_url <> '';
