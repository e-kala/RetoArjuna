-- Esquema de la plataforma de cursos de Reto Arjuna.
-- La identidad (usuario/correo/contraseña) vive únicamente en Flarum (base `retoarju_foro`,
-- tabla `users`). Esta base solo guarda perfil/course data, enlazado por `flarum_user_id`.
-- Adaptado del esquema de nuywame (content/backend/migrations/00*.sql), sin CFDI/facturación
-- y sin foro propio por curso (se enlaza al Flarum real vía `cursos.foro_url`).

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Perfil de usuario (no credenciales — eso es de Flarum)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios_perfil (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  flarum_user_id INT UNSIGNED NOT NULL,
  rol ENUM('estudiante','instructor','admin') NOT NULL DEFAULT 'estudiante',
  telefono VARCHAR(20) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  avatar_cache VARCHAR(255) NULL,
  username_cache VARCHAR(255) NULL,
  email_cache VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_perfil_flarum (flarum_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Cursos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS cursos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  descripcion TEXT NULL,
  nivel ENUM('principiante','intermedio','avanzado') NOT NULL DEFAULT 'principiante',
  duracion_horas DECIMAL(5,1) NULL,
  precio DECIMAL(10,2) NOT NULL DEFAULT 0,
  imagen_portada VARCHAR(255) NULL,
  video_intro VARCHAR(255) NULL,
  foro_url VARCHAR(500) NULL COMMENT 'Enlace al hilo/tag del Flarum real para discutir el curso',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  gratuito TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cursos_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Lecciones
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS lecciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  curso_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  descripcion TEXT NULL,
  tipo_contenido ENUM('video','pdf','texto','quiz') NOT NULL DEFAULT 'video',
  contenido_url VARCHAR(500) NULL,
  contenido_texto MEDIUMTEXT NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  duracion_min INT UNSIGNED NULL,
  vista_previa TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leccion_orden (curso_id, orden),
  CONSTRAINT fk_lecciones_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Progreso del alumno por lección
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS progreso (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  curso_id INT UNSIGNED NOT NULL,
  leccion_id INT UNSIGNED NOT NULL,
  completado TINYINT(1) NOT NULL DEFAULT 0,
  tiempo_visto_seg INT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_acceso TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_progreso_usuario_leccion (usuario_id, leccion_id),
  CONSTRAINT fk_progreso_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE,
  CONSTRAINT fk_progreso_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE CASCADE,
  CONSTRAINT fk_progreso_leccion FOREIGN KEY (leccion_id) REFERENCES lecciones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Cupones
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS cupones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(40) NOT NULL,
  tipo ENUM('porcentaje','monto_fijo') NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  vigencia_desde DATE NULL,
  vigencia_hasta DATE NULL,
  uso_maximo INT UNSIGNED NULL,
  usos_actuales INT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cupones_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Pagos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  curso_id INT UNSIGNED NOT NULL,
  cupon_id INT UNSIGNED NULL,
  monto DECIMAL(10,2) NOT NULL,
  descuento DECIMAL(10,2) NOT NULL DEFAULT 0,
  metodo_pago ENUM('stripe','transferencia') NOT NULL,
  transaccion_id VARCHAR(191) NULL,
  estado ENUM('pendiente','confirmado','rechazado') NOT NULL DEFAULT 'pendiente',
  comprobante_url VARCHAR(500) NULL,
  fecha_pago DATETIME NULL,
  fecha_validacion DATETIME NULL,
  validado_por INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pagos_estado (estado),
  CONSTRAINT fk_pagos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE,
  CONSTRAINT fk_pagos_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE CASCADE,
  CONSTRAINT fk_pagos_cupon FOREIGN KEY (cupon_id) REFERENCES cupones (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Quizzes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS quizzes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  leccion_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  puntaje_minimo_aprobar INT UNSIGNED NOT NULL DEFAULT 70,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_quizzes_leccion (leccion_id),
  CONSTRAINT fk_quizzes_leccion FOREIGN KEY (leccion_id) REFERENCES lecciones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_preguntas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quiz_id INT UNSIGNED NOT NULL,
  enunciado TEXT NOT NULL,
  tipo ENUM('opcion_multiple','verdadero_falso') NOT NULL DEFAULT 'opcion_multiple',
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_quiz_preguntas_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_opciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pregunta_id INT UNSIGNED NOT NULL,
  texto VARCHAR(255) NOT NULL,
  es_correcta TINYINT(1) NOT NULL DEFAULT 0,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_quiz_opciones_pregunta FOREIGN KEY (pregunta_id) REFERENCES quiz_preguntas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_intentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  quiz_id INT UNSIGNED NOT NULL,
  puntaje INT UNSIGNED NOT NULL,
  total_preguntas INT UNSIGNED NOT NULL,
  aprobado TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_quiz_intentos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE,
  CONSTRAINT fk_quiz_intentos_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Certificados
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS certificados (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  curso_id INT UNSIGNED NOT NULL,
  codigo VARCHAR(40) NOT NULL,
  fecha_emision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_certificado_usuario_curso (usuario_id, curso_id),
  UNIQUE KEY uq_certificado_codigo (codigo),
  CONSTRAINT fk_certificados_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE,
  CONSTRAINT fk_certificados_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Log de notificaciones (correos)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificaciones_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NULL,
  tipo VARCHAR(40) NOT NULL,
  destinatario VARCHAR(150) NOT NULL,
  asunto VARCHAR(200) NOT NULL,
  estado ENUM('enviado','error') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_notificaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Datos de ejemplo (curso gratuito con lecciones de prueba)
-- --------------------------------------------------------
INSERT INTO cursos (titulo, slug, descripcion, nivel, duracion_horas, precio, gratuito, activo, foro_url)
VALUES (
  'Introducción al Reto Arjuna',
  'introduccion-reto-arjuna',
  'Curso introductorio gratuito para conocer la filosofía y estructura del Reto Arjuna.',
  'principiante', 2.5, 0, 1, 1,
  'http://localhost/RetoArjuna/foro/public/'
)
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo);

INSERT INTO lecciones (curso_id, titulo, tipo_contenido, contenido_texto, orden, vista_previa)
SELECT id, 'Bienvenida', 'texto', '<p>Bienvenido al Reto Arjuna. En esta lección conocerás el propósito del programa.</p>', 1, 1
FROM cursos WHERE slug = 'introduccion-reto-arjuna'
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo);

INSERT INTO lecciones (curso_id, titulo, tipo_contenido, contenido_texto, orden, vista_previa)
SELECT id, 'Cuestionario inicial', 'quiz', NULL, 2, 0
FROM cursos WHERE slug = 'introduccion-reto-arjuna'
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo);
