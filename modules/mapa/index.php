<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../config/database.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Geolocalización | SIGEVEM</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet Heat CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/mapa.css">
    
    <style>
    #map {
        height: calc(100vh - 200px);
        border-radius: 8px;
        z-index: 1;
    }
    .map-controls {
        margin-bottom: 20px;
    }
    .filter-bar {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 15px;
    }
    .filter-bar input,
    .filter-bar select {
        flex: 1;
        min-width: 150px;
    }
    .map-type-selector {
        display: flex;
        gap: 5px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    .map-type-btn {
        padding: 8px 16px;
        border: 2px solid var(--primary);
        background: white;
        color: var(--primary);
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }
    .map-type-btn.active {
        background: var(--primary);
        color: white;
    }
    .legend {
        display: flex;
        gap: 20px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
    .legend-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    .legend-dot.activa { background: #28A745; }
    .legend-dot.mantenimiento { background: #FFC107; }
    .legend-dot.fuera_servicio { background: #DC3545; }
    .legend-dot.pendiente { background: #6F42C1; }
    
    /* Icono para Sitios en la leyenda */
    .legend-icon-site { color: #007bff; font-size: 16px; }
    
    /* Heatmap Legend */
    .heatmap-legend {
        display: none;
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-top: 15px;
    }
    .heatmap-legend.visible {
        display: block;
    }
    .heatmap-legend-title {
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 10px;
        font-size: 14px;
    }
    .heatmap-gradient {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }
    .heatmap-bar {
        flex: 1;
        height: 20px;
        background: linear-gradient(to right, rgba(0,255,0,0.3), rgba(255,255,0,0.4), rgba(255,165,0,0.5), rgba(255,0,0,0.6));
        border-radius: 4px;
    }
    .heatmap-labels {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: var(--text-muted);
    }
    .heatmap-info {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid var(--gray-200);
        font-size: 13px;
        color: var(--text-muted);
    }
    
    .sidebar-panel {
        position: fixed;
        right: -400px;
        top: 0;
        width: 400px;
        height: 100vh;
        background: white;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        transition: right 0.3s ease;
        z-index: 1000;
        overflow-y: auto;
    }
    .sidebar-panel.active {
        right: 0;
    }
   
    .sidebar-close {
        position: absolute;
        top: 20px;
        right: 20px;
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
    }
    .sidebar-body {
        padding: 20px;
    }
    .detail-row {
        display: flex;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--gray-200);
    }
    .detail-label {
        font-weight: 600;
        color: var(--text-muted);
        width: 120px;
        flex-shrink: 0;
    }
    .detail-value {
        flex: 1;
        color: var(--text-dark);
    }
    .camera-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 20px;
        background: var(--bg-light);
    }
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 20px;
    }
    .stats-bar {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .stat-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
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
     
    <!-- Botón menú móvil -->
    <button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
         <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="page-header">
            <h1 class="page-title">Mapa de Geolocalización</h1>
            <p class="page-subtitle">Visualización de infraestructura municipal</p>
        </div>

        <!-- Filtros -->
        <div class="card map-controls">
            <div class="card-body">
                <div class="filter-bar">
                    <input type="text" id="searchCamera" class="form-control" placeholder="Buscar cámara...">
                    <select id="filterZona" class="form-control">
                        <option value="">Todas las Zonas</option>
                        <option value="Norte">Norte</option>
                        <option value="Sur">Sur</option>
                        <option value="Centro">Centro</option>
                        <option value="Oriente">Oriente</option>
                        <option value="Poniente">Poniente</option>
                    </select>
                    <select id="filterEstado" class="form-control">
                        <option value="">Todos los Estados</option>
                        <option value="activa">Activas</option>
                        <option value="mantenimiento">Mantenimiento</option>
                        <option value="fuera_servicio">Fuera de Servicio</option>
                        <option value="pendiente">Pendientes</option>
                    </select>
                    <button onclick="applyFilters()" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Aplicar
                    </button>
                    <button onclick="clearFilters()" class="btn btn-secondary">
                        <i class="fas fa-eraser"></i> Limpiar
                    </button>
                </div>

                <!-- Tipo de mapa -->
                <div class="map-type-selector">
                    <button class="map-type-btn active" onclick="changeMapType('street')">Mapa</button>
                    <button class="map-type-btn" onclick="changeMapType('satellite')">Satélite</button>
                    <button class="map-type-btn" onclick="changeMapType('hybrid')">Híbrido</button>
                    <!-- BOTÓN TOGGLE LÍMITE MUNICIPAL -->
                    <button class="map-type-btn" id="btnMunicipio" onclick="toggleMunicipio()">
                        <i class="fas fa-draw-polygon"></i> Límite Municipal
                    </button>
                    <!-- BOTÓN TOGGLE HEATMAP -->
                    <button class="map-type-btn" id="btnHeatmap" onclick="toggleHeatmap()">
                        <i class="fas fa-fire"></i> 🔥 Mapa de Calor
                    </button>
                    <!-- BOTÓN TOGGLE SITIOS -->
                    <button class="map-type-btn" id="btnSites" onclick="toggleSites()">
                        <i class="fas fa-building"></i> Sitios
                    </button>
                </div>

                <!-- Leyenda del Heatmap (oculta por defecto) -->
                <div class="heatmap-legend" id="heatmapLegend">
                    <div class="heatmap-legend-title">
                        <i class="fas fa-fire-alt"></i> Densidad de Fallas (Último Año)
                    </div>
                    <div class="heatmap-gradient">
                        <span style="font-size: 12px;">Baja</span>
                        <div class="heatmap-bar"></div>
                        <span style="font-size: 12px;">Crítica</span>
                    </div>
                    <div class="heatmap-labels">
                        <span>🟢 Preventivo (1x)</span>
                        <span>🟡 Correctivo (2x)</span>
                        <span>🔴 Emergencia (3x)</span>
                    </div>
                    <div class="heatmap-info">
                        <i class="fas fa-info-circle"></i> 
                        El calor se calcula ponderando tipo y frecuencia de mantenimientos
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="stats-bar" id="statsBar">
                    <div class="stat-badge" style="background: #d4edda; color: #155724;">
                        <div class="legend-dot activa"></div>
                        <span id="countActivas">Activas: 0</span>
                    </div>
                    <div class="stat-badge" style="background: #fff3cd; color: #856404;">
                        <div class="legend-dot mantenimiento"></div>
                        <span id="countMantenimiento">Mantenim.: 0</span>
                    </div>
                    <div class="stat-badge" style="background: #f8d7da; color: #721c24;">
                        <div class="legend-dot fuera_servicio"></div>
                        <span id="countFuera">Fuera: 0</span>
                    </div>
                    <div class="stat-badge" style="background: #e2d4f0; color: #4a148c;">
                        <div class="legend-dot pendiente"></div>
                        <span id="countPendientes">Pendientes: 0</span>
                    </div>
                </div>
                
                <!-- Leyenda de iconos -->
                <!-- Leyenda unificada -->
<div class="legend">
    <div style="font-weight: 700; margin-right: 10px; color: var(--primary);">Leyenda:</div>
    
    <!-- Cámaras -->
    <div class="legend-item"><div class="legend-dot activa"></div> Cámaras Activas</div>
    <div class="legend-item"><div class="legend-dot mantenimiento"></div> Cámaras Mant.</div>
    <div class="legend-item"><div class="legend-dot fuera_servicio"></div> Cámaras Fuera</div>
    
    <span style="margin: 0 10px; opacity: 0.3;">|</span>
    
    <!-- Sitios -->
    <div class="legend-item"><div class="legend-dot" style="background: #28A745;"></div> Sitios Activos</div>
    <div class="legend-item"><div class="legend-dot" style="background: #FFC107;"></div> Sitios en Mant.</div>
    <div class="legend-item"><div class="legend-dot" style="background: #DC3545;"></div> Sitios Fuera</div>
</div>

        <!-- Mapa -->
        <div class="card">
            <div class="card-body p-0">
                <div id="map"></div>
            </div>
        </div>
    </main>

    <!-- Panel Lateral de Detalles -->
    <div class="sidebar-panel" id="detailPanel">
        <div class="sidebar-header">
            <div class="sidebar-header-content">
                <i class="fas fa-camera"></i>
                <strong id="panelCameraId">CAM-0000</strong>
                <span id="panelStatus" class="status-badge status-activa">Activa</span>
            </div>
            <button class="sidebar-close" onclick="closePanel()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="sidebar-body">
            <!-- Imagen -->
            <div class="camera-image-container">
                <img id="panelImage" src="" alt="Cámara" onerror="this.style.display='none'; document.getElementById('imagePlaceholder').style.display='flex';">
                <div class="image-placeholder" id="imagePlaceholder">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            
            <!-- Información en 2 columnas -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">MARCA</div>
                    <div class="info-value" id="panelMarca">-</div>
                </div>
                <div class="info-item">
                    <div class="info-label">MODELO</div>
                    <div class="info-value" id="panelModelo">-</div>
                </div>
                <div class="info-item">
                    <div class="info-label">TIPO</div>
                    <div class="info-value" id="panelTipo">-</div>
                </div>
                <div class="info-item">
                    <div class="info-label">ZONA</div>
                    <div class="info-value" id="panelZona">-</div>
                </div>
            </div>
            
            <div class="info-full">
                <div class="info-label">UBICACIÓN</div>
                <div class="info-value" id="panelUbicacion">
                    <i class="fas fa-map-marker-alt"></i>
                    <span id="panelUbicacionText">-</span>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">INSTALACIÓN</div>
                    <div class="info-value" id="panelInstalacion">-</div>
                </div>
                <div class="info-item">
                    <div class="info-label">COORDENADAS</div>
                    <div class="info-value" id="panelCoordenadas">-</div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="action-buttons">
                <a href="#" id="btnVerDetalle" class="btn btn-primary btn-block">
                    <i class="fas fa-eye"></i> Ver Detalle
                </a>
                <?php if ($_SESSION['rol_id'] != 3): ?>
                <a href="#" id="btnMantenimiento" class="btn btn-warning btn-block">
                    <i class="fas fa-tools"></i> Programar Mantenimiento
                </a>
                <a href="#" id="btnEditar" class="btn btn-outline-secondary btn-block">
                    <i class="fas fa-edit"></i> Editar Cámara
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    let map;
    let markers = [];
    let currentLayerGroup;
    let municipioLayer = null;
    let heatLayer = null;
    let heatmapVisible = false;
    let heatmapData = [];
    
    // NUEVAS VARIABLES PARA SITIOS
    let siteLayerGroup = null;
    let sitesVisible = true;

    // Inicializar mapa
    function initMap() {
        map = L.map('map').setView([19.6012, -99.0597], 13);
        
        const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        });
        
        const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri'
        });
        
        const hybridLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri'
        });
        
        streetLayer.addTo(map);
        currentLayerGroup = L.layerGroup().addTo(map);

        // Crear el grupo de capas para sitios PERO NO AGREGARLO AL MAPA
        siteLayerGroup = L.layerGroup();
        
        window.mapLayers = {
            street: streetLayer,
            satellite: satelliteLayer,
            hybrid: hybridLayer
        };

        municipioLayer = null;
        loadCameras();
        loadSitios(); // ← Cargar sitios al inicio
    }

    // Toggle Mapa de Calor
    function toggleHeatmap() {
        const btn = document.getElementById('btnHeatmap');
        const legend = document.getElementById('heatmapLegend');
        
        if (!heatmapVisible) {
            if (heatmapData.length === 0) {
                fetch('get_heatmap_data.php')
                    .then(response => response.json())
                    .then(data => {
                        heatmapData = data;
                        createHeatLayer();
                        btn.classList.add('active');
                        legend.classList.add('visible');
                        setMarkersOpacity(0.6);
                        heatmapVisible = true;
                    })
                    .catch(error => {
                        console.error('Error cargando heatmap:', error);
                        alert('Error al cargar el mapa de calor. Intente nuevamente.');
                    });
            } else {
                createHeatLayer();
                btn.classList.add('active');
                legend.classList.add('visible');
                setMarkersOpacity(0.6);
                heatmapVisible = true;
            }
        } else {
            if (heatLayer) {
                map.removeLayer(heatLayer);
                heatLayer = null;
            }
            btn.classList.remove('active');
            legend.classList.remove('visible');
            setMarkersOpacity(1.0);
            heatmapVisible = false;
        }
    }

    // Crear capa de calor
    function createHeatLayer() {
        if (heatLayer) {
            map.removeLayer(heatLayer);
        }
        const heatPoints = heatmapData.map(item => [item[0], item[1], item[2]]);
        heatLayer = L.heatLayer(heatPoints, {
            radius: 60,
            blur: 12,
            maxZoom: 16,
            minOpacity: 0.4,
            gradient: {
                0.0: 'rgba(0, 255, 0, 0.3)',
                0.2: 'rgba(255, 255, 0, 0.4)',
                0.5: 'rgba(255, 165, 0, 0.5)',
                1.0: 'rgba(255, 0, 0, 0.6)'
            }
        }).addTo(map);
    }

    // Cambiar opacidad de marcadores
    function setMarkersOpacity(opacity) {
        markers.forEach(marker => {
            marker.setStyle({
                opacity: opacity,
                fillOpacity: opacity * 0.8
            });
        });
    }

    // Mostrar / ocultar límite municipal
    function toggleMunicipio() {
        const btn = document.getElementById('btnMunicipio');
        
        if (municipioLayer === null) {
            fetch('../../assets/geojson/export.geojson')
                .then(res => {
                    if (!res.ok) throw new Error('No se pudo cargar el GeoJSON');
                    return res.json();
                })
                .then(data => {
                    municipioLayer = L.geoJSON(data, {
                        style: {
                            color: '#0057A8',
                            weight: 3,
                            opacity: 0.9,
                            fill: false
                        }
                    });
                    municipioLayer.addTo(map);
                    btn.classList.add('active');
                })
                .catch(err => {
                    console.error('Error al cargar límite municipal:', err);
                    alert('No se pudo cargar el límite municipal. Verifica la ruta del archivo GeoJSON.');
                });
        } else {
            if (map.hasLayer(municipioLayer)) {
                map.removeLayer(municipioLayer);
                btn.classList.remove('active');
            } else {
                map.addLayer(municipioLayer);
                btn.classList.add('active');
            }
        }
    }

    // Cargar cámaras
    function loadCameras(filters = {}) {
        fetch('get_camaras.php?' + new URLSearchParams(filters))
            .then(response => response.json())
            .then(data => {
                updateMarkers(data);
                updateStats(data);
            })
            .catch(error => {
                console.error('Error al cargar cámaras:', error);
            });
    }

    // Actualizar marcadores
    function updateMarkers(cameras) {
        currentLayerGroup.clearLayers();
        markers = [];
        const colors = {
            activa: '#28A745',
            mantenimiento: '#FFC107',
            fuera_servicio: '#DC3545',
            pendiente: '#6F42C1'
        };
        cameras.forEach(camara => {
            const color = colors[camara.estatus.toLowerCase()] || '#6c757d';
            const marker = L.circleMarker([camara.latitud, camara.longitud], {
                radius: 8,
                fillColor: color,
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            });
            marker.bindPopup(`<strong>${camara.inventario_id}</strong><br>${camara.marca}`);
            marker.on('click', () => { showCameraDetails(camara); });
            currentLayerGroup.addLayer(marker);
            markers.push(marker);
        });
    }

    // Actualizar estadísticas
    function updateStats(cameras) {
        const stats = { activa: 0, mantenimiento: 0, fuera_servicio: 0, pendiente: 0 };
        cameras.forEach(camara => {
            const estado = camara.estatus.toLowerCase();
            if (stats[estado] !== undefined) { stats[estado]++; }
        });
        document.getElementById('countActivas').textContent = `Activas: ${stats.activa}`;
        document.getElementById('countMantenimiento').textContent = `Mantenim.: ${stats.mantenimiento}`;
        document.getElementById('countFuera').textContent = `Fuera: ${stats.fuera_servicio}`;
        document.getElementById('countPendientes').textContent = `Pendientes: ${stats.pendiente}`;
    }

    // Mostrar detalles
    function showCameraDetails(camara) {
        document.getElementById('panelCameraId').textContent = camara.inventario_id;
        const statusEl = document.getElementById('panelStatus');
        const estadoLower = camara.estatus.toLowerCase();
        statusEl.textContent = camara.estatus;
        statusEl.className = 'badge badge-' + 
            (estadoLower === 'activa' ? 'success' : 
             estadoLower === 'mantenimiento' ? 'warning' : 
             estadoLower === 'fuera_servicio' ? 'danger' : 'info');
        document.getElementById('panelMarca').textContent = camara.marca || '-';
        document.getElementById('panelModelo').textContent = camara.modelo || '-';
        document.getElementById('panelTipo').textContent = camara.tipo_camara || '-';
        document.getElementById('panelZona').textContent = camara.zona || '-';
        document.getElementById('panelUbicacionText').textContent = camara.direccion || '-';
        document.getElementById('panelInstalacion').textContent = camara.fecha_instalacion ? 
            new Date(camara.fecha_instalacion).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
        document.getElementById('panelCoordenadas').textContent = `${parseFloat(camara.latitud).toFixed(6)}, ${parseFloat(camara.longitud).toFixed(6)}`;
        const img = document.getElementById('panelImage');
        const placeholder = document.getElementById('imagePlaceholder');
        img.onload = function() { this.style.display = 'block'; placeholder.style.display = 'none'; };
        img.onerror = function() { this.style.display = 'none'; placeholder.style.display = 'flex'; };
        fetch(`get_camera_image.php?id=${camara.id}`)
            .then(response => response.json())
            .then(data => { if (data.ruta_archivo) { img.src = '../../' + data.ruta_archivo; } else { img.src = ''; img.onerror(); } })
            .catch(() => { img.src = ''; img.onerror(); });
        document.getElementById('btnVerDetalle').href = `../camaras/ver.php?id=${camara.id}`;
        document.getElementById('btnEditar').href = `../camaras/editar.php?id=${camara.id}`;
        document.getElementById('btnMantenimiento').href = `../mantenimiento/programar.php?camara_id=${camara.id}`;
        document.getElementById('detailPanel').classList.add('active');
    }

    // Cerrar panel
    function closePanel() { document.getElementById('detailPanel').classList.remove('active'); }

    // Cambiar tipo de mapa
    function changeMapType(type) {
        Object.values(window.mapLayers).forEach(layer => { map.removeLayer(layer); });
        window.mapLayers[type].addTo(map);
        document.querySelectorAll('.map-type-btn:not(#btnMunicipio):not(#btnHeatmap):not(#btnSites)').forEach(btn => { btn.classList.remove('active'); });
        event.target.classList.add('active');
    }

    // Aplicar filtros
    function applyFilters() {
        const filters = { search: document.getElementById('searchCamera').value, zona: document.getElementById('filterZona').value, estado: document.getElementById('filterEstado').value };
        loadCameras(filters);
    }

    // Limpiar filtros
    function clearFilters() {
        document.getElementById('searchCamera').value = '';
        document.getElementById('filterZona').value = '';
        document.getElementById('filterEstado').value = '';
        loadCameras();
    }

    // ==========================================
    // LÓGICA DE SITIOS (NUEVA - NO TOCA CÁMARAS)
    // ==========================================
    function loadSitios() {
    fetch('../sitios/get_sitios_mapa.php')
        .then(response => response.json())
        .then(data => {
            if (!siteLayerGroup) {
                siteLayerGroup = L.layerGroup().addTo(map);
            }
            siteLayerGroup.clearLayers();

            // 🎨 Colores según estado del sitio
            const statusColors = {
                'activo': '#28A745',        // Verde
                'mantenimiento': '#FFC107', // Amarillo
                'fuera_servicio': '#DC3545',// Rojo
                'pendiente': '#6F42C1'      // Morado
            };

            data.forEach(sitio => {
                const color = statusColors[sitio.estado] || '#007bff'; // Azul por defecto

                const icon = L.divIcon({
                    className: 'custom-div-icon',
                    html: `<i class="fas fa-building" style="color: ${color}; font-size: 20px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);"></i>`,
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });

                const marker = L.marker([sitio.latitud, sitio.longitud], { icon: icon });
                
                marker.bindPopup(`
                    <div style="text-align:center; min-width: 150px;">
                        <strong style="font-size:14px;">${sitio.nombre}</strong><br>
                        <span style="color:${color}; font-weight:bold;">● ${sitio.estado}</span><br>
                        <span style="color:#666; font-size:12px;">${sitio.tipo_inmueble} | ${sitio.zona}</span><br>
                        <span style="font-size:11px;">📦 ${sitio.total_activos} activos</span><br>
                        <a href="../../modules/sitios/ver.php?id=${sitio.id}" class="btn btn-sm btn-primary" style="margin-top:8px; display:inline-block; text-decoration:none; color:white; padding:4px 12px; border-radius:4px; font-size:12px;">Ver Detalle</a>
                    </div>
                `);
                
                siteLayerGroup.addLayer(marker);
            });
        })
        .catch(error => console.error('Error al cargar sitios:', error));
}

    function toggleSites() {
        sitesVisible = !sitesVisible;
        const btn = document.getElementById('btnSites');
        if (sitesVisible) {
            if (!siteLayerGroup) { loadSitios(); }
            else { map.addLayer(siteLayerGroup); }
            btn.classList.add('active');
        } else {
            if (siteLayerGroup) { map.removeLayer(siteLayerGroup); }
            btn.classList.remove('active');
        }
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', initMap);
    </script>

</body>
</html>