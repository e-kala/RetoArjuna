-- Permite ligar un tema del foro a un curso y, opcionalmente, a una lección
-- específica — independiente de `categoria_id` (que sigue organizando por
-- tema general: Preguntas, Experiencias, etc., igual que los datos reales
-- migrados de Flarum). Con esto, "Discutir en el foro" desde un curso o una
-- lección deja de depender de una URL escrita a mano: se genera solo.
-- Ejecutar una sola vez contra retoarju_platform.

SET NAMES utf8mb4;

ALTER TABLE foro_temas
  ADD COLUMN curso_id INT UNSIGNED NULL AFTER categoria_id,
  ADD COLUMN leccion_id INT UNSIGNED NULL AFTER curso_id;

ALTER TABLE foro_temas
  ADD KEY idx_foro_temas_curso (curso_id),
  ADD KEY idx_foro_temas_leccion (leccion_id);

-- ON DELETE SET NULL (no CASCADE): si se borra un curso/lección, las
-- publicaciones de la comunidad sobre ese tema no deberían desaparecer,
-- solo pierden el enlace directo al curso.
ALTER TABLE foro_temas
  ADD CONSTRAINT fk_foro_temas_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_foro_temas_leccion FOREIGN KEY (leccion_id) REFERENCES lecciones (id) ON DELETE SET NULL;
