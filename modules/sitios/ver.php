<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

$sitio_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sitio_id <= 0) {
    header('Location: index.php');
    exit;
}

// Obtener datos del sitio
$sql = "SELECT s.*, 
               sv.estado as validacion_estado, 
               sv.observaciones as validacion_obs,
               sv.fecha_validacion,
               u.nombre_completo as validado_por
        FROM sitios s
        LEFT JOIN sitios_validacion sv ON s.id = sv.sitio_id 
            AND sv.id = (SELECT MAX(id) FROM sitios_validacion WHERE sitio_id = s.id)
        LEFT JOIN usuarios u ON sv.usuario_validacion_id = u.id
        WHERE s.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $sitio_id]);
$sitio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sitio) {
    $_SESSION['error_message'] = 'Sitio no encontrado';
    header('Location: index.php');
    exit;
}

// Obtener evidencia fotográfica
$img_sql = "SELECT * FROM sitios_evidencia_fotografica WHERE sitio_id = :id ORDER BY fecha_subida DESC LIMIT 1";
$img_stmt = $pdo->prepare($img_sql);
$img_stmt->execute([':id' => $sitio_id]);
$evidencia = $img_stmt->fetch(PDO::FETCH_ASSOC);

// Calcular total de activos
$total_activos = $sitio['activos_computadoras'] + $sitio['activos_servidores'] + $sitio['activos_impresoras'] + $sitio['activos_otros'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Sitio | SIGEVEM</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    
    <style>
    .detail-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .detail-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    .info-card {
        background: var(--bg-light);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .info-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
    .info-item {
        display: flex;
        flex-direction: column;
    }
    .info-label {
        font-size: 12px;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 4px;
    }
    .info-value {
        font-size: 15px;
        color: var(--text-dark);
        font-weight: 500;
    }
    .evidencia-img {
        width: 100%;
        max-height: 300px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid var(--gray-200);
    }
    .activos-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .activo-tag {
        background: white;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid var(--gray-200);
        font-size: 14px;
    }
    .activo-tag strong {
        color: var(--primary);
        margin-right: 5px;
    }
    #miniMap {
        height: 250px;
        border-radius: 8px;
        border: 1px solid var(--gray-200);
    }
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
            <div>
                <h1 class="page-title">Detalle del Sitio</h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($sitio['nombre']); ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Regresar
                </a>
                
                <!-- Editar: Solo Admin/Supervisor -->
                <?php if ($_SESSION['rol_id'] != 3): ?>
                <a href="editar.php?id=<?php echo $sitio['id']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit"></i> Editar
                </a>
                <?php endif; ?>
                
                <!-- Validación: Solo Admin/Supervisor -->
                <?php if (strtolower($sitio['validacion_estado']) === 'pendiente' && ($_SESSION['rol_id'] == 1 || $_SESSION['rol_id'] == 2)): ?>
                    <a href="validar.php?id=<?php echo $sitio['id']; ?>&accion=aprobar" class="btn btn-success">
                        <i class="fas fa-check"></i> Aprobar
                    </a>
                    <a href="validar.php?id=<?php echo $sitio['id']; ?>&accion=rechazar" class="btn btn-danger">
                        <i class="fas fa-times"></i> Rechazar
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Principal -->
        <div class="info-card">
            <div class="detail-header">
                <div>
                    <h2 style="margin: 0 0 5px 0;">
                        <?php echo htmlspecialchars($sitio['inventario_id']); ?> — <?php echo htmlspecialchars($sitio['nombre']); ?>
                    </h2>
                    <div class="text-muted">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($sitio['calle'] . ' ' . $sitio['numero_exterior'] . ', ' . $sitio['colonia'] . ', ' . $sitio['zona']); ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php
                    $estado_class = match(strtolower($sitio['estado'])) {
                        'activo' => 'background: #d4edda; color: #155724;',
                        'mantenimiento' => 'background: #fff3cd; color: #856404;',
                        'fuera_servicio' => 'background: #f8d7da; color: #721c24;',
                        default => 'background: #e2e3e5; color: #383d41;'
                    };
                    ?>
                    <span class="detail-badge" style="<?php echo $estado_class; ?>">
                        <i class="fas fa-circle" style="font-size: 10px; vertical-align: middle;"></i> <?php echo htmlspecialchars($sitio['estado']); ?>
                    </span>
                    
                    <?php
                    $val_class = match(strtolower($sitio['validacion_estado'])) {
                        'aprobada' => 'background: #d4edda; color: #155724;',
                        'rechazada' => 'background: #f8d7da; color: #721c24;',
                        default => 'background: #e2d4f0; color: #4a148c;'
                    };
                    ?>
                    <span class="detail-badge" style="<?php echo $val_class; ?>">
                        <?php echo htmlspecialchars($sitio['validacion_estado']); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
            <!-- Columna Izquierda -->
            <div>
                <div class="info-card">
                    <div class="info-title"><i class="fas fa-building"></i> Información General</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Tipo de Inmueble</span>
                            <span class="info-value"><?php echo htmlspecialchars($sitio['tipo_inmueble']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Zona / Sector</span>
                            <span class="info-value"><?php echo htmlspecialchars($sitio['zona']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Conectividad</span>
                            <span class="info-value"><?php echo htmlspecialchars($sitio['tipo_internet'] ?: 'No especificada'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Fecha de Registro</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($sitio['fecha_registro'])); ?></span>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-title"><i class="fas fa-laptop"></i> Activos Tecnológicos (Total: <?php echo $total_activos; ?>)</div>
                    <div class="activos-list">
                        <div class="activo-tag"><strong><?php echo $sitio['activos_computadoras']; ?></strong> 💻 Computadoras</div>
                        <div class="activo-tag"><strong><?php echo $sitio['activos_servidores']; ?></strong> 🖥️ Servidores</div>
                        <div class="activo-tag"><strong><?php echo $sitio['activos_impresoras']; ?></strong> 🖨️ Impresoras</div>
                        <div class="activo-tag"><strong><?php echo $sitio['activos_otros']; ?></strong> 📦 Otros</div>
                    </div>
                </div>

                <?php if (!empty($sitio['referencias'])): ?>
                <div class="info-card">
                    <div class="info-title"><i class="fas fa-sticky-note"></i> Referencias</div>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($sitio['referencias'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Columna Derecha -->
            <div>
                <div class="info-card">
                    <div class="info-title"><i class="fas fa-image"></i> Evidencia Fotográfica</div>
                    <?php if ($evidencia): ?>
                        <img src="../../<?php echo htmlspecialchars($evidencia['ruta_archivo']); ?>" alt="Evidencia" class="evidencia-img">
                        <div class="text-muted mt-2" style="font-size: 13px;">
                            <i class="fas fa-clock"></i> Subido: <?php echo date('d/m/Y H:i', strtotime($evidencia['fecha_subida'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted" style="background: var(--bg-light); border-radius: 8px;">
                            <i class="fas fa-image" style="font-size: 32px; opacity: 0.3;"></i><br>
                            Sin evidencia fotográfica
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <div class="info-title"><i class="fas fa-check-double"></i> Validación</div>
                    <?php if (strtolower($sitio['validacion_estado']) !== 'pendiente'): ?>
                        <div class="alert" style="background: <?php echo strtolower($sitio['validacion_estado']) == 'aprobada' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo strtolower($sitio['validacion_estado']) == 'aprobada' ? '#155724' : '#721c24'; ?>; border-radius: 6px; padding: 12px;">
                            <i class="fas fa-<?php echo strtolower($sitio['validacion_estado']) == 'aprobada' ? 'check-circle' : 'times-circle'; ?>"></i> 
                            <strong><?php echo htmlspecialchars($sitio['validacion_estado']); ?></strong> por 
                            <?php echo htmlspecialchars($sitio['validado_por'] ?: 'Sistema'); ?>
                            <div style="font-size: 12px; margin-top: 5px;">
                                <?php echo $sitio['fecha_validacion'] ? date('d/m/Y H:i', strtotime($sitio['fecha_validacion'])) : ''; ?>
                            </div>
                        </div>
                        <?php if (!empty($sitio['validacion_obs'])): ?>
                            <div class="mt-2 p-2" style="background: white; border-radius: 4px; font-size: 14px;">
                                <strong>Observaciones:</strong><br>
                                <?php echo nl2br(htmlspecialchars($sitio['validacion_obs'])); ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info" style="border-radius: 6px;">
                            <i class="fas fa-hourglass-half"></i> Este sitio está pendiente de revisión y aprobación.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <div class="info-title"><i class="fas fa-map-marked-alt"></i> Ubicación</div>
                    <div id="miniMap"></div>
                    <div class="text-muted mt-2" style="font-size: 13px;">
                        <i class="fas fa-crosshairs"></i> Lat: <?php echo $sitio['latitud']; ?> | Lon: <?php echo $sitio['longitud']; ?>
                    </div>
                </div>

                <!-- Botones de acción (CON PERMISOS POR ROL) -->
                <div class="action-buttons" style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                    
                    <?php if ($_SESSION['rol_id'] != 3): ?>
                        <!-- Admin/Supervisor: Pueden programar mantenimiento y editar -->
                        <a href="mantenimiento_programar.php?id=<?php echo $sitio['id']; ?>" class="btn btn-warning" style="width: 100%; text-align: center;">
                            <i class="fas fa-tools"></i> Programar Mantenimiento
                        </a>
                        
                        <a href="editar.php?id=<?php echo $sitio['id']; ?>" class="btn btn-outline-secondary" style="width: 100%; text-align: center;">
                            <i class="fas fa-edit"></i> Editar Sitio
                        </a>
                    <?php else: ?>
                        <!-- Técnico: Solo ve sus tareas asignadas -->
                        <a href="mantenimiento_listado.php" class="btn btn-outline-primary" style="width: 100%; text-align: center;">
                            <i class="fas fa-list-check"></i> Ver Mis Tareas Asignadas
                        </a>
                        
                        <div class="alert alert-info" style="font-size: 13px; margin-bottom: 0;">
                            <i class="fas fa-info-circle"></i> Como técnico, puedes completar las órdenes que te hayan asignado desde el listado de mantenimientos.
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    // Mini mapa de referencia
    const lat = <?php echo $sitio['latitud']; ?>;
    const lng = <?php echo $sitio['longitud']; ?>;
    
    if (lat && lng) {
        const miniMap = L.map('miniMap').setView([lat, lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(miniMap);
        L.marker([lat, lng]).addTo(miniMap);
    }
    </script>
</body>
</html>