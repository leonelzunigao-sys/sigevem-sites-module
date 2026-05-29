<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] == 3) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;
$sql = "SELECT * FROM camaras WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$camara = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$camara) {
    $_SESSION['error_message'] = 'Cámara no encontrada';
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cámara | SIGEVEM</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
    #mapPicker { height: 400px; border-radius: 8px; border: 2px solid var(--gray-300); margin-bottom: 15px; }
    .coord-info { background: var(--bg-light); padding: 15px; border-radius: 8px; margin-bottom: 15px; }
    .coord-row { display: flex; gap: 15px; margin-bottom: 10px; }
    .coord-label { font-weight: 600; color: var(--primary); min-width: 100px; }
    .coord-value { flex: 1; font-family: monospace; padding: 8px; background: white; border-radius: 4px; }
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
                <span class="badge-role">Rol: <?php echo htmlspecialchars($_SESSION['rol_nombre']); ?></span>
            </div>
        </div>
    </header>

    <?php include '../../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="page-header">
            <h1 class="page-title">Editar Cámara</h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($camara['inventario_id']); ?></p>
        </div>

        <form action="editar_process.php" method="POST" enctype="multipart/form-data" id="editarForm">
            <input type="hidden" name="id" value="<?php echo $camara['id']; ?>">
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-camera"></i> Información de la Cámara</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="inventario_id">ID de Cámara</label>
                            <input type="text" class="form-control" id="inventario_id" value="<?php echo htmlspecialchars($camara['inventario_id']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="numero_camara">Número de Cámara</label>
                            <input type="text" class="form-control" id="numero_camara" name="numero_camara" value="<?php echo htmlspecialchars($camara['numero_camara'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="marca">Marca *</label>
                            <input type="text" class="form-control" id="marca" name="marca" value="<?php echo htmlspecialchars($camara['marca']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="modelo">Modelo</label>
                            <input type="text" class="form-control" id="modelo" name="modelo" value="<?php echo htmlspecialchars($camara['modelo'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_camara">Tipo de Cámara *</label>
                            <select class="form-control" id="tipo_camara" name="tipo_camara" required>
                                <option value="PTZ" <?php echo $camara['tipo_camara'] == 'PTZ' ? 'selected' : ''; ?>>PTZ</option>
                                <option value="Domo IP" <?php echo $camara['tipo_camara'] == 'Domo IP' ? 'selected' : ''; ?>>Domo IP</option>
                                <option value="Fija" <?php echo $camara['tipo_camara'] == 'Fija' ? 'selected' : ''; ?>>Fija</option>
                                <option value="Bullet" <?php echo $camara['tipo_camara'] == 'Bullet' ? 'selected' : ''; ?>>Bullet</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="zona">Zona / Sector *</label>
                            <select class="form-control" id="zona" name="zona" required>
                                <option value="Norte" <?php echo $camara['zona'] == 'Norte' ? 'selected' : ''; ?>>Norte</option>
                                <option value="Sur" <?php echo $camara['zona'] == 'Sur' ? 'selected' : ''; ?>>Sur</option>
                                <option value="Centro" <?php echo $camara['zona'] == 'Centro' ? 'selected' : ''; ?>>Centro</option>
                                <option value="Oriente" <?php echo $camara['zona'] == 'Oriente' ? 'selected' : ''; ?>>Oriente</option>
                                <option value="Poniente" <?php echo $camara['zona'] == 'Poniente' ? 'selected' : ''; ?>>Poniente</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="direccion">Dirección / Calle *</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="2" required><?php echo htmlspecialchars($camara['direccion']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="colonia">Colonia / Barrio</label>
                            <input type="text" class="form-control" id="colonia" name="colonia" value="<?php echo htmlspecialchars($camara['colonia'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Ubicación en Mapa *</label>
                        <div id="mapPicker"></div>
                    </div>

                    <div class="coord-info">
                        <div class="coord-row">
                            <span class="coord-label">Latitud:</span>
                            <div class="coord-value" id="latDisplay"><?php echo $camara['latitud']; ?></div>
                            <input type="hidden" id="latitud" name="latitud" value="<?php echo $camara['latitud']; ?>" required>
                        </div>
                        <div class="coord-row">
                            <span class="coord-label">Longitud:</span>
                            <div class="coord-value" id="lonDisplay"><?php echo $camara['longitud']; ?></div>
                            <input type="hidden" id="longitud" name="longitud" value="<?php echo $camara['longitud']; ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="numero_serie">Número de Serie / IP</label>
                            <input type="text" class="form-control" id="numero_serie" name="numero_serie" value="<?php echo htmlspecialchars($camara['numero_serie'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="fecha_instalacion">Fecha de Instalación *</label>
                            <input type="date" class="form-control" id="fecha_instalacion" name="fecha_instalacion" value="<?php echo $camara['fecha_instalacion']; ?>" required>
                        </div>
                    </div>

                    <!-- CAMPO DE ESTADO - SOLO ADMIN Y SUPERVISOR -->
                    <?php if ($_SESSION['rol_id'] == 1 || $_SESSION['rol_id'] == 2): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="estatus">Estado *</label>
                            <select class="form-control" id="estatus" name="estatus" required>
                                <option value="activa" <?php echo $camara['estatus'] == 'activa' ? 'selected' : ''; ?>>Activa</option>
                                <option value="mantenimiento" <?php echo $camara['estatus'] == 'mantenimiento' ? 'selected' : ''; ?>>En Mantenimiento</option>
                                <option value="fuera_servicio" <?php echo $camara['estatus'] == 'fuera_servicio' ? 'selected' : ''; ?>>Fuera de Servicio</option>
                                <option value="pendiente" <?php echo $camara['estatus'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="referencias">Referencias</label>
                            <textarea class="form-control" id="referencias" name="referencias" rows="3"><?php echo htmlspecialchars($camara['referencias'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label for="referencias">Referencias</label>
                        <textarea class="form-control" id="referencias" name="referencias" rows="3"><?php echo htmlspecialchars($camara['referencias'] ?? ''); ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="ver.php?id=<?php echo $camara['id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Actualizar Cámara
                </button>
            </div>
        </form>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    let map, marker;
    let selectedCoords = { lat: <?php echo $camara['latitud']; ?>, lng: <?php echo $camara['longitud']; ?> };

    function initMap() {
        map = L.map('mapPicker').setView([selectedCoords.lat, selectedCoords.lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        marker = L.marker([selectedCoords.lat, selectedCoords.lng]).addTo(map);
        map.on('click', function(e) {
            selectedCoords = { lat: e.latlng.lat, lng: e.latlng.lng };
            document.getElementById('latitud').value = selectedCoords.lat.toFixed(8);
            document.getElementById('longitud').value = selectedCoords.lng.toFixed(8);
            document.getElementById('latDisplay').textContent = selectedCoords.lat.toFixed(8);
            document.getElementById('lonDisplay').textContent = selectedCoords.lng.toFixed(8);
            marker.setLatLng([selectedCoords.lat, selectedCoords.lng]);
        });
    }
    document.getElementById('editarForm').addEventListener('submit', function(e) {
        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btn.disabled = true;
    });
    document.addEventListener('DOMContentLoaded', initMap);
    </script>
</body>
</html>