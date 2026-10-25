# Contexto completo del desarrollo: Clínica Vida Salud

**Fecha del trabajo documentado:** 24 de octubre de 2026.  
**Zona horaria del negocio:** `America/La_Paz`.  
**Propósito:** conservar el contexto funcional y técnico, explicar las decisiones tomadas y permitir que otra persona o una nueva sesión retome el proyecto sin depender del historial del chat.

Este documento describe trabajo realizado y resultados comprobados. Los pendientes se identifican expresamente. No contiene la contraseña de PostgreSQL ni la clave privada de la aplicación; esos valores permanecen en los archivos de entorno locales.

## 1. Solicitud original y alcance acordado

Se solicitó desarrollar frontend y backend de una aplicación para la Clínica Vida Salud con:

- Laravel y PostgreSQL.
- Persistencia real, validación en servidor y pruebas.
- Organización del código por módulos y carpetas claras.
- Diseño basado en dos imágenes proporcionadas.
- Revisión previa de las inconsistencias de la guía.
- Consulta de las decisiones que alteraran permisos o reglas del negocio.
- Instrucciones para instalar y ejecutar el sistema en la computadora del usuario.

Después se solicitó expresamente:

- Diseño profesional.
- Validaciones JavaScript y PHP.
- Manejo correcto de errores.
- Comentarios útiles en el código.
- Buenas prácticas de programación.

El alcance implementado abarca acceso, usuarios y roles, pacientes, médicos, especialidades, horarios, citas, historia clínica, consultas, diagnósticos, tratamientos, laboratorio, portal del paciente, reportes y bitácora.

No se incorporaron como requisitos facturación, cobros, inventario, teleconsulta ni decisiones automáticas de diagnóstico o tratamiento. Las decisiones clínicas las registra el personal autorizado.

## 2. Material de referencia y ubicación del proyecto

### 2.1. Carpeta de trabajo

```text
D:\Universidad Carlos Villagra\2026-2\Ingenieria de requisitos y modelado - SC\Nueva carpeta (3)
```

La aplicación se construyó dentro de esta carpeta, sobre la instalación de Laravel que ya existía.

### 2.2. Guía funcional

```text
C:\Users\USUARIO\Downloads\Vida_Salud_Guia_Desarrollo_Web.md
```

La guía sintetiza un documento académico de 95 páginas y distingue el núcleo de citas, el portal y la ampliación clínica y de laboratorio. Se revisaron sus reglas, pantallas, modelo de datos, pruebas CP01–CP24 e inconsistencias pendientes.

### 2.3. Referencias visuales

```text
C:\Users\USUARIO\Downloads\Diseño\Inicio de sesión Clínica Vida Salud.png
C:\Users\USUARIO\Downloads\Diseño\Selección de módulos para Clínica Vida Salud.png
```

Se inspeccionaron ambas imágenes. Sus rasgos principales son el acceso centrado, fondo claro, encabezado azul, identidad de la clínica y tarjetas de módulos con iconos.

### 2.4. Modelo proporcionado

El usuario entregó las definiciones de 21 tablas, sus campos y relaciones. La implementación conserva los nombres españoles en singular de ese modelo y adapta Laravel a ellos.

## 3. Estado inicial encontrado

### 3.1. Aplicación

Existía la estructura base de Laravel con `vendor`, configuración, rutas y pruebas de ejemplo. La ruta principal mostraba la vista de bienvenida. El modelo inicial de autenticación era el estándar de Laravel y todavía no correspondía a `usuario` y `password_hash`.

El archivo `.env` apuntaba inicialmente a SQLite. Las migraciones originales eran las genéricas de usuarios, caché y trabajos; aún no representaban el dominio de la clínica.

### 3.2. Herramientas

Se verificaron PHP y Composer. El PHP encontrado primero en el `PATH` era Herd Lite 8.5.12 y no tenía `pdo_pgsql` habilitado. PostgreSQL 18 y sus herramientas estaban instalados, aunque `psql` no se resolvía inicialmente desde el `PATH`.

### 3.3. Base de datos

La conexión autorizada a `Parcial2` funcionó usando el usuario local `postgres`.

Se encontró el esquema `vida_salud`, con:

- 21 tablas del dominio original.
- Siete vistas.
- 38 disparadores originales.
- Funciones de validación, permisos, disponibilidad y auditoría.
- Cuatro roles y 16 permisos iniciales.
- Cero pacientes, usuarios y citas en la inspección inicial.

Las vistas existentes eran:

```text
historial_clinico
reporte_atenciones
reporte_citas
reporte_ordenes
reporte_pacientes
reporte_resultados
resumen_citas_diarias
```

La base real estaba más avanzada que el diccionario y algunas observaciones de la guía: ya tenía exclusiones de intervalos, varias unicidades normalizadas y validación mediante disparadores. Se revisó esa implementación antes de ampliarla.

### 3.4. Restricción inicial de la sesión

Al principio la sesión solo permitía lectura. En ese estado se inspeccionaron documentos, diseños, código y metadatos, pero no se implementaron cambios. El usuario autorizó continuar y la sesión pasó posteriormente a permitir escritura y red. La implementación comenzó después de ese cambio efectivo.

## 4. Inconsistencias revisadas y decisiones adoptadas

Las preguntas sobre permisos, acceso clínico, laboratorio, portal, solapamientos y reprogramación se presentaron antes de implementar esos puntos. El usuario indicó después «haz todo» y autorizó completar lo que faltaba. Se adoptaron las decisiones descritas a continuación como concreción de esa delegación, no como respuestas individuales a cada pregunta.

| Punto revisado | Resolución implementada |
| --- | --- |
| Recepción aparecía como actor de mantenimiento de médicos/especialidades en algunas partes | Administración mantiene esos catálogos; recepción los consulta y gestiona pacientes y citas. |
| Conflicto sobre quién solicita análisis | El médico solicita en sus consultas; laboratorio procesa y corrige resultados. |
| Administrador y acceso a salud | La administración no recibe acceso clínico automático por su rol. |
| Vínculo usuario–paciente ausente | Se agregaron vínculo único, verificador y fecha de verificación. |
| Acceso clínico propio del paciente poco definido | El portal muestra diagnósticos, tratamientos y resultados publicados propios; no notas internas. |
| Registro con CI existente | Se crea una solicitud pendiente, sin asociar automáticamente al paciente. |
| Cambio de médico al reprogramar | Se conserva la restricción existente: para cambiar médico, paciente o especialidad se cancela y crea otra cita. |
| Reservas simultáneas del mismo paciente | Se impiden también cuando son con médicos diferentes. |
| Límite de gestión de citas del paciente | El paciente confirma, cancela o reprograma citas propias antes de su inicio. |
| Desactivaciones con reservas futuras | Deben resolverse las citas afectadas; no se cancelan ni reprograman silenciosamente. |
| Estados y publicación de laboratorio | Se concretaron estados, cancelación sin resultados, publicación por médico y retirada de publicación tras corrección. |
| Acceso por usuario o correo | Ambos se normalizan sin distinguir mayúsculas; el nombre de usuario no admite `@`. |
| Código de cita no incluido en la tabla original | Se agregó una columna única generada por PostgreSQL. |
| Restricciones declaradas pendientes en la guía pero presentes en la base | Se reutilizaron y complementaron; no se supuso que faltaban sin inspeccionarlas. |
| Numeración inconsistente de casos de uso | Se priorizaron nombres funcionales y RF; la matriz CP conserva los identificadores originales de la guía. |

El médico no recibió una función para cancelar citas por una mención ambigua del documento. Su actuación de agenda se centra en su alcance propio y la atención autorizada.

## 5. Tecnologías y preparación del entorno

### 5.1. Versiones registradas durante el trabajo

| Herramienta | Versión observada o condición |
| --- | --- |
| Laravel | 13.38.0 instalada; restricción de Composer `^13.17` |
| PHP utilizado para PostgreSQL | 8.5.12 |
| Composer | 2.8.12 observado al preparar el entorno |
| PostgreSQL | 18.6 |
| PHPUnit | 12.5.40 registrado en la verificación |
| Node.js | 24.21.0 observado durante el desarrollo |
| npm | 11.19.0 observado durante el desarrollo |
| Laravel Boost | 2.10.3 instalado |

Estos datos son una fotografía del entorno de la sesión, no una afirmación de que sean las últimas versiones disponibles.

### 5.2. Laravel Boost y reglas del repositorio

Se siguió la instrucción inicial de `AGENTS.md`:

```text
composer require laravel/boost --dev
php artisan boost:install --guidelines --mcp --no-interaction
```

Después de instalarlo se volvió a leer `AGENTS.md`, que pasó a contener las reglas generadas para el proyecto. Se consultó documentación del framework mediante Boost antes de implementar comportamiento dependiente de Laravel.

Se utilizó el comando interno de ejecución de herramientas de Boost cuando el servidor MCP nuevo todavía no figuraba como herramienta directa de la sesión. Se consultó también `GetAbsoluteUrl` antes de compartir la dirección local.

### 5.3. PHP con soporte real de PostgreSQL

Para no sustituir la instalación de Herd Lite se preparó un runtime separado:

```text
C:\Users\USUARIO\.config\vida-salud\php85\php.exe
```

Se descargó la distribución oficial de PHP para Windows, se comprobó su SHA-256 y se habilitaron extensiones, entre ellas `pdo_pgsql` y `pgsql`.

Composer declara ahora `ext-pdo_pgsql` como requisito. Los scripts seleccionan un PHP con esa extensión, evitando que el comando global de Herd produzca `could not find driver`.

La configuración local de Boost para Codex CLI se ajustó para usar este ejecutable. La configuración local de `.codex` está excluida de Git.

### 5.4. Configuración de la aplicación

- Conexión `pgsql`.
- Base operativa `Parcial2`.
- Esquema de búsqueda `vida_salud,public`.
- Zona horaria de aplicación y conexión PostgreSQL: `America/La_Paz`.
- Sesiones en archivos para la instalación local.
- Caché en archivos y cola local síncrona.
- Archivos clínicos en almacenamiento privado.
- `APP_DEBUG=false` en la instalación operativa local preparada.
- Credenciales PostgreSQL y claves en `.env`; configuración de pruebas en `.env.testing`.

Las pantallas sirven CSS y JavaScript estáticos. Node es útil para sus pruebas, pero no se necesita ejecutar Vite para utilizar esta interfaz.

## 6. Estructura y arquitectura

La modularización utiliza espacios de nombres normales de Laravel y su autoload PSR-4. No se instaló un paquete adicional de módulos.

```text
app/
├── Models/
│   └── User.php
├── Http/
│   ├── Middleware/EnsureActiveAccount.php
│   └── Requests/
│       ├── AppointmentRequest.php
│       ├── CatalogRequest.php
│       ├── LabResultRequest.php
│       └── LoginRequest.php
└── Modules/
    ├── Acceso/Controllers/AuthController.php
    ├── Inicio/Controllers/HomeController.php
    ├── Catalogos/
    │   ├── Controllers/CatalogController.php
    │   └── Services/CatalogService.php
    ├── Agenda/
    │   ├── Controllers/AgendaController.php
    │   └── Services/AppointmentService.php
    ├── Clinica/
    │   ├── Controllers/ClinicalController.php
    │   └── Services/ClinicalService.php
    ├── Laboratorio/
    │   ├── Controllers/LabController.php
    │   └── Services/LabService.php
    ├── Portal/Controllers/
    │   ├── PortalController.php
    │   └── PortalRegistrationController.php
    ├── Reportes/Controllers/ReportController.php
    ├── Auditoria/Controllers/AuditController.php
    └── Shared/Clinic.php

config/clinic.php                 Catálogos, columnas, campos y permisos
routes/web.php                   Rutas y middleware
resources/views/                 Presentación Blade por área
public/assets/app.css            Diseño y estados visuales
public/assets/app.js             Interacción y formularios
public/assets/validation.js      Validadores puros JavaScript
lang/es/validation.php           Mensajes PHP en español
database/migrations/             Evolución del esquema
database/seeders/                Roles y datos ficticios
database/vida_salud_existing.sql  Snapshot inicial de estructura
scripts/                         Instalación, inicio y recuperación
tests/                           Pruebas PHP y JavaScript
docs/                            Decisiones, etapas y evidencias
```

### Responsabilidades

- **Controladores:** reciben la petición, coordinan validación/autorización y devuelven vista o respuesta.
- **Form Requests:** validan solicitudes estructuradas y autorizan las operaciones que les corresponden.
- **Servicios:** aplican reglas del negocio y transacciones.
- **`Clinic`:** concentra utilidades de autorización, alcance, fechas y contexto de auditoría.
- **Query Builder y modelo `User`:** acceden a PostgreSQL mediante consultas parametrizadas.
- **PostgreSQL:** añade integridad, claves, exclusiones, validación de estados, eventos y versiones.
- **Blade y JavaScript:** presentan los datos y mejoran la interacción sin sustituir la validación del servidor.

Pacientes, médicos, especialidades, horarios, usuarios, roles y análisis se reúnen en el módulo `Catalogos` con definiciones específicas. No se creó un modelo Eloquent independiente por cada tabla ni una carpeta distinta para cada catálogo: se reutilizaron componentes y servicios.

## 7. Base de datos: conservación y ampliaciones

### 7.1. Las 21 tablas originales

| Grupo | Tablas |
| --- | --- |
| Acceso y auditoría | `rol`, `permiso`, `rol_permiso`, `usuario`, `bitacora` |
| Pacientes e historia | `paciente`, `historia_clinica` |
| Directorio médico | `medico`, `especialidad`, `medico_especialidad`, `horario` |
| Agenda | `cita`, `evento_cita` |
| Atención | `consulta`, `diagnostico`, `tratamiento` |
| Laboratorio | `analisis_laboratorio`, `orden_laboratorio`, `orden_analisis`, `resultado_laboratorio`, `resultado_version` |

Se mantuvieron los tipos y relaciones del esquema real, incluidos `timestamptz`, claves compuestas y unicidades pertinentes.

### 7.2. Las cinco migraciones incorporadas

1. **`2026_10_24_134957_bootstrap_vida_salud_schema.php`**
   - Exige PostgreSQL.
   - Detecta si ya existe `vida_salud.paciente` y no sobreescribe el esquema existente.
   - En una base vacía importa el snapshot e instala `btree_gist` si hace falta.
   - Elimina del dump las directivas exclusivas de `psql` antes de ejecutarlo por conexión PHP.
   - Protege la reversión para no borrar automáticamente el esquema clínico original.

2. **`2026_10_24_134958_extend_vida_salud_for_patient_portal.php`**
   - Agrega vínculo único y verificado entre usuario y paciente.
   - Agrega código de cita generado y único.
   - Agrega datos de publicación de resultados.
   - Agrega unicidad normalizada de correo de usuario.
   - Impide solapamientos del mismo paciente.
   - Añade coherencia entre roles, perfiles y vínculos.
   - Protege desactivaciones con citas futuras.
   - Integra IP en auditoría y limita las versiones de resultados a correcciones reales.

3. **`2026_10_24_134959_create_vida_salud_framework_tables.php`**
   - Crea `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches` y `failed_jobs`.
   - Mantiene `usuario` como tabla de autenticación; no crea otra tabla de usuarios de dominio.
   - Su reversión comprueba que la infraestructura no contenga datos.

4. **`2026_10_24_135000_create_patient_portal_requests.php`**
   - Crea `solicitud_portal` para solicitudes persistentes de cuenta y su resolución.

5. **`2026_10_24_135001_correct_laboratory_publication_audit.php`**
   - Distingue la corrección del contenido de la publicación de resultados.
   - Verifica al médico solicitante como actor de publicación.
   - Evita atribuir esa publicación al laboratorista original.

Las migraciones se ejecutaron en la base operativa y en la base de pruebas correspondiente. La instalación se comprobó con `migrate --seed`, sin vaciar `Parcial2`.

### 7.3. Campos agregados

| Tabla | Campos o ampliación |
| --- | --- |
| `usuario` | `paciente_id`, `paciente_verificado_por`, `paciente_verificado_en` |
| `cita` | `codigo`, generado como `VS-00000001`, con unicidad |
| `resultado_laboratorio` | `publicado_por`, `publicado_en` |
| `solicitud_portal` | Usuario, CI solicitado, nacimiento, teléfono, estado, fechas, responsable y observación de resolución |

Los tres datos de vínculo del usuario deben estar todos vacíos o todos completos. Los dos datos de publicación de un resultado también deben ser coherentes entre sí.

Una cuenta médica debe conservar su rol compatible y no puede convertirse en vínculo de paciente. Una cuenta de paciente puede existir todavía sin verificar.

### 7.4. Cantidad de tablas observada tras la ampliación

El respaldo de la aplicación contenía **29 tablas** en `vida_salud`: las 21 originales, la tabla de solicitudes, seis tablas de infraestructura y la tabla de migraciones de esta instalación.

No debe confundirse esa cifra con 29 entidades clínicas: varias tablas pertenecen al soporte de Laravel.

## 8. Acceso, roles y permisos

### 8.1. Autenticación adaptada al esquema

`app/Models/User.php` usa la tabla `usuario`, la contraseña almacenada en `password_hash` y los campos reales del proyecto. No depende de los nombres estándar `users`, `email` y `password` del esqueleto inicial.

El acceso contempla:

- Nombre de usuario o correo normalizado.
- Verificación del hash de contraseña.
- Cuenta activa y rol activo.
- Regeneración de sesión al ingresar.
- Invalidación de sesión y regeneración de token al salir.
- Limitación de intentos de acceso y solicitudes.
- Registro de ingreso en bitácora.

`EnsureActiveAccount` vuelve a comprobar el estado durante las peticiones protegidas. No basta con que una cuenta hubiera estado activa al iniciar sesión.

### 8.2. Roles iniciales

| Rol | Alcance implementado |
| --- | --- |
| Administrador | Usuarios, roles, pacientes y citas administrativas, directorio, horarios, análisis, verificación del portal, reportes administrativos y bitácora. Sin acceso clínico automático. |
| Recepcionista | Pacientes y citas, verificación de solicitudes, agenda y reportes administrativos; lectura de médicos, especialidades y horarios. |
| Médico | Agenda propia, atención propia, historia autorizada de pacientes relacionados, diagnósticos y tratamientos, solicitud de órdenes y resultados en su alcance. |
| Laboratorista | Lectura de órdenes y resultados y, con permiso de procesamiento, carga/corrección de resultados y adjuntos. |
| Paciente | Perfil y citas propios, diagnósticos y tratamientos propios y resultados publicados propios, después de la verificación de cuenta. |

El paciente no puede consultar o modificar registros ajenos cambiando identificadores. El médico no obtiene la agenda general al quedar inactivo su perfil profesional.

### 8.3. Códigos de permiso

```text
usuarios.gestionar
roles.gestionar
pacientes.gestionar
medicos.gestionar
especialidades.gestionar
horarios.gestionar
analisis.gestionar
citas.gestionar
agenda.consultar
atencion.registrar
historia.consultar
laboratorio.solicitar
laboratorio.consultar
laboratorio.procesar
reportes.administrativos
reportes.clinicos
reportes.laboratorio
bitacora.consultar
citas.propias
perfil.propio
```

Se separa lectura de laboratorio y procesamiento: retirar `laboratorio.procesar` no elimina la lectura ya autorizada por `laboratorio.consultar`.

Las restricciones de propiedad y relación de atención se aplican además del permiso. El alcance no se decide a partir de un ID de paciente o médico que el navegador declare como propio.

## 9. Funciones implementadas por módulo

### 9.1. Inicio

- Panel con tarjetas y accesos pertinentes al usuario.
- Estadísticas autorizadas de pacientes o citas.
- Acceso a solicitudes pendientes del portal para el personal correspondiente.
- Navegación común, identificación del usuario y cierre de sesión.

### 9.2. Catálogos

- Listados paginados, búsqueda, formularios de alta y edición.
- Pacientes con CI normalizado, contacto y estado.
- Médicos vinculados a una cuenta profesional y sus especialidades.
- Especialidades y análisis con estado activo/inactivo.
- Horarios semanales de los médicos.
- Usuarios, roles y asignación de permisos.
- Asociación verificada de cuenta de paciente mediante operación del personal autorizado.
- Mensajes de duplicidad y errores de referencia.

`config/clinic.php` define campos, columnas, etiquetas y permiso de mantenimiento por catálogo. Recepción puede abrir los catálogos de consulta permitidos, pero no adquiere por ello sus operaciones de escritura.

### 9.3. Agenda y citas

- Selección de paciente, médico, especialidad, fecha y bloque.
- Consulta asíncrona de disponibilidad.
- Reserva persistente de 30 minutos.
- Código único de cita.
- Agenda filtrada y paginada.
- Confirmación, reprogramación, cancelación, atención e inasistencia según autorización y estado.
- Motivo obligatorio al cancelar.
- Conservación de eventos e historial.
- Bloqueo de citas incompatibles del médico o del paciente.

### 9.4. Atención clínica

- Listado de citas de atención del médico.
- Antecedentes personales, familiares y alergias.
- Consulta vinculada a una cita, con notas y responsable.
- Registro de diagnósticos y código de clasificación opcional.
- Registro de tratamientos e indicaciones, con fechas coherentes.
- Historia de atenciones anteriores del paciente para el profesional autorizado.
- Solicitud de análisis a partir de una consulta propia.
- Publicación de resultados de órdenes propias completas.

Guardar una consulta válida se integra con el disparador que marca la cita como Atendida. La lectura de antecedentes exige `historia.consultar`; conservar permiso para registrar atención no concede por sí solo lectura de historia.

La funcionalidad descrita no implica una edición genérica de todas las entidades clínicas ni eliminación de expedientes: diagnósticos y tratamientos tienen las operaciones de registro implementadas y el historial se conserva.

### 9.5. Laboratorio

- Catálogo de análisis mantenido por administración.
- Listado y detalle de órdenes autorizadas.
- Procesamiento de los análisis solicitados.
- Registro de resultado y observaciones.
- Adjunto privado PDF, PNG o JPEG, hasta 5 MB.
- Finalización de orden cuando todos los análisis tienen resultados.
- Versiones de correcciones con valores y autor anteriores.
- Consulta de versiones en el detalle.
- Descarga del informe actual mediante autorización.
- Cancelación de órdenes sin resultados, con motivo.

Los adjuntos anteriores se conservan para sus referencias históricas. Esto no equivale a afirmar que exista una ruta independiente de descarga para cada versión histórica.

### 9.6. Portal

- Solicitud pública de registro.
- Cola persistente de verificación para el personal autorizado.
- Cuenta inicialmente inactiva y sin vínculo automático por CI.
- Perfil propio con teléfono, correo, dirección y contacto de emergencia.
- Reserva y gestión de citas propias.
- Historial de citas propias.
- Diagnósticos y tratamientos propios.
- Resultados propios que el médico haya publicado.
- Exclusión de notas internas y borradores de laboratorio.

### 9.7. Reportes

Se incorporaron reportes de:

- Citas.
- Pacientes registrados.
- Atenciones médicas propias autorizadas.
- Órdenes de laboratorio.
- Resultados de laboratorio autorizados.

Hay filtros de fechas y, cuando corresponde, médico, especialidad y estado; listados paginados y total de registros. El límite superior de un período incluye el último día solicitado mediante un intervalo hasta el comienzo del día siguiente.

Los reportes administrativos no seleccionan antecedentes, notas internas ni resultados. El reporte de resultados conserva el alcance de órdenes propias del médico y requiere autorización de lectura de laboratorio. La actividad se consulta además en la bitácora.

Se agregó impresión mediante el navegador. No se implementó un generador dedicado de exportación CSV o PDF como requisito obligatorio.

### 9.8. Auditoría

- Consulta paginada de actividad con actor, fecha, acción y entidad.
- Filtros de período y entidad.
- Contexto transaccional de usuario e IP.
- Integración con disparadores existentes.
- Registro explícito de acceso y resolución de solicitudes del portal.

La bitácora registra operaciones técnicas y responsables; no constituye una copia completa de todos los valores anteriores de cada tabla. Las versiones de laboratorio tienen su mecanismo específico.

## 10. Reglas críticas de agenda y concurrencia

### 10.1. Reserva

Una reserva nueva exige paciente activo y combinación activa de médico, cuenta, rol, especialidad y asociación médico–especialidad. El intervalo debe:

1. Estar en el futuro según el servidor.
2. Durar 30 minutos.
3. Quedar completo dentro de una franja válida.
4. Alinearse desde el inicio de esa franja.
5. No superponerse con otra cita ocupante del médico o del paciente.

La alineación no obliga a comenzar en una hora en punto. Una franja desde 09:15 puede producir bloques 09:15–09:45 y 09:45–10:15.

### 10.2. Intervalos y ocupación

Se usan intervalos semiabiertos `[inicio, fin)`. Las citas contiguas son válidas.

Conservan ocupación:

```text
Programada
Confirmada
Atendida
```

No ocupan disponibilidad:

```text
Cancelada
Inasistencia
```

Cancelar conserva la fila y su motivo y permite reservar nuevamente ese bloque, si las demás condiciones se cumplen.

### 10.3. Transiciones

| Estado inicial | Acción | Resultado o condición |
| --- | --- | --- |
| Programada | Confirmar | Confirmada |
| Programada o Confirmada | Reprogramar | Nuevo bloque futuro libre; queda Programada |
| Programada o Confirmada | Cancelar | Cancelada con motivo |
| Programada o Confirmada | Registrar atención | Atendida desde el inicio del bloque y con permiso |
| Programada o Confirmada | Registrar ausencia | Inasistencia desde el final del bloque y con permiso |
| Estado final | Cambio directo | Se rechaza para conservar el historial |

El paciente solo dispone de sus acciones autorizadas antes del inicio. No puede promover una cita a Atendida.

### 10.4. Transacciones y bloqueos

`AppointmentService` bloquea los recursos con un orden común y vuelve a validar dentro de la transacción. Los disparadores PostgreSQL añaden verificaciones y eventos. Las exclusiones GiST ofrecen una garantía de integridad adicional.

En una reprogramación conflictiva no se pierde la cita original: se conservan su intervalo, estado y eventos. Reducir un horario no puede dejar citas pendientes fuera de cobertura. La operación y sus registros relacionados se confirman o revierten juntos.

## 11. Flujo de verificación del portal

1. La persona envía nombre de usuario, nombres, apellidos, correo, fecha de nacimiento, teléfono, contraseña y CI opcional.
2. Se crea una cuenta de rol Paciente **inactiva**.
3. Se crea `solicitud_portal` en estado Pendiente.
4. No se crea automáticamente una asociación con un paciente existente, aunque el CI coincida.
5. Recepción o administración verifica la identidad presencialmente.
6. El personal selecciona expresamente al paciente existente. Si es una persona nueva, primero debe registrarse correctamente el paciente en su módulo.
7. Se exige coincidencia de nacimiento y del CI declarado cuando existe, además de confirmar la verificación.
8. La aprobación activa y vincula la cuenta, con responsable y fecha.
9. El rechazo conserva la solicitud y su observación.
10. El vínculo único impide apropiarse de un paciente que ya tiene otra cuenta asociada.

El formulario público no puede concederse rol, permiso, estado activo o vínculo mediante campos adicionales manipulados.

## 12. Laboratorio, publicación y correcciones

### 12.1. Flujo de la orden

```text
Pendiente → En proceso → Completada
```

Una orden debe tener al menos un análisis activo. Mientras admite procesamiento, puede cancelarse con motivo solamente si no tiene resultados. No se permite trasladar un resultado a otra orden.

### 12.2. Publicación

Completar una orden no publica automáticamente el contenido al paciente. El médico solicitante publica la orden completa y se registra como autor de esa publicación.

La publicación:

- No cambia al autor técnico original del resultado.
- No genera una versión falsa de corrección.
- No depende de que el laboratorista original conserve una cuenta activa.

### 12.3. Corrección

Cambiar resultado, observaciones o referencia de archivo conserva la versión anterior con sus responsables y retira la publicación del contenido corregido. Se requiere una nueva publicación médica para que vuelva a aparecer en el portal.

Un guardado sin cambios clínicos no debe crear versiones adicionales. La aplicación conserva los archivos anteriores y elimina el archivo recién cargado si su transacción falla.

## 13. Diseño, interacción y accesibilidad

La presentación usa Blade y recursos propios, sin un servicio externo de diseño ni una SPA independiente.

Elementos implementados:

- Acceso centrado y panel principal con tarjetas basados en las referencias.
- Encabezado azul e iconos SVG.
- Formularios, tablas, paneles, estados y botones consistentes.
- Componentes Blade reutilizables para campos, estadísticas, títulos, iconos y estados vacíos.
- Paginación y tablas con desplazamiento horizontal cuando es necesario.
- Etiquetas asociadas a controles, enlace para saltar al contenido y foco visible.
- Mensajes de validación en español con atributos ARIA.
- Diseño CSS para escritorio y pantallas pequeñas, incluidas reglas por debajo de 480 px.
- Consideración de movimiento reducido e impresión.
- Botón para mostrar u ocultar contraseña.
- Distinción entre ausencia de horarios y error al consultarlos.
- Prevención de envíos repetidos y recuperación de botones al volver con el navegador.

**Límite de evidencia:** las referencias visuales se inspeccionaron y el HTML/CSS se implementó y compiló, pero no se completó una revisión visual e interactiva real de la aplicación en navegador. La herramienta de navegador rechazó el acceso al origen local al no concederse esa autorización. No se utilizó otro navegador ni una vía alternativa para eludir ese rechazo.

## 14. Validaciones JavaScript, PHP y PostgreSQL

### 14.1. JavaScript

Se separó la lógica pura en `public/assets/validation.js` para poder probarla sin navegador ni dependencias adicionales.

Se validan, según el control y las restricciones declaradas:

- Campos obligatorios, incluidos textos formados solo por espacios.
- Correo y formato de nombre de usuario.
- Longitudes mínimas y máximas.
- Contraseña nueva de al menos 12 caracteres y confirmación coincidente.
- Fechas válidas y límites como nacimiento no futuro.
- Fechas finales no anteriores a iniciales.
- Hora final posterior a inicial y franjas completas de 30 minutos.
- Rangos numéricos y errores de formato nativo.
- Selección múltiple y casillas obligatorias.
- Tipo y tamaño de archivos admitidos.

La integración en `app.js` muestra errores junto a controles, un resumen accesible y foco en el primer error. Los mensajes se insertan como texto, sin interpretar HTML del usuario.

La mejora es progresiva: sin JavaScript siguen presentes las restricciones HTML y las validaciones PHP. La confirmación de disponibilidad, la unicidad y los permisos permanecen a cargo del servidor y la base.

### 14.2. PHP

Los Form Requests y controladores validan nuevamente datos, tipos, formatos, longitudes, referencias y condiciones de la operación. Los servicios comprueban reglas del negocio, permisos y propiedad.

Se protege especialmente:

- Reservar para otro paciente.
- Acceder a una cita ajena por su URL.
- Consultar historia sin permiso o sin relación asistencial.
- Registrar atención antes de tiempo.
- Solicitar órdenes desde un rol incorrecto.
- Registrar tratamientos con fechas invertidas.
- Publicar o descargar resultados sin autorización.
- Cambiar identidad o vínculo desde el perfil del paciente.

### 14.3. PostgreSQL

Claves, unicidades, restricciones CHECK, exclusiones, funciones y disparadores aportan la última garantía de integridad. No se depende exclusivamente de que el navegador envíe datos correctos.

## 15. Manejo de errores y medidas de seguridad

- Traducción de duplicidades, conflictos de horario, referencias inválidas y restricciones del negocio a mensajes comprensibles.
- Respuestas JSON de validación para peticiones asíncronas.
- Conservación de datos del formulario al regresar por un error, excluyendo contraseñas.
- Mensajes que distinguen sesión vencida, falta de permiso, límite de solicitudes y fallo de disponibilidad.
- Páginas en español para 403, 404, 419, 429 y 500.
- Presentación sin trazas SQL ni credenciales para el usuario.
- Hash de contraseñas y protección CSRF en formularios.
- Escape de contenido al renderizar Blade.
- Validación de rol y cuenta en el servidor.
- Descargas privadas por rutas autorizadas, fuera de `public`.
- Encabezados como `X-Content-Type-Options`, `X-Frame-Options` y política de referencia.
- Control de caché de respuestas autenticadas.
- Secretos y respaldos fuera del versionado.

No se presenta esta lista como una auditoría de seguridad exhaustiva ni como certificación para operar con datos clínicos reales en producción.

## 16. Comentarios y buenas prácticas del código

Se agregaron comentarios PHPDoc y JavaScript que explican especialmente:

- La revalidación de disponibilidad dentro de una transacción.
- El orden común de bloqueos para evitar estrategias incompatibles.
- Los intervalos semiabiertos y las garantías de PostgreSQL.
- El origen de la identidad de paciente desde la sesión verificada.
- El alcance de las consultas antes de buscar registros por ID.
- El contexto transaccional de actor e IP para auditoría.
- La conservación de versiones y adjuntos y la compensación de archivos fallidos.
- La función de las validaciones del navegador frente a las del servidor.
- La conservación de espacios en las contraseñas y el tratamiento de mensajes como texto.

Se utilizaron métodos con tipos, nombres descriptivos, servicios separados, consultas parametrizadas y componentes reutilizables. Se mejoró la legibilidad de las transiciones de cita y se ejecutó Laravel Pint.

Los comentarios no sustituyen las pruebas ni se añadieron como repetición de cada línea. Se concentraron en las decisiones y reglas que podrían perderse al mantener el sistema.

## 17. Problemas detectados y corregidos durante la implementación

| Problema | Corrección realizada |
| --- | --- |
| El PHP global no tenía controlador PostgreSQL | Runtime completo separado, requisito Composer y selección automática en scripts. |
| Laravel buscaba por defecto en `public` | Configuración `vida_salud,public`, respetando tablas existentes. |
| Posible diferencia de zona horaria entre PHP y PostgreSQL | Zona del negocio configurada en aplicación, conexión y utilidades de fecha. |
| Selectores de médicos/pacientes fallaban por expresiones sin alias en `pluck` | Alias explícitos para las etiquetas calculadas. |
| Actualizar todas las asociaciones de especialidad a inactivas afectaba citas incluso sin retirar esa especialidad | Actualización de las asociaciones realmente retiradas y conservación de las seleccionadas. |
| Publicar un resultado generaba una versión aunque no hubiera corrección | Versionado limitado a cambios de contenido clínico. |
| La publicación podía auditarse con el laboratorista original | Validación separada de publicación y actor médico real. |
| Un laboratorista original inactivo podía impedir publicar un resultado válido | Publicación autorizada por el médico solicitante, independiente del estado actual del autor técnico anterior. |
| Retirar permiso de historia no impedía toda su lectura | Comprobación explícita de `historia.consultar` en la pantalla clínica. |
| Un perfil médico inactivo podía dejar de clasificarse como médico y caer en la agenda general | Clasificación por rol y exigencia de perfil activo para el alcance médico. |
| El reporte administrativo dependía indebidamente del permiso de agenda | Consulta de reporte autorizada con el permiso administrativo correspondiente. |
| La lectura de laboratorio dependía también de procesamiento | Separación real entre consulta y escritura, manteniendo el alcance autorizado. |
| Repetir seeders podía reinicializar permisos configurados | Inicialización de permisos solo en el primer arranque o en roles nuevos. |
| El selector de herramientas intentaba interpretar `psqlODBC` como número de versión | Filtro de directorios de versiones de PostgreSQL. |
| Riesgo de codificación en PowerShell/Python | Lectura y escritura UTF-8 explícitas; scripts PowerShell con BOM. |
| Una versión inicial del informe había cambiado los significados CP01–CP24 | Matriz corregida a los identificadores originales, con pruebas complementarias para cubrir casos críticos. |
| La validación JavaScript inicial era limitada | Módulo de validación puro, mensajes por campo, resumen accesible y pruebas Node. |
| Respuestas de sesión vencida podían parecer un fallo genérico al cargar horarios | Distinción de redirecciones y códigos de error en la consulta asíncrona. |

Las pruebas de regresión cubren varios de estos defectos. La existencia de esta revisión no implica que no puedan existir otros problemas: el navegador real y la carga alta siguen pendientes.

## 18. Pruebas ejecutadas y evidencias

### 18.1. Suite PHP completa

Última ejecución completa registrada durante la construcción:

| Dato | Resultado |
| --- | --- |
| Pruebas | **146 aprobadas de 146** |
| Aserciones | **542** |
| Duración | **38,287 segundos** |
| Base | `Parcial2_test` |
| Evidencia | `storage/logs/phpunit-final-all.xml` |

Comando utilizado con el PHP completo:

```powershell
& $env:VIDA_SALUD_PHP vendor/bin/phpunit --log-junit storage/logs/phpunit-final-all.xml --stop-on-failure
```

El resultado intermedio de 131 pruebas y 470 aserciones comunicado antes corresponde a una etapa anterior. Luego se agregaron reglas de aceptación y regresiones hasta llegar a 146; no se trata de dos proyectos distintos.

### 18.2. Revisión posterior de calidad

Después de reforzar la validación del frontend y los comentarios:

- **22 pruebas JavaScript aprobadas**, mediante `npm run test:js`.
- **93 pruebas PHP y 334 aserciones aprobadas**, correspondientes a páginas por rol, agenda y autenticación.
- Evidencia PHP de esa revisión: `storage/logs/phpunit-quality.xml`.
- Sintaxis JavaScript comprobada.
- Laravel Pint ejecutado.
- Plantillas Blade compiladas.
- Sintaxis PHP de las vistas compiladas comprobada.

Las 93 pruebas son una repetición de un subconjunto de la suite, no 93 casos adicionales al total de 146. Las 22 pruebas JavaScript pertenecen a otra suite y no acreditan interacción DOM en un navegador.

### 18.3. Archivos de pruebas

| Archivo o grupo | Cobertura principal |
| --- | --- |
| `AuthenticationTest.php` | Acceso, credenciales, cuenta inactiva, salida e invitados. |
| `CatalogTest.php` | Alta y normalización de paciente, duplicidades, permisos y escape de HTML. |
| `AppointmentTest.php` | Reserva, disponibilidad, conflictos, estados, propiedad y perfil médico inactivo. |
| `AppointmentConcurrencyTest.php` | Dos variantes de reservas incompatibles con procesos reales. |
| `ClinicalAndLaboratoryTest.php` | Atención, relación asistencial, diagnóstico, órdenes, resultados, corrección y portal. |
| `PortalRegistrationTest.php` | Solicitud pendiente, verificación y prevención de apropiación de cuenta. |
| `RolePagesTest.php` | Renderizado completo de vistas permitidas y rechazo de rutas sin permiso. |
| `AcceptanceRulesTest.php` | Bloques contiguos, liberación, rollback, horarios ocupados, desactivación, reportes, adjuntos y permisos finos. |
| `TestDatabaseSafetyTest.php` | Rechazo de configuraciones peligrosas antes de limpiar una base. |
| `tests/JavaScript/validation.test.mjs` | Reglas puras JavaScript de campos y relaciones. |

Se conservaron también pruebas de ejemplo del esqueleto donde correspondía; el total incluye lo realmente ejecutado por PHPUnit.

### 18.4. Concurrencia real

No se simuló simplemente una doble llamada secuencial. Se ejecutaron procesos PHP separados, con conexiones y transacciones independientes, datos de preparación confirmados y una barrera de sincronización.

La prueba observa que la segunda conexión espera un bloqueo en `pg_stat_activity` mientras la primera conserva su transacción. Luego se confirma la primera y se comprueba:

- Una respuesta de reserva confirmada.
- Un rechazo de la reserva incompatible.
- Una sola cita persistida.
- Un solo evento inicial de esa cita.

Se probaron dos variantes:

1. Mismo médico y pacientes distintos.
2. Mismo paciente y médicos distintos.

En la ejecución separada se registraron **2 pruebas aprobadas y 16 aserciones**, con evidencia en `storage/logs/phpunit-concurrency.xml`. Esas pruebas forman parte de las 146 de la suite completa; no se suman otra vez.

### 18.5. Protección de la base de trabajo

Antes de reconstruir datos, la infraestructura de pruebas exige:

- Entorno `testing`.
- Driver PostgreSQL.
- Nombre de base terminado en `_test`.
- Coincidencia con el nombre real obtenido de `current_database()`.
- Ausencia de configuración de aplicación en caché que pudiera desviar la conexión.

Los tests habituales usan transacciones. La preparación de algunos escenarios históricos importa una cita pasada de manera controlada porque el reloj real de PostgreSQL no se puede retroceder como un reloj PHP de prueba. Se restaura el comportamiento normal de los disparadores antes de ejecutar las operaciones bajo prueba.

La concurrencia requiere datos confirmados entre procesos y tiene una limpieza adaptada al esquema clínico protegido. No deben ejecutarse dos suites simultáneas sobre la misma base de pruebas.

### 18.6. CP01–CP24

La matriz de [docs/aceptacion.md](docs/aceptacion.md) conserva la numeración original. Incluye evidencia para paciente nuevo, duplicidad de CI, reserva, superposición, horario, cancelación, historial, agenda, acceso, validación, concurrencia, permisos, propiedad, especialidades, estados activos, horarios ocupados, rollback, estados finales, restauración, reportes, vínculo de cuenta, datos de salud y bloques contiguos.

CP24 corresponde a **bloques contiguos**, no a rendimiento. La latencia con volumen es un criterio no funcional independiente.

## 19. Respaldo y restauración comprobados

Se generaron dos respaldos locales:

```text
storage/backups/Parcial2_antes_vida_salud.dump
storage/backups/Parcial2_aplicacion_20261024.dump
```

El primero conserva el estado anterior a los cambios. El segundo contiene el estado de demostración de la aplicación ya ampliada.

Se ejecutaron realmente `scripts/backup.ps1` y `scripts/restore.ps1`, restaurando el segundo archivo en la base nueva:

```text
Parcial2_restore_test
```

Comprobaciones realizadas:

- Restauración con `pg_restore --single-transaction --exit-on-error`.
- Conteos `count(*)` comparados en las 29 tablas del esquema.
- Ninguna diferencia de conteos entre origen y destino en esa verificación.
- Cero restricciones sin validar en el destino.
- Una consulta adicional confirmó ausencia de referencias usuario–rol inválidas.

Conteos de referencia del respaldo:

| Dato | Cantidad |
| --- | ---: |
| Usuarios | 5 |
| Roles | 5 |
| Permisos | 20 |
| Asociaciones rol–permiso | 26 |
| Pacientes ficticios | 1 |
| Médicos | 1 |
| Especialidades | 1 |
| Horarios | 5 |
| Análisis | 3 |
| Migraciones | 5 |

Las tablas de citas, consultas, diagnósticos, tratamientos y resultados estaban vacías en ese respaldo. Esta evidencia no demuestra recuperación con expedientes clínicos reales o carga alta. Los conteos anteriores son los de ese momento, no valores que deban seguir siendo iguales después de utilizar el sistema.

Los adjuntos de `storage/app/private` y las claves de la aplicación requieren un respaldo separado: un dump PostgreSQL no contiene los archivos del sistema operativo.

La base operativa no se sustituyó por la restauración. El script de recuperación rechaza `Parcial2`, exige un nombre de base nuevo y no utiliza `--clean` para borrar objetos existentes.

## 20. Datos ficticios y cuentas de demostración

`DatabaseSeeder` ejecuta:

```text
RolePermissionSeeder
DemoAccountSeeder
```

Cuentas creadas cuando no existían:

| Usuario | Rol |
| --- | --- |
| `admin` | Administrador |
| `recepcion` | Recepcionista |
| `medico` | Medico |
| `laboratorio` | Laboratorista |
| `paciente` | Paciente |

La contraseña inicial local de demostración documentada es:

```text
VidaSalud2026!
```

Procede de `DEMO_PASSWORD`; repetir un seeder no restablece la contraseña de una cuenta que ya existía. Por tanto, si alguien la cambia posteriormente, no debe suponerse que el valor de demostración sigue siendo válido.

Directorio de demostración:

- Ana Prueba, CI `TEST-001`, con cuenta de paciente verificada.
- Dr Uno, registro profesional `MED-TEST-01`.
- Medicina General.
- Horarios de lunes a viernes de 08:00 a 12:00 creados cuando faltaban.
- Hemograma completo, glucosa en sangre y examen general de orina.

No se sembraron citas futuras, consultas u órdenes ficticias en la base operativa como parte del directorio. Los casos de prueba construyen sus propios datos en la base aislada y pueden usar horarios distintos para sus escenarios.

## 21. Instalación y ejecución en esta computadora

### 21.1. Selección de PHP y carpeta

Desde PowerShell:

```powershell
Set-Location -LiteralPath 'D:\Universidad Carlos Villagra\2026-2\Ingenieria de requisitos y modelado - SC\Nueva carpeta (3)'
$env:VIDA_SALUD_PHP = 'C:\Users\USUARIO\.config\vida-salud\php85\php.exe'
```

La variable configura el ejecutable para la sesión de terminal. No reemplaza globalmente el PHP de Herd.

### 21.2. Instalación

```powershell
.\scripts\install.ps1
```

El instalador:

1. Busca un PHP con versión suficiente y `pdo_pgsql`.
2. Reutiliza `.env` o lo crea desde `.env.example` si falta.
3. Solicita credenciales de PostgreSQL cuando corresponde, con contraseña oculta.
4. Ejecuta Composer con el mismo PHP seleccionado.
5. Limpia caché de configuración.
6. Genera una clave de aplicación si falta.
7. Ejecuta migraciones y seeders sin vaciar la base.

Para volver a configurar la conexión:

```powershell
.\scripts\install.ps1 -ConfigureDatabase
```

La base debe existir y PostgreSQL debe estar iniciado. Se ejecutó el instalador sobre esta instalación existente y terminó correctamente. No se afirmó haberlo verificado en otra máquina limpia.

### 21.3. Arranque

```powershell
.\scripts\start.ps1
```

Para otro puerto:

```powershell
.\scripts\start.ps1 -Port 8001
```

El servidor escucha localmente. La dirección documentada de acceso es `http://localhost:8000` cuando el servidor está iniciado en el puerto predeterminado. Se verificó su arranque en 8000 y una prueba del helper en 8001; esta última instancia de prueba se detuvo.

El estado de un proceso no es permanente: al retomar el proyecto hay que comprobar si el servidor sigue ejecutándose. Para detener el proceso iniciado en una terminal se utiliza `Ctrl+C`.

### 21.4. Variables de conexión, sin secretos

```dotenv
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=Parcial2
DB_USERNAME=postgres
DB_SCHEMA=vida_salud,public
DB_PASSWORD=CONFIGURAR_LOCALMENTE
```

La contraseña real ya configurada no se copia en este archivo. Tampoco se copia `APP_KEY`.

## 22. Comandos útiles para continuar

Los comandos siguientes presuponen haber definido `$env:VIDA_SALUD_PHP` como en la sección anterior y estar en la raíz del proyecto.

### Diagnóstico básico

```powershell
& $env:VIDA_SALUD_PHP -v
& $env:VIDA_SALUD_PHP -m
& $env:VIDA_SALUD_PHP artisan migrate:status --no-interaction
& $env:VIDA_SALUD_PHP artisan route:list --except-vendor
& $env:VIDA_SALUD_PHP artisan config:clear --no-interaction
```

### Pruebas PHP

Usar exclusivamente la base de pruebas configurada. No dirigirlas a `Parcial2`.

```powershell
& $env:VIDA_SALUD_PHP vendor/bin/phpunit
& $env:VIDA_SALUD_PHP vendor/bin/phpunit --group concurrency
& $env:VIDA_SALUD_PHP vendor/bin/phpunit tests/Feature/AcceptanceRulesTest.php
```

### Pruebas JavaScript

```powershell
npm run test:js
```

Este comando usa el ejecutor de pruebas incluido en Node y no requiere instalar una biblioteca de pruebas externa.

### Formato y vistas

```powershell
& $env:VIDA_SALUD_PHP vendor/bin/pint --dirty --format agent
& $env:VIDA_SALUD_PHP artisan view:cache
node --check public/assets/app.js
node --check public/assets/validation.js
```

### Respaldo y restauración

El directorio de destino debe existir y el archivo de respaldo debe ser nuevo:

```powershell
.\scripts\backup.ps1 -OutputFile 'D:\Respaldos\vida-salud-nuevo.dump'
.\scripts\restore.ps1 -BackupFile 'D:\Respaldos\vida-salud-nuevo.dump' -NewDatabase Parcial2_restaurada_nueva
```

La herramienta solicita contraseña si no dispone de una configuración local válida. No incluirla en scripts ni en el comando como texto visible.

**No ejecutar `migrate:fresh`, `db:wipe` ni restauraciones destructivas sobre `Parcial2`.** La reconstrucción de esquema de pruebas tiene protecciones y un alcance separado.

## 23. Mapa de navegación y rutas principales

Las rutas se declaran en `routes/web.php`. Los nombres se utilizan para generar enlaces y no depender de direcciones escritas manualmente dentro de las vistas.

| Ruta | Uso principal |
| --- | --- |
| `GET /ingresar` | Formulario de acceso. |
| `POST /ingresar` | Autenticación con limitación de solicitudes. |
| `GET /registro` y `POST /registro` | Solicitud pública de cuenta de paciente. |
| `GET /` | Panel de módulos autorizado. |
| `POST /salir` | Cierre de sesión. |
| `GET /gestion/{module}` | Listado de catálogo. |
| `GET /gestion/{module}/nuevo` y `POST /gestion/{module}` | Formulario y alta de catálogo. |
| `GET /gestion/{module}/{id}/editar` | Formulario de edición. |
| `PUT` o `POST /gestion/{module}/{id}` | Actualización del registro autorizado. |
| `GET /agenda` | Agenda e historial de citas según alcance. |
| `GET /agenda/nueva` y `POST /agenda` | Formulario y reserva. |
| `GET /agenda/disponibilidad` | Respuesta JSON con bloques libres. |
| `GET /agenda/{cita}` | Detalle y eventos. |
| `POST /agenda/{cita}/accion` | Cambio de estado o reprogramación. |
| `GET /atencion` y `GET /atencion/{cita}` | Atención del médico y detalle clínico autorizado. |
| `POST /historias/{paciente}` | Registro/actualización de antecedentes autorizados. |
| `POST /atencion/{cita}/consulta` | Guardado de consulta. |
| `POST /consultas/{consulta}/diagnosticos` | Registro de diagnóstico. |
| `POST /diagnosticos/{diagnostico}/tratamientos` | Registro de tratamiento. |
| `POST /consultas/{consulta}/ordenes` | Solicitud de análisis. |
| `GET /laboratorio` y `GET /laboratorio/{orden}` | Órdenes y resultados autorizados. |
| `POST /laboratorio/analisis/{ordenAnalisis}/resultado` | Alta/corrección de resultado. |
| `POST /laboratorio/{orden}/estado` | Estado de procesamiento/cancelación autorizada. |
| `POST /ordenes/{orden}/publicar` | Publicación por el médico solicitante. |
| `GET /informes/{resultado}` | Descarga privada del informe actual. |
| `GET /portal` | Información y citas propias del paciente. |
| `GET /portal/perfil` y `POST /portal/perfil` | Contacto del paciente. |
| `GET /solicitudes-portal` | Cola de verificación del personal autorizado. |
| `POST /solicitudes-portal/{solicitud}` | Aprobación o rechazo. |
| `GET /reportes` | Reportes según permiso y alcance. |
| `GET /bitacora` | Auditoría autorizada. |

Las rutas internas comparten autenticación y comprobación de cuenta activa. Los controladores y servicios añaden los permisos y límites propios de cada operación; el middleware común no sustituye esas comprobaciones.

## 24. Documentación, archivos locales y versionado

### 24.1. Documentos de referencia del repositorio

| Archivo | Contenido |
| --- | --- |
| [README.md](README.md) | Requisitos, instalación, inicio, cuentas, uso, pruebas, respaldo y problemas frecuentes. |
| [docs/decisiones.md](docs/decisiones.md) | Reglas adoptadas y alcance de permisos, portal, citas y laboratorio. |
| [docs/schema.md](docs/schema.md) | Contrato de datos, migraciones, contexto de auditoría y seeders. |
| [docs/etapas.md](docs/etapas.md) | Secuencia de construcción y verificación por etapa. |
| [docs/aceptacion.md](docs/aceptacion.md) | Resultados automatizados, matriz CP01–CP24 y límites de la evidencia. |
| [docs/validacion_restauracion.md](docs/validacion_restauracion.md) | Restauración real, conteos y alcance de demostración. |
| [AGENTS.md](AGENTS.md) | Reglas de trabajo generadas después de instalar Boost. |
| Este archivo | Contexto consolidado para retomar el proyecto. |

### 24.2. Archivos que no deben confundirse con documentación pública

- `.env`: conexión y clave de esta instalación.
- `.env.testing`: conexión aislada para pruebas.
- `storage/backups`: copias locales, excluidas de Git.
- `storage/app/private`: adjuntos de laboratorio.
- `storage/logs`: logs y evidencias locales de PHPUnit.
- `.codex/config.toml`: configuración local, con ruta al PHP adecuado para este equipo.

Los archivos JUnit se generaron localmente, pero no debe suponerse que estarán disponibles en otro clon si no se transportan expresamente como evidencias. Tampoco deben publicarse respaldos o expedientes por el hecho de que este repositorio tenga documentación académica.

### 24.3. Estado de Git observado

Se inicializó un repositorio Git local. En la revisión usada para preparar este contexto los archivos del proyecto seguían apareciendo como no versionados y no había un remoto configurado. No se creó ni publicó un repositorio GitHub, no se hizo `push` y no se documentó un commit final.

Si se continúa con versionado, primero deben revisarse los archivos incluidos y sus exclusiones. La inicialización de Git no equivale a tener una entrega publicada.

### 24.4. Restos del esqueleto

El proyecto conserva algunos archivos estándar, como configuración Vite, scripts genéricos de Composer, recursos iniciales o vistas de bienvenida sin uso en las rutas principales. Su presencia no significa que sean el flujo de instalación recomendado.

Para esta aplicación se comprobaron `scripts/install.ps1` y `scripts/start.ps1`. No es necesario ejecutar `composer run setup`, compilar una SPA ni utilizar la base SQLite inicial para seguir el flujo documentado.

## 25. Qué está terminado y qué requiere trabajo adicional

### 25.1. Implementado y comprobado en el alcance indicado

- Código frontend y backend organizado por módulos.
- Conexión real a PostgreSQL y uso de `Parcial2`.
- Migraciones compatibles con el esquema observado y arranque de base vacía en pruebas.
- Acceso, roles, permisos y controles de propiedad.
- Catálogos, horarios y agenda.
- Reserva, cancelación, reprogramación, estados y eventos.
- Atención clínica y laboratorio.
- Registro/verificación del portal y acceso propio.
- Reportes y bitácora.
- Validación JavaScript, HTML, PHP y restricciones de base.
- Manejo de errores en español y comentarios sobre reglas importantes.
- Pruebas PHP y JavaScript descritas, incluida concurrencia real.
- Ejecución del instalador sobre esta instalación existente y arranque local.
- Respaldo y restauración de demostración en una base nueva.
- Documentación de instalación, decisiones y evidencias.

### 25.2. Pendientes de verificación

1. **Revisión visual real en navegador:** comparar la aplicación renderizada con los diseños de referencia.
2. **Interacción real JavaScript/DOM:** probar mensajes, selectores de disponibilidad, navegación, historial del navegador, confirmaciones y archivos en un navegador autorizado.
3. **Compatibilidad y accesibilidad de uso:** recorrido por teclado, Chromium, Firefox y ancho de 360 px. Hay implementación responsive, pero falta la evidencia de ejecución en esos entornos.
4. **Prueba de carga:** medir agenda con 10.000 citas y cinco usuarios concurrentes, y reportes con el umbral acordado.
5. **Instalación en una máquina limpia:** el helper se ejecutó en la instalación existente; no se afirmó validación en otro equipo desde cero.
6. **Recuperación con expediente y adjuntos representativos:** la restauración ejecutada comprobó la estructura y datos demo existentes, con las tablas clínicas transaccionales vacías.

Objetivos de latencia documentados, todavía no certificados:

```text
Agenda: 2 segundos con 10.000 citas y cinco usuarios concurrentes.
Reportes: 5 segundos.
```

Los 38,287 segundos de PHPUnit no son el tiempo de respuesta de una agenda ni una prueba de carga.

### 25.3. Funciones opcionales o no asumidas como implementadas

- Exportadores dedicados CSV/PDF.
- Recordatorios automáticos por correo o mensajería.
- Publicación/hosting y dominio público.
- Repositorio remoto, URL y QR de entrega.
- Recuperación de contraseña autoservicio y otras políticas no concretadas como requisito de esta implementación.
- Calendario especial de feriados y ausencias.
- Facturación, inventario y teleconsulta, fuera del alcance definido.

Estas funciones no deben presentarse en una defensa o entrega como ya desarrolladas.

## 26. Recomendaciones concretas para la próxima sesión

1. Leer este contexto, `AGENTS.md`, `README.md` y `docs/aceptacion.md`.
2. Comprobar la carpeta de trabajo y seleccionar el PHP con `pdo_pgsql`.
3. Revisar `git status` antes de tocar archivos; existen cambios del proyecto que todavía no están en un commit.
4. Comprobar conexión y migraciones sin vaciar la base operativa.
5. Elegir una tarea pendiente concreta; no reconstruir desde cero lo ya implementado.
6. Si se necesita revisión visual, obtener autorización para el origen local en la herramienta de navegador. No eludir un rechazo anterior usando otra vía de acceso.
7. Si se cambian permisos o reglas del negocio respecto de lo documentado, consultarlo con el usuario.
8. Ejecutar las pruebas adecuadas al cambio en la base test y actualizar las evidencias correspondientes.
9. Mantener secretos, adjuntos y respaldos fuera de Git.

### Bloque breve para retomar con otro agente

> Este es un proyecto existente de Clínica Vida Salud en Laravel 13.38 y PostgreSQL 18.6, con PHP 8.5.12. La carpeta de trabajo es `D:\Universidad Carlos Villagra\2026-2\Ingenieria de requisitos y modelado - SC\Nueva carpeta (3)`. La base operativa es `Parcial2`, esquema `vida_salud`; las pruebas usan `Parcial2_test`. Usa el PHP completo de `C:\Users\USUARIO\.config\vida-salud\php85\php.exe` o los helpers de `scripts`, porque el PHP global de Herd Lite no tiene `pdo_pgsql`. Hay cinco migraciones, código por módulos en `app/Modules` y frontend Blade/CSS/JavaScript. Existen acceso, catálogos, agenda transaccional, historia/consulta/diagnóstico/tratamiento, laboratorio con versiones y publicación médica, portal verificado, reportes y auditoría. La última suite PHP completa registrada aprobó 146 pruebas y 542 aserciones; una revisión posterior aprobó 22 pruebas JavaScript y repitió 93 pruebas PHP. No sumes las repeticiones al total. Se comprobó restauración de un respaldo demo comparando las 29 tablas en una base nueva. Faltan revisión visual/DOM en navegador autorizado, carga y otras verificaciones identificadas en el contexto. No ejecutes `migrate:fresh` sobre `Parcial2`, no publiques credenciales y no cambies reglas de permisos sin consultar. Lee primero `AGENTS.md`, este archivo, `README.md` y `docs/aceptacion.md`. El repositorio local no tiene una entrega remota publicada.

## 27. Alcance de este archivo

Para atender la solicitud de contexto se revisaron el código, las rutas, la documentación y la existencia de evidencias y respaldos. Se creó este Markdown en la raíz del proyecto. Esa tarea de documentación no implica haber vuelto a ejecutar todas las pruebas, cambiado reglas del negocio o modificado datos operativos.

Los resultados de ejecución aquí descritos corresponden a las verificaciones identificadas de la sesión de desarrollo y de la revisión posterior de calidad.
