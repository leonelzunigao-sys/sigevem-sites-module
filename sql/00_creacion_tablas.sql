-- ============================================
-- SIGEVEM - BASE DE DATOS (8 TABLAS)
-- ORDEN CORRECTO DE CREACIÓN
-- ============================================

-- 1. ROLES (sin dependencias)
CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. USUARIOS (depende de roles)
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nombre_completo VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol_id INTEGER REFERENCES roles(id),
    estatus VARCHAR(20) DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_ultimo_acceso TIMESTAMP,
    ultimo_ip VARCHAR(45)
);

-- 3. PERMISOS POR ROL (depende de roles)
CREATE TABLE permisos_rol (
    id SERIAL PRIMARY KEY,
    rol_id INTEGER REFERENCES roles(id),
    modulo VARCHAR(50) NOT NULL,
    permiso VARCHAR(20) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    UNIQUE(rol_id, modulo, permiso)
);

-- 4. CÁMARAS (depende de usuarios)
CREATE TABLE camaras (
    id SERIAL PRIMARY KEY,
    inventario_id VARCHAR(50) UNIQUE,
    numero_camara VARCHAR(50) UNIQUE NOT NULL,
    marca VARCHAR(50),
    modelo VARCHAR(100),
    numero_serie VARCHAR(100),
    tipo_camara VARCHAR(50),
    fecha_instalacion DATE,
    estatus VARCHAR(20) DEFAULT 'pendiente',
    direccion TEXT,
    colonia VARCHAR(100),
    zona VARCHAR(50),
    sector VARCHAR(50),
    area_responsable VARCHAR(100),
    latitud DECIMAL(10, 8) NOT NULL,
    longitud DECIMAL(11, 8) NOT NULL,
    referencias TEXT,
    usuario_registro_id INTEGER REFERENCES usuarios(id),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP
);

-- 5. VALIDACIÓN DE CÁMARAS (depende de camaras y usuarios)
CREATE TABLE camaras_validacion (
    id SERIAL PRIMARY KEY,
    camara_id INTEGER REFERENCES camaras(id),
    usuario_registro_id INTEGER REFERENCES usuarios(id),
    usuario_validacion_id INTEGER REFERENCES usuarios(id),
    estado VARCHAR(20) DEFAULT 'pendiente',
    observaciones_rechazo TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_validacion TIMESTAMP,
    fecha_activacion TIMESTAMP
);

-- 6. MANTENIMIENTO TAREAS (depende de camaras y usuarios)
-- ¡ESTA DEBE IR ANTES QUE evidencia_fotografica!
CREATE TABLE mantenimiento_tareas (
    id SERIAL PRIMARY KEY,
    camara_id INTEGER REFERENCES camaras(id),
    tecnico_id INTEGER REFERENCES usuarios(id),
    programado_por_id INTEGER REFERENCES usuarios(id),
    tipo VARCHAR(20) NOT NULL,
    descripcion TEXT,
    fecha_programada DATE,
    fecha_limite DATE,
    estado VARCHAR(20) DEFAULT 'pendiente',
    evidencia_ruta VARCHAR(255),
    fecha_inicio TIMESTAMP,
    fecha_completado TIMESTAMP,
    notificado_email BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. EVIDENCIA FOTOGRÁFICA (depende de camaras, mantenimiento_tareas y usuarios)
-- ¡AHORA SÍ PUEDE REFERENCIAR A mantenimiento_tareas!
CREATE TABLE evidencia_fotografica (
    id SERIAL PRIMARY KEY,
    camara_id INTEGER REFERENCES camaras(id),
    tarea_mantenimiento_id INTEGER REFERENCES mantenimiento_tareas(id),
    ruta_archivo VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255),
    tipo VARCHAR(50),
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_id INTEGER REFERENCES usuarios(id)
);

-- 8. BITÁCORA DE SISTEMA (depende de usuarios)
CREATE TABLE bitacora_sistema (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER REFERENCES usuarios(id),
    accion VARCHAR(50),
    modulo VARCHAR(50),
    registro_id INTEGER,
    detalles JSONB,
    ip_origen VARCHAR(45),
    user_agent TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);