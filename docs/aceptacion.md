# Aceptación y evidencia de la ejecución del plan de corrección

## Actualización vigente: corrección de CU3, CU5, CU6 y CU18

El 25/10/2026 se corrigieron las cuatro brechas de la [comparación de casos de uso](../output/comparacion-casos-de-uso-20261025.md). El detalle funcional y el estado de los veinte casos se encuentran en [correccion_casos_parciales.md](correccion_casos_parciales.md). Esta entrega añade también la consulta de agenda de CU10 necesaria para completar CU6.

| Comprobación final | Resultado | Evidencia |
| --- | --- | --- |
| Suite PHP consolidada | **207 pruebas, 913 aserciones; cero errores, fallos o pruebas omitidas** | [phpunit-casos-parciales-all.xml](../storage/logs/phpunit-casos-parciales-all.xml) |
| Casos nuevos de esta corrección | **49 pruebas Feature, 221 aserciones adicionales** respecto a 158/692 | `PatientAccountManagementTest`, `AppointmentQueriesTest` y `ScheduleQueriesTest` |
| JavaScript existente | **40/40 pruebas** | `node --test tests/JavaScript/*.test.mjs` |
| Estilo PHP | Pint `--test` aprobado sobre los archivos PHP modificados y añadidos | Controladores, servicios, request, rutas y pruebas nuevas |
| Vistas Blade | Compilación aprobada | `php artisan view:cache` |

Las pruebas se ejecutaron en `Parcial2_test`, en la instancia independiente `127.0.0.1:55432`, manteniendo las barreras de entorno, driver y nombre real. La suite final tardó aproximadamente 37,4 segundos; esto no representa una medición de latencia de la agenda. No se añadieron migraciones, se modificó el esquema operativo ni se utilizaron datos operativos como fixtures. La instancia de pruebas ya estaba iniciada y se dejó en ese estado.

Se verificaron la conservación del vínculo y contraseña al editar usuarios; el registro explícito del verificador y la IP al cambiar el vínculo; el rechazo de asociación duplicada, campos de verificación manipulados y cambios sin confirmación; la conservación de pacientes/citas al desvincular; y el recorrido desde la creación de una cuenta hasta su vinculación.

Las consultas comprueban permisos e identificadores en el servidor, limitan al médico a su propia agenda, omiten el motivo clínico para administración/recepción y preservan citas canceladas y personas inactivas. Se probaron fechas inválidas, límites del día local, filtros combinados, páginas sin duplicados, escape de HTML y selección de horarios por médico y día.

Para reproducir la suite con la instancia de pruebas disponible:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '55432'
$env:DB_DATABASE = 'Parcial2_test'
$env:DB_USERNAME = 'postgres'
$env:DB_PASSWORD = ''
$env:DB_URL = ''
php vendor/bin/phpunit --log-junit storage/logs/phpunit-casos-parciales-all.xml
node --test tests/JavaScript/*.test.mjs
```

**Navegador visual pendiente:** Browser Use rechazó observar `http://127.0.0.1:8000` porque no se concedió permiso para ese origen. No se atribuyen capturas ni recorridos visuales, móviles o con JavaScript real a esta entrega. Las pruebas Feature sí renderizaron los formularios/listados y verificaron sus solicitudes. No se ejecutaron mediciones de rendimiento.

Las secciones siguientes conservan la evidencia histórica del primer plan y sus correcciones anteriores.

## Alcance y entorno

Revisión del **25/10/2026**, limitada a CU2, CU4, CU6, CU7, CU18/RF05 y CU20 del [plan de corrección](plan_correccion_casos_implementados.md). No se ejecutó el [plan de implementación de casos pendientes](plan_implementacion_casos_pendientes.md).

Entorno: PHP 8.5.12 con `pdo_pgsql`, Laravel 13.38.0, PHPUnit 12.5.40 y PostgreSQL 18.6. Las dependencias se instalaron desde el lock existente; `composer.lock`, el snapshot SQL y las migraciones anteriores se conservaron intactos.

Las pruebas utilizaron **una instancia PostgreSQL independiente en `127.0.0.1:55432`, base `Parcial2_test`**, inicializada para esta verificación con datos ficticios. No se utilizaron credenciales ni se modificó la base operativa `Parcial2`. Se mantuvo la barrera que comprueba entorno `testing`, driver, sufijo `_test` y nombre real de la conexión antes de reconstruir el esquema.

## Resultados comprobados

| Verificación | Resultado | Evidencia |
| --- | --- | --- |
| Baseline PHP antes de corregir | 56/56 pruebas; 189 aserciones | [phpunit-correccion-baseline.xml](../storage/logs/phpunit-correccion-baseline.xml) |
| Baseline JavaScript | 22/22 pruebas | Suite original, antes de modificar validaciones y salida |
| Suite PHP al finalizar el primer plan | 128/128 pruebas; 501 aserciones; cero errores, fallos o pruebas omitidas | [phpunit-correccion-all.xml](../storage/logs/phpunit-correccion-all.xml) |
| Suite PHP tras corregir baja y búsqueda de médicos | **158/158 pruebas; 692 aserciones; cero errores, fallos o pruebas omitidas** | [phpunit-directorio-all.xml](../storage/logs/phpunit-directorio-all.xml) |
| JavaScript final | **40/40 pruebas** | `tests/JavaScript/validation.test.mjs` y `logout.test.mjs` |
| Estilo PHP | Pint `--test` aprobado | Controladores, requests, configuración, rutas, seeders, migraciones nuevas y pruebas Feature |
| Vistas Blade | Compilación aprobada | `php artisan view:cache` |
| HTTP real de CU2, con cookies y CSRF | Confirmación/cancelación conservan sesión; POST sin token y replay devuelven 419; cierre nativo redirige al ingreso; una sola SALIDA | [cu2-http-result.json](../storage/logs/cu2-http-result.json) |

La suite inicial tardó aproximadamente 23,6 segundos y la suite ampliada de directorio 28,5 segundos. Estos tiempos corresponden a pruebas automatizadas y no demuestran latencia de páginas o reportes.

Las cantidades históricas de 146, 131 o 93 pruebas documentadas anteriormente no se usan como evidencia de esta copia: varias suites mencionadas allí no estaban presentes. La referencia de esta ejecución es el resultado consolidado indicado arriba.

## Casos verificados

### CU2: salida

Confirmación y cancelación sin terminar la sesión; formulario POST nativo; cierre con registro de actor e IP; invalidación de datos privados; respuesta JSON; rechazo de invitados; idempotencia de dos peticiones con la misma cookie, aun cuando ambas ya resolvieron al usuario; una sesión nueva registra su propia salida. La clave de operación se deriva en servidor y no se acepta del cliente.

PostgreSQL garantiza unicidad de `bitacora.clave_operacion`. Un fallo provocado después de insertar la auditoría revierte la transacción y conserva autenticación y datos de sesión. La reversión de la migración de idempotencia está protegida cuando hay operaciones registradas.

Las pruebas JavaScript ejecutan las funciones reales de salida: un solo reintento de red, ausencia de reintento para 403/422/429/500, tratamiento de 401 y 419, prevención de doble envío y recuperación del botón y mensaje accesible tras un fallo persistente.

Además se verificó un servidor HTTP local en entorno `local`, con CSRF activo y sesiones en archivos, mediante solicitudes programáticas con la cuenta ficticia de laboratorio. La confirmación y cancelación conservan autenticación; un POST sin token devuelve 419; el formulario nativo cierra la sesión y redirige al ingreso; la cookie anterior no permite abrir inicio. Repetir cookie y token anteriores devuelve 419 y el GET de confirmación conduce al ingreso. Una consulta de solo lectura a PostgreSQL confirmó exactamente una SALIDA nueva y ninguna adicional por el replay. Esta comprobación no utiliza ni sustituye un navegador visual.

### CU4: roles

Confirmación sin borrado, eliminación de rol personalizado sin usuarios, auditoría única de la fila eliminada y eliminación transaccional de sus permisos. Rechazo de roles asignados a usuarios activos o inactivos, roles del sistema, rol propio, registros inexistentes y solicitudes sin permiso.

### CU6 y CU7: directorio

Gestión por recepción y administración; creación desde una cuenta médica existente; edición de nombres, apellidos, correo y perfil sin modificar privilegios; búsquedas por nombre, apellido y registro; datos escapados en HTML.

Se comprueba el rechazo de campos privilegiados incluso nulos o vacíos, tipos de entrada inválidos, traslado de cuenta y cambios parciales ante duplicados. Se puede desactivar un perfil cuya cuenta ya está inactiva, sin reactivarla. Las citas futuras impiden una baja incompatible; las canceladas y sus eventos se conservan al desactivar el directorio.

La actualización puntual añade solo los dos permisos acordados a recepción y conserva permisos personalizados. Repetir los seeders no revierte configuraciones posteriores.

**Pendiente condicionado:** el enlace del directorio médico a la agenda requiere CU10. No se creó un módulo de Agenda para completar esta dependencia, porque el usuario autorizó solamente el primer plan.

### CU18/RF05: duración

Disponibilidad y reserva SQL para 15, 30, 45 y 60 minutos; cálculo de final y duración en servidor frente a valores manipulados; validación de formularios y restricciones SQL; superposición de médico y paciente; bloques contiguos; reprogramación con finales anteriores y nuevos; reversión de conflictos y protección de horarios con citas pendientes.

La prueba de actualización legacy retira temporalmente la migración nueva en la base aislada, crea una cita y sus eventos con el esquema anterior y vuelve a aplicar la migración. Comprueba la conservación de todas las columnas anteriores, duración 30, eventos inmutables y cantidad de auditorías. La suite también comprueba el esquema desde una instalación vacía mediante las migraciones reales.

### CU20: bitácora

Filtros combinados de usuario, acción, entidad y fechas; actor Sistema; último día incluido; fecha final sin inicial; rechazo de rangos y fechas inválidas, incluido año cero; paginación y regreso con filtros; detalle con IP; rechazo de URLs externas como retorno, registros inexistentes y usuarios no autorizados; escape de texto y ausencia de modificaciones durante la consulta.

## Comandos de verificación

La configuración local de pruebas se encuentra en `.env.testing`, excluido del control de versiones. Para reproducir contra esta instancia temporal debe estar iniciada y deben establecerse explícitamente host y puerto, porque `phpunit.xml` conserva los valores predeterminados de la instalación habitual:

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '55432'
$env:DB_USERNAME = 'postgres'
$env:DB_PASSWORD = ''
$correctionPhp = 'C:/Users/USUARIO/.config/vida-salud/php85/php.exe'
& $correctionPhp vendor/bin/phpunit --log-junit storage/logs/phpunit-correccion-all.xml --stop-on-failure
node --test tests/JavaScript/*.test.mjs
& $correctionPhp artisan view:cache
```

La instancia de verificación se detiene al finalizar el trabajo; sus archivos locales se conservan en `.runtime/correccion-pg/data`, excluidos del control de versiones. Para volver a iniciarla desde la raíz del proyecto:

```powershell
& 'C:/Program Files/PostgreSQL/18/bin/pg_ctl.exe' -D .runtime/correccion-pg/data -l .runtime/correccion-pg/postgres.log -o '-h 127.0.0.1 -p 55432' -w start
```

Después de las pruebas se puede detener esa misma instancia con `pg_ctl.exe -D .runtime/correccion-pg/data -m fast -w stop`. Estas instrucciones corresponden únicamente a la instancia temporal de datos ficticios.

El comando Composer de Herd selecciona otro PHP sin `pdo_pgsql`. La instalación verificada utilizó el PHP completo anterior para ejecutar el PHAR de Composer, igual que hace el selector del instalador del proyecto. No se actualizaron versiones.

## Límites de la validación

- **Navegador visual/interactivo pendiente.** Computer Use no pudo identificar con suficiente confianza la URL actual de Brave y detuvo la acción antes de navegar a la aplicación. No se acreditan recorridos, capturas, vista móvil, JavaScript deshabilitado real ni simulación de red dentro de un navegador.
- Los casos de interfaz sí se renderizaron por HTTP en las pruebas Feature y la lógica JavaScript se probó en Node; esas evidencias no sustituyen la validación visual.
- No se ejecutaron los módulos pendientes de agenda, atención, laboratorio, portal o reportes, ni sus mediciones de rendimiento.
- En la ejecución inicial no se aplicaron migraciones a la base operativa. La corrección posterior del cierre aplicó únicamente la migración de idempotencia, con respaldo y validación en una copia, como se detalla más abajo. Los ajustes posteriores del directorio no requieren migraciones ni modificaciones de datos operativos.

## Entrega

Migraciones nuevas: permisos de directorio para recepción, duración configurable y auditoría de salida idempotente. La documentación de decisiones y esquema refleja estos cambios. El segundo plan permanece sin modificaciones.

## Corrección del cierre en la instalación operativa, 25/10/2026

El cierre fallaba en `Parcial2` con SQLSTATE `42703`: faltaba `bitacora.clave_operacion`. La migración de idempotencia existía en el código y en la base de pruebas, pero estaba pendiente en la conexión usada por la aplicación.

Se generó el respaldo [Parcial2_antes_cierre_sesion_20261025_175653.dump](../storage/backups/Parcial2_antes_cierre_sesion_20261025_175653.dump), se restauró en una copia independiente y se validó allí la migración `2026_10_25_000003_make_logout_audit_idempotent`. Después se aplicó exclusivamente esa migración a `Parcial2`, comprobando la conservación de las cantidades de registros de sus 29 tablas; solo aumentó el registro de migraciones.

La comprobación HTTP real sobre `http://127.0.0.1:8000` aprobó el POST nativo y el POST con las cabeceras usadas por JavaScript: ambos cierran la sesión, el inicio protegido redirige al ingreso y repetir la cookie y el token anteriores devuelve 419. Los dos cierres registraron exactamente dos SALIDA con claves distintas y ningún evento duplicado. También aprobaron las 16 pruebas de autenticación (80 aserciones) y las 12 pruebas JavaScript de cierre. Esta comprobación HTTP no acredita un recorrido visual de navegador.

La evidencia local se conserva en `.runtime/logout-fix.json` y `.runtime/logout-fix-http.py`.

## Corrección de baja y búsqueda de médicos, 25/10/2026

Se corrigió el bloqueo al editar o desactivar un médico cuya última especialidad quedó inactiva. El formulario conserva esas opciones seleccionadas y las identifica como «(inactiva)». El servidor permite mantener únicamente los vínculos vigentes del propio médico; rechaza nuevas asignaciones de especialidades inactivas y exige alguna activa al crear o reactivar el perfil. Los vínculos conservados no se reescriben y las asignaciones retiradas permanecen como historial inactivo.

La búsqueda admite nombres completos y especialidades del listado, normaliza espacios y conserva los campos anteriores. Una especialidad inactiva con vínculo vigente sigue siendo localizable; un vínculo retirado deja de producir coincidencias. Las coincidencias por varias especialidades no duplican filas ni alteran los 15 registros por página.

Se añadieron **30 pruebas Feature con 191 aserciones** en `tests/Feature/DoctorDirectoryTest.php`, partiendo de las tres reproducciones de la revisión y ampliándolas para cubrir administración y recepción, cuenta desactivada, reactivación, entradas manipuladas, conservación de citas canceladas y eventos, auditoría de diferencias, reversión ante citas futuras, búsqueda y paginación. La expectativa del selector se actualizó para exigir la especialidad inactiva visible, seleccionada y habilitada.

La suite consolidada aprobó **158 pruebas PHP con 692 aserciones**, las **40 pruebas JavaScript** y Pint `--test` sobre los archivos PHP modificados. Las pruebas utilizaron `Parcial2_test` en la instancia independiente `127.0.0.1:55432`, con las barreras de seguridad existentes. No se ejecutaron migraciones ni cambios de datos sobre `Parcial2` para estos ajustes.

**Límite de interfaz:** las pruebas Feature renderizan el formulario y comprueban el guardado mediante solicitudes HTTP de prueba. Se preparó además un servidor temporal con datos ficticios, pero el navegador rechazó el acceso a `http://127.0.0.1:8021` porque no se concedió permiso para ese origen. El servidor temporal se detuvo. No se acreditan recorridos visuales ni una ejecución real con JavaScript activado o desactivado.
