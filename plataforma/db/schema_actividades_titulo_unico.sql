-- Corrige que `actividades` no tuviera ninguna llave única real (solo `id`),
-- a diferencia de cursos/eventos/productos/cupones/foro_categorias/noticias
-- (todas con slug o código único). Sin esto, INSERT IGNORE solo deduplica si
-- el id coincide exactamente entre entornos — si el id de producción quedó
-- distinto al de local (p. ej. porque el archivo semilla se corrió más de una
-- vez ahí), cada importación agrega otra copia de las mismas actividades.
--
-- IMPORTANTE: si este archivo se corre en un entorno donde `actividades` ya
-- quedó duplicada (el síntoma que motivó este arreglo), hay que des-duplicar
-- ANTES de agregar la llave única, si no el ALTER falla. Este script lo hace
-- solo (conserva la fila de menor id por título, borra las demás).
-- Ejecutar una sola vez contra retoarju_platform.

SET NAMES utf8mb4;

DELETE a1 FROM actividades a1
INNER JOIN actividades a2
WHERE a1.titulo = a2.titulo AND a1.id > a2.id;

ALTER TABLE actividades
  ADD UNIQUE KEY uq_actividades_titulo (titulo);
