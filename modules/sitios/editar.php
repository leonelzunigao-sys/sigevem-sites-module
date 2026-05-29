<?php
session_start();

// Solo Admin y Supervisor pueden editar
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol_id'] != 1 && $_SESSION['rol_id'] != 2)) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

$sitio_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sitio_id <= 0) {
    header('Location: index.php');
    exit;
}

// Obtener datos actuales del sitio
$sql = "SELECT * FROM sitios WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $sitio_id]);
$sitio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sitio) {
    $_SESSION['error_message'] = 'Sitio no encontrado';
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Sitio | SIGEVEM</title>
    
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
    .coord-row:last-child { margin-bottom: 0; }
    .coord-label { font-weight: 600; color: var(--primary); min-width: 100px; }
    .coord-value { flex: 1; font-family: monospace; padding: 8px; background: white; border-radius: 4px; }
    .btn-clear-coords { margin-top: 10px; }
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
    .activo-card h4 { margin: 0 0 10px 0; color: var(--primary); font-size: 14px; }
    .activo-input { width: 100%; padding: 8px; border: 1px solid var(--gray-300); border-radius: 4px; text-align: center; font-size: 16px; }
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
            <h1 class="page-title">Editar Sitio</h1>
            <p class="page-subtitle">Modificando: <?php echo htmlspecialchars($sitio['inventario_id'] . ' — ' . $sitio['nombre']); ?></p>
        </div>

        <form action="editar_process.php" method="POST" enctype="multipart/form-data" id="editarForm">
            <input type="hidden" name="sitio_id" value="<?php echo $sitio_id; ?>">
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Información del Sitio</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="inventario_id">ID de Sitio</label>
                            <input type="text" class="form-control" id="inventario_id" name="inventario_id" 
                                   value="<?php echo htmlspecialchars($sitio['inventario_id']); ?>" readonly style="background: var(--bg-light); cursor: not-allowed;">
                            <small class="form-text">El ID no es modificable.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre">Nombre del Sitio *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?php echo htmlspecialchars($sitio['nombre']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_inmueble">Tipo de Inmueble *</label>
                            <select class="form-control" id="tipo_inmueble" name="tipo_inmueble" required>
                                <option value="">Seleccionar tipo</option>
                                <?php 
                                $tipos = ['Administrativo', 'Educativo', 'Salud', 'Seguridad / C5', 'Servicios Públicos', 'Otros'];
                                foreach ($tipos as $t): 
                                    $selected = ($sitio['tipo_inmueble'] == $t) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $t; ?>" <?php echo $selected; ?>><?php echo $t; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="zona">Zona / Sector *</label>
                            <select class="form-control" id="zona" name="zona" required>
                                <option value="">Seleccionar zona</option>
                                <?php 
                                $zonas = ['Norte', 'Sur', 'Centro', 'Oriente', 'Poniente'];
                                foreach ($zonas as $z): 
                                    $selected = ($sitio['zona'] == $z) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $z; ?>" <?php echo $selected; ?>><?php echo $z; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- DIRECCIÓN -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label for="calle">Calle / Avenida *</label>
                            <input type="text" class="form-control" id="calle" name="calle" 
                                   value="<?php echo htmlspecialchars($sitio['calle']); ?>" required>
                            <small class="form-text" id="calle-status">
                                <i class="fas fa-map-marker-alt"></i> Se actualiza al mover el marcador en el mapa
                            </small>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label for="numero_exterior">Número Exterior *</label>
                            <input type="text" class="form-control" id="numero_exterior" name="numero_exterior" 
                                   value="<?php echo htmlspecialchars($sitio['numero_exterior']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="colonia">Colonia / Barrio</label>
                            <input type="text" class="form-control" id="colonia" name="colonia" 
                                   value="<?php echo htmlspecialchars($sitio['colonia']); ?>">
                        </div>
                    </div>

                    <!-- MAP PICKER -->
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Ubicación en Mapa *</label>
                        <div id="mapPicker"></div>
                        <small class="form-text">Arrastra el marcador para actualizar la ubicación y dirección</small>
                    </div>

                    <!-- COORDENADAS -->
                    <div class="coord-info">
                        <div class="coord-row">
                            <span class="coord-label"><i class="fas fa-location-crosshairs"></i> Latitud:</span>
                            <div class="coord-value" id="latDisplay"><?php echo $sitio['latitud'] ?: 'Sin seleccionar'; ?></div>
                            <input type="hidden" id="latitud" name="latitud" value="<?php echo $sitio['latitud']; ?>" required>
                        </div>
                        <div class="coord-row">
                            <span class="coord-label"><i class="fas fa-location-crosshairs"></i> Longitud:</span>
                            <div class="coord-value" id="lonDisplay"><?php echo $sitio['longitud'] ?: 'Sin seleccionar'; ?></div>
                            <input type="hidden" id="longitud" name="longitud" value="<?php echo $sitio['longitud']; ?>" required>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-clear-coords" onclick="clearCoords()">
                            <i class="fas fa-trash"></i> Eliminar selección
                        </button>
                    </div>

                    <!-- ACTIVOS -->
                    <div class="form-group">
                        <label><i class="fas fa-laptop"></i> Activos Tecnológicos</label>
                        <div class="activos-grid">
                            <div class="activo-card">
                                <h4><i class="fas fa-desktop"></i> Computadoras</h4>
                                <input type="number" class="activo-input" name="activos_computadoras" 
                                       value="<?php echo (int)$sitio['activos_computadoras']; ?>" min="0">
                            </div>
                            <div class="activo-card">
                                <h4><i class="fas fa-server"></i> Servidores</h4>
                                <input type="number" class="activo-input" name="activos_servidores" 
                                       value="<?php echo (int)$sitio['activos_servidores']; ?>" min="0">
                            </div>
                            <div class="activo-card">
                                <h4><i class="fas fa-print"></i> Impresoras</h4>
                                <input type="number" class="activo-input" name="activos_impresoras" 
                                       value="<?php echo (int)$sitio['activos_impresoras']; ?>" min="0">
                            </div>
                            <div class="activo-card">
                                <h4><i class="fas fa-box"></i> Otros</h4>
                                <input type="number" class="activo-input" name="activos_otros" 
                                       value="<?php echo (int)$sitio['activos_otros']; ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_internet">Tipo de Conectividad</label>
                            <select class="form-control" id="tipo_internet" name="tipo_internet">
                                <option value="">Seleccionar tipo</option>
                                <?php 
                                $conexiones = ['Fibra Óptica', 'Inalámbrico', 'Cable', 'Satelital', 'Sin conexión'];
                                foreach ($conexiones as $c): 
                                    $selected = ($sitio['tipo_internet'] == $c) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $c; ?>" <?php echo $selected; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="estado">Estado del Sitio</label>
                            <select class="form-control" id="estado" name="estado">
                                <option value="activo" <?php echo $sitio['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="mantenimiento" <?php echo $sitio['estado'] == 'mantenimiento' ? 'selected' : ''; ?>>En Mantenimiento</option>
                                <option value="fuera_servicio" <?php echo $sitio['estado'] == 'fuera_servicio' ? 'selected' : ''; ?>>Fuera de Servicio</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="referencias">Referencias / Observaciones</label>
                        <textarea class="form-control" id="referencias" name="referencias" rows="3"><?php echo htmlspecialchars($sitio['referencias']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="ver.php?id=<?php echo $sitio_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Guardar Cambios
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

    // Coordenadas actuales del sitio o default
    const currentLat = <?php echo $sitio['latitud'] ? $sitio['latitud'] : 19.6012; ?>;
    const currentLng = <?php echo $sitio['longitud'] ? $sitio['longitud'] : -99.0597; ?>;

    function initMap() {
        map = L.map('mapPicker').setView([currentLat, currentLng], 16);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Crear marcador arrastrable en la posición actual
        marker = L.marker([currentLat, currentLng], { draggable: true }).addTo(map);
        selectedCoords = { lat: currentLat, lng: currentLng };

        // Evento al arrastrar el marcador
        marker.on('dragend', function(e) {
            const pos = marker.getLatLng();
            setCoordinates(pos.lat, pos.lng);
        });

        // También permitir clic para mover
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            setCoordinates(e.latlng.lat, e.latlng.lng);
        });
    }

    function setCoordinates(lat, lng) {
        selectedCoords = { lat, lng };
        
        document.getElementById('latitud').value = lat.toFixed(8);
        document.getElementById('longitud').value = lng.toFixed(8);
        document.getElementById('latDisplay').textContent = lat.toFixed(8);
        document.getElementById('lonDisplay').textContent = lng.toFixed(8);
        
        // Geocodificación inversa al mover
        obtenerDireccion(lat, lng);
    }

    function obtenerDireccion(lat, lng) {
        const calle = document.getElementById('calle');
        const numero = document.getElementById('numero_exterior');
        const colonia = document.getElementById('colonia');
        const status = document.getElementById('calle-status');
        
        if (!calle) return;

        status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando dirección...';
        
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
            headers: { 'User-Agent': 'SIGEVEM/1.0' }
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.address) {
                const c = data.address.road || data.address.pedestrian || '';
                if (c) calle.value = c;
                
                const n = data.address.house_number || '';
                numero.value = n;
                
                const col = data.address.neighbourhood || data.address.suburb || '';
                if (col) colonia.value = col;
                
                status.innerHTML = '<span style="color:green">✅ Dirección actualizada</span>';
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
        
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }
    }

    document.getElementById('editarForm').addEventListener('submit', function(e) {
        if (!selectedCoords) {
            e.preventDefault();
            alert('Por favor selecciona una ubicación en el mapa');
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