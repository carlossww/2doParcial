# Revisión de los casos de uso de Vida Salud

**Fecha:** 25 de octubre de 2026.  
**Fuente:** `C:\Users\USUARIO\Downloads\2doparcial\Vida Salud - Grupo 9 (3).pdf` (93 páginas).  
**Alcance de la revisión:** los 20 casos de uso y su priorización (pp. 19-42), el modelo general y la relación por paquetes (pp. 45 y 48), los diagramas de análisis de casos de uso (pp. 49-55) y los diagramas de secuencia (pp. 69-77). Se contrastaron con los requisitos de las pp. 4-11 y las reglas transaccionales de las pp. 83-84. Las páginas indicadas coinciden con la numeración del PDF.

## Evaluación general

El documento contiene una base organizada: identifica cinco actores, mantiene identificadores CU1-CU20 y presenta para cada caso objetivo, resumen, actores, condiciones y flujos. También distingue correctamente varias consultas de las operaciones que modifican información. CU8 y CU17 describen la selección de disponibilidad y contemplan conflictos de horario; CU18 reconoce el problema de modificar horarios asociados a citas.

Antes de considerar cerrada la especificación, es necesario corregir la correspondencia entre requisitos, actores, fichas y diagramas. Hay contradicciones funcionales, además de errores de redacción. Los hallazgos describen el documento; no prueban defectos en el código del sistema.

## Correcciones prioritarias

### 1. Completar la trazabilidad entre requisitos y casos de uso — Alta

**Evidencia:** los RF01-RF13 de las pp. 6-10 se concentran en acceso, pacientes, médicos, especialidades, horarios, citas e informes. La lista de CU y las fichas incorporan además permisos configurables, historia clínica, diagnósticos, tratamientos, laboratorio, portal del paciente y bitácora.

**Consecuencia:** varias funcionalidades detalladas carecen de un requisito funcional explícito que delimite su alcance y permita comprobarlas. A la inversa, RF10 y RF12 no tienen un flujo equivalente claramente especificado.

**Corrección:** añadir los RF correspondientes a las funcionalidades incorporadas y construir una matriz RF-CU. Si alguna función queda para otra entrega, identificarla expresamente como alcance posterior y ajustar sus prioridades y diagramas.

### 2. Especificar el control de asistencia y los estados de la cita — Alta

**Evidencia:** RF10 exige administrar los estados programada, confirmada, atendida, cancelada e inasistencia (p. 9). CU9 cubre cancelación y reprogramación, mientras CU10 es una consulta que no modifica datos. No hay un flujo explícito para confirmar asistencia, finalizar la atención o registrar inasistencia. El análisis de CU12 introduce además `EnAtencion` (p. 52), estado que no figura en RF10.

**Corrección:** incorporar un caso de uso de control de asistencia/estado, o ampliar un caso existente con esos flujos. Definir una única tabla de transiciones con estado inicial, acción, actor permitido, condición y estado final. Resolver si `EnAtencion` pertenece al modelo. No hace falta imponer un número concreto de casos de uso; sí cubrir las acciones requeridas.

### 3. Unificar las responsabilidades de los actores — Alta

**Evidencia:**

- CU6 y CU7 nombran a Recepcionista como actor e iniciador, pero sus precondiciones nombran al Administrador (pp. 28-29).
- La descripción de Administrador le asigna médicos y especialidades (p. 19). El diagrama general conecta esas funciones con Recepcionista (p. 45), mientras los diagramas de análisis y secuencia usan Administrador (pp. 50-51 y 71-72).
- La descripción de Laboratorista le permite ordenar análisis (p. 19), y el análisis de CU14 lo incluye (p. 53); la ficha CU14 y su secuencia solo incluyen Médico (pp. 36 y 75).

**Corrección:** acordar una matriz de permisos y aplicarla en todos los modelos. La asignación más consistente con la descripción inicial sería Administrador para CU6/CU7 y Médico para solicitar órdenes, pero la decisión funcional debe quedar explícita. Si ambos roles intervienen, distinguir qué operaciones puede realizar cada uno.

### 4. Resolver el acceso y las capacidades del Paciente — Alta

**Evidencia:** CU17 exige que el paciente tenga una cuenta y haya iniciado sesión (p. 39), y el diagrama general lo conecta con iniciar/cerrar sesión (p. 45). Sin embargo, CU1 y CU2 solo incluyen Personal de la Clínica y sus cuatro roles (pp. 23-24).

CU11 incluye a Paciente como actor e iniciador, pero objetivo, precondición, postcondición y flujo solo describen al Médico, con búsqueda y selección de pacientes (p. 33). Además, la descripción de Paciente promete reprogramar, cancelar y consultar sus citas (p. 19), pero CU9 solo admite Recepcionista y CU17 solo desarrolla la reserva.

**Corrección:** incluir al Paciente en CU1/CU2 mediante un actor común adecuado, como Usuario del sistema. Resolver si CU11 permite consultar la historia clínica propia; de ser así, añadir un flujo propio y limitarlo al paciente autenticado. Completar las variantes del portal para cancelar, reprogramar y consultar citas, o ajustar la descripción del actor al alcance efectivo. Historia de citas e historia clínica deben tratarse como consultas distintas.

### 5. Alinear las operaciones descritas con sus diagramas — Media

**Evidencia:** CU18 describe registrar, consultar, modificar y desactivar horarios, y contempla conflictos con citas existentes (p. 40). Su análisis usa `eliminarHorario` y su secuencia ejecuta `DELETE horario` (pp. 54 y 77).

CU14 se denomina Gestionar órdenes, pero su ficha solo desarrolla la creación; el análisis incorpora consulta y cancelación y la secuencia incorpora consulta de estado (pp. 36, 53 y 75). CU17 incorpora cancelación, reprogramación y avisos en el análisis, operaciones ausentes de su ficha (pp. 39 y 54).

**Corrección:** definir una lista de operaciones por CU y representar la misma lista en ficha, análisis y secuencia. Para CU18, conservar la desactivación descrita o documentar expresamente cuándo puede eliminarse un horario y cómo se protegen las citas relacionadas.

### 6. Completar las alternativas y la salida de cada error — Media

**Evidencia:** CU8 y CU9 enumeran condiciones de error sin describir la respuesta del sistema ni dónde continúa o termina el flujo (pp. 30-31). CU1 usa como único flujo secundario una expiración de sesión, pero no desarrolla credenciales incorrectas o cuenta inactiva (p. 23). En varios CU se mezclan consulta y modificación y se termina siempre en guardar cambios.

**Corrección:** para cada alternativa indicar el paso que la dispara, condición, respuesta, datos que se conservan y punto de retorno o terminación. Una consulta debe finalizar mostrando datos; no debe recorrer obligatoriamente los pasos de edición y guardado. Si eliminar no es una operación admitida, retirar las alternativas que solo regulan esa eliminación o definir su alcance.

### 7. Hacer explícitas las reglas de reserva y su ejecución atómica — Media

**Evidencia:** la secuencia de CU9 valida una anticipación mínima y CU17 valida límite de citas y anticipación (pp. 73 y 76), pero sus fichas no establecen esos valores ni remiten a reglas identificadas. Las pp. 83-84 sí describen duración básica de 30 minutos, pacientes y médicos activos, correspondencia médico-especialidad, bloqueo del médico y transacciones.

**Corrección:** asignar identificadores a esas reglas y referenciarlas desde CU8, CU9, CU17 y CU18. Documentar la anticipación y los límites sin inventar valores. En CU17, cuya ficha verifica disponibilidad antes de mostrar el resumen y recibir la confirmación, precisar que al guardar se vuelve a validar dentro de la operación atómica. Incorporar la recuperación ante conflicto concurrente y el resultado de un fallo, conservando los datos anteriores.

### 8. Corregir la presentación del modelo y el control de las secuencias — Media

**Evidencia:** en el diagrama general de la p. 45, los actores están dentro del rectángulo que visualmente representa el sistema y ese límite no tiene nombre. Las pp. 70-72 y 77 muestran secuencias donde, después del bloque de autorización, aparecen solicitudes posteriores fuera de la rama autorizada.

**Corrección:** nombrar el límite del sistema y situar los actores fuera de él. Simplificar las asociaciones actor-CU para reducir cruces. En las secuencias, encerrar las operaciones posteriores dentro de la rama permitida o representar la terminación tras denegar acceso. Esto aclara el modelo; no demuestra que la aplicación permita operaciones no autorizadas. Añadir `include` o `extend` únicamente cuando exista una relación funcional que lo justifique.

## Observaciones por caso de uso

| Caso | Páginas de la ficha | Observación y ajuste recomendado |
|---|---:|---|
| CU1. Iniciar sesión | 23 | Incluir Paciente. Especificar credenciales incorrectas y cuenta inactiva. Revisar la precondición de credenciales ya válidas si se pretende cubrir intentos fallidos. Cambiar la alternativa de expiración por alternativas propias de autenticación; la expiración puede documentarse como comportamiento general de sesión. |
| CU2. Cerrar sesión | 24 | Incluir Paciente. El resumen promete bitácora, pero el flujo no explicita ese registro. Añadir la cancelación de la confirmación y precisar qué sucede si el reintento por error de conexión no funciona. |
| CU3. Gestionar usuarios | 25 | Separar las rutas de consulta, alta, actualización y cambio de estado. El resumen admite activar cuentas, pero la selección de operaciones solo menciona desactivar. Alinear esa lista con los diagramas y explicitar la asignación del rol. |
| CU4. Gestionar roles y permisos | 26 | A3 regula eliminar un rol, operación que no aparece en el flujo principal. Definir si se permite eliminar, desactivar o únicamente modificar. Separar consulta y configuración y precisar cuándo se aplican los permisos actualizados. |
| CU5. Gestionar pacientes | 27 | La precondición nombra Administrador aunque el actor es Recepcionista. La postcondición habla de cuenta de usuario; debe referirse al registro del paciente. Distinguir consulta de modificación y desactivación de eliminación, conservando el historial exigido por RF02. |
| CU6. Gestionar médicos | 28 | Resolver Administrador/Recepcionista y sustituir la postcondición de cuenta de usuario por registro del médico. A3 menciona paciente donde corresponde médico. Explicitar especialidades y el efecto de desactivar sobre nuevas citas y citas ya programadas. |
| CU7. Configurar especialidades | 29 | Resolver el actor y la precondición. Alinear desactivación con la alternativa de eliminación. Referenciar la unicidad por nombre normalizado de RNF06 y precisar qué ocurre con médicos y citas vinculados. |
| CU8. Agendar cita médica | 30 | La secuencia de selección es adecuada. Completar la respuesta y el retorno de A1-A4; referenciar duración, fecha válida, entidades activas y correspondencia médico-especialidad. Especificar conflicto al confirmar, registro del evento y ausencia de cambios parciales. |
| CU9. Cancelar/Reprogramar cita | 31 | Desarrollar por separado ambas ramas y cada alternativa. Indicar que cancelar libera el horario y conserva el registro, y que reprogramar mantiene la cita anterior si falla. Incorporar al Paciente si se conserva el alcance de su descripción. Definir la anticipación que aparece en la secuencia. |
| CU10. Consultar agenda médica | 32 | La postcondición de solo lectura es clara. La ficha admite Recepcionista y Médico; los diagramas de las pp. 52 y 73 solo representan Médico. Añadir la variante de Recepción o identificar expresamente el escenario representado. Completar los filtros y campos de RF11, incluido estado. Corregir el título C10 por CU10. |
| CU11. Consultar historial clínico | 33 | Resolver la participación de Paciente y limitar cualquier consulta del portal a sus propios datos. Añadir alternativa de acceso denegado al control del paso 6. Este CU no sustituye el historial de citas de RF12. |
| CU12. Registrar diagnóstico | 34 | La precondición solo exige permiso para consultar historial; debe exigir permiso para registrar diagnósticos. La ficha debe explicar la atención/consulta a la que se asocia el diagnóstico, como ya muestran los diagramas. Completar alternativa de datos clínicos inválidos y alinear el estado EnAtencion con RF10. |
| CU13. Registrar tratamiento | 35 | Exigir autorización de registro y describir la selección del diagnóstico y su pertenencia al paciente/consulta. La alternativa de ausencia de diagnóstico es útil, pero el flujo debe mostrar cómo se busca y asocia. Alinear con la selección de diagnóstico representada en la p. 74. |
| CU14. Gestionar órdenes de laboratorio | 36 | Resolver la intervención de Laboratorista y especificar permiso de creación. Unificar si el CU comprende crear, consultar y cancelar. Nombrar el estado inicial, la consulta de origen y las condiciones de cancelación. |
| CU15. Registrar/cargar resultados | 37 | Precisar estados permitidos, resultados parciales y cuándo se completa una orden. El análisis verifica si están completos todos los análisis, pero la secuencia pasa a completada después del registro (pp. 53 y 75). Definir formatos/tamaño de adjuntos y alternativas de archivo inválido, fallo al almacenar o duplicación del resultado. |
| CU16. Consultar resultados | 38 | La ficha incluye Médico y Laboratorista, pero análisis y secuencia solo muestran Médico (pp. 54 y 76). Describir la variante del laboratorio. La secuencia restringe al médico solicitante; trasladar esa regla a la ficha o definir el criterio de autorización que realmente corresponda. |
| CU17. Reservar cita médica | 39 | Mantenerlo como variante del portal es válido aunque comparta reglas con CU8. Unificar autenticación con CU1/CU2, vincular la reserva al paciente autenticado y validar al guardar. Alinear las funciones adicionales del análisis con la ficha y definir las reglas de límite/anticipación de la secuencia. |
| CU18. Gestionar horarios | 40 | La ficha contempla conflictos con citas existentes. Corregir la discrepancia desactivar/eliminar y representar esa protección en la secuencia. RF05 exige definir duración; aclarar si es configurable o si se aplica la duración básica de 30 minutos indicada en la p. 83. |
| CU19. Generar reportes | 41 | La prioridad es Media en la p. 22 y Alta en RF13 (p. 10): unificarla. Explicitar filtros por médico, especialidad y estado, totales y exportación opcional. La existencia previa de datos no necesita ser una precondición para permitir consultar un período o una base vacíos. |
| CU20. Consultar bitácora | 42 | A1 habla de generar el reporte; debe referirse a una consulta de bitácora sin coincidencias. La existencia previa de registros puede retirarse como precondición. El análisis añade exportación (p. 55): incorporarla a la ficha si forma parte del alcance. |

## Matriz inicial de trazabilidad

La matriz identifica correspondencias documentales; no acredita implementación ni pruebas superadas.

| Requisito | Caso relacionado | Situación |
|---|---|---|
| RF01. Inicio/cierre y acceso | CU1, CU2 | Corregir cobertura de Paciente y alternativas de autenticación. |
| RF02. Pacientes | CU5 | Cobertura parcial: corregir actor/precondición y postcondición; completar consulta histórica. |
| RF03. Médicos | CU6 | Unificar responsable y datos/operaciones exigidos. |
| RF04. Especialidades | CU7 | Unificar responsable y tratamiento de registros inactivos. |
| RF05. Horarios y duración | CU18 | Resolver duración configurable/básica y desactivación/eliminación. |
| RF06. Registro de citas | CU8; CU17 como variante del portal | Hacer explícitas las diferencias del portal y las reglas comunes. |
| RF07. Prevención de superposiciones | CU8, CU9, CU17 | Referenciar reglas y garantías transaccionales de las pp. 83-84. |
| RF08. Reprogramación | CU9 | Completar rama, errores y conservación de la programación anterior. |
| RF09. Cancelación | CU9 | Explicitar liberación del horario y conservación histórica. |
| RF10. Control de asistencia | Sin flujo completo equivalente | Especificar confirmación, atención, inasistencia y transiciones. |
| RF11. Consulta de agenda | CU10 | Completar variante de Recepción, filtros y campos. |
| RF12. Historial de citas por paciente | Sin flujo específico completo | CU11 consulta historia clínica; CU5 menciona gestión general, sin desarrollar esta consulta. |
| RF13. Reportes | CU19 | Unificar prioridad y filtros/totales requeridos. |

Faltan RF explícitos para el contenido adicional de CU3, CU4, CU11-CU16, CU20 y las capacidades específicas del portal de CU17. Pueden incorporarse requisitos nuevos o ampliarse los existentes, manteniendo sus identificadores y la trazabilidad de los cambios.

## Ejemplos de redacción corregida

### CU5: condiciones

- **Precondición:** la recepcionista tiene una sesión activa y permiso para realizar la operación solicitada sobre pacientes.
- **Postcondición de modificación:** el registro del paciente queda creado, actualizado o desactivado según la operación confirmada. La desactivación conserva las citas y los registros asociados.
- **Postcondición de consulta:** se muestran los pacientes o el detalle solicitado y no se modifica información.

### CU8: alternativa de horario ocupado

**A3, desde la validación final de disponibilidad:** si el horario fue ocupado por otra solicitud, el sistema rechaza la creación y no guarda una cita parcial. Conserva la selección del paciente y del médico, actualiza los horarios disponibles e informa el conflicto. El flujo vuelve a la selección de fecha y hora.

### CU18: operación sobre un horario con citas

Antes de modificar o desactivar el horario, el sistema identifica las citas que quedarían fuera de la disponibilidad resultante. Si existen citas afectadas que no se han gestionado, informa el conflicto y conserva el horario anterior. Tras resolverlo, permite repetir la operación. La secuencia debe representar esta condición y el cambio de estado del horario de forma consistente con la ficha.

## Orden recomendado para la corrección

1. Acordar alcance, matriz de actores/permisos y catálogo de estados.
2. Completar RF-CU, especialmente RF10, RF12 y las funciones clínicas/del portal.
3. Corregir las 20 fichas, separando rutas de consulta y modificación y completando alternativas.
4. Trasladar las reglas comunes de agenda a referencias identificadas y usarlas en las fichas.
5. Actualizar el modelo general, los diagramas de análisis y las secuencias para que representen las mismas decisiones.
6. Derivar criterios de aceptación: acceso por cada rol, consulta propia del paciente, transiciones válidas e inválidas, dos reservas simultáneas de una franja, reprogramación fallida sin pérdida de la cita y desactivación de horario con citas pendientes.

La revisión no modificó el PDF original.
