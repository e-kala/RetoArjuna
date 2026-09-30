# Despliegue por FTP

La selección del servidor se controla con **una sola variable** en `.env`:

```dotenv
DEPLOY_ENTORNO=pruebas
```

Valores permitidos:

- `pruebas`: sube a `/pruebas.arjuna.mx` y valida `https://pruebas.arjuna.mx/`.
- `produccion`: sube a `/public_html` y valida `https://arjuna.mx/`.

Las variables `FTP_HOST`, `FTP_USER`, `FTP_PASS`, `FTP_PUERTO` y
`FTP_PROTOCOLO` permanecen iguales para ambos destinos. El servidor solo
ofrece FTP clásico; las credenciales viajan sin cifrado.

## Uso

Revisar qué subiría el último commit, sin modificar el servidor:

```bash
scripts/deploy_ftp.sh --dry-run
```

Subir los archivos cambiados por el último commit:

```bash
scripts/deploy_ftp.sh
```

Subir archivos concretos:

```bash
scripts/deploy_ftp.sh plataforma/assets/css/platform.css plataforma/index.php
```

Subir todo el código versionado permitido:

```bash
scripts/deploy_ftp.sh --all
```

Producción pide escribir `PRODUCCION` antes de comenzar. En automatizaciones
controladas se puede usar `--yes`.

## Protecciones

El script siempre excluye configuración y datos propios de cada entorno:

- `.env`, credenciales y notas locales;
- `plataforma/backend/config.local.php`;
- `plataforma/uploads/`, `logs/`, `matomo/` y `plataforma/vendor/`;
- dumps SQL y archivos de documentación/diseño.

Antes de reemplazar cada archivo descarga la versión remota a
`/tmp/retoarjuna-deploy-backups/`, verifica el archivo subido con SHA-256 y
restaura lo ya reemplazado si ocurre un error.

Las bases de datos **no se seleccionan desde este script**. Cada entorno debe
conservar su propio `config.local.php` remoto:

- producción: `retoarju_platform`;
- pruebas: `retoarju_pruebas`.
