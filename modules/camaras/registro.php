<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] == 2) {
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
    <title>Registrar Cámara | SIGEVEM</title>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/mapa.css">
    
    <style>
    #mapPicker {
        height: 400px;
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
    .btn-clear-coords { margin-top: 10px; }
    .geo-status { margin-top: 6px; font-size: 13px; }
    </style>
</head>
<body>
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

    <?php include '../../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="page-header">
            <h1 class="page-title">Registrar Nueva Cámara</h1>
            <p class="page-subtitle">Complete el formulario para registrar una nueva cámara en el sistema</p>
        </div>

        <form action="registro_process.php" method="POST" enctype="multipart/form-data" id="registroForm">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-camera"></i> Información de la Cámara</h3>
                </div>
                <div class="card-body">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="inventario_id">ID de Cámara *</label>
                            <input type="text" class="form-control" id="inventario_id" name="inventario_id" placeholder="CAM-0050" required>
                            <small class="form-text">Formato: CAM-XXXX (4 dígitos)</small>
                        </div>
                        <div class="form-group">
                            <label for="numero_camara">Número de Cámara</label>
                            <input type="text" class="form-control" id="numero_camara" name="numero_camara" placeholder="001">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="marca">Marca *</label>
                            <input type="text" class="form-control" id="marca" name="marca" placeholder="Hikvision" required>
                        </div>
                        <div class="form-group">
                            <label for="modelo">Modelo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" placeholder="DS-2DE4425IWG-E">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_camara">Tipo de Cámara *</label>
                            <select class="form-control" id="tipo_camara" name="tipo_camara" required>
                                <option value="">Seleccionar tipo</option>
                                <option value="PTZ">PTZ</option>
                                <option value="Domo IP">Domo IP</option>
                                <option value="Fija">Fija</option>
                                <option value="Bullet">Bullet</option>
                                <option value="360">360°</option>
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

                    <!-- DIRECCIÓN CON GEOCODIFICACIÓN -->
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label for="direccion">Calle / Avenida *</label>
                            <input type="text" class="form-control" id="direccion" name="direccion"
                                   placeholder="Se completa al seleccionar en el mapa" required>
                            <small class="form-text geo-status" id="geo-status">
                                <i class="fas fa-map-marker-alt"></i> Haz clic en el mapa para autocompletar
                            </small>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="numero_exterior">Número Exterior</label>
                            <input type="text" class="form-control" id="numero_exterior" name="numero_exterior" placeholder="Ej: 450">
                            <small class="form-text">Capturar manualmente si no aparece</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="colonia">Colonia / Barrio</label>
                            <input type="text" class="form-control" id="colonia" name="colonia" placeholder="Se completa automáticamente">
                        </div>
                    </div>

                    <!-- MAP PICKER -->
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Ubicación en Mapa *</label>
                        <div id="mapPicker"></div>
                        <small class="form-text">Haz clic en el mapa para seleccionar la ubicación de la cámara</small>
                    </div>

                    <!-- COORDENADAS -->
                    <div class="coord-info">
                        <div class="coord-row">
                            <span class="coord-label"><i class="fas fa-location-crosshairs"></i> Latitud:</span>
                            <div class="coord-value" id="latDisplay">Sin seleccionar</div>
                            <input type="hidden" id="latitud" name="latitud">
                        </div>
                        <div class="coord-row">
                            <span class="coord-label"><i class="fas fa-location-crosshairs"></i> Longitud:</span>
                            <div class="coord-value" id="lonDisplay">Sin seleccionar</div>
                            <input type="hidden" id="longitud" name="longitud">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-clear-coords" onclick="clearCoords()">
                            <i class="fas fa-trash"></i> Eliminar selección
                        </button>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="numero_serie">Número de Serie / IP</label>
                            <input type="text" class="form-control" id="numero_serie" name="numero_serie" placeholder="192.168.1.150">
                        </div>
                        <div class="form-group">
                            <label for="fecha_instalacion">Fecha de Instalación *</label>
                            <input type="date" class="form-control" id="fecha_instalacion" name="fecha_instalacion" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="referencias">Referencias</label>
                        <textarea class="form-control" id="referencias" name="referencias" rows="3"
                                  placeholder="Punto de referencia cercano, características especiales, etc."></textarea>
                        <small class="form-text">Describa puntos de referencia cercanos para facilitar la ubicación</small>
                    </div>

                    <div class="form-group">
                        <label for="imagen">Evidencia Fotográfica</label>
                        <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                        <small class="form-text">Sube una foto de la cámara instalada (JPG, PNG - Máx. 5MB)</small>
                    </div>

                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Guardar Cámara
                </button>
            </div>
        </form>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    var map, marker, selectedCoords = null;
    window.map = null;

    function initMap() {
        window.map = L.map('mapPicker').setView([19.6012, -99.0597], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        map.on('click', function(e) {
            setCoordinates(e.latlng.lat, e.latlng.lng);
        });
    }

    function setCoordinates(lat, lng) {
        selectedCoords = { lat: lat, lng: lng };

        document.getElementById('latitud').value  = lat.toFixed(8);
        document.getElementById('longitud').value = lng.toFixed(8);
        document.getElementById('latDisplay').textContent = lat.toFixed(8);
        document.getElementById('lonDisplay').textContent = lng.toFixed(8);

        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map);
        }

        var direccionInput = document.getElementById('direccion');
        var numeroInput    = document.getElementById('numero_exterior');
        var coloniaInput   = document.getElementById('colonia');
        var statusEl       = document.getElementById('geo-status');

        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando dirección...';
        direccionInput.disabled = true;

        var url = 'https://nominatim.openstreetmap.org/reverse'
                + '?format=json'
                + '&lat=' + lat
                + '&lon=' + lng
                + '&zoom=18'
                + '&addressdetails=1'
                + '&accept-language=es-MX';

        fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.address) {
                var addr = data.address;

                var calle = addr.road || addr.pedestrian || addr.highway || '';
                direccionInput.value = calle;

                if (addr.house_number) {
                    numeroInput.value = addr.house_number;
                    statusEl.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i> Dirección encontrada automáticamente';
                } else {
                    numeroInput.value = '';
                    statusEl.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:#ffc107;"></i> Número no encontrado, captúrelo manualmente';
                    numeroInput.focus();
                }

                var colonia = addr.neighbourhood || addr.suburb || addr.quarter || addr.city_district || '';
                coloniaInput.value = colonia;

            } else {
                statusEl.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#dc3545;"></i> No se encontró dirección. Escríbala manualmente.';
            }
            direccionInput.disabled = false;
        })
        .catch(function(err) {
            console.error('Error geocodificación:', err);
            statusEl.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545;"></i> Error de conexión. Escríbala manualmente.';
            direccionInput.disabled = false;
        });
    }

    function clearCoords() {
        selectedCoords = null;
        document.getElementById('latitud').value  = '';
        document.getElementById('longitud').value = '';
        document.getElementById('latDisplay').textContent = 'Sin seleccionar';
        document.getElementById('lonDisplay').textContent = 'Sin seleccionar';
        document.getElementById('direccion').value = '';
        document.getElementById('numero_exterior').value = '';
        document.getElementById('colonia').value = '';
        document.getElementById('geo-status').innerHTML = '<i class="fas fa-map-marker-alt"></i> Haz clic en el mapa para autocompletar';
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }
    }

    document.getElementById('registroForm').addEventListener('submit', function(e) {
        if (!selectedCoords) {
            e.preventDefault();
            alert('Por favor selecciona una ubicación en el mapa');
            document.getElementById('mapPicker').scrollIntoView({ behavior: 'smooth' });
            return false;
        }
        var btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btn.disabled = true;
    });

    document.addEventListener('DOMContentLoaded', initMap);

     map.on('click', function(e) {
    var lat = e.latlng.lat;
    var lng = e.latlng.lng;
    var url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&zoom=18&addressdetails=1&accept-language=es-MX';
    fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d && d.address) {
            var a = d.address;
            document.getElementById('direccion').value = a.road || a.pedestrian || a.highway || '';
            document.getElementById('colonia').value = a.neighbourhood || a.suburb || a.quarter || '';
        }
    });
});


    </script>
</body>
</html>