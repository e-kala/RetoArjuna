-- Fase A de la mejora grande del foro (subcategorías + edición con historial).
-- Referencia/desarrollo local — el cambio real para producción se pliega en
-- exportar_produccion.sql (regla: "1 solo sql para actualizar en producción").

SET NAMES utf8mb4;

-- Subcategorías: categoría + 2 niveles más (profundidad máxima validada en
-- aplicación, no aquí — ver foro_categoria_form.php).
ALTER TABLE foro_categorias
  ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL AFTER id;

ALTER TABLE foro_categorias DROP FOREIGN KEY IF EXISTS fk_foro_categorias_parent;
ALTER TABLE foro_categorias ADD CONSTRAINT fk_foro_categorias_parent FOREIGN KEY (parent_id) REFERENCES foro_categorias (id) ON DELETE CASCADE;

ALTER TABLE foro_categorias ADD KEY IF NOT EXISTS idx_foro_categorias_parent (parent_id);

-- Edición de temas: deliberadamente NO auto-`ON UPDATE` — foro_temas ya se
-- actualiza hoy por razones que no son una edición de contenido (vistas,
-- respuestas_count, fijado/cerrado); un timestamp automático marcaría como
-- "editado" algo que solo se fijó o se cerró.
ALTER TABLE foro_temas
  ADD COLUMN IF NOT EXISTS editado_en DATETIME NULL AFTER contenido,
  ADD COLUMN IF NOT EXISTS editado_por INT UNSIGNED NULL AFTER editado_en;

ALTER TABLE foro_temas DROP FOREIGN KEY IF EXISTS fk_foro_temas_editado_por;
ALTER TABLE foro_temas ADD CONSTRAINT fk_foro_temas_editado_por FOREIGN KEY (editado_por) REFERENCES usuarios_perfil (id) ON DELETE SET NULL;

-- Edición de respuestas: mismo par explícito que foro_temas — NO se reusa el
-- `updated_at` que ya existe (aunque hoy sería seguro), porque el panel de
-- moderación de una fase posterior casi seguro va a querer ocultar/moderar
-- una respuesta sin que eso se lea como "el autor la editó".
ALTER TABLE foro_respuestas
  ADD COLUMN IF NOT EXISTS editado_en DATETIME NULL AFTER contenido,
  ADD COLUMN IF NOT EXISTS editado_por INT UNSIGNED NULL AFTER editado_en;

ALTER TABLE foro_respuestas DROP FOREIGN KEY IF EXISTS fk_foro_respuestas_editado_por;
ALTER TABLE foro_respuestas ADD CONSTRAINT fk_foro_respuestas_editado_por FOREIGN KEY (editado_por) REFERENCES usuarios_perfil (id) ON DELETE SET NULL;

-- Historial: cada fila es una foto de cómo estaba el contenido justo ANTES
-- de una edición, con quién la hizo y cuándo.
CREATE TABLE IF NOT EXISTS foro_temas_historial (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tema_id INT UNSIGNED NOT NULL,
  titulo_anterior VARCHAR(200) NOT NULL,
  contenido_anterior MEDIUMTEXT NOT NULL,
  editado_por INT UNSIGNED NOT NULL,
  editado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_foro_temas_historial_tema (tema_id),
  CONSTRAINT fk_foro_temas_historial_tema FOREIGN KEY (tema_id) REFERENCES foro_temas (id) ON DELETE CASCADE,
  CONSTRAINT fk_foro_temas_historial_usuario FOREIGN KEY (editado_por) REFERENCES usuarios_perfil (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS foro_respuestas_historial (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  respuesta_id INT UNSIGNED NOT NULL,
  contenido_anterior MEDIUMTEXT NOT NULL,
  editado_por INT UNSIGNED NOT NULL,
  editado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_foro_respuestas_historial_respuesta (respuesta_id),
  CONSTRAINT fk_foro_respuestas_historial_respuesta FOREIGN KEY (respuesta_id) REFERENCES foro_respuestas (id) ON DELETE CASCADE,
  CONSTRAINT fk_foro_respuestas_historial_usuario FOREIGN KEY (editado_por) REFERENCES usuarios_perfil (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
