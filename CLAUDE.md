# CLAUDE.md — RetoArjuna (arjuna.mx)

Contexto persistente del proyecto para Claude Code. Basado en el estado real del código
(verificado directamente en el repo) y en el historial de trabajo de Claude Code, cruzado
contra el `checklist.txt` del repo y el vault de Obsidian (`Organizador.md`, `Reto Arjuna.md`).
Actualizado el 2026-09-29 — revisión focalizada de navegación y catálogos; la auditoría
integral base es del 2026-08-18. La versión anterior (generada solo desde Cowork/Obsidian)
estaba desactualizada en varios puntos clave; ver "Correcciones" abajo.

## Qué es esto
Plataforma web del ecosistema Arjuna (Academia/Reto Arjuna): cursos, tienda, membresías, foro y
panel de administración. Prioridad #1 entre los proyectos activos.

- Stack: PHP plano + mysqli (sin framework, sin build step), MySQL/MariaDB, HTML/CSS/JS.
  Bootstrap 5.3.5 por CDN en todo el sitio (incluido el foro, migrado en agosto 2026).
- Pagos: Stripe (tarjeta vía Payment Element/Checkout Sessions) + transferencia bancaria manual.
- Repo: github.com/e-kala/RetoArjuna — ramas `master` y `desarrollo` (confirmado via `git remote`).
- Dominios relacionados (según vault de Obsidian, no verificado desde el código): retoarjuna.org,
  sostener.retoarjuna.org — el foro NO vive en un dominio aparte, es nativo dentro de este mismo
  repo (ver corrección de Foro abajo).
- Credenciales de acceso: no están versionadas. El FTP local vive en `.env` (gitignored) y cada
  entorno tiene su propio `plataforma/backend/config.local.php` — plantilla documentada en
  `plataforma/backend/config.example.php`. Nunca las escribas en código ni en commits.

## Estructura principal (reorganizada 2026-08-18)
Todo lo que la app necesita vive ahora dentro de `plataforma/` — `foro/`, `assets/`,
`digital-creative/` y `vendor/` eran hermanos sueltos en la raíz y se movieron adentro para
que la estructura sea más limpia (sin cambiar el DocumentRoot ni fusionar los dos `index.php`).

- `index.php`, `reto-arjuna.html`, `asesoria.html`, `entrenamiento.html` — landing pages, se
  quedan en la raíz a propósito (son estáticas/de marketing, separadas de la app).
- `plataforma/` — la app completa:
  - `backend/`, `panel/`, `db/`, `content/` — lógica, paneles admin/usuario, esquema, vistas.
  - `foro/` — foro nativo en PHP (no Flarum, ver corrección abajo).
  - `assets/` — `platform.css`, sistema de diseño compartido por toda la plataforma.
  - `digital-creative/` — imágenes/CSS/JS del template de marketing.
  - `vendor/` — NO es Composer (no hay `composer.json` en el proyecto): assets de PHPMailer sin
    usar (el mailer real usa `mail()` nativo) + JS/CSS del formulario de contacto de las
    landing pages.
- `plataforma/index.php` es el router frontal de la app mediante `?action=...`; los catálogos
  públicos viven en `plataforma/content/{cursos,eventos,tienda}_catalogo.php` y los detalles en
  `curso_detalle.php`, `evento_detalle.php` y `producto_detalle.php`.
- `.gitignore` excluye `/plataforma/vendor/`, `plataforma/backend/config.local.php` y `logs/*.log`.
- `logs/` se queda deliberadamente FUERA de `plataforma/` (por seguridad, mejor no anidarlo bajo
  algo servido más profundo).

## Correcciones a lo que decía la versión anterior de este archivo
- **Foro: NO se está migrando a Flarum.** Flarum se evaluó, se instaló, y se **eliminó por
  completo** en una sesión anterior (2026-08-02) — el usuario pidió explícitamente un foro
  propio para que el login nunca dependa de un sistema externo. Desde entonces el foro nativo
  (`plataforma/foro/`) se construyó y se amplió mucho: categorías con 3 niveles de
  subcategorías, edición de temas/respuestas con historial visible, editor de texto enriquecido
  (Quill) con saneamiento HTML propio, paginación, panel de moderación (admin) y de actividad
  (usuario), y migración completa a componentes de Bootstrap. No hay ninguna cuenta de Flarum ni
  SSO externo — la autenticación es 100% nativa (`usuarios_perfil`, `password_hash`/Google OAuth).
- **Tienda**: el flujo de compra digital/física, ocultar precio cuando ya se compró ("Ya lo
  tienes"/`$adquirido`), y poder eliminar filas en "mis compras" y en admin "pagos" — todo esto
  **ya está implementado**, no es pendiente.
- **Membresía**: Stripe para membresía **ya está integrado** (`membresia_iniciar.php`,
  `membresia_portal.php`, suscripciones recurrentes vía Checkout Session). La configuración
  remota se auditó el 2026-09-29: producción usa llave principal LIVE y pruebas usa TEST.
- **Panel admin**: el botón de "Productos" en la barra lateral, el formulario de enlace de
  descarga en "editar producto", y el navbar configurable desde admin — todo esto **ya está
  implementado**.
- **Cupones**: la funcionalidad completa de cupones (panel, lógica de checkout, tabla en la BD)
  se **eliminó** a petición del usuario (2026-08-16) — si algo del vault todavía la menciona,
  ya no existe.

## Pendientes reales (verificado contra el código, no solo el vault)

### Despliegue / entornos
- El despliegue manual por FTP se hace con `scripts/deploy_ftp.sh`; la única variable que
  selecciona destino es `DEPLOY_ENTORNO=pruebas|produccion` en `.env`. Las raíces remotas son
  `/pruebas.arjuna.mx` y `/public_html`, respectivamente. Ver `DESPLIEGUE.md`.
- El script nunca sube `config.local.php`: pruebas debe conservar `DB_NAME=retoarju_pruebas`
  y producción usa `retoarju_platform`. Tampoco sincroniza uploads, logs, Matomo ni credenciales.
- `pruebas.arjuna.mx` quedó sincronizado y verificado el 2026-09-29 con el rediseño `v=3.0`.
  Su `config.local.php` remoto se corrigió para usar `retoarju_pruebas`, llaves Stripe TEST y
  destinatario de correo forzado; antes estaba cayendo silenciosamente en la BD/Stripe LIVE.
- Verificar desde el Dashboard de Stripe que el webhook LIVE de producción esté entregando
  eventos correctamente. El secreto está definido en el servidor, pero esta auditoría no
  valida el estado del endpoint externo ni su historial de entregas.

### Membresía
- Confirmar compatibilidad de Stripe Checkout con Edge (no probado).
- Contenido/copy de la membresía (encuentro semanal en vivo, grabaciones, acceso temprano) —
  esto es redacción/marketing, no código.
- Acceso a grabaciones de eventos por membresía ya se corrigió (2026-08-17): ya no da acceso
  silencioso a cualquier evento por ser miembro, exige inscripción real (botón "Accesar gratis
  con mi membresía" para eventos incluidos, "Comprar acceso" para el resto).

### Eventos
- Seguir ampliando la página de detalle de evento con más información (alcance exacto sin
  definir — confirmar con el usuario qué falta).

### Landing / navegación
- Integración más completa: landing anterior + próximos eventos + evento "sostener" + webinars
  (gratis y de paga) + calendario + sedes (GDL, Puebla, Aguascalientes, etc.) — no verificado
  si algo de esto ya se hizo, tratar como pendiente hasta confirmar.
- Subir los cursos ya pregrabados y localizar/subir los videos de YouTube del reto — tarea de
  contenido, no de código.

### App Sánscrito (repo aparte, no verificado desde aquí)
- Que aparezca solo en español.
- Adaptación para Edge.

## Convenciones de trabajo
- Antes de tocar pagos, confirmar en qué ambiente se está trabajando (local / pruebas.arjuna.mx /
  producción) — cada uno tiene su propio `config.local.php`, nunca reusar llaves de Stripe entre
  entornos (mezclar TEST y LIVE ya causó al menos un bug real).
- No commitear credenciales, `config.local.php` ni archivos bajo `logs/`.
- Un solo archivo de SQL para producción: `plataforma/db/exportar_produccion.sql` — se
  regenera cada vez que cambia el esquema, nunca se sube un `schema_*.sql` suelto.
- Este proyecto es prioridad activa — cuando haya ambigüedad entre proyectos, arjuna.mx va primero.
- Prioridad actual del usuario: una versión de producción básica-pero-estable antes que
  features nuevas — lo riesgoso se prueba primero en `pruebas.arjuna.mx`.
- Las tarjetas de los catálogos de cursos, eventos y tienda son clicables completas mediante
  `.pf-card-clicable` + `data-href`. El listener delegado compartido vive en
  `plataforma/content/scripts.php`; ignora clicks en enlaces y botones internos.
- El sistema visual compartido está en `plataforma/assets/css/platform.css` (versión de caché
  `v=3.0`): variables de color, tipografía, navbar, tarjetas, botones, formularios, tablas,
  foro y paneles. El admin usa un espacio de trabajo responsivo definido por
  `.ra-admin-shell`/`.ra-admin-workspace`: barra lateral en escritorio y barra horizontal
  desplazable en móvil. Antes de agregar CSS inline, revisar si el componente debe resolverse ahí.
- Antes de tocar `redirect_post_login()`/`volver_validado()` (auth.php) o cualquier página
  con guard de "ya tienes sesión" (ingreso/registro/olvide/restablecer contraseña), leer
  `FLUJO_SESION_REDIRECCIONES.md` — ese mecanismo ya se rompió más de una vez al corregir un
  bug de redirección sin revisar los demás flujos que comparte.
