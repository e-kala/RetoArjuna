-- Referencia/desarrollo local — el cambio real para producción se pliega en
-- exportar_produccion.sql (regla: "1 solo sql para actualizar en producción").
--
-- Antes, un miembro activo tenía acceso automático a TODOS los cursos (sin
-- excepción) pero solo a los eventos marcados `solo_miembros`. Ahora ambos
-- usan el mismo flag explícito `incluido_membresia`, marcado por el admin
-- curso por curso / evento por evento — no hay comportamiento implícito.
--
-- DEFAULT 0 a propósito (decisión del usuario 2026-08-15): ningún curso
-- existente queda incluido automáticamente — el admin revisa y marca cada
-- uno manualmente. Esto significa que, justo después de aplicar este cambio,
-- los miembros dejan de tener acceso automático a cursos de pago hasta que
-- se vuelvan a marcar los que correspondan desde el panel.
ALTER TABLE cursos
  ADD COLUMN IF NOT EXISTS incluido_membresia TINYINT(1) NOT NULL DEFAULT 0 AFTER gratuito;

-- En eventos, `solo_miembros` (exclusivo, bloquea a quien no es miembro) y
-- `incluido_membresia` (miembros lo obtienen gratis, pero no exclusivo) son
-- conceptos distintos e independientes — la lógica de acceso trata
-- `solo_miembros` como si también implicara `incluido_membresia`.
ALTER TABLE eventos
  ADD COLUMN IF NOT EXISTS incluido_membresia TINYINT(1) NOT NULL DEFAULT 0 AFTER solo_miembros;
