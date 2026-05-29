<?php
session_start();

// Solo Admin y Supervisor pueden registrar sitios
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol_id'] != 1 && $_SESSION['rol_id'] != 2)) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Sitio | SIGEVEM</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/mapa.css">
    
    <style>
    #mapPicker {
        height: 350px;
        border-radius: 8px;
        border: 2px solid var(--gray-300);
        margin-bottom: 15px;
    }
    .coord-info {
        background: var(--bg-light);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .coord-row {
        display: flex;
        gap: 15px;
        margin-bottom: 10px;
    }
    .coord-row:last-child {
        margin-bottom: 0;
    }
    .coord-label {
        font-weight: 600;
        color: var(--primary);
        min-width: 100px;
    }
    .coord-value {
        flex: 1;
        font-family: monospace;
        padding: 8px;
        background: white;
        border-radius: 4px;
    }
    .btn-clear-coords {
        margin-top: 10px;
    }
    .activos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    .activo-card {
        background: var(--bg-light);
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid var(--primary);
    }
    .activo-card h4 {
        margin: 0 0 10px 0;
        color: var(--primary);
        font-size: 14px;
    }
    .activo-input {
        width: 100%;
        padding: 8px;
        border: 1px solid var(--gray-300);
        border-radius: 4px;
        text-align: center;
        font-size: 16px;
    }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="dashboard-header">
        <div class="header-left">
            <img src="../../assets/img/logo-ecatepec-largo.png" alt="SIGEVEM" class="header-logo">
        </div>
        <div class="header-center">
            <h2 class="system-title">SIGEVEM</h2>
            <p class="system-subtitle">Sistema Integral de Gestión y Geolocalización de Infraestructura de Videovigilancia Municipal</p>
        </div>
        <div class="header-right">
            <div class="user-badge">
                <span class="badge-datetime" id="headerDatetime"></span>
                <span class="badge-role">Rol: <?php echo htmlspecialchars($_SESSION['rol_nombre']); ?></span>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="page-header">
            <h1 class="page-title">Registrar Nuevo Sitio</h1>
            <p class="page-subtitle">Complete el formulario para registrar un nuevo sitio tecnológico en el sistema</p>
        </div>

        <form action="registro_process.php" method="POST" enctype="multipart/form-data" id="registroForm">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Información del Sitio</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="inventario_id">ID de Sitio *</label>
                            <input type="text" class="form-control" id="inventario_id" name="inventario_id" 
                                   placeholder="SIT-0001" required>
                            <small class="form-text">Formato: SIT-XXXX (4 dígitos)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre">Nombre del Sitio *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   placeholder="Delegación Zona Norte" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_inmueble">Tipo de Inmueble *</label>
                            <select class="form-control" id="tipo_inmueble" name="tipo_inmueble" required>
                                <option value="">Seleccionar tipo</option>
                                <option value="Administrativo">Administrativo</option>
                                <option value="Educativo">Educativo</option>
                                <option value="Salud">Salud</option>
                                <option value="Seguridad / C5">Seguridad / C5</option>
                                <option value="Servicios Públicos">Servicios Públicos</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="zona">Zona / Sector *</label>
                            <select class="form-control" id="zona" name="zona" required>
                                <option value="">Seleccionar zona</option>
                                <option value="Norte">Norte</option>
                                <option value="Sur">Sur</option>
                                <option value="Centro">Centro</option>
                                <option value="Oriente">Oriente</option>
                                <option value="Poniente">Poniente</option>
                            </select>
                        </div>
                    </div>

                    <!-- SECCIÓN DE DIRECCIÓN CON GEOCODIFICACIÓN -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label for="calle">Calle / Avenida *</label>
                            <input type="text" class="form-control" id="calle" name="calle" 
                                   placeholder="Ej: Av. Insurgentes" required>
                            <small class="form-text" id="calle-status">
                                <i class="fas fa-map-marker-alt"></i> Se completa automáticamente al seleccionar en el mapa
                            </small>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label for="numero_exterior">Número Exterior *</label>
                            <input type="text" class="form-control" id="numero_exterior" name="numero_exterior" 
                                   placeholder="Ej: #450" required>
                            <small class="form-text">
                                <i class="fas fa-keyboard"></i> Capturar manualmente
                            </small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="colonia">Colonia / Barrio</label>
                            <input type="text" class="form-control" id="colonia" name="colonia" 
                                   placeholder="Centro Histórico">
                        </div>
                    </div>

                    <!-- MAP PICKER -->
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Ubicación en Mapa *</label>
                        <div id="mapPicker"></div>
                        <small class="form-text">Haz clic en el mapa para seleccionar la ubicación del sitio</small>
                    </div>

                    <!-- COORDENADAS -->
                    <div class="coord-info">
                        <div class="coord-row">
                            <span class="coord-label"><i class="fas fa-location-crosshairs"></i> Latitud:</span>
                            <div class="coord-value" id="latDisplay">Sin seleccionar</div>
                            <input type="hidden" id="latitud" name="latitud" required>
                        </div>
                        <div class="coord-row">
                            <span class="coord-label"><i class="fas fa-location-crosshairs"></i> Longitud:</span>
                            <div class="coord-value" id="lonDisplay">Sin seleccionar</div>
                            <input type="hidden" id="longitud" name="longitud" required>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-clear-coords" onclick="clearCoords()">
                            <i class="fas fa-trash"></i> Eliminar selección
                        </button>
                    </div>

                    <!-- ACTIVOS TECNOLÓGICOS -->
                    <div class="form-group">
                        <label><i class="fas fa-laptop"></i> Activos Tecnológicos (Conteo)</label>
                        <div class="activos-grid">
                            <div class="activo-card">
                                <h4><i class="fas fa-desktop"></i> Computadoras</h4>
                                <input type="number" class="activo-input" id="activos_computadoras" name="activos_computadoras" 
                                       value="0" min="0">
                            </div>
                            <div class="activo-card">
                                <h4><i class="fas fa-server"></i> Servidores</h4>
                                <input type="number" class="activo-input" id="activos_servidores" name="activos_servidores" 
                                       value="0" min="0">
                            </div>
                            <div class="activo-card">
                                <h4><i class="fas fa-print"></i> Impresoras</h4>
                                <input type="number" class="activo-input" id="activos_impresoras" name="activos_impresoras" 
                                       value="0" min="0">
                            </div>
                            <div class="activo-card">
                                <h4><i class="fas fa-box"></i> Otros</h4>
                                <input type="number" class="activo-input" id="activos_otros" name="activos_otros" 
                                       value="0" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_internet">Tipo de Conectividad</label>
                            <select class="form-control" id="tipo_internet" name="tipo_internet">
                                <option value="">Seleccionar tipo</option>
                                <option value="Fibra Óptica">Fibra Óptica</option>
                                <option value="Inalámbrico">Inalámbrico</option>
                                <option value="Cable">Cable</option>
                                <option value="Satelital">Satelital</option>
                                <option value="Sin conexión">Sin conexión</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_registro">Fecha de Registro *</label>
                            <input type="date" class="form-control" id="fecha_registro" name="fecha_registro" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="referencias">Referencias / Observaciones</label>
                        <textarea class="form-control" id="referencias" name="referencias" rows="3" 
                                  placeholder="Punto de referencia cercano, características especiales, etc."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="imagen">Evidencia Fotográfica</label>
                        <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                        <small class="form-text">Sube una foto del sitio (fachada, sala de servidores, etc.) - JPG, PNG - Máx. 5MB</small>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Guardar Sitio
                </button>
            </div>
        </form>
    </main>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    let map;
    let marker;
    let selectedCoords = null;

    const ECATEPEC_LAT = 19.6012;
    const ECATEPEC_LON = -99.0597;

    function initMap() {
        map = L.map('mapPicker').setView([ECATEPEC_LAT, ECATEPEC_LON], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        map.on('click', function(e) {
            setCoordinates(e.latlng.lat, e.latlng.lng);
        });
    }

    function setCoordinates(lat, lng) {
        selectedCoords = { lat, lng };
        
        document.getElementById('latitud').value = lat.toFixed(8);
        document.getElementById('longitud').value = lng.toFixed(8);
        document.getElementById('latDisplay').textContent = lat.toFixed(8);
        document.getElementById('lonDisplay').textContent = lng.toFixed(8);
        
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map);
        }
        
        // Geocodificación inversa
        obtenerDireccion(lat, lng);
    }

    function obtenerDireccion(lat, lng) {
        const calle = document.getElementById('calle');
        const numero = document.getElementById('numero_exterior');
        const colonia = document.getElementById('colonia');
        const status = document.getElementById('calle-status');
        
        if (!calle) return;

        status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
        
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
            headers: { 'User-Agent': 'SIGEVEM/1.0' }
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.address) {
                // Calle
                const c = data.address.road || data.address.pedestrian || '';
                if (c) calle.value = c;
                
                // Número
                const n = data.address.house_number || '';
                numero.value = n;
                if (!n) numero.focus();
                
                // Colonia
                const col = data.address.neighbourhood || data.address.suburb || '';
                if (col) colonia.value = col;
                
                status.innerHTML = '<span style="color:green">✅ Dirección encontrada</span>';
            } else {
                status.innerHTML = '<span style="color:red">❌ No encontrada</span>';
            }
        })
        .catch(err => {
            console.error('Error geocodificación:', err);
            status.innerHTML = '<span style="color:red"> Error de red</span>';
        });
    }

    function clearCoords() {
        selectedCoords = null;
        document.getElementById('latitud').value = '';
        document.getElementById('longitud').value = '';
        document.getElementById('latDisplay').textContent = 'Sin seleccionar';
        document.getElementById('lonDisplay').textContent = 'Sin seleccionar';
        
        document.getElementById('calle').value = '';
        document.getElementById('numero_exterior').value = '';
        document.getElementById('colonia').value = '';
        document.getElementById('calle-status').innerHTML = '<i class="fas fa-map-marker-alt"></i> Se completa automáticamente';
        
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }
    }

    // Validar formulario
    document.getElementById('registroForm').addEventListener('submit', function(e) {
        if (!selectedCoords) {
            e.preventDefault();
            alert('Por favor selecciona una ubicación en el mapa');
            document.getElementById('mapPicker').scrollIntoView({ behavior: 'smooth' });
            return false;
        }
        
        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btn.disabled = true;
    });

    document.addEventListener('DOMContentLoaded', initMap);
    </script>
</body>
</html>