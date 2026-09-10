# Flujo de sesión, login/registro y redirecciones

Este documento existe porque el mecanismo de "a dónde mandar a alguien después
de iniciar sesión / registrarse" se ha roto dos veces seguidas al corregir
otro bug relacionado (ver "Casos ya resueltos" al final). Es un subsistema
pequeño pero compartido por muchas páginas distintas — cualquier cambio en él
hay que verificarlo contra TODOS los flujos de este mapa, no solo el que se
está arreglando.

No es un mapa de toda la plataforma (checkout, cupones, membresía tienen su
propia lógica) — está acotado a sesión/login/registro/redirección, que es la
parte que ha demostrado ser frágil.

## Piezas centrales (todas en `plataforma/backend/auth.php`)

| Función | Qué hace |
|---|---|
| `login_user(int $id)` | Único punto de entrada a una sesión autenticada (login nativo, registro, Google). |
| `is_logged_in()` | Si hay sesión activa. |
| `current_user()` | Datos del usuario actual o `null`. |
| `require_login()` / `require_role()` | Cortan la ejecución si no hay sesión/rol suficiente. |
| `volver_validado(string $volver)` | Sanea un `volver` recibido del cliente: solo rutas propias del sitio (anti open-redirect), descarta si apunta a `ingreso`/`registro`/al `index.php` raíz de mercadeo. Todo lo demás se conserva **tal cual, con su query string completa**. |
| `redirect_post_login(string $accion='', string $volver='')` | Decide a dónde mandar a alguien justo después de autenticarse. |
| `redirect_post_login_con_aviso_sesion(string $volver='')` | Igual, pero para el guard de "ya tienes sesión abierta" — agrega `?sesion=activa` o `&sesion=activa` (según si el destino ya trae su propio query string) para el toast de `navbar.php`. |

### Reglas de `redirect_post_login()`, en orden de prioridad

1. **Admin → siempre `panel/index.php`**, sin excepción. Ignora `$volver` aunque venga de un curso/evento público — un admin entra a administrar, no a comprar.
2. **`volver` válido** (no vacío tras `volver_validado()`) → ahí.
3. Si no, `panel/dashboard.php` (+ `?action=$accion` si se pasó).

## Mapa de flujos reales

### A — Comprar sin sesión (usuario nuevo)
```
curso_detalle.php / evento_detalle.php (sin sesión)
  → clic "Inscribirme"/"Comprar"
  → ?action=registro&volver=<detalle...&auto=1>
  → registro_back.php → login_user() → redirect_post_login('perfil', volver)
  → vuelve a detalle...&auto=1
  → la página ve auto=1 → header a checkout.php (pago) o auto-inscribe por AJAX (gratis/membresía)
```

### B — Igual, pero YA hay sesión abierta al hacer clic (ej. llega desde una landing en otra pestaña)
```
curso_detalle.php / evento_detalle.php (con sesión, SIN acceso todavía)
  → clic "Inscribirme"/"Comprar" → mismo link: ?action=registro&volver=<detalle...&auto=1>
  → registro.php ve is_logged_in()=true
  → DEBE reenviar el MISMO $_GET['volver'] a redirect_post_login_con_aviso_sesion()
  → misma cadena que A desde aquí
```

### C — Curso/evento vinculado a una landing de venta (`landing_page_id`)
```
curso_detalle.php / evento_detalle.php
  → si NO es admin Y tiene landing_page_id Y NO tiene acceso todavía
    Y NO trae ?auto=1 con sesión → redirige a la landing (index.php?action=landing&slug=...)
  → si SÍ trae ?auto=1 con sesión → NO se redirige a la landing, sigue hacia
    el bloque de auto-inscripción/checkout de abajo
```
El bypass de `auto=1` es a propósito: sin él, cualquier curso/evento vendido
desde una landing rebota de vuelta a la landing justo en el momento en que
debería completar la compra, y el flujo A/B nunca llega a `checkout.php`.

### D — Recuperar/restablecer contraseña
No cargan `volver` con intención de compra (no tiene sentido "volver a
comprar" desde ahí). Su guard de "ya tienes sesión" solo manda al panel, sin
volver a ningún lado específico.

### E — Google OAuth (`google_auth.php`)
Es un endpoint JSON puro, no una página con guard de "ya logueado" — siempre
recibió y reenvió `$_POST['volver']` correctamente desde el principio, nunca
tuvo el bug de B.

## Checklist antes de tocar `auth.php` (redirect_post_login/volver_validado)
o cualquier página con guard de "ya tienes sesión" (`ingreso.php`,
`registro.php`, `olvide_contrasena.php`, `restablecer_contrasena.php`)

- [ ] Flujo A (comprar sin sesión) sigue llegando a checkout/auto-inscripción.
- [ ] Flujo B (comprar con sesión YA abierta) sigue llegando igual — probar
      explícitamente visitando `ingreso.php?volver=...` **ya logueado**, no
      solo el login real.
- [ ] Flujo C (curso/evento con landing vinculada) sigue llegando a
      checkout/auto-inscripción con `?auto=1`, y sigue rebotando a la landing
      SIN `auto=1`.
- [ ] Un admin sigue yendo siempre a `panel/index.php` sin importar `volver`.
- [ ] Un `volver` que apunte a `ingreso`/`registro`/al `index.php` raíz se
      sigue descartando (evita loops de login→login).
- [ ] La URL final del redirect es válida: un solo `?`, el resto con `&`
      (si el destino de `volver` ya traía su propia query string).
- [ ] Probar con curl + cookie jar los 3 casos de arriba, no solo a ojo en el
      navegador — un `?` de más o un `auto=1?sesion=activa` no siempre se
      nota visualmente pero rompe el `=== '1'` estricto que usa el auto-checkout.

## Archivos que participan en esta cadena

**Guard "ya tienes sesión" (usan `redirect_post_login_con_aviso_sesion()`):**
`content/ingreso.php`, `content/registro.php`, `content/olvide_contrasena.php`,
`content/restablecer_contrasena.php`

**Tras autenticar de verdad (usan `redirect_post_login()`):**
`backend/ingreso_back.php`, `backend/registro_back.php`, `backend/google_auth.php`,
`backend/restablecer_contrasena_back.php`

**Generan el `volver` inicial con `&auto=1`:**
`content/curso_detalle.php`, `content/evento_detalle.php`

**Redirigen a landing / consumen `auto=1`:**
`content/curso_detalle.php`, `content/evento_detalle.php`

## Casos ya resueltos (para no repetirlos)

1. **Admin se quedaba en `volver` en vez de ir a su panel.** `redirect_post_login()`
   no distinguía rol antes de mirar `volver`. Fix: admin gana siempre, sin excepción.
2. **`ingreso.php`/`registro.php` perdían el `volver` cuando ya había sesión.**
   El guard de "ya tienes sesión" llamaba a `redirect_post_login()` sin pasarle
   `$_GET['volver']`. Fix: pasarlo siempre (`redirect_post_login_con_aviso_sesion()`).
3. **`?sesion=activa` pegado con `?` fijo rompía un `volver` que ya traía su
   propia query string** (`...&auto=1` se volvía `...&auto=1?sesion=activa`,
   y `$_GET['auto'] === '1'` dejaba de cumplirse). Fix: pegar con `&` si el
   destino ya tiene `?`.
4. **Curso/evento con landing vinculada rebotaba a la landing incluso con
   `?auto=1`**, antes de llegar al bloque de auto-checkout. Fix: excluir
   `auto=1` (con sesión) de la condición que redirige a la landing.
