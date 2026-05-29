<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

$sql = "SELECT 
    c.*,
    cv.estado as estado_validacion,
    cv.observaciones_rechazo,
    u.nombre_completo as registrado_por
FROM camaras c
LEFT JOIN camaras_validacion cv ON c.id = cv.camara_id
LEFT JOIN usuarios u ON c.usuario_registro_id = u.id
WHERE c.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$camara = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$camara) {
    $_SESSION['error_message'] = 'Cámara no encontrada';
    header('Location: index.php');
    exit;
}

// Obtener imágenes
$img_sql = "SELECT * FROM evidencia_fotografica WHERE camara_id = :camara_id ORDER BY fecha_subida DESC";
$img_stmt = $pdo->prepare($img_sql);
$img_stmt->execute([':camara_id' => $id]);
$imagenes = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Cámara | SIGEVEM</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
    .detail-row { display: flex; padding: 12px 0; border-bottom: 1px solid var(--gray-200); }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-weight: 600; color: var(--primary); width: 200px; flex-shrink: 0; }
    .detail-value { flex: 1; color: var(--text-dark); }
    .map-view { height: 300px; border-radius: 8px; margin-top: 10px; }
    .imagen-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 15px; }
    .imagen-item { position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .imagen-item img { width: 100%; height: 150px; object-fit: cover; cursor: pointer; }
    .imagen-item .btn { position: absolute; top: 8px; right: 8px; padding: 4px 8px; font-size: 12px; }
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
            <h1 class="page-title">Detalles de la Cámara</h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($camara['inventario_id']); ?></p>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Información General</h3>
                <a href="index.php" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Regresar
                </a>
            </div>
            <div class="card-body">
                <div class="detail-row">
                    <div class="detail-label">ID de Cámara:</div>
                    <div class="detail-value"><strong><?php echo htmlspecialchars($camara['inventario_id']); ?></strong></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Marca:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($camara['marca']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Modelo:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($camara['modelo'] ?? 'N/A'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Tipo:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($camara['tipo_camara']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Número de Serie/IP:</div>
                    <div class="detail-value"><code><?php echo htmlspecialchars($camara['numero_serie'] ?? 'N/A'); ?></code></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Dirección:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($camara['direccion']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Colonia:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($camara['colonia'] ?? 'N/A'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Zona:</div>
                    <div class="detail-value"><span class="badge badge-secondary"><?php echo htmlspecialchars($camara['zona']); ?></span></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Referencias:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($camara['referencias'] ?? 'Sin referencias')); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Coordenadas:</div>
                    <div class="detail-value">
                        <strong>Lat:</strong> <?php echo $camara['latitud']; ?>, 
                        <strong>Lon:</strong> <?php echo $camara['longitud']; ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Ubicación en Mapa:</div>
                    <div class="detail-value">
                        <div id="mapView" class="map-view"></div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Fecha de Instalación:</div>
                    <div class="detail-value"><?php echo date('d/m/Y', strtotime($camara['fecha_instalacion'])); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Estado:</div>
                    <div class="detail-value">
                        <?php
                        $estado_class = [
                            'activa' => 'badge-success',
                            'mantenimiento' => 'badge-warning',
                            'fuera_servicio' => 'badge-danger',
                            'pendiente' => 'badge-info'
                        ];
                        $estado_label = [
                            'activa' => 'Activa',
                            'mantenimiento' => 'En Mantenimiento',
                            'fuera_servicio' => 'Fuera de Servicio',
                            'pendiente' => 'Pendiente'
                        ];
                        $estado = strtolower($camara['estatus']);
                        ?>
                        <span class="badge <?php echo $estado_class[$estado] ?? 'badge-secondary'; ?>">
                            <?php echo $estado_label[$estado] ?? $camara['estatus']; ?>
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Registrado por:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($camara['registrado_por']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Fecha de Registro:</div>
                    <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($camara['fecha_registro'])); ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($imagenes)): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h3><i class="fas fa-images"></i> Evidencia Fotográfica</h3>
            </div>
            <div class="card-body">
                <div class="imagen-gallery">
                    <?php foreach ($imagenes as $imagen): ?>
                    <div class="imagen-item">
                        <img src="../../<?php echo htmlspecialchars($imagen['ruta_archivo']); ?>" alt="Imagen" onclick="window.open('../../<?php echo htmlspecialchars($imagen['ruta_archivo']); ?>', '_blank')">
                        <?php if ($_SESSION['rol_id'] != 3): ?>
                        <a href="eliminar_imagen.php?id=<?php echo $imagen['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta imagen?')">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['rol_id'] != 3): ?>
        <div class="card mt-3">
            <div class="card-body">
                <a href="editar.php?id=<?php echo $camara['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Editar Cámara
                </a>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    const map = L.map('mapView').setView([<?php echo $camara['latitud']; ?>, <?php echo $camara['longitud']; ?>], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    L.marker([<?php echo $camara['latitud']; ?>, <?php echo $camara['longitud']; ?>]).addTo(map).bindPopup('<?php echo htmlspecialchars($camara['inventario_id']); ?>').openPopup();
    </script>
</body>
</html>