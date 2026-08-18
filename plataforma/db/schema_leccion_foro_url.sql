-- Referencia/desarrollo local — el cambio real para producción se pliega en
-- exportar_produccion.sql (regla: "1 solo sql para actualizar en producción").
--
-- Permite enlazar una lección a un tema específico del foro (elegido con el
-- buscador en vivo de _foro_link_field.php), igual que ya existía para
-- cursos/eventos vía `foro_url`.
ALTER TABLE lecciones
  ADD COLUMN IF NOT EXISTS foro_url VARCHAR(500) NULL AFTER vista_previa;
