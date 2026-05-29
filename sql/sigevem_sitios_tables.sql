-- ============================================
-- MÓDULO DE SITIOS - TABLAS COMPLETAS
-- ============================================

-- 1. TABLA PRINCIPAL: sitios
CREATE TABLE sitios (
    id SERIAL PRIMARY KEY,
    inventario_id VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo_inmueble VARCHAR(50) NOT NULL,
    zona VARCHAR(20) NOT NULL,
    
    -- Dirección
    calle VARCHAR(150),
    numero_exterior VARCHAR(20),
    colonia VARCHAR(100),
    
    -- Geolocalización
    latitud DECIMAL(10, 8),
    longitud DECIMAL(11, 8),
    
    -- Activos Tecnológicos (conteos)
    activos_computadoras INT DEFAULT 0,
    activos_servidores INT DEFAULT 0,
    activos_impresoras INT DEFAULT 0,
    activos_otros INT DEFAULT 0,
    
    -- Conectividad
    tipo_internet VARCHAR(50),
    
    -- Estados
    estado VARCHAR(20) DEFAULT 'activo',
    validacion_estado VARCHAR(20) DEFAULT 'pendiente',
    
    -- Auditoría
    usuario_registro_id INT REFERENCES usuarios(id),
    fecha_registro DATE NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para búsquedas rápidas
CREATE INDEX idx_sitios_inventario ON sitios(inventario_id);
CREATE INDEX idx_sitios_zona ON sitios(zona);
CREATE INDEX idx_sitios_estado ON sitios(estado);
CREATE INDEX idx_sitios_validacion ON sitios(validacion_estado);

-- ============================================
-- 2. TABLA: sitios_validacion (Historial de aprobaciones)
CREATE TABLE sitios_validacion (
    id SERIAL PRIMARY KEY,
    sitio_id INT REFERENCES sitios(id) ON DELETE CASCADE,
    usuario_registro_id INT REFERENCES usuarios(id),
    usuario_validacion_id INT REFERENCES usuarios(id),
    estado VARCHAR(20) NOT NULL, -- pendiente, aprobada, rechazada
    observaciones TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_validacion TIMESTAMP
);

CREATE INDEX idx_sitios_val_sitio ON sitios_validacion(sitio_id);
CREATE INDEX idx_sitios_val_estado ON sitios_validacion(estado);

-- ============================================
-- 3. TABLA: sitios_evidencia_fotografica
CREATE TABLE sitios_evidencia_fotografica (
    id SERIAL PRIMARY KEY,
    sitio_id INT REFERENCES sitios(id) ON DELETE CASCADE,
    ruta_archivo VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(100) NOT NULL,
    tipo VARCHAR(50), -- 'registro', 'mantenimiento', 'otro'
    descripcion TEXT,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_id INT REFERENCES usuarios(id)
);

CREATE INDEX idx_sitios_evid_sitio ON sitios_evidencia_fotografica(sitio_id);

-- ============================================
-- 4. TABLA: sitios_mantenimiento
CREATE TABLE sitios_mantenimiento (
    id SERIAL PRIMARY KEY,
    sitio_id INT REFERENCES sitios(id) ON DELETE CASCADE,
    tecnico_id INT REFERENCES usuarios(id),
    programado_por_id INT REFERENCES usuarios(id),
    tipo VARCHAR(20) NOT NULL, -- preventivo, correctivo, emergencia
    descripcion TEXT,
    fecha_programada DATE NOT NULL,
    fecha_limite DATE,
    estado VARCHAR(20) DEFAULT 'pendiente', -- pendiente, en_proceso, completado
    evidencia_ruta VARCHAR(255),
    fecha_inicio TIMESTAMP,
    fecha_completado TIMESTAMP,
    notificado_email BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    validado_por_id INT REFERENCES usuarios(id),
    fecha_validacion TIMESTAMP,
    observaciones TEXT,
    observaciones_rechazo TEXT
);

CREATE INDEX idx_sitios_mant_sitio ON sitios_mantenimiento(sitio_id);
CREATE INDEX idx_sitios_mant_estado ON sitios_mantenimiento(estado);
CREATE INDEX idx_sitios_mant_fecha ON sitios_mantenimiento(fecha_programada);

-- ============================================
-- DATOS DE EJEMPLO (OPCIONAL)
-- ============================================
-- INSERT INTO sitios (inventario_id, nombre, tipo_inmueble, zona, calle, numero_exterior, colonia, latitud, longitud, activos_computadoras, activos_servidores, tipo_internet, usuario_registro_id, fecha_registro)
-- VALUES 
-- ('SIT-0001', 'Presidencia Municipal', 'Administrativo', 'Centro', 'Plaza Juárez', 'S/N', 'Centro', 19.6012, -99.0597, 62, 3, 'Fibra Óptica', 1, CURRENT_DATE),
-- ('SIT-0002', 'Delegación Zona Norte', 'Administrativo', 'Norte', 'Av. Central', '#120', 'Jardines de Ecatepec', 19.6150, -99.0500, 24, 1, 'Inalámbrico', 1, CURRENT_DATE);

-- INSERT INTO sitios_validacion (sitio_id, usuario_registro_id, estado, fecha_registro)
-- VALUES (1, 1, 'aprobada', CURRENT_TIMESTAMP), (2, 1, 'pendiente', CURRENT_TIMESTAMP);