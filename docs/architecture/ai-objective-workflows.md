# Contrato de objetivos y flujos de IA

Este documento describe el contrato compartido de los flujos compuestos. Los propietarios siguen siendo `AIOrchestrator`, `ToolRegistry`, `ToolExecutor`, `AiObjectiveLifecycle`, `ObjectiveValidator` y los jobs existentes. Laravel no interpreta lenguaje natural ni introduce un segundo planificador.

## Flujo

```text
Chat -> mensaje y AiRun persistidos -> AIOrchestrator
  -> modelo descubre capacidades y declara objectives.define
  -> objetivo conserva resultados solicitados y hechos corregidos
  -> modelo solicita aclaraciones o propone execution_plans.create
  -> backend valida referencias, permisos, cobertura y verificaciones
  -> preview + confirmación explícita de la revisión
  -> ExecuteAiExecutionPlan ejecuta las herramientas registradas por bloques
  -> lecturas registradas + aserciones sobre resultados reales
  -> ObjectiveValidator verifica el objetivo completo
  -> modelo recibe evidencia y pendientes: continúa, repara o responde
  -> cierre persistido condicionado al resultado del validador
```

Una petición aislada de una escritura conserva su herramienta y preview habituales. Una operación que pertenece a un objetivo compuesto usa un plan, aunque sea la única que quede pendiente. Las lecturas ordinarias no necesitan declarar un objetivo.

## Alcance duradero

`objectives.define` registra `expected_results` con claves estables y `required_facts` con estado `resolved`, `missing` o `ambiguous`. El servidor atribuye las correcciones al mensaje de usuario del turno. Los resultados omitidos en una llamada posterior permanecen pendientes; una omisión no cancela trabajo. La cancelación explícita usa `objectives.cancel`, preservando escrituras ya realizadas.

Los planes pueden cubrir subconjuntos del alcance. No pueden reemplazarlo por su propia lista de pasos. `unplanned_results`, los hechos corregidos y las identidades de resultados completados permanecen en el contexto operativo, incluso tras su compactación. El modelo debe reutilizar esos IDs.

## Contrato de un plan

- Entre 1 y 50 escrituras registradas; bloques de hasta 10 y hasta 10 lecturas de verificación por plan. Un alcance mayor se implementa mediante varios planes del mismo objetivo.
- `step_key` identifica una operación; `action_key` identifica una capacidad. No son intercambiables. `verification_rules.operation_key` referencia una clave de operación existente.
- Cada escritura declara `covers_result_keys` del alcance. Cada resultado cubierto exige al menos una lectura con aserciones.
- `input` usa el contrato público de la herramienta. `{"$from":"crear_cliente.id"}` consume evidencia de una escritura anterior; Laravel deriva las dependencias. `after` expresa solo secuenciación. No se reconstruyen argumentos a partir del texto del usuario.
- Las lecturas de `completion_steps` declaran `covers_result_keys` y `assertions`. Cada aserción tiene `path` (segmentos string/entero), `operator` y `value`. Los operadores son `exists`, `equals`, `count_equals` y `contains`; el valor puede referenciar una escritura con `$from`. Las referencias de aserciones también generan dependencias.
- Las aserciones se evalúan sobre `result_ref_json`, no sobre texto del asistente ni sobre el estado HTTP. Por ejemplo, `path: ["items", "*", "id"]`, `operator: "contains"`, `value: {"$from":"crear_tarea.id"}` comprueba identidad. Para responsables o vínculos se comprueba también el campo correspondiente mediante una lectura que lo exponga.
- El digest de aprobación incluye los pasos de verificación. Una revisión con argumentos o comprobaciones modificados exige una nueva confirmación.

## Reparación y cierre

`execution_plans.revise` modifica solo escrituras fallidas/no resueltas del plan existente. Un error derivado `DEPENDENCY_UNAVAILABLE` vuelve a espera cuando se revisa el plan, para que la reparación del antecesor permita continuar. Las validaciones reales del paso siguen aplicándose.

También admite `completion_steps` para corregir lecturas fallidas por su clave existente, sin eliminar su cobertura. `items` puede ser vacío en una reparación de verificaciones. Las escrituras completadas y las comprobaciones aprobadas son inmutables. `execution_plans.latest` expone el estado real de cada comprobación.

Que las escrituras de un plan terminen no significa que el objetivo esté completo. El cierre exige operaciones requeridas completadas con evidencia, resultados del alcance cubiertos, hechos resueltos, verificaciones aprobadas y confirmación coherente. Si el modelo entrega solo texto antes de cumplirlo, recibe hasta dos oportunidades adicionales de continuación dentro del presupuesto del turno. Al agotarlas, el mensaje y el AiRun conservan un estado incompleto; no se publica éxito global como respuesta persistida.

## Recuperación del proveedor

`ProcessChatMessage` y `ContinueConfirmedConversation` usan el mismo lock compartido por conversación. Ante timeout, conversación bloqueada o protocolo incierto, no se repite inmediatamente la llamada remota. Se persiste `provider_recovery`, se difiere el job y, transcurrido `AI_CONVERSATION_RECOVERY_DELAY_SECONDS` (45 por defecto, mínimo 30), se crea una conversación remota nueva con el estado local y la evidencia confirmada.

La respuesta remota desconocida no se entrega al executor. Se descartan los outputs pendientes del protocolo anterior al rehidratarlo, no los resultados del dominio. Los reintentos son limitados por el job; una ejecución completada o cancelada no se reabre por un marcador de recuperación. Los errores públicos conservan mensajes seguros y referencias de diagnóstico.

## Integrar un módulo nuevo

1. Reutilizar sus actions/services y validadores. Registrar la capacidad y su schema en el registro existente, junto a permisos, clasificación de lectura/escritura y presentación remota. Mantener descubrimiento, aliases y catálogo coherentes.
2. Conectar el handler al executor existente. Toda escritura debe soportar el mecanismo compartido de preview/confirmación, transacción, idempotencia, auditoría y aislamiento por `workspace_id`. Una operación externa incierta necesita su reconciliación específica antes de poder anunciarse como reintentable.
3. Devolver un `result_ref_json` estable con identidades reales. Los batches deben ofrecer resultados por clave estable para referenciar cada elemento, no depender del orden de una lista mutable.
4. Exponer lecturas que permitan comprobar las promesas del módulo: identidades, relaciones, asignaciones, cantidades o estados. Describir la forma de esa evidencia en su contrato para que el modelo construya aserciones correctas.
5. Verificar selección/schema, permisos y tenant, preview/confirmación, dependencias, rechazo de evidencia incorrecta y recuperación sin duplicados. Incluir al menos un flujo que combine el módulo con otro. No añadir condiciones por frases del usuario en el orquestador.

## Límites y operación

El backend comprueba el alcance declarado y la evidencia estructurada. La extracción semántica completa del mensaje y la pertinencia de las aserciones siguen dependiendo del modelo; requieren evaluaciones con proveedor real. Una suite con proveedor simulado no demuestra que un modelo interpretará siempre correctamente un pedido complejo.

Los objetivos históricos conservan sus registros; este cambio no infiere retrospectivamente resultados omitidos ni ejecuta de nuevo una conversación antigua. Al retomarlos, el modelo debe declarar el alcance completo a partir del contexto y revisar los pendientes.

No hay migración de esquema. La API local y los workers basados en imágenes requieren reconstrucción/reinicio para cargar el código; no basta con editar el checkout. La cola necesita un worker activo. No se deben reejecutar indiscriminadamente jobs o planes históricos para validar el despliegue.

Regresiones principales: `AiObjectiveDurabilityTest`, `ObjectiveWorkflowIntegrationTest`, `AiFirstOrchestrationBoundaryTest`, `AiRunDurableRuntimeTest`, `GlobalExecutionPlanTest`, `RecipeExecutionPlanTest` y `ResultAssertionsTest`. Ejecutar desde la raíz `docker compose run --rm api-test 'php vendor/bin/phpunit'` en el entorno aislado de pruebas.
