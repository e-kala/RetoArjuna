-- Reparación de emergencia: en algún punto la tabla `actividades` quedó SIN
-- PRIMARY KEY/AUTO_INCREMENT (probablemente por una recreación manual de la
-- tabla que omitió esas cláusulas). Sin llave primaria, INSERT IGNORE no tiene
-- nada que comparar y cada importación agrega filas de más — así se detectó
-- (18 filas = 6 títulos × 3, con el mismo id repetido 3 veces cada uno, algo
-- estructuralmente imposible con una PRIMARY KEY real).
--
-- Este script reconstruye la tabla desde cero con la estructura correcta,
-- de-duplicando por contenido completo (no por id, que no es confiable aquí).
-- Seguro de correr aunque la tabla YA esté bien: si no hay duplicados, el
-- resultado son las mismas filas (con ids nuevos, sin efecto real ya que nada
-- referencia actividades.id por llave foránea).
-- Ejecutar contra retoarju_platform con: mysql --default-character-set=utf8mb4 ...

SET NAMES utf8mb4;

CREATE TABLE actividades_fixed (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(150) NOT NULL,
  descripcion TEXT NULL,
  icono VARCHAR(10) NOT NULL DEFAULT '📌',
  enlace_url VARCHAR(500) NULL,
  orden INT NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_actividades_titulo (titulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO actividades_fixed (titulo, descripcion, icono, enlace_url, orden, activo, created_at)
SELECT titulo, descripcion, icono, enlace_url, orden, activo, MIN(created_at)
FROM actividades
GROUP BY titulo, descripcion, icono, enlace_url, orden, activo;

DROP TABLE actividades;
RENAME TABLE actividades_fixed TO actividades;
