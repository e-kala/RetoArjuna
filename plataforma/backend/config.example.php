<?php
// Plantilla para config.local.php — este archivo SÍ va en git, config.local.php
// NO (ver .gitignore). Para dar de alta un entorno nuevo (local, pruebas.arjuna.mx,
// producción): copiar este archivo a config.local.php en el mismo directorio y
// llenar los valores reales de ESE entorno. conexion.php funciona sin él (usa
// valores de relleno "reemplazar_..."), así que un config.local.php a medio
// llenar no rompe nada — cada función/pantalla que dependa de un valor sin
// reemplazar simplemente se desactiva sola (ver config_esta_lista()).
//
// Cada entorno define solo lo que le aplica a ÉL — nunca copiar credenciales
// de otro entorno (sobre todo las de Stripe: LIVE es dinero real, mezclar TEST
// y LIVE entre entornos es exactamente el bug que causó el error de checkout
// visto el 2026-08-17).

// --- Base de datos: la única diferencia real entre local/pruebas/producción ---
// define('DB_HOST', 'localhost');
// define('DB_USER', 'retoarju_platform');
// define('DB_PASS', 'contraseña real de este entorno');
// define('DB_NAME', 'retoarju_platform');       // local
// define('DB_NAME', 'retoarju_pruebas');        // pruebas.arjuna.mx
// define('DB_NAME', 'retoarju_platform');       // producción (arjuna.mx)

// --- Login con Google ---
// define('GOOGLE_CLIENT_ID', '...');
// define('GOOGLE_CLIENT_SECRET', '...');

// --- Pagos con Stripe ---
// Usar SIEMPRE llaves TEST (pk_test_/sk_test_) en local y en pruebas.arjuna.mx.
// Las llaves LIVE (pk_live_/sk_live_) van SOLO en el config.local.php de
// producción — nunca copiarlas a otro entorno "por si acaso".
// define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
// define('STRIPE_SECRET_KEY', 'sk_test_...');
// El webhook secret es POR ENDPOINT registrado en el Dashboard de Stripe, y
// cada modo (test/live) tiene el suyo — no reutilizar el de otro entorno.
// define('STRIPE_WEBHOOK_SECRET', 'whsec_...');

// --- Modo prueba de Stripe (solo tiene sentido en producción/pruebas.arjuna.mx) ---
// Permite que un admin, o una cuenta marcada "es_prueba" (ver
// usuarios_perfil.es_prueba), active desde el sitio (menú de usuario →
// "Activar modo prueba de Stripe") un modo donde SOLO ESA CUENTA ve/usa
// llaves TEST — cualquier otro usuario, incluida otra cuenta admin/de prueba
// que no lo haya activado, sigue viendo siempre las llaves LIVE de arriba.
// Útil para probar el flujo completo de pagos en el sitio real sin arriesgar
// dinero real ni tener que editar este archivo a mano cada vez. Si no se
// definen estas 3 líneas, el botón de activar sigue apareciendo pero no
// cambia nada (cae de vuelta a LIVE).
// define('STRIPE_PUBLISHABLE_KEY_PRUEBA', 'pk_test_...');
// define('STRIPE_SECRET_KEY_PRUEBA', 'sk_test_...');
// define('STRIPE_WEBHOOK_SECRET_PRUEBA', 'whsec_...'); // webhook aparte en modo TEST, o `stripe listen --forward-to` apuntando a stripe_webhook.php

// --- Transferencia bancaria ---
// define('BANCO_NOMBRE', '...');
// define('BANCO_CLABE', '...');
// define('BANCO_TITULAR', '...');
// define('WHATSAPP_PAGOS', '52...');

// --- Correo saliente ---
// define('EMAIL_REMITENTE', 'noreply@...');
// define('EMAIL_REMITENTE_NOMBRE', 'Reto Arjuna');
//
// SMTP opcional — si se definen estas 4, enviar_email() manda por SMTP (con el
// PHPMailer ya vendorizado en plataforma/vendor/PHPMailer/) en vez de mail()
// nativo. Pensado SOLO para LOCAL, donde no hay ningún servidor de correo (MTA)
// instalado y mail() no puede enviar nada real. En pruebas.arjuna.mx/producción
// NO definir estas 4 líneas — ahí mail() nativo ya funciona con el propio
// servidor de correo del hosting (cPanel).
// define('EMAIL_SMTP_HOST', 'smtp.gmail.com');
// define('EMAIL_SMTP_PORT', 587);
// define('EMAIL_SMTP_USER', 'tu-cuenta@gmail.com');
// define('EMAIL_SMTP_PASS', 'contraseña de aplicación de 16 caracteres (NO la contraseña normal de la cuenta)');
//
// Redirección de pruebas — si se define, TODO correo que genere la plataforma
// llega a esta dirección en vez de al destinatario real (notificaciones_log
// sigue guardando el destinatario real, solo cambia a dónde LLEGA el correo).
// Pensado para local/pruebas.arjuna.mx, para poder revisar cómo se ven los
// correos sin arriesgar mandarle algo a un usuario real. NUNCA definir esto en
// el config.local.php de producción.
// define('EMAIL_FORZAR_DESTINATARIO', 'correo-de-pruebas@gmail.com');
//
// A dónde llegan los mensajes del formulario de contacto de las landing pages
// (reto-arjuna.html/asesoria.html/entrenamiento.html) — a diferencia de los
// correos transaccionales de arriba (que van al usuario dueño de la compra),
// un mensaje de contacto siempre es PARA el equipo Arjuna, en cualquier
// ambiente. Si no se define, cae de vuelta a EMAIL_REMITENTE.
// define('EMAIL_ADMIN_CONTACTO', 'admin@...');
