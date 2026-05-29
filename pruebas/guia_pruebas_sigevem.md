# GUÍA DE PRUEBAS FUNCIONALES - SIGEVEM
## Sistema Integral de Gestión y Geolocalización de Infraestructura de Videovigilancia Municipal

---

## PREPARACIÓN PREVIA

Antes de iniciar las pruebas asegúrate de tener:
- [ ] Un usuario Administrador activo
- [ ] Un usuario Supervisor activo
- [ ] Un usuario Técnico activo
- [ ] Base de datos limpia (sin datos basura)
- [ ] Navegador con F12 disponible para detectar errores

**Convención:** ✅ Resultado esperado | ❌ Si esto pasa, hay un bug

---

## MÓDULO 0 — AUTENTICACIÓN

### Prueba 0.1 — Acceso directo a URL sin sesión
1. Sin iniciar sesión, ve a `localhost:8080/sigevem`
- ✅ Redirige automáticamente a `login.php`

### Prueba 0.2 — Acceso directo a módulo protegido sin sesión
1. Sin iniciar sesión, ve a `localhost:8080/sigevem/modules/dashboard/index.php`
- ✅ Redirige al login

### Prueba 0.3 — Login con credenciales incorrectas
1. Ingresa un email válido con contraseña incorrecta
- ✅ Muestra mensaje de error "Correo o contraseña incorrectos"
- ✅ No entra al sistema
- ✅ Se registra en bitácora como `login_fallido`

### Prueba 0.4 — Login con email inexistente
1. Ingresa un email que no existe en el sistema
- ✅ Muestra mensaje de error
- ✅ No revela si el email existe o no

### Prueba 0.5 — Login exitoso como Administrador
1. Ingresa credenciales correctas del Administrador
- ✅ Redirige al dashboard de Administrador
- ✅ Muestra "Rol: Administrador" en el header
- ✅ Sidebar muestra todos los módulos
- ✅ Se registra en bitácora como `login`

### Prueba 0.6 — Login exitoso como Supervisor
1. Ingresa credenciales del Supervisor
- ✅ Redirige al dashboard de Supervisor
- ✅ Sidebar NO muestra "Usuarios" como opción editable
- ✅ Se registra en bitácora

### Prueba 0.7 — Login exitoso como Técnico
1. Ingresa credenciales del Técnico
- ✅ Redirige al dashboard de Técnico
- ✅ Sidebar muestra solo: Dashboard, Mapa, Cámaras, Mantenimiento, Bitácora
- ✅ NO muestra Reportes ni Usuarios
- ✅ Se registra en bitácora

### Prueba 0.8 — Logout
1. Desde cualquier rol, haz clic en "Cerrar Sesión"
- ✅ Redirige al login
- ✅ Si intentas ir atrás con el navegador, redirige al login
- ✅ Se registra en bitácora como `logout`


## MÓDULO 1 — DASHBOARD

### Prueba 1.1 — Dashboard Administrador
1. Inicia sesión como Administrador
- ✅ KPIs muestran datos reales (Total, Activas, Mantenimiento, Fuera de Servicio)
- ✅ "Cámaras Pendientes de Validación" muestra datos reales o mensaje vacío
- ✅ "Actividad Reciente" muestra registros de bitácora
- ✅ Los 4 botones de Acciones Rápidas funcionan y enrutan correctamente
- ✅ La fecha y hora es correcta (hora de México)

### Prueba 1.2 — Dashboard Supervisor
1. Inicia sesión como Supervisor
- ✅ KPIs muestran datos reales
- ✅ El % de operatividad es calculado (no hardcodeado)
- ✅ "Mis Reportes" muestra el contador real
- ✅ Actividad reciente desde bitácora

### Prueba 1.3 — Dashboard Técnico
1. Inicia sesión como Técnico
- ✅ KPIs muestran SOLO sus tareas (no las de otros técnicos)
- ✅ "Mis Tareas Asignadas" muestra solo sus tareas
- ✅ "Mis Cámaras Registradas" muestra solo las que él registró
- ✅ NO hay botones de acción en las tarjetas de tareas

---

## MÓDULO 2 — CÁMARAS

### Prueba 2.1 — Registro de cámara (como Técnico)
1. Inicia sesión como Técnico
2. Ve a Cámaras → Registrar
3. Llena todos los campos requeridos con datos reales
4. Selecciona ubicación en el mapa
5. Sube una imagen (opcional)
6. Guarda
- ✅ Cámara registrada con estatus "Pendiente"
- ✅ Aparece en la lista de cámaras
- ✅ Se registra en bitácora como `registrar`
- ✅ Aparece en "Cámaras Pendientes de Validación" del dashboard Admin

### Prueba 2.2 — Registro con campos faltantes
1. Intenta registrar sin llenar campos obligatorios
- ✅ Muestra errores de validación
- ✅ No guarda el registro

### Prueba 2.3 — Registro con ID duplicado
1. Intenta registrar con un ID de cámara ya existente
- ✅ Muestra error "El ID ya existe"

### Prueba 2.4 — Validar cámara — Aprobar (como Administrador)
1. Inicia sesión como Administrador
2. Ve a la cámara recién registrada
3. Aprueba la cámara
- ✅ Estatus cambia a "Activa"
- ✅ Se registra en bitácora como `aprobar`
- ✅ Desaparece de "Pendientes de Validación"

### Prueba 2.5 — Validar cámara — Rechazar
1. Registra otra cámara como Técnico
2. Como Administrador, rechaza con una observación
- ✅ Cámara permanece en estado "Pendiente"
- ✅ Se registra en bitácora como `rechazar` con el motivo
- ✅ El motivo de rechazo queda guardado

### Prueba 2.6 — Editar cámara (como Administrador/Supervisor)
1. Edita datos de una cámara existente
- ✅ Los cambios se guardan correctamente
- ✅ Se registra en bitácora como `editar`

### Prueba 2.7 — Técnico NO puede editar cámaras de otros
1. Inicia sesión como Técnico
2. Intenta acceder a editar una cámara que no registró él
- ✅ Redirige al dashboard o muestra error de permisos

### Prueba 2.8 — Eliminar cámara (como Administrador)
1. Registra una cámara de prueba
2. Elimínala
- ✅ Desaparece de la lista
- ✅ Se registra en bitácora como `eliminar`

---

## MÓDULO 3 — MAPA

### Prueba 3.1 — Visualización del mapa
1. Ve al módulo Mapa
- ✅ El mapa carga correctamente centrado en Ecatepec
- ✅ Los marcadores de cámaras aparecen en sus coordenadas
- ✅ Cada color de marcador corresponde al estatus correcto

### Prueba 3.2 — Panel de información al hacer clic
1. Haz clic en cualquier marcador del mapa
- ✅ Panel lateral se desliza desde la derecha
- ✅ Muestra: ID, marca, modelo, tipo, zona, dirección, fecha, coordenadas
- ✅ Muestra imagen de la cámara o placeholder si no tiene
- ✅ Botones "Ver Detalle", "Programar Mantenimiento", "Editar Cámara" funcionan

### Prueba 3.3 — Filtros del mapa
1. Filtra por zona
- ✅ Solo muestra cámaras de esa zona
2. Filtra por estado
- ✅ Solo muestra cámaras con ese estatus
3. Limpia filtros
- ✅ Vuelven a aparecer todas las cámaras

### Prueba 3.4 — Tipos de mapa
1. Cambia entre Mapa, Satélite e Híbrido
- ✅ El mapa cambia correctamente sin perder los marcadores

---

## MÓDULO 4 — MANTENIMIENTO

### Prueba 4.1 — Programar mantenimiento (como Administrador/Supervisor)
1. Ve a Mantenimiento → Programar
2. Selecciona cámara, técnico, tipo, fechas y descripción
3. Guarda
- ✅ Tarea creada con estado "Pendiente"
- ✅ Aparece en la lista con ID MNT-XXX
- ✅ Se registra en bitácora como `programar`
- ✅ Técnico ve la tarea en su dashboard

### Prueba 4.2 — Técnico NO puede programar
1. Inicia sesión como Técnico
- ✅ El botón "Programar Mantenimiento" NO aparece

### Prueba 4.3 — Iniciar tarea (como Técnico)
1. Inicia sesión como Técnico
2. Ve a Mantenimiento
3. Confirma que solo ve SUS tareas
4. Inicia una tarea pendiente
- ✅ Estado cambia a "En Proceso"
- ✅ Se registra en bitácora como `iniciar`

### Prueba 4.4 — Completar tarea con evidencia
1. Con la tarea en proceso, intenta completar SIN subir foto
- ✅ Muestra error "Debes subir al menos una foto"
2. Completa con foto y observaciones
- ✅ Estado cambia a "Completado"
- ✅ Cámara cambia a estatus "Mantenimiento"
- ✅ Se registra en bitácora como `completar`

### Prueba 4.5 — Técnico NO puede ejecutar tareas de otros
1. Como Técnico, intenta ejecutar una tarea asignada a otro técnico
- ✅ Muestra error de permisos

### Prueba 4.6 — Validar tarea (como Administrador)
1. Con tarea en "Completado", valida como Administrador
- ✅ Estado cambia a "Validado"
- ✅ Cámara regresa a "Activa"
- ✅ Se registra en bitácora como `validar`

### Prueba 4.7 — Rechazar validación
1. Con tarea en "Completado", rechaza con motivo
- ✅ Estado regresa a "En Proceso"
- ✅ Se registra en bitácora como `rechazar`
- ✅ El motivo queda guardado

### Prueba 4.8 — Cancelar tarea (como Administrador)
1. Cancela una tarea pendiente o en proceso
- ✅ Estado cambia a "Cancelado"
- ✅ El botón cancelar NO aparece en tareas completadas o validadas
- ✅ Se registra en bitácora como `cancelar`

---

## MÓDULO 5 — REPORTES

### Prueba 5.1 — Acceso por rol
1. Inicia sesión como Técnico e intenta acceder a Reportes
- ✅ Redirige al dashboard (sin acceso)
2. Como Administrador/Supervisor, accede normalmente
- ✅ Carga correctamente con gráficas y tarjetas

### Prueba 5.2 — Gráficas
1. Verifica las dos gráficas
- ✅ "Cámaras por Zona" muestra barras con datos reales
- ✅ "Estado de Cámaras" muestra la dona con colores correctos

### Prueba 5.3 — Generar reporte desde tarjeta
1. Haz clic en cualquier tarjeta de tipo de reporte
- ✅ Abre el modal con ese tipo preseleccionado

### Prueba 5.4 — Generar cada tipo de reporte
Prueba los 6 tipos uno por uno:
- [ ] Cámaras por Zona
- [ ] Inventario Completo
- [ ] Mantenimientos por Técnico
- [ ] Estadísticas de Operatividad
- [ ] Tiempos de Respuesta
- [ ] Validaciones Pendientes

Para cada uno:
- ✅ Descarga el archivo CSV
- ✅ El archivo abre correctamente en Excel con acentos
- ✅ Tiene cabecera, datos y total de registros
- ✅ Se registra en bitácora como `exportar`

### Prueba 5.5 — Reporte con filtro de fechas
1. Genera un reporte con rango de fechas
- ✅ Solo incluye registros del período seleccionado

### Prueba 5.6 — Reportes Generados
1. Genera 2-3 reportes
2. Revisa la sección "Reportes Generados"
- ✅ Aparecen los reportes generados con fecha y usuario

---

## MÓDULO 6 — BITÁCORA

### Prueba 6.1 — Visualización general
1. Como Administrador, ve a Bitácora
- ✅ Muestra todos los registros de todos los usuarios
- ✅ Paginación de 15 registros por página funciona
- ✅ Los badges de colores corresponden al tipo de acción

### Prueba 6.2 — Técnico solo ve sus acciones
1. Como Técnico, ve a Bitácora
- ✅ Solo aparecen sus propias acciones
- ✅ NO aparece el checkbox "Mostrar solo mis acciones"

### Prueba 6.3 — Filtro por acción
1. Filtra por "login"
- ✅ Solo muestra registros de login

### Prueba 6.4 — Filtro por módulo
1. Filtra por "camaras"
- ✅ Solo muestra acciones del módulo de cámaras

### Prueba 6.5 — Filtro por fecha
1. Filtra por rango de fechas
- ✅ Solo muestra registros del período

### Prueba 6.6 — Buscador
1. Busca por nombre de usuario
- ✅ Filtra correctamente

### Prueba 6.7 — Modal de detalle
1. Haz clic en cualquier fila
- ✅ Abre modal con información completa del registro
- ✅ Se cierra con X, Escape o clic fuera

### Prueba 6.8 — Exportar CSV
1. Aplica algún filtro y exporta
- ✅ Descarga CSV con los registros filtrados
- ✅ Abre correctamente en Excel

### Prueba 6.9 — Checkbox "Mostrar solo mis acciones"
1. Como Administrador, activa el checkbox
- ✅ Solo muestra las acciones del administrador logueado

---

## MÓDULO 7 — USUARIOS

### Prueba 7.1 — Acceso por rol
1. Como Supervisor, intenta acceder a Usuarios
- ✅ Solo puede ver, no crear ni editar
2. Como Técnico, intenta acceder
- ✅ Redirige al dashboard

### Prueba 7.2 — Crear usuario
1. Como Administrador, crea un nuevo usuario con cada rol
- ✅ Usuario aparece en la lista
- ✅ Puede iniciar sesión con las credenciales creadas
- ✅ Se registra en bitácora como `crear`

### Prueba 7.3 — Validación al crear
1. Intenta crear con email duplicado
- ✅ Muestra error "El correo ya está registrado"
2. Intenta con contraseñas que no coinciden
- ✅ Muestra error de validación

### Prueba 7.4 — Editar usuario
1. Edita nombre y rol de un usuario
- ✅ Cambios guardados correctamente
- ✅ Se registra en bitácora con los campos modificados

### Prueba 7.5 — Desactivar/Activar usuario
1. Desactiva un usuario activo
- ✅ Estado cambia a "Inactivo"
- ✅ El usuario desactivado NO puede iniciar sesión
- ✅ Se registra en bitácora como `desactivar`
2. Reactiva el usuario
- ✅ Puede volver a iniciar sesión
- ✅ Se registra como `activar`

### Prueba 7.6 — Resetear contraseña
1. Resetea la contraseña de un usuario
- ✅ Muestra la nueva contraseña temporal
- ✅ El usuario puede iniciar sesión con la nueva contraseña
- ✅ Se registra en bitácora como `resetear`

### Prueba 7.7 — Eliminar usuario nuevo
1. Crea un usuario de prueba sin hacer nada con él
2. Elimínalo
- ✅ Desaparece de la lista
- ✅ Se registra en bitácora como `eliminar`

### Prueba 7.8 — Eliminar usuario con historial
1. Intenta eliminar un usuario que tiene registros
- ✅ Muestra mensaje indicando que tiene registros asociados
- ✅ Sugiere usar Desactivar en su lugar

### Prueba 7.9 — No puede auto-eliminarse
1. Intenta eliminar el usuario con el que estás logueado
- ✅ Muestra error de protección

---

## PRUEBAS DE FLUJO COMPLETO

### Flujo A — Registro y activación de cámara
1. Técnico registra cámara → 2. Admin la aprueba → 3. Cámara aparece activa en mapa
- ✅ Todo el flujo funciona sin errores
- ✅ Bitácora registra cada paso

### Flujo B — Mantenimiento completo
1. Admin programa mantenimiento → 2. Técnico lo inicia → 3. Técnico lo completa con foto → 4. Admin valida → 5. Cámara regresa a activa
- ✅ Estados cambian correctamente en cada paso
- ✅ Bitácora registra cada acción

### Flujo C — Reporte después de actividad
1. Realiza varias acciones en el sistema
2. Genera reporte de Mantenimientos por Técnico
- ✅ El reporte refleja la actividad realizada

---

## CHECKLIST FINAL

- [ ] Todos los módulos cargan sin errores de PHP
- [ ] La hora en todos los registros es correcta (hora México)
- [ ] Los 3 roles tienen acceso solo a lo que deben
- [ ] La bitácora registra todas las acciones importantes
- [ ] Los CSV exportados abren correctamente en Excel
- [ ] El mapa muestra las cámaras en sus coordenadas reales
- [ ] No hay datos hardcodeados visibles en ningún módulo
- [ ] Los mensajes de error son claros y útiles

---

*SIGEVEM v1.0 — Gobierno Municipal de Ecatepec*
*Guía de Pruebas Funcionales*
