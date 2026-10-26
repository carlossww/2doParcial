# Plan de corrección de los casos de uso implementados

## 1. Objetivo y referencia

Corregir las diferencias entre la aplicación actual y los casos de uso CU2, CU4, CU6, CU7 y CU20. Incorporar además la corrección de CU18/RF05: duración configurable de las citas.

Referencia funcional: `C:/Users/USUARIO/Downloads/2doparcial/Vida Salud - Grupo 9 (3).pdf`. Las fichas están en las páginas 23–42; RF05 está en la página 7. Las rutas de código de este documento son relativas a la raíz `Sistema_Gestion_Clinica-main`.

**Estado de ejecución, 25/10/2026:** correcciones de CU2, CU4, CU6, CU7, CU18/RF05 y CU20 implementadas. La suite consolidada aprueba 128 pruebas PHP con 501 aserciones y 40 pruebas JavaScript. La verificación visual/interactiva está pendiente porque la herramienta de navegador no pudo verificar la URL actual de Brave. El enlace de CU6 a la agenda permanece diferido hasta implementar CU10. No se ejecutó el segundo plan ni se modificó la base operativa. Evidencia y límites en [aceptacion.md](aceptacion.md).

Plan complementario: [Implementación de los casos pendientes](plan_implementacion_casos_pendientes.md).

## 2. Decisiones acordadas

- Administrador y recepción podrán gestionar médicos y especialidades. Esto resuelve la contradicción del PDF, que menciona a recepción como actor y al administrador en las precondiciones.
- Los horarios admitirán citas de 15, 30, 45 o 60 minutos. La duración inicial será de 30 minutos.
- Las citas existentes conservarán sus fechas y su duración de 30 minutos.
- Se mantendrán Laravel, Blade, JavaScript y PostgreSQL. No se reemplazará la arquitectura ni el esquema original.
- Las modificaciones de base de datos se harán con migraciones nuevas, conservando el snapshot SQL y las migraciones anteriores.
- No se eliminarán pacientes, médicos, especialidades, citas, consultas ni resultados para resolver diferencias con el documento.

## 3. Preparación común

1. Instalar las dependencias desde `composer.lock`, sin actualizar versiones. En la copia revisada no estaban presentes `vendor` ni `.env`.
2. Configurar las pruebas con `APP_ENV=testing`, PostgreSQL y `Parcial2_test`. Mantener las barreras de `tests/Support/TestDatabaseSafety.php`.
3. Registrar un resultado inicial de las pruebas realmente presentes. No presentar las cifras históricas de `docs/aceptacion.md` como resultados de esta copia.
4. Antes de modificar la base operativa, generar un respaldo y validar las migraciones sobre una copia independiente.
5. Mantener autor e IP mediante `Clinic::transaction`. Aprovechar los triggers de auditoría existentes para no duplicar eventos.

## 4. CU2: cerrar sesión

### Ubicación actual

- `app/Modules/Acceso/Controllers/AuthController.php`: método `logout`.
- `resources/views/layouts/app.blade.php`: formulario del botón de salida.
- `public/assets/app.js`: comportamiento de formularios.
- `routes/web.php`: ruta `POST /salir`, nombre `logout`.

### Diferencia

El cierre actual invalida la sesión y redirige al ingreso. Faltan la confirmación previa, la entrada de salida en bitácora y el reintento ante problemas de conexión descritos en la página 24.

### Cambios

1. Agregar `GET /salir/confirmar`, con nombre `logout.confirm`, y una vista `resources/views/auth/logout-confirm.blade.php`.
2. Cambiar el botón de salida para abrir esa confirmación. La pantalla tendrá opciones de confirmar o regresar y funcionará sin JavaScript.
3. Mantener `POST /salir` con protección CSRF para efectuar el cierre.
4. Antes de invalidar la sesión, guardar en una transacción una entrada de bitácora con acción `SALIDA`, entidad `usuario`, identificador del actor e IP.
5. Finalizar la sesión, invalidar sus datos, regenerar el token y redirigir al ingreso. Para solicitudes JSON, devolver una respuesta de éxito que permita al cliente redirigir.
6. En la confirmación, permitir un único reintento automático de la petición ante un fallo de red. Si la sesión ya terminó, redirigir al ingreso sin registrar otra salida. Si persiste el fallo, mostrar reintento manual.
7. No reintentar automáticamente errores de validación ni errores del servidor como si fueran fallos de red.
8. La ejecución añadió una protección durable: `bitacora.clave_operacion`, nullable y única, deriva del identificador de sesión únicamente en el servidor. `insertOrIgnore` impide dos entradas de salida aun si el bloqueo temporal de sesión expira. Las auditorías históricas conservan NULL y la reversión queda protegida si ya existen claves registradas.

### Pruebas y aceptación

- Cancelar conserva la sesión y no agrega una salida a la bitácora.
- Confirmar registra una sola salida y deja al usuario sin autenticación.
- La confirmación funciona con JavaScript desactivado.
- Un reintento después de un cierre exitoso no duplica la auditoría.
- Un error persistente no causa reintentos indefinidos.
- Ampliar `tests/Feature/AuthenticationTest.php` y verificar el comportamiento de red en navegador.

## 5. CU4: gestionar roles y permisos

### Ubicación actual

- `app/Modules/Catalogos/Controllers/CatalogController.php`: formularios y operaciones de catálogos.
- `app/Modules/Catalogos/Services/CatalogService.php`: método `save`, rama `roles`.
- `app/Http/Requests/CatalogRequest.php`: validaciones de roles y permisos.
- `resources/views/catalog/index.blade.php`: listado y acciones.
- `routes/web.php`: rutas de gestión.

### Diferencia

El módulo permite crear, consultar y editar roles y permisos, pero no ofrece eliminación. La ficha de la página 26 contempla el intento de eliminación de un rol asignado.

### Cambios

1. Agregar exclusivamente para roles `DELETE /gestion/roles/{id}`, nombre `catalog.roles.destroy`. No crear una eliminación genérica para todos los catálogos.
2. Incorporar un método específico en el controlador y un método `deleteRole` en el servicio.
3. Exigir `roles.gestionar`, bloquear el rol dentro de una transacción y rechazar registros inexistentes.
4. Impedir eliminar los roles predeterminados Administrador, Recepcionista, Medico, Laboratorista y Paciente, además del rol del actor.
5. Impedir eliminar cualquier rol con usuarios asociados, aunque estén inactivos. Informar que deben reasignarse primero.
6. Para un rol personalizado sin usuarios, eliminar sus asignaciones de permisos y después el rol, dentro de la misma transacción.
7. Aprovechar las claves foráneas y los triggers de `rol` y `rol_permiso`; no duplicar su auditoría desde PHP.
8. Agregar confirmación y mensajes específicos para rol protegido, asignado, inexistente o eliminado.

### Pruebas y aceptación

- Se elimina un rol personalizado sin usuarios y desaparecen sus asignaciones.
- Se rechaza un rol asignado a usuarios activos o inactivos.
- Se rechazan los roles protegidos y los intentos sin permiso.
- No quedan cambios parciales si falla la transacción.
- La eliminación y sus cambios relacionados quedan auditados.
- Ampliar `tests/Feature/CatalogTest.php` y la cobertura de páginas por rol.

## 6. CU6: gestionar médicos

### Ubicación actual

- `config/clinic.php`: definición del catálogo `medicos`.
- `app/Modules/Catalogos/Services/CatalogService.php`: métodos `query`, `options` y `save` para médicos.
- `app/Modules/Catalogos/Controllers/CatalogController.php`: búsqueda y formularios.
- `app/Http/Requests/CatalogRequest.php`: validaciones de médicos.
- `database/seeders/RolePermissionSeeder.php`: permisos iniciales.
- `resources/views/catalog/index.blade.php` y `form.blade.php`: vistas compartidas.

### Diferencias

Recepción solo consulta el módulo, mientras la ficha de la página 28 la identifica como actor de gestión. RF03 también requiere presentar y modificar los datos profesionales y de contacto completos. Actualmente nombres, apellidos y correo pertenecen a la cuenta vinculada y la búsqueda del listado no los cubre completamente.

### Cambios

1. Conservar `medicos.gestionar` para administración y otorgarlo a recepción.
2. Actualizar el seeder para instalaciones nuevas y agregar una migración puntual para los roles existentes. Añadir solo el permiso acordado, sin reemplazar otras asignaciones ni contraseñas.
3. Mostrar código, registro profesional, nombres, apellidos, teléfono, correo, especialidades y estado.
4. Obtener nombres, apellidos y correo de la cuenta vinculada. Actualizarlos conjuntamente con el perfil médico dentro de una transacción.
5. Permitir desde este módulo únicamente esos datos de identidad y contacto. No permitir cambiar rol, permisos, contraseña o estado de la cuenta mediante el formulario de médico.
6. Conservar la creación de cuentas a cargo del administrador. Recepción seleccionará una cuenta médica existente y activa.
7. Corregir la búsqueda para consultar nombres y apellidos de la cuenta vinculada, además del registro profesional y los demás campos pertinentes.
8. Conservar las restricciones de identidad del perfil, especialidades activas, duplicados y protección de citas futuras.
9. Una vez implementado CU10, agregar un acceso a la agenda del médico desde el directorio.

### Pruebas y aceptación

- Administrador y recepción crean, editan y desactivan perfiles autorizados.
- Recepción no obtiene gestión de usuarios o roles.
- Se rechazan campos manipulados para cambiar privilegios o trasladar el perfil a otra cuenta.
- Nombres, apellidos y registro profesional producen búsquedas correctas.
- La desactivación conserva el historial y no deja citas futuras sin resolver.
- El enlace a agenda se verifica durante la integración con CU10.

## 7. CU7: configurar especialidades

### Ubicación actual

- `config/clinic.php`: catálogo `especialidades`.
- `app/Http/Requests/CatalogRequest.php`: validaciones.
- `app/Modules/Catalogos/Services/CatalogService.php`: guardado y comparación de nombres normalizados.
- `database/seeders/RolePermissionSeeder.php`: permisos iniciales.
- Vistas compartidas de `resources/views/catalog`.

### Diferencia

La ficha de la página 29 señala a recepción como actor, pero el permiso de modificación solo se asigna inicialmente al administrador.

### Cambios

1. Otorgar `especialidades.gestionar` a recepción y conservarlo en administración.
2. Aplicar la actualización tanto a instalaciones nuevas como existentes sin sustituir otros permisos.
3. Mantener las operaciones de alta, consulta, búsqueda, edición y desactivación.
4. Conservar la comparación normalizada de nombres y las restricciones sobre citas futuras.
5. Utilizar desactivación para retirar una especialidad; no agregar eliminación física de especialidades vinculadas.

### Pruebas y aceptación

- Ambos roles pueden gestionar el catálogo.
- Modificar las pruebas que actualmente esperan rechazo para recepción al crear especialidades.
- Se rechazan nombres equivalentes por espacios o capitalización.
- Se conserva el historial y se rechazan desactivaciones que afecten citas futuras pendientes.
- Los roles ajenos continúan sin acceso de escritura.

## 8. CU18/RF05: horarios y duración de citas

### Ubicación actual

- `config/clinic.php`: catálogo `horarios`.
- `app/Http/Requests/CatalogRequest.php`: validación de franjas.
- `app/Modules/Catalogos/Services/CatalogService.php`: guardado de horarios.
- `public/assets/validation.js`: validación fija de bloques de 30 minutos.
- `tests/JavaScript/validation.test.mjs`: expectativas actuales de 30 minutos.
- `database/vida_salud_existing.sql`: funciones, restricciones y triggers originales de horarios y citas.
- `database/migrations/`: extensiones posteriores de integridad y disponibilidad.

### Diferencia

RF05 pide configurar la duración, pero las restricciones y funciones actuales imponen 30 minutos. No basta con agregar un campo a la interfaz.

### Cambios del esquema

Crear migraciones nuevas para:

1. Agregar `horario.duracion_minutos`, obligatorio, valor inicial 30 y valores permitidos 15, 30, 45 y 60.
2. Agregar `cita.duracion_minutos`, obligatorio y con valor inicial 30 para todas las citas existentes. Este campo conserva la duración de la reserva, independientemente de cambios posteriores del horario.
3. Sustituir `cita_check` por la comprobación de que `fin` corresponde a `inicio` más la duración guardada.
4. Adaptar la restricción de franja completa de `horario` a su duración configurada.
5. Adaptar `horarios_disponibles`, `agendar_cita`, `reprogramar_cita`, `validar_cita` y `proteger_horario`, eliminando sus cálculos fijos de 30 minutos o 1800 segundos.
6. Mantener las exclusiones por médico y paciente con intervalos `[inicio, fin)`.
7. Añadir `fin_anterior` y `fin_nuevo` a `evento_cita` y actualizar `registrar_evento_cita` para los eventos futuros. Los eventos antiguos permanecerán intactos; al mostrarlos, se podrá calcular su final histórico usando los 30 minutos que regían entonces.
8. Impedir modificar la duración de una franja cuando afecte citas futuras Programada o Confirmada. No recalcular silenciosamente esas citas.

### Cambios de aplicación

1. Mostrar y editar la duración por horario; mantener esta gestión en administración.
2. Validar los valores permitidos y que la franja contenga bloques completos.
3. Generar bloques alineados con el inicio de la franja correspondiente.
4. Calcular duración y final de cita en el servidor, sin confiar en datos equivalentes enviados por el navegador.
5. Al reprogramar, tomar la duración de la franja de destino y conservar el intervalo anterior en el evento.
6. Actualizar conjuntamente las validaciones PHP, JavaScript y PostgreSQL.
7. No modificar el snapshot original para introducir estos cambios.

### Pruebas y aceptación

- Franjas y reservas válidas para 15, 30, 45 y 60 minutos.
- Rechazo de valores no permitidos, bloques incompletos o desalineados y salidas de jornada.
- Citas contiguas permitidas y superpuestas rechazadas, por médico y paciente.
- Conservación exacta de citas anteriores después de migrar.
- Reprogramación entre franjas de distinta duración con historial correcto.
- Rechazo de cambios de duración que afecten citas futuras.
- Migración comprobada sobre una base vacía y una copia del esquema existente.

## 9. CU20: consultar bitácora

### Ubicación actual

- `app/Modules/Auditoria/Controllers/AuditController.php`: método `index`.
- `resources/views/auditoria/index.blade.php`: filtros y listado.
- `routes/web.php`: ruta de bitácora.

### Diferencia

El listado actual filtra por fechas y entidad. Faltan filtros por usuario y acción y una vista individual de detalle, según la página 42.

### Cambios

1. Agregar filtros `usuario_id` y `accion`, preservando los actuales y la paginación de 30 registros.
2. Poblar opciones desde usuarios y acciones registrados; incluir una opción para eventos del sistema sin usuario.
3. Validar rangos de fechas y valores de filtros sin construir SQL directamente desde la entrada.
4. Agregar `GET /bitacora/{id}`, nombre `auditoria.show`, método `show` y vista `resources/views/auditoria/show.blade.php`.
5. Mostrar usuario, fecha y hora, acción, entidad, registro, descripción e IP.
6. Conservar los filtros al paginar y regresar desde el detalle.
7. Exigir `bitacora.consultar` en ambas rutas; no habilitar edición o eliminación.

### Pruebas y aceptación

- Filtros individuales y combinados con resultados conocidos.
- Inclusión de todo el último día del período.
- Consulta correcta de eventos de usuarios y del sistema.
- Detalle coherente con el listado y 404 para registros inexistentes.
- Acceso denegado para los demás roles.

## 10. Orden de ejecución y cierre

1. Preparar entorno y pruebas iniciales.
2. Implementar CU2 y CU4.
3. Aplicar los permisos acordados y completar CU6 y CU7.
4. Implementar CU20.
5. Migrar y completar CU18 antes de construir la agenda del segundo plan.
6. Verificar regresión de CU1, CU3 y CU5 y de todos los módulos existentes.
7. Integrar el acceso del directorio médico a la agenda cuando CU10 esté implementado.
8. Actualizar `docs/decisiones.md`, `docs/schema.md`, `docs/aceptacion.md` y README para reflejar únicamente resultados comprobados.

Ejecutar las pruebas PHP en la base protegida de pruebas y `npm run test:js`; comprobar además los flujos reales en navegador. No ejecutar `migrate:fresh` ni reconstrucciones del esquema sobre `Parcial2`.

### Lista de aceptación

- [x] CU2 corregido y verificado con pruebas PHP y JavaScript.
- [x] CU4 corregido y verificado.
- [x] CU6 corregido en su módulo actual y verificado.
- [ ] Integración final de CU6 con agenda: diferida porque depende de CU10, fuera de esta ejecución.
- [x] CU7 corregido y verificado.
- [x] CU18/RF05 corregido; preservación de citas existentes verificada con migración legacy.
- [x] CU20 corregido y verificado.
- [x] Regresión de funciones existentes aprobada.
- [x] Documentación y evidencias actualizadas.
- [ ] Recorridos visuales/interactivos: herramienta detenida por imposibilidad de verificar la URL actual de Brave.

### Registro de evidencia

| Caso o etapa | Comando o recorrido | Resultado | Evidencia | Fecha |
| --- | --- | --- | --- | --- |
| Baseline PHP | PHPUnit antes de corregir | 56 pruebas, 189 aserciones, aprobado | `storage/logs/phpunit-correccion-baseline.xml` | 25/10/2026 |
| Suite PHP consolidada | PHPUnit después de corregir | 128 pruebas, 501 aserciones, aprobado | `storage/logs/phpunit-correccion-all.xml` | 25/10/2026 |
| JavaScript | `node --test tests/JavaScript/*.test.mjs` | 40/40 aprobadas | `tests/JavaScript` | 25/10/2026 |
| Formato y vistas | Pint `--test` y `artisan view:cache` | Aprobados | Detalle en `docs/aceptacion.md` | 25/10/2026 |
| CU2 HTTP real | Cookies, CSRF, confirmación, cancelación y POST nativo | Aprobado; una SALIDA y replay sin duplicados | `storage/logs/cu2-http-result.json` | 25/10/2026 |
| Navegador | Control de Brave | Pendiente; la herramienta no verificó URL y detuvo UI | Limitación registrada, sin capturas ni recorridos acreditados | 25/10/2026 |
