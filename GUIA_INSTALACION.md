# Guía de instalación y ejecución — Clínica Vida Salud

Esta guía explica cómo preparar Windows, configurar PostgreSQL, instalar las dependencias y abrir la aplicación en el navegador.

**Proyecto:** Laravel + PostgreSQL, con interfaz Blade, CSS y JavaScript.  
**Base de datos operativa:** `Parcial2`.  
**Esquema:** `vida_salud`.  
**Entorno comprobado:** PHP 8.5.12 y PostgreSQL 18.6, el 24/10/2026.

## Inicio rápido en tu computadora actual

En tu equipo ya se prepararon el PHP compatible, PostgreSQL y la base de la aplicación. Para instalar/verificar las dependencias y arrancar, abre PowerShell y ejecuta:

```powershell
Set-Location -LiteralPath 'D:\Universidad Carlos Villagra\2026-2\Ingenieria de requisitos y modelado - SC\Nueva carpeta (3)'
$env:VIDA_SALUD_PHP = 'C:\Users\USUARIO\.config\vida-salud\php85\php.exe'
.\scripts\install.ps1
.\scripts\start.ps1
```

Espera a que aparezca `Server running on ...`. **Deja esa terminal abierta** y visita:

**http://localhost:8000**

Acceso de demostración:

```text
Usuario: admin
Contraseña inicial: VidaSalud2026!
```

Si esa contraseña se cambió anteriormente, usa la nueva: el instalador no restablece contraseñas de cuentas existentes.

Si ya instalaste las dependencias y no hubo cambios de instalación, en los siguientes arranques basta con entrar en la carpeta y ejecutar `.\scripts\start.ps1`.

---

## 1. Requerimientos

| Requisito | Qué necesitas |
| --- | --- |
| Sistema | Windows de 64 bits para el runtime preparado; comandos de esta guía en PowerShell. |
| PHP | **8.5.x es la opción comprobada.** Las dependencias actuales de `composer.lock` exigen al menos **8.4.1**. |
| Controlador de base | Extensión PHP **`pdo_pgsql`** habilitada. |
| Extensiones PHP | `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `PDO`, `session`, `SimpleXML`, `tokenizer`, `xml`, `xmlreader` y `xmlwriter`; `zip` facilita la instalación. Composer verificará los requisitos reales. |
| Composer | Composer 2, accesible desde la terminal, con su archivo `composer.phar`. |
| PostgreSQL | **PostgreSQL 18**, versión del esquema y de las herramientas comprobadas. |
| Herramientas PostgreSQL | `psql` y `createdb`; `pg_dump` y `pg_restore` si utilizarás respaldos. |
| Base y permisos | `Parcial2` existente y usuario con permisos para crear el esquema, tablas y la extensión `btree_gist` cuando falte. En el entorno local se utiliza `postgres`. |
| Archivos | Proyecto completo, incluido `composer.lock`, `.env.example`, `database/vida_salud_existing.sql` y `scripts`. |
| Escritura | Permiso para escribir en el proyecto, especialmente en `storage` y `bootstrap/cache`. |
| Navegador | Un navegador actual con JavaScript habilitado para disponibilidad y validaciones interactivas. |
| Internet | Para descargar herramientas y dependencias cuando todavía no están instaladas. |

**No hace falta instalar Apache, XAMPP, MySQL o SQLite para seguir esta guía.** Se usa el servidor local de Laravel y PostgreSQL.

**Node.js y npm son opcionales:** solo se necesitan para ejecutar las pruebas JavaScript. La interfaz actual carga archivos de `public/assets`; para verla no hace falta `npm install`, `npm run dev` ni `npm run build`.

> Sobre PHP: `composer.json` declara `^8.3`, pero eso no rebaja el mínimo de las versiones resueltas en `composer.lock`. El comprobador generado por Composer exige `>= 8.4.1`. Utiliza el PHP 8.5 preparado o uno que cumpla las dependencias; no uses opciones para ignorar requisitos de plataforma.

## 2. Instalar las herramientas, solo si te faltan

Si estás en la computadora donde se desarrolló el proyecto, continúa con el paso 3. Esta sección sirve para preparar otro equipo o reparar una instalación incompleta.

### 2.1. PHP

Puedes obtener la distribución para Windows indicada por la [documentación oficial de PHP](https://www.php.net/manual/en/install.windows.manual.php). Para este flujo con consola utiliza PHP 8.5, x64, NTS. Si falta una DLL del runtime de Visual C++, instala el redistribuible x64 que enlaza esa misma documentación.

1. Descarga el ZIP de PHP para Windows desde el sitio oficial.
2. Extrae **todo su contenido**, incluidas las DLL y la carpeta `ext`, en una carpeta estable.
3. Una ubicación compatible con los helpers del proyecto es:

   ```text
   C:\Users\TU_USUARIO\.config\vida-salud\php85
   ```

4. Si no existe `php.ini`, copia `php.ini-development` como `php.ini` dentro de esa carpeta.
5. Abre ese `php.ini` con un editor de texto.
6. Habilita las extensiones necesarias quitando `;` de las líneas existentes o agregando las que falten. No dupliques líneas ya activas. Para la distribución completa utilizada en el proyecto, la configuración relevante incluye:

   ```ini
   extension_dir = "ext"
   extension=pdo_pgsql
   extension=pgsql
   extension=mbstring
   extension=openssl
   extension=fileinfo
   extension=curl
   extension=zip
   extension=intl
   extension=gd

   date.timezone=America/La_Paz
   upload_max_filesize=5M
   post_max_size=8M
   ```

   `pdo_pgsql` es obligatorio. `pgsql`, `intl` y `gd` están habilitadas en el runtime preparado, aunque no todas son un requisito directo de conexión. Las extensiones que ya vienen integradas, como JSON en este entorno, no necesitan una línea `extension=`.

7. Configura `upload_max_filesize` y `post_max_size` si vas a cargar informes de hasta 5 MB: PHP también limita la carga antes de que llegue a Laravel.

En **tu instalación actual**, el ejecutable preparado es:

```text
C:\Users\USUARIO\.config\vida-salud\php85\php.exe
```

El PHP global de Herd Lite que había en el equipo no tiene `pdo_pgsql`; los helpers lo descartan y buscan un ejecutable compatible.

### 2.2. Composer

Descarga y ejecuta `Composer-Setup.exe` siguiendo la [instalación oficial de Composer para Windows](https://getcomposer.org/doc/00-intro.md#installation-windows). Cuando pida PHP, selecciona el ejecutable completo del paso anterior. Después de instalar Composer, cierra la terminal y abre otra para que se actualice el `PATH`.

Comprueba:

```powershell
composer -V
Get-Command composer
```

El helper de instalación espera encontrar `composer.phar` junto al lanzador `composer`. En la instalación actual está en:

```text
C:\Users\USUARIO\.config\herd-lite\bin\composer.phar
```

Ese archivo puede ser ejecutado por el PHP completo, aunque su carpeta también contenga el PHP de Herd.

### 2.3. PostgreSQL

Obtén PostgreSQL 18 desde la [página oficial de instaladores para Windows](https://www.postgresql.org/download/windows/). Durante la instalación prepara el servidor y sus herramientas de consola; pgAdmin sirve como alternativa gráfica para administrar las bases.

Guarda estos datos de tu instalación:

- Usuario de conexión: normalmente `postgres` en este entorno local.
- Contraseña que asignaste.
- Puerto: `5432` en los comandos de esta guía.

La ubicación comprobada de las herramientas es:

```text
C:\Program Files\PostgreSQL\18\bin
```

No necesitas reinstalar PostgreSQL si ya está funcionando con la base correcta.

## 3. Entrar a la carpeta del proyecto

Abre PowerShell y ejecuta:

```powershell
Set-Location -LiteralPath 'D:\Universidad Carlos Villagra\2026-2\Ingenieria de requisitos y modelado - SC\Nueva carpeta (3)'
```

Si copiaste el proyecto a otra ubicación, cambia esa ruta por la tuya.

Comprueba que estás en la raíz:

```powershell
Get-Location
Test-Path .\artisan
Test-Path .\composer.json
Test-Path .\database\vida_salud_existing.sql
Test-Path .\scripts\install.ps1
```

Los cuatro `Test-Path` deben mostrar `True`. Si alguno falla, revisa la ubicación o copia los archivos que faltan.

## 4. Seleccionar y comprobar PHP

Para el equipo actual:

```powershell
$env:VIDA_SALUD_PHP = 'C:\Users\USUARIO\.config\vida-salud\php85\php.exe'
& $env:VIDA_SALUD_PHP -v
& $env:VIDA_SALUD_PHP --ini
& $env:VIDA_SALUD_PHP -m
```

Debes ver PHP 8.5.12 en el entorno preparado y **`pdo_pgsql`** en la lista de módulos.

Comprobación breve del controlador:

```powershell
& $env:VIDA_SALUD_PHP -m | Select-String 'pdo_pgsql'
```

Si no aparece, revisa el `php.ini` que muestra `--ini`, habilita el controlador y repite la comprobación. Editar otro `php.ini` no modifica el ejecutable que estás utilizando.

Si estás en otro equipo, asigna a `VIDA_SALUD_PHP` la ruta real de su `php.exe`. Esta variable se configura para la terminal actual; no cambia el PHP global de Windows.

## 5. Comprobar PostgreSQL y preparar `Parcial2`

### 5.1. Confirmar que el servidor está iniciado

```powershell
Get-Service '*postgres*'
```

En el equipo actual el servicio se llama `postgresql-x64-18` y debe estar en estado `Running`. Si está detenido, inícialo desde la aplicación Servicios de Windows. Iniciar un servicio puede requerir una terminal con permisos de administrador.

### 5.2. Comprobar conexión

```powershell
$pgBin = 'C:\Program Files\PostgreSQL\18\bin'
& "$pgBin\psql.exe" -h localhost -p 5432 -U postgres -d postgres -c 'SELECT version();'
```

Introduce la contraseña cuando se solicite. Si la versión aparece, la conexión funciona.

### 5.3. Crear la base, únicamente si no existe

Si `Parcial2` ya existe —como en tu equipo— **no la vuelvas a crear ni la borres**. Puedes comprobarlo con:

```powershell
& "$pgBin\psql.exe" -h localhost -p 5432 -U postgres -d postgres -c "SELECT datname FROM pg_database WHERE datname = 'Parcial2';"
```

Si no devuelve una fila y estás preparando una instalación nueva:

```powershell
& "$pgBin\createdb.exe" -h localhost -p 5432 -U postgres --encoding=UTF8 Parcial2
```

El nombre es exactamente `Parcial2`, con mayúscula inicial. Al crearla mediante SQL en una herramienta gráfica debes escribir `CREATE DATABASE "Parcial2";` para conservar esa capitalización.

El instalador crea el esquema y las tablas mediante migraciones cuando la base está vacía. Si encuentra el esquema original `vida_salud`, lo reconoce y aplica las ampliaciones pendientes; no debes importar manualmente el snapshot encima de la base existente.

## 6. Configurar e instalar la aplicación

### 6.1. Ejecutar el instalador

Con la base preparada y la variable de PHP configurada:

```powershell
.\scripts\install.ps1
```

Si `.env` no existe, el instalador lo copia desde `.env.example` y pregunta:

```text
Base de datos existente [Parcial2]
Usuario PostgreSQL [postgres]
Contraseña PostgreSQL
```

Puedes presionar Enter para aceptar el nombre y usuario predeterminados. Escribe la contraseña real de tu servidor cuando se solicite; se introduce de forma oculta.

Si `.env` ya existe, sus datos se conservan. Para solicitar de nuevo la configuración de base:

```powershell
.\scripts\install.ps1 -ConfigureDatabase
```

### 6.2. Qué realiza el instalador

1. Selecciona el PHP compatible.
2. Ejecuta `composer install` mediante ese mismo PHP.
3. Instala las versiones fijadas en `composer.lock`.
4. Limpia la caché de configuración.
5. Genera `APP_KEY` si no estaba configurada.
6. Ejecuta `artisan migrate --seed`.
7. Crea las cuentas y datos de demostración que falten.

Al terminar deberías ver:

```text
Instalación terminada. Ejecute .\scripts\start.ps1.
```

No ejecutes `composer update` para una instalación normal. Tampoco necesitas repetir la instalación de Laravel Boost: ya forma parte de las dependencias del proyecto.

### 6.3. Valores esperados de `.env`

Si necesitas revisar el archivo, ábrelo con:

```powershell
notepad .env
```

La configuración relevante es:

```dotenv
APP_NAME="Clínica Vida Salud"
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost:8000
APP_LOCALE=es
APP_FALLBACK_LOCALE=es

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=Parcial2
DB_USERNAME=postgres
DB_PASSWORD="TU_CONTRASEÑA_POSTGRESQL"
DB_SCHEMA=vida_salud,public
DB_SSLMODE=prefer

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

DEMO_PASSWORD="VidaSalud2026!"
```

`TU_CONTRASEÑA_POSTGRESQL` es un marcador de posición: reemplázalo por tu contraseña o utiliza el instalador interactivo, que se encarga del formato. No borres ni reemplaces la línea `APP_KEY` ya generada.

Después de editar `.env`:

```powershell
& $env:VIDA_SALUD_PHP artisan config:clear --no-interaction
```

No necesitas copiar tu contraseña real en esta guía ni en el código fuente.

## 7. Confirmar las migraciones

```powershell
& $env:VIDA_SALUD_PHP artisan migrate:status --no-interaction
```

Las cinco migraciones del proyecto deben aparecer como `Ran`:

```text
2026_10_24_134957_bootstrap_vida_salud_schema
2026_10_24_134958_extend_vida_salud_for_patient_portal
2026_10_24_134959_create_vida_salud_framework_tables
2026_10_24_135000_create_patient_portal_requests
2026_10_24_135001_correct_laboratory_publication_audit
```

Si quedaron migraciones pendientes tras corregir un error de configuración:

```powershell
& $env:VIDA_SALUD_PHP artisan migrate --seed --no-interaction
```

**No uses `migrate:fresh` ni `db:wipe` sobre `Parcial2`.** El proceso de instalación normal utiliza migraciones incrementales y no necesita vaciar la base.

## 8. Iniciar la web

```powershell
.\scripts\start.ps1
```

El script debe mostrar el PHP seleccionado y un mensaje de servidor iniciado:

```text
PHP seleccionado: ...\php85\php.exe
Server running on [http://127.0.0.1:8000]
```

Mantén abierta la terminal. Abre el navegador en:

**http://localhost:8000**

Si `localhost` no resuelve correctamente en tu equipo, utiliza la dirección `127.0.0.1` que muestra el servidor en la terminal.

No abras `public/index.php` directamente como un archivo desde el Explorador. Accede mediante la dirección HTTP del servidor.

### Si el puerto 8000 ya está ocupado

```powershell
.\scripts\start.ps1 -Port 8001
```

Abre la dirección del nuevo puerto que indica la terminal. No detengas otro programa sin comprobar primero qué proceso ocupa el puerto.

### Para detener la web

En la terminal del servidor pulsa:

```text
Ctrl+C
```

Eso detiene el servidor local; no borra la aplicación ni la base de datos.

## 9. Ingresar y comprobar que funciona

### 9.1. Cuentas de demostración

Si se crearon con el `DEMO_PASSWORD` predeterminado:

| Usuario | Rol | Contraseña inicial |
| --- | --- | --- |
| `admin` | Administrador | `VidaSalud2026!` |
| `recepcion` | Recepcionista | `VidaSalud2026!` |
| `medico` | Médico | `VidaSalud2026!` |
| `laboratorio` | Laboratorista | `VidaSalud2026!` |
| `paciente` | Paciente verificado | `VidaSalud2026!` |

Las cuentas existentes conservan su contraseña. Si configuraste otro `DEMO_PASSWORD` antes de crearlas, utiliza ese valor.

### 9.2. Recorrido de comprobación

1. Ingresa con `admin` y verifica que aparezca el panel de módulos.
2. Abre Usuarios, Médicos o Especialidades y comprueba sus registros de demostración.
3. Cierra sesión.
4. Ingresa con `recepcion`, abre Citas y elige crear una reserva.
5. Selecciona el paciente de demostración, Medicina General y Dr Uno.
6. Elige un día **futuro de lunes a viernes** y solicita los bloques libres. Los horarios iniciales son de 08:00 a 12:00.
7. Si deseas comprobar persistencia, guarda una cita ficticia y confirma que aparece en la agenda. Esa operación sí crea una fila real en `Parcial2`; usa únicamente datos de demostración para esta comprobación.

Si realizaste la reserva, puedes cancelarla con motivo desde la propia aplicación; se conserva su historial.

La cuenta pública que alguien cree desde Registro queda pendiente de verificación. Para una comprobación inmediata utiliza las cuentas de demostración ya habilitadas.

## 10. Cómo volver a ejecutarla otro día

No necesitas reinstalar las dependencias en cada arranque. Con PostgreSQL iniciado:

```powershell
Set-Location -LiteralPath 'D:\Universidad Carlos Villagra\2026-2\Ingenieria de requisitos y modelado - SC\Nueva carpeta (3)'
.\scripts\start.ps1
```

El helper busca el PHP compatible preparado en el perfil del usuario. Si utilizas otra ubicación, vuelve a definir `VIDA_SALUD_PHP` antes del arranque.

## 11. Solución de errores frecuentes

| Error o síntoma | Qué revisar o ejecutar |
| --- | --- |
| `could not find driver` | Se usó un PHP sin `pdo_pgsql`. Define `VIDA_SALUD_PHP`, comprueba `-m` y usa los scripts. |
| `Composer detected issues in your platform` | Usa PHP 8.5 o uno compatible con el mínimo efectivo de 8.4.1 y las extensiones requeridas. No ignores las comprobaciones. |
| `composer` no se reconoce | Instala Composer y abre una terminal nueva. Revisa `Get-Command composer`. |
| No se encuentra `composer.phar` | El helper espera el PHAR junto al lanzador. Revisa la instalación estándar de Composer o utiliza la alternativa manual de la sección 12. |
| No se puede conectar a PostgreSQL | Comprueba el servicio, host, puerto y `DB_DATABASE`. Repite el comando `psql` del paso 5. |
| `password authentication failed` | Corrige la contraseña mediante `install.ps1 -ConfigureDatabase`; después limpia la configuración. |
| La base no existe | Crea exactamente `Parcial2` o corrige `DB_DATABASE`. |
| `relation ... does not exist` | Comprueba `DB_SCHEMA=vida_salud,public` y ejecuta las migraciones pendientes. |
| Falta `btree_gist` o permiso para crear objetos | Para la instalación local usa un usuario con permisos de migración. No omitas las restricciones del esquema. |
| `No application encryption key has been specified` | Si `APP_KEY` está vacía, vuelve a ejecutar el instalador. No regeneres una clave válida de una instalación existente. |
| No se puede escribir en logs, vistas o sesiones | Revisa los permisos de escritura de `storage` y `bootstrap/cache`. |
| `Address already in use` | Usa otro puerto mediante `start.ps1 -Port 8001`. |
| El navegador no abre la página | Comprueba que la terminal siga mostrando el servidor activo y usa el puerto indicado. |
| Error 419 | Actualiza la pantalla e inicia sesión nuevamente si corresponde. |
| Error 429 | Espera un minuto antes de volver a intentar el acceso o registro. |
| Error 403/404 con otra cuenta | Puede corresponder al permiso o propiedad del registro; no todos los roles ven todos los módulos. |
| El registro público no deja ingresar | La cuenta requiere verificación y habilitación por recepción o administración. |
| No aparecen horarios | Comprueba día hábil futuro, especialidad del médico, estados activos y reservas existentes. |
| Informes de más de 2 MB fallan | Revisa `upload_max_filesize` y `post_max_size` del PHP que usa el servidor; configúralos como en el paso 2 y reinicia el servidor. |
| Estilos o JavaScript desactualizados | Recarga la página y comprueba que `public/assets` esté completo. No hace falta iniciar Vite para esta interfaz. |

### Si PowerShell bloquea los archivos `.ps1`

Para ejecutar el script del proyecto en un proceso nuevo, sin cambiar permanentemente la política del equipo:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\install.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\start.ps1
```

Ejecuta primero el instalador; el segundo comando inicia el servidor y mantiene su proceso ocupado. Si una política administrada de Windows impide esa ejecución, consulta al administrador del equipo; no cambies políticas corporativas para forzarla.

### Revisar el registro de errores

```powershell
Get-Content .\storage\logs\laravel.log -Tail 50
```

Antes de compartir ese contenido, revisa que no incluya datos privados. El log puede contener información de consultas y registros del entorno local.

## 12. Alternativa manual si no puedes usar el instalador

No necesitas esta sección si `install.ps1` terminó correctamente. Utiliza siempre el mismo PHP compatible.

### 12.1. Preparar `.env` sin reemplazar uno existente

```powershell
if (-not (Test-Path -LiteralPath .env)) {
    Copy-Item -LiteralPath .env.example -Destination .env
}
notepad .env
```

Configura los valores del paso 6 y guarda el archivo.

### 12.2. Instalar dependencias

Localiza tu `composer.phar`. En el equipo preparado:

```powershell
$composerPhar = 'C:\Users\USUARIO\.config\herd-lite\bin\composer.phar'
& $env:VIDA_SALUD_PHP $composerPhar install --no-interaction --prefer-dist
```

Si lo instalaste en otra carpeta, sustituye la ruta. No continúes si Composer informa un error.

### 12.3. Clave, configuración y migraciones

```powershell
& $env:VIDA_SALUD_PHP artisan config:clear --no-interaction
```

**Solo si `APP_KEY` está vacía en una instalación nueva:**

```powershell
& $env:VIDA_SALUD_PHP artisan key:generate --no-interaction
```

Después:

```powershell
& $env:VIDA_SALUD_PHP artisan migrate --seed --no-interaction
& $env:VIDA_SALUD_PHP artisan serve --host=127.0.0.1 --port=8000 --no-interaction
```

Abre `http://localhost:8000` y utiliza las cuentas del paso 9.

## 13. Pruebas opcionales, después de arrancar la web

No son un requisito para abrir la aplicación.

- Pruebas PHP: requieren una base independiente `Parcial2_test` y `.env.testing` correctamente configurado; consulta [README.md](README.md).
- Pruebas JavaScript: con Node.js instalado puedes ejecutar `npm run test:js`.

**No dirijas las pruebas a `Parcial2`: reconstruyen datos en la base de pruebas.**

Para información adicional están disponibles [el contexto completo](CONTEXTO_COMPLETO_VIDA_SALUD.md), [las decisiones funcionales](docs/decisiones.md) y [las evidencias de aceptación](docs/aceptacion.md).

## 14. Alcance de esta guía

Esta guía sirve para ejecución **local en Windows**. El instalador y el arranque se comprobaron sobre la instalación existente del proyecto. No equivale a una validación de instalación desde cero en otra computadora ni a un despliegue público en producción.

La revisión visual/interactiva en navegador y la medición de carga documentadas en aceptación siguen pendientes. Crear esta guía no modifica datos de la clínica ni vuelve a ejecutar migraciones por sí solo.
