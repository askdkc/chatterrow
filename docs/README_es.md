# 茶多楼 chatterrow

## Hemos integrado en el chat las funciones que respaldan la gestión centralizada del trabajo
<img width="100%" alt="Chatterrow サービス紹介" src="./assets/chatterrow.gif" />

Construido con Laravel 13, Inertia 3 y Svelte 5, es un groupware orientado a proyectos con una interfaz de usuario al estilo de Discord.

Crea canales para cada proyecto y gestiona de forma centralizada los chats, las tareas, los archivos y los diagramas de Gantt. Utiliza Laravel Reverb para la transmisión en tiempo real y ONLYOFFICE Document Server para las vistas previas de solo lectura de archivos de Office.

## Ventajas del groupware on-premise (茶多楼 es el servicio de la parte App Server)
<img width="650" height="362" alt="image" src="https://github.com/user-attachments/assets/6dda7830-caef-45c2-8a26-d10fb8f42c58" />

## Funciones principales

- **Gestión de proyectos**: configurar el nombre, el contenido, la fecha de inicio, la fecha de finalización y los miembros del proyecto
- **Canales**: organizar las conversaciones, tareas y archivos dentro de un proyecto por canal
- **Chat en tiempo real**: sincronizar mensajes, hilos y número de respuestas mediante Laravel Reverb
- **Markdown seguro**: escape de HTML, restricciones de URL HTTP(S), resaltado de sintaxis de código con Shiki
- **Archivos adjuntos**: D&D de archivos/carpetas, cargas en grupos de 10, miniaturas de imágenes, PDF y Office
- **Vista previa de archivos**: visor central para imágenes y PDF, vista previa de Office con ONLYOFFICE, salir con Esc
- **Conversión y almacenamiento de Office/PDF a Markdown**: convertir a Markdown en segundo plano para facilitar su uso en el entrenamiento de IA
- **Gestión de tareas**: fecha de inicio, hora de inicio, fecha de finalización, hora de finalización, prioridad, notas y estado de finalización
- **Diagrama de Gantt**: visualización del periodo por proyecto o canal
- **Recordatorios de vencimiento**: notificaciones automáticas mediante el scheduler y el queue worker
- **Temas**: compatibilidad con los modos oscuro y claro
- **Atajos de teclado**: el envío de mensajes y la creación de tareas se realizan con `Cmd+Enter` o `Ctrl+Enter`. El Enter de confirmación del IME no envía el mensaje

## Tecnologías

| Capa       | Tecnología                                                     |
|------------|----------------------------------------------------------------|
| Backend    | Laravel 13 / PHP 8.5+                                         |
| Frontend   | Inertia 3 / Svelte 5 / Tailwind CSS 4 / Vite 8                |
| Database   | SQLite o PostgreSQL                                           |
| Realtime   | Laravel Reverb (WebSocket)                                    |
| Preview    | Shiki / ONLYOFFICE / poppler / ImageMagick                    |
| Conversion | Microsoft MarkItDown 0.1.7 (PDF / DOCX / XLSX / PPTX)         |
| Queue      | Redis / Laravel queue worker                                  |
| Office     | ONLYOFFICE Document Server Community Edition (JWT, solo lectura) |
| Production | Ubuntu nginx-extras / PHP-FPM / Supervisor / Certbot          |

## Requisitos de producción

- Ubuntu 24.04 LTS o Ubuntu 26.04 LTS (amd64)
- PHP 8.5 CLI/FPM y extensión Redis
- Python 3.10 o superior, MarkItDown 0.1.7, Redis Server
- Un usuario normal con acceso a sudo o un usuario root
- 2 CPU, 2 GB de RAM y al menos 30 GB de espacio libre en disco (la recomendación oficial de ONLYOFFICE es de al menos 40 GB)
- Se recomiendan al menos 4 GB de swap
- Hacer accesibles los puertos TCP 80/443 desde Internet
- Un nombre DNS para la aplicación

Ejemplo:

```text
chat.example.com  A/AAAA -> サーバー
```

ONLYOFFICE se publica en `/onlyoffice/` bajo el mismo dominio que la aplicación. No exponga externamente ONLYOFFICE, Reverb ni los puertos 8080, 8081 y 8090 utilizados para las conexiones internas de la aplicación. En el firewall en la nube o del host, permita únicamente el puerto utilizado para SSH y los puertos 80/443.

## Configuración automática en un entorno Ubuntu

En un Ubuntu Server nuevo, clone este repositorio y ejecute `setup.sh`. Se le solicitarán interactivamente el dominio, la base de datos y la dirección de correo de Let's Encrypt. Puede ejecutarlo como usuario normal o como root.

```bash
apt install -y git

git clone https://github.com/askdkc/chatterrow.git
cd chatterrow
./setup.sh
```

Para entornos de desarrollo o verificación local, redes cerradas y otros casos en los que no se necesiten un dominio público accesible desde Internet ni HTTPS, ejecútelo con `--no-ssl`. No se utilizará Let's Encrypt y se configurará únicamente HTTP. Si utiliza un dominio local como `chatterrow.test`, configure previamente la resolución de nombres hacia este servidor mediante DNS o `/etc/hosts`.

```bash
./setup.sh --domain chatterrow.test --database sqlite --no-ssl
```

Al ejecutar `setup.sh`, se solicitará la contraseña de sudo. Si utiliza un usuario con capacidad para usar sudo sin contraseña, ejecútelo con la opción `--sudo-nopasswd`, como en el ejemplo siguiente.


```bash
./setup.sh --sudo-nopasswd
```

Si se ejecuta sin opciones, cuando se detecta un sudo que no requiere contraseña, la configuración no comienza y el proceso termina mostrando cómo utilizar `--sudo-nopasswd`.

Ejemplo de entrada después de ejecutar `setup.sh`:

```text
Application domain (e.g. chat.example.com): chat.example.com
Application database:
  1) sqlite
  2) postgresql
Select database (default: 1): 2
Let's Encrypt email (optional): admin@example.com
（中略）
PostgreSQL password (leave blank to generate):
```

La URL pública de ONLYOFFICE es `https://<アプリドメイン>/onlyoffice`. En el ejemplo anterior es `https://chat.example.com/onlyoffice`.

La configuración ejecuta automáticamente lo siguiente.

1. Configura `nginx-extras` oficial de Ubuntu, PHP 8.5, PostgreSQL, Redis, RabbitMQ y Node.js 24
2. Instala extensiones de PHP, Poppler, ImageMagick, Ghostscript y fuentes japonesas mediante apt, y configura la ruta absoluta de ImageMagick en `.env`
3. Construye MarkItDown 0.1.7 en el entorno virtual de Python `.markitdown/venv` y verifica `pip check` y la versión de la CLI
4. Ajusta PostgreSQL de acuerdo con el número de CPU y la RAM instalada
5. Al seleccionar PostgreSQL, configura un rol con el mismo nombre que el usuario que ejecuta el proceso (`DEPLOY_USER`) y con `SUPERUSER LOGIN`
6. Instala ONLYOFFICE Document Server con JWT habilitado, en el puerto interno 8080 (`ONLYOFFICE_PORT` permite cambiarlo)
7. Aplica las dependencias, el frontend y las migraciones al repositorio clonado
8. Configura mediante nginx la aplicación, ONLYOFFICE, Reverb y la ruta de descarga interna de ONLYOFFICE
9. Mantiene como usuario `www-data` 10 procesos de cola de Redis, Reverb y el scheduler mediante Supervisor
10. Emite el certificado con Certbot y habilita `certbot.timer` y el hook de recarga de nginx
11. Aplica diariamente las actualizaciones de seguridad de Ubuntu, incluido nginx, mediante `unattended-upgrades`
12. Ejecuta comprobaciones de salud de PHP 8.5, Redis, PostgreSQL, ONLYOFFICE, Supervisor y la aplicación

nginx utiliza únicamente paquetes APT de Ubuntu.

## OnlyOffice local en macOS

En macOS no instale el paquete de OnlyOffice para Linux; inicie DocumentServer con el `container` de Apple. Se requieren Apple silicon y macOS 26 o posterior.

1. Instale [Apple Container](https://github.com/apple/container).
2. Instale ImageMagick con `brew install imagemagick`.
3. Prepare el `.env` de la aplicación Laravel. Si no existe, `setup.sh` copia `.env.example`.
4. Ajuste `APP_URL` a la URL local real.
5. Ejecute la configuración desde la raíz del repositorio. En macOS no son necesarios `--domain` ni `--database`.

```bash
cd /path/to/chatterrow
./setup.sh
```

No se sobrescribe todo el `.env` existente. Solo se actualizan las siguientes opciones de ONLYOFFICE y la ruta absoluta de ImageMagick detectada.

```dotenv
ONLYOFFICE_ENABLED
ONLYOFFICE_DOCUMENT_SERVER_URL
ONLYOFFICE_PUBLIC_URL
APP_ONLYOFFICE_INTERNAL_URL
ONLYOFFICE_JWT_SECRET
ONLYOFFICE_ALLOW_DOWNLOAD
ONLYOFFICE_ALLOW_PRINT
IMAGEMAGICK_PATH
```

### Durante el desarrollo en macOS: detección automática de Valet, Herd y artisan serve

`setup.sh` comprueba si existen los comandos `valet` y `herd`, así como las respuestas de `APP_URL/up` y `127.0.0.1:<ポート>/up`, y selecciona el método de conexión. Si responden tanto Valet/Herd como artisan serve, se prioriza el lado de Valet/Herd de `APP_URL`.

| Servidor de desarrollo | Ejemplo de `.env`: `APP_URL` | `APP_ONLYOFFICE_INTERNAL_URL` configurada automáticamente |
|------------------------|--------------------------------|-----------------------------------------------------------|
| Laravel Valet          | `http://chatterrow.test`        | `http://chatterrow.test`                                  |
| Laravel Herd           | `http://chatterrow.test`        | `http://chatterrow.test`                                  |
| `php artisan serve`    | `http://localhost:8000`         | `http://chatter-host.container.internal:8000`             |

Si utiliza artisan serve, inícielo antes de la configuración o antes de abrir una vista previa.

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Para sobrescribir la detección automática:

```bash
MACOS_APP_SERVER=artisan ./setup.sh
MACOS_APP_SERVER=artisan MACOS_ARTISAN_PORT=9000 ./setup.sh
MACOS_APP_SERVER=valet ./setup.sh
MACOS_APP_SERVER=herd ./setup.sh
```

La configuración hace lo siguiente.

- Inicia Apple Container con `container system start`
- Hace pull de `onlyoffice/documentserver:latest` cada vez y lo inicia en arm64
- Publica OnlyOffice en `127.0.0.1:8086`
- Asigna 4 CPU, 4 GB de memoria y 2 GB de memoria compartida
- Fija el servidor DNS del contenedor en `1.1.1.1`
- Habilita JWT y configura la clave secreta compartida en `.env`
- Cada vez que se ejecuta `setup.sh` en macOS, vuelve a crear el contenedor de OnlyOffice conservando los volúmenes con nombre
- Conecta `chatter-host.container.internal` al loopback de macOS a través de `203.0.113.150`
- En Valet/Herd, asigna el nombre de host de la aplicación a `203.0.113.150` únicamente dentro del contenedor
- Descarga y verifica Source Han Sans JP / Noto Serif CJK JP, y vuelve a generar el catálogo de fuentes de OnlyOffice
- Actualiza `ONLYOFFICE_DOCUMENT_SERVER_URL`, `ONLYOFFICE_PUBLIC_URL` y `APP_ONLYOFFICE_INTERNAL_URL`
- Comprueba la salud de `/up` de DocumentServer y Laravel

Para sobrescribir el servidor DNS, especifique la variable de entorno. Indique el valor como una dirección IPv4.

Ejemplo de ejecución cambiando el 1.1.1.1 de Cloudflare por el 8.8.8.8 de Google:
```bash
MACOS_CONTAINER_DNS=8.8.8.8 ./setup.sh
```

Para los datos persistentes se utilizan los siguientes volúmenes con nombre.

```text
chatterrow-onlyoffice-data
chatterrow-onlyoffice-logs
chatterrow-onlyoffice-cache
chatterrow-onlyoffice-postgresql
```

Comprobar y detener el contenedor:

```bash
container list
container logs chatterrow-onlyoffice-documentserver
container stop chatterrow-onlyoffice-documentserver
```

Comprobación de salud:

```bash
curl -fsS http://127.0.0.1:8086/healthcheck
container exec chatterrow-onlyoffice-documentserver \
    curl -fsS --max-time 5 "$(sed -n 's/^APP_ONLYOFFICE_INTERNAL_URL=//p' .env)/up"
```

### Prueba de inicialización completa del contenedor y los volúmenes

Las operaciones siguientes eliminan PostgreSQL interno de OnlyOffice, la configuración, la caché y los registros. No las ejecute si hay datos necesarios.

```bash
container stop chatterrow-onlyoffice-documentserver
container delete chatterrow-onlyoffice-documentserver
container volume delete \
    chatterrow-onlyoffice-data \
    chatterrow-onlyoffice-logs \
    chatterrow-onlyoffice-cache \
    chatterrow-onlyoffice-postgresql
./setup.sh
```

[Los volúmenes con nombre de Apple Container](https://github.com/apple/container/blob/main/docs/command-reference.md#volume-management) se crean implícitamente cuando no existen mediante `container run --volume <名前>:<パス>`, por lo que con el procedimiento anterior puede comprobarse la configuración inicial, incluida la creación de los volúmenes.

### Fuentes japonesas (macOS)

Aunque instale fuentes en el host macOS, el contenedor aislado de OnlyOffice no puede utilizarlas. `setup.sh` descarga las fuentes de peso fijo desde los repositorios oficiales con versiones fijadas, verifica su SHA-256 y después las registra en OnlyOffice.

- [Source Han Sans JP](https://github.com/adobe-fonts/source-han-sans) Light / Regular / Bold (2.005R, OTF del subconjunto JP)
- [Noto Serif CJK JP](https://github.com/notofonts/noto-cjk) Regular / Bold (Serif2.003, OTF japonés)

Las fuentes se guardan dentro del volumen `chatterrow-onlyoffice-data` en `/var/www/onlyoffice/Data/custom-fonts`. Al volver a ejecutar la configuración, se comprueba el SHA-256 de los archivos dentro del contenedor y no se vuelven a descargar si coincide.

Después de registrar las versiones de peso fijo, `setup.sh` elimina los `AllFonts.js` y `font_selection.bin` existentes y ejecuta `allfontsgen`. Si se conservan los archivos existentes, `allfontsgen` puede reutilizar el catálogo y no registrar las nuevas fuentes.

El converter de OnlyOffice 9.4 puede sustituir las fuentes de tema japonesas de Microsoft Office por `NanumGothic` o `Droid Sans Fallback`. Los alias de fontconfig no afectan a esta ruta de conversión. Por ello, [scripts/patch-onlyoffice-font-catalog.php](../scripts/patch-onlyoffice-font-catalog.php) aplica las siguientes correcciones a `font_selection.bin` del servidor y a los dos `AllFonts.js` del navegador.

- Registra los alias de las familias 游ゴシック, Yu Gothic, Meiryo y MS Gothic en Source Han Sans JP
- Registra los alias de las familias 游明朝, Yu Mincho y MS Mincho en Noto Serif CJK JP
- Cambia la referencia a la fuente real de `NanumGothic` seleccionada por el converter a Source Han Sans JP
- Cambia la referencia a la fuente real de `Droid Sans Fallback` seleccionada por el converter a Noto Serif CJK JP

Este catálogo se comparte entre la conversión de DOCX, XLSX y PPTX y la visualización en el navegador. Después de las correcciones, se genera la caché de JS, se reinician docservice y converter y se borra la caché de DocumentServer. Si el formato del catálogo o los nombres de fuentes necesarios cambian en una futura versión `latest`, la configuración termina con un error sin utilizar un catálogo incorrecto.

No se llama al `documentserver-generate-allfonts.sh` estándar porque también vuelve a generar temas de presentación innecesarios y, en el entorno de Apple Container, ese proceso puede no finalizar.

Al actualizar el catálogo de fuentes también cambia la generación de la caché de documentos de OnlyOffice, por lo que los DOCX ya abiertos se vuelven a convertir sin reutilizar un `Editor.bin` antiguo.

Si un contenedor existente deja de responder en `Generating presentation themes`, vuelva a ejecutar `./setup.sh` tal cual. En macOS, cada ejecución hace pull de `onlyoffice/documentserver:latest` y fuerza la recreación del contenedor existente de OnlyOffice. Los cuatro volúmenes con nombre anteriores no se eliminan, por lo que se conservan los datos persistentes de OnlyOffice.

Las fuentes 游明朝 / 游ゴシック de Microsoft no se incluyen ni se redistribuyen. Dentro del contenedor de OnlyOffice se utilizan las siguientes fuentes alternativas.

| Fuente indicada por Office (DOCX / XLSX / PPTX)    | Fuente alternativa       |
|-----------------------------------------------------|--------------------------|
| 游明朝 / Yu Mincho / MS Mincho                      | Noto Serif CJK JP        |
| 游ゴシック / Yu Gothic / Meiryo / MS Gothic         | Source Han Sans JP       |

Se corrigen los caracteres ausentes y la sustitución por fuentes latinas inadecuadas, pero como el ancho de los caracteres difiere del de las fuentes 游, no se garantiza que coincidan exactamente los saltos de línea ni el número de páginas. Si necesita una coincidencia exacta, coloque por separado en el área de fuentes personalizadas de OnlyOffice los archivos reales de las fuentes 游, después de comprobar sus licencias de uso.

Comprobar el estado del registro:

```bash
container exec chatterrow-onlyoffice-documentserver \
    awk '$1 == "nameserver" { print $2 }' /etc/resolv.conf
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游明朝:lang=ja'
container exec chatterrow-onlyoffice-documentserver \
    fc-match '游ゴシック Light:lang=ja'
```

Los valores esperados son, en orden, `1.1.1.1`, `NotoSerifCJKjp-Regular.otf` y `SourceHanSansJP-Light.otf`.

En macOS no se conceden permisos de edición a OnlyOffice; permanece como vista previa ReadOnly. La API de conversión de DocumentServer también puede utilizarse de forma independiente de esta configuración ReadOnly.

## Configuración no interactiva

En entornos de automatización de Ubuntu, `--domain` y `--database` son obligatorios. No son necesarios en la configuración local de OnlyOffice para macOS.

```bash
./setup.sh \
    --domain chat.example.com \
    --email admin@example.com \
    --database postgresql
```

### Opciones

| Opción                       | Valor predeterminado                 | Descripción                                          |
|------------------------------|--------------------------------------|------------------------------------------------------|
| `--domain <domain>`          | entrada interactiva                  | Dominio público de la aplicación                     |
| `--email <email>`            | vacío                                | Correo de registro y avisos de vencimiento de Let's Encrypt |
| `--database <driver>`        | `sqlite` en modo interactivo         | `sqlite` o `postgresql`                              |
| `--db-name <name>`           | `chatterrow`                         | Nombre de la base de datos PostgreSQL de la aplicación |
| `--db-user <name>`           | `chatterrow`                         | Rol PostgreSQL de la aplicación                      |
| `--db-password <password>`   | generado automáticamente              | Contraseña PostgreSQL de la aplicación               |
| `--app-dir <path>`           | repositorio donde se encuentra `setup.sh` | Directorio de despliegue bajo `/home` o `/var/www` |
| `--repo <url>`               | URL SSH de GitHub                    | Repositorio Git que se desplegará                    |
| `--onlyoffice-image <image>` | `onlyoffice/documentserver:latest`   | Imagen de DocumentServer que se descarga y utiliza cada vez en macOS |
| `--sudo-nopasswd`            | desactivado                          | Para usuarios sudo sin contraseña. Omite `sudo -v`  |
| `--no-ssl`                   | desactivado                          | Omite Certbot y configura HTTP                       |

También pueden utilizarse las variables de entorno en mayúsculas con el mismo nombre. Ejemplos: `DOMAIN`, `DATABASE`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DEPLOY_USER`, `SUDO_NOPASSWD`, `ONLYOFFICE_PORT`, `ONLYOFFICE_JWT_SECRET`.

Si se omite la contraseña de PostgreSQL, se genera un valor aleatorio de 64 caracteres y se guarda en los siguientes lugares.

```text
/etc/chatterrow/database-password  root:root 0600
/home/ubuntu/chatterrow/.env       <deploy-user>:www-data 0640
```

Al seleccionar PostgreSQL, se crea o actualiza el rol de PostgreSQL que tiene el mismo nombre que el usuario que ejecuta el proceso (`DEPLOY_USER`; si no se especifica, `id -un` cuando se ejecuta como usuario normal o `root` cuando se ejecuta como root) como `LOGIN SUPERUSER`. `DB_USER`, utilizado para la conexión de la aplicación, se mantiene como un rol sin privilegios.

## Ajuste automático de PostgreSQL

PostgreSQL también es necesario para ONLYOFFICE, por lo que se instala y ajusta incluso cuando la aplicación utiliza SQLite. Si hay varios clústeres de PostgreSQL en Ubuntu, la configuración se detiene para no modificar el clúster equivocado.

Archivo de configuración:

```text
/etc/postgresql/<version>/<cluster>/conf.d/99-chatterrow-tuning.conf
```

Criterios principales de cálculo:

| Configuración          | Criterio                                           |
|------------------------|----------------------------------------------------|
| `shared_buffers`       | 20% de la RAM, entre 128 MB y 8 GB                 |
| `effective_cache_size` | 60% de la RAM, entre 256 MB y 64 GB                |
| `maintenance_work_mem` | 5% de la RAM, entre 64 MB y 1 GB                   |
| `work_mem`             | Calculado de forma conservadora a partir de la RAM, `shared_buffers` y el número máximo de conexiones |
| `max_connections`      | Calculado a partir de CPU y RAM, entre 50 y 300    |
| parallel workers       | Calculados a partir del número de CPU y con un límite |

Como este servidor también aloja PHP, ONLYOFFICE, Redis y RabbitMQ, la asignación es más conservadora que en un servidor dedicado exclusivamente a PostgreSQL. Al volver a ejecutar la configuración, se recalcula a partir del número actual de CPU y la RAM.

## Configuración de puertos

Entorno de producción Ubuntu:

| Puerto   | Uso                                  | Alcance de publicación |
|----------|--------------------------------------|------------------------|
| 80 / 443 | nginx, Certbot y Web pública         | Internet               |
| 8080     | ONLYOFFICE Document Server           | dirigido a localhost  |
| 8081     | Laravel Reverb                       | dirigido a localhost  |
| 8090     | Obtener archivos firmados desde ONLYOFFICE | solo 127.0.0.1    |
| 5432     | PostgreSQL                           | se recomienda conexión local |

Entorno local macOS:

| Puerto | Uso                                           | Alcance de publicación |
|--------|-----------------------------------------------|------------------------|
| 8086   | ONLYOFFICE Document Server en Apple Container | solo `127.0.0.1`      |
| 8000   | Puerto predeterminado de `php artisan serve`  | solo `127.0.0.1`      |

Al utilizar Valet/Herd, la aplicación Laravel funciona con el nombre de host de `APP_URL` y el puerto HTTP/HTTPS habitual. El puerto de artisan serve se determina a partir de `APP_URL` o `MACOS_ARTISAN_PORT`.

## SSL y actualización automática

Certbot utiliza `/var/www/letsencrypt` como webroot ACME fijo, configura en nginx el certificado del dominio de la aplicación sin pasar el challenge a Laravel y sirve también `/onlyoffice/` con el mismo certificado. `certbot.timer` comprueba periódicamente cuándo corresponde renovar y, tras una renovación correcta, recarga nginx. Durante la configuración también se ejecuta un dry-run.

`unattended-upgrades` comprueba a diario el origen de seguridad de Ubuntu y actualiza, junto con sus dependencias, `nginx`, `nginx-extras` y los `libnginx-mod-*` correspondientes. También mantiene las actualizaciones de seguridad distintas de nginx. No aplica automáticamente el pocket normal `-updates`.

```bash
sudo systemctl status certbot.timer
sudo certbot certificates
sudo certbot renew --dry-run
sudo systemctl status apt-daily-upgrade.timer
sudo unattended-upgrade --dry-run --debug
```

## Operaciones

### Comprobación de procesos

```bash
php8.5 --version
php8.5 -m | grep -E 'redis|pdo_sqlite|pdo_pgsql'
redis-cli ping
sudo supervisorctl status 'chatterrow-queue:*'
sudo supervisorctl restart 'chatterrow-queue:*'
sudo supervisorctl restart chatterrow-reverb chatterrow-schedule
sudo tail -f /var/log/chatterrow-queue_*.log /var/log/chatterrow-queue-error_*.log
```

Los workers de Queue ejecutan `/usr/bin/php8.5 artisan queue:work redis --sleep=3 --tries=5 --max-time=3600` en 10 procesos. Compruebe que los 10 estén en estado `RUNNING`.

### Reprocesamiento de conversiones Markdown

Se vuelven a poner en cola los archivos que han fallado y los archivos `pending`/`processing` que no se han actualizado durante cierto tiempo.

```bash
php artisan files:markdown
php artisan files:markdown --server=1 --stale-after=900
php artisan queue:work redis --once
```

`files:markdown` no convierte los formatos antiguos de Office (DOC, XLS, PPT y ODF) a Markdown. Las funciones de vista previa de ONLYOFFICE para estos formatos siguen estando disponibles.

### Actualización de la aplicación

Puede volver a ejecutar `setup.sh` con la misma configuración. Se conservan la contraseña existente de PostgreSQL y la configuración de nginx con TLS habilitado, y Git solo se actualiza cuando es posible hacer fast-forward.

```bash
cd /path/to/chatterrow-source
./setup.sh --domain chat.example.com --database postgresql --email admin@example.com
```

### Copias de seguridad

SQLite:

```bash
sudo install -d /backup
sudo -u www-data sqlite3 /home/ubuntu/chatterrow/database/database.sqlite \
    ".backup /backup/chatterrow-$(date +%F).sqlite"
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

PostgreSQL:

```bash
sudo install -d /backup
sudo -u postgres pg_dump --format=custom chatterrow > /backup/chatterrow-$(date +%F).dump
sudo rsync -a /home/ubuntu/chatterrow/storage/app/ /backup/storage-app/
sudo rsync -a /home/ubuntu/chatterrow/storage/markdowned-docs/ /backup/markdowned-docs/
```

## Principales variables de entorno

| Variable                         | Descripción                                             |
|----------------------------------|---------------------------------------------------------|
| `APP_URL`                        | URL pública de la aplicación                            |
| `DB_CONNECTION`                  | `sqlite` o `pgsql`                                      |
| `QUEUE_CONNECTION`               | Cola Redis (`redis`)                                    |
| `MARKITDOWN_PATH`                | Ruta de la CLI de MarkItDown. Si no se especifica, está dentro de `.markitdown/venv` |
| `MARKITDOWN_TIMEOUT`             | Segundos de tiempo de espera de conversión por archivo  |
| `MARKITDOWN_PYTHON_MIN_VERSION`  | Versión mínima de Python necesaria para el entorno MarkItDown (3.10) |
| `IMAGEMAGICK_PATH`               | Ruta absoluta de `magick` o `convert`                   |
| `REVERB_APP_ID/KEY/SECRET`       | Credenciales de Reverb                                   |
| `REVERB_HOST/PORT/SCHEME`        | WebSocket público al que se conectan el navegador y Laravel |
| `REVERB_SERVER_HOST/PORT`        | Destino interno de escucha de Reverb. La configuración utiliza `127.0.0.1:8081` |
| `REVERB_ALLOWED_ORIGINS`         | Dominios públicos autorizados para conectarse a Reverb  |
| `ONLYOFFICE_DOCUMENT_SERVER_URL` | URL interna de ONLYOFFICE a la que se conecta Laravel (127.0.0.1 en Ubuntu) |
| `ONLYOFFICE_PUBLIC_URL`           | URL pública de ONLYOFFICE visible desde el navegador     |
| `APP_ONLYOFFICE_INTERNAL_URL`    | URL interna de la aplicación desde la que ONLYOFFICE obtiene archivos |
| `ONLYOFFICE_JWT_SECRET`          | Clave secreta JWT compartida con ONLYOFFICE              |

## Solución de problemas

| Síntoma                              | Comprobación                                                                                              |
|--------------------------------------|-----------------------------------------------------------------------------------------------------------|
| 502 Bad Gateway                      | `sudo systemctl status php*-fpm`, `sudo nginx -t`                                                         |
| No se actualiza en tiempo real       | `sudo supervisorctl status chatterrow-reverb`, conexión a `/app/` en Network del navegador              |
| No se genera la vista previa del adjunto | `/var/log/chatterrow-queue-error_*.log`, ONLYOFFICE/Poppler/ImageMagick                                |
| `exec: convert: not found`           | Después de obtener la ruta absoluta mediante `command -v magick` o `command -v convert`, configure `IMAGEMAGICK_PATH` y ejecute `php artisan optimize:clear` |
| Falla la conversión a Markdown       | `storage/logs/laravel.log`, `/var/log/chatterrow-queue-error_*.log`, `php artisan files:markdown`       |
| La cola Redis no se procesa          | `redis-cli ping`, compruebe que `php8.5 -m` incluya `redis`, y `sudo supervisorctl status 'chatterrow-queue:*'` |
| No se abre la vista previa de Office (Ubuntu) | `curl http://127.0.0.1:8080/healthcheck`, clave secreta JWT, URL interna del puerto 8090, `php artisan files:previews` |
| No se abre la vista previa de Office (macOS) | `curl http://127.0.0.1:8086/healthcheck`, clave secreta JWT, `APP_ONLYOFFICE_INTERNAL_URL`, accesibilidad de `/up` desde el contenedor |
| No se puede conectar a PostgreSQL    | `.env`, `sudo -u postgres pg_isready`, `/etc/chatterrow/database-password`                              |
| No se puede emitir el certificado   | A/AAAA del dominio de la aplicación, accesibilidad del puerto 80 desde Internet, `/var/log/letsencrypt/` |

## Desarrollo local

```bash
composer install
composer markitdown:install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run dev
```

Inicie Laravel y Reverb en terminales separadas.

```bash
php artisan serve
php artisan reverb:start --port=8081
php artisan queue:work redis
```

Si Redis Server no está iniciado, en macOS ejecute `brew services start redis` y en Ubuntu ejecute `sudo systemctl enable --now redis-server`.

Verificación:

```bash
php artisan test
php artisan files:markdown
npm run test:unit
npm run lint:check
npm run types:check
npm run build
```

## Licencia

MIT. Si instala ONLYOFFICE Docs Community Edition, consulte también las condiciones de AGPLv3.
