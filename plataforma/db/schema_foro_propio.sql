-- Quita la dependencia de Flarum como identidad: usuarios_perfil pasa a ser la
-- fuente de verdad (usuario/correo/contraseña propios + Google opcional), y se
-- agregan las tablas del foro propio (categorías, temas, respuestas, likes,
-- notificaciones). Ejecutar una sola vez contra retoarju_platform, ANTES de correr
-- migrar_datos_flarum.php.

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- usuarios_perfil: de "perfil ligado a Flarum" a identidad completa
-- --------------------------------------------------------
ALTER TABLE usuarios_perfil
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER rol,
  ADD COLUMN google_id VARCHAR(64) NULL AFTER password_hash;

ALTER TABLE usuarios_perfil
  ADD UNIQUE KEY uq_usuarios_perfil_google_id (google_id);

-- username_cache/email_cache ya no son una caché de Flarum: son la fuente de verdad.
ALTER TABLE usuarios_perfil
  MODIFY username_cache VARCHAR(255) NOT NULL,
  MODIFY email_cache VARCHAR(255) NOT NULL,
  ADD UNIQUE KEY uq_usuarios_perfil_username (username_cache),
  ADD UNIQUE KEY uq_usuarios_perfil_email (email_cache);

-- flarum_user_id ya no significa nada sin Flarum.
ALTER TABLE usuarios_perfil
  DROP KEY uq_usuarios_perfil_flarum,
  DROP COLUMN flarum_user_id;

-- --------------------------------------------------------
-- Categorías del foro
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS foro_categorias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  descripcion TEXT NULL,
  orden INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_foro_categorias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Temas (el hilo incluye el post inicial como su propio contenido)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS foro_temas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  contenido MEDIUMTEXT NOT NULL,
  fijado TINYINT(1) NOT NULL DEFAULT 0,
  cerrado TINYINT(1) NOT NULL DEFAULT 0,
  respuestas_count INT UNSIGNED NOT NULL DEFAULT 0,
  vistas INT UNSIGNED NOT NULL DEFAULT 0,
  ultima_respuesta_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_foro_temas_slug (slug),
  KEY idx_foro_temas_categoria (categoria_id),
  FULLTEXT KEY ft_foro_temas (titulo, contenido),
  CONSTRAINT fk_foro_temas_categoria FOREIGN KEY (categoria_id) REFERENCES foro_categorias (id) ON DELETE CASCADE,
  CONSTRAINT fk_foro_temas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Respuestas
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS foro_respuestas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tema_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  contenido MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_foro_respuestas_tema (tema_id),
  FULLTEXT KEY ft_foro_respuestas (contenido),
  CONSTRAINT fk_foro_respuestas_tema FOREIGN KEY (tema_id) REFERENCES foro_temas (id) ON DELETE CASCADE,
  CONSTRAINT fk_foro_respuestas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Likes (a un tema O a una respuesta, exactamente uno de los dos)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS foro_likes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  tema_id INT UNSIGNED NULL,
  respuesta_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_foro_likes (usuario_id, tema_id, respuesta_id),
  CONSTRAINT fk_foro_likes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE,
  CONSTRAINT fk_foro_likes_tema FOREIGN KEY (tema_id) REFERENCES foro_temas (id) ON DELETE CASCADE,
  CONSTRAINT fk_foro_likes_respuesta FOREIGN KEY (respuesta_id) REFERENCES foro_respuestas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Notificaciones (respuesta a mi tema, o mención a mí)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS foro_notificaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL COMMENT 'destinatario',
  tipo ENUM('respuesta','mencion') NOT NULL,
  tema_id INT UNSIGNED NOT NULL,
  respuesta_id INT UNSIGNED NULL,
  actor_usuario_id INT UNSIGNED NOT NULL COMMENT 'quién generó la notificación',
  leida TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_foro_notif_usuario (usuario_id, leida),
  CONSTRAINT fk_foro_notif_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE,
  CONSTRAINT fk_foro_notif_tema FOREIGN KEY (tema_id) REFERENCES foro_temas (id) ON DELETE CASCADE,
  CONSTRAINT fk_foro_notif_respuesta FOREIGN KEY (respuesta_id) REFERENCES foro_respuestas (id) ON DELETE CASCADE,
  CONSTRAINT fk_foro_notif_actor FOREIGN KEY (actor_usuario_id) REFERENCES usuarios_perfil (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
