<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

// Obtener tarea con información completa
$sql = "SELECT 
    mt.*,
    c.inventario_id,
    c.marca,
    c.modelo,
    c.tipo_camara,
    c.direccion,
    c.colonia,
    c.zona,
    c.latitud,
    c.longitud,
    c.numero_serie,
    u.nombre_completo as tecnico_nombre,
    u.email as tecnico_email,
    u2.nombre_completo as programado_por,
    u3.nombre_completo as validado_por
FROM mantenimiento_tareas mt
LEFT JOIN camaras c ON mt.camara_id = c.id
LEFT JOIN usuarios u ON mt.tecnico_id = u.id
LEFT JOIN usuarios u2 ON mt.programado_por_id = u2.id
LEFT JOIN usuarios u3 ON mt.validado_por_id = u3.id
WHERE mt.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$tarea = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tarea) {
    $_SESSION['error_message'] = 'Tarea no encontrada';
    header('Location: index.php');
    exit;
}

// Obtener evidencias fotográficas
$evidencias_sql = "SELECT * FROM evidencia_fotografica WHERE tarea_mantenimiento_id = :tarea_id ORDER BY fecha_subida ASC";
$evidencias_stmt = $pdo->prepare($evidencias_sql);
$evidencias_stmt->execute([':tarea_id' => $id]);
$evidencias = $evidencias_stmt->fetchAll(PDO::FETCH_ASSOC);

// Estado badge class
$estado_class = [
    'pendiente' => 'badge-warning',
    'en_proceso' => 'badge-info',
    'completado' => 'badge-success',
    'validado' => 'badge-success',
    'cancelado' => 'badge-danger'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Mantenimiento | SIGEVEM</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/mantenimiento.css">
    <style>
    .detail-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 30px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    .detail-card {
        background: var(--bg-light);
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid var(--primary);
    }
    .detail-card-label {
        font-size: 11px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    .detail-card-value {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-dark);
    }
    .timeline {
        position: relative;
        padding: 20px 0;
        margin-bottom: 20px;
    }
    .timeline-item {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        position: relative;
    }
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 40px;
        bottom: -20px;
        width: 2px;
        background: var(--gray-300);
    }
    .timeline-item:last-child::before {
        display: none;
    }
    .timeline-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        z-index: 1;
    }
    .timeline-icon.completed {
        background: var(--success);
    }
    .timeline-icon.pending {
        background: var(--warning);
    }
    .timeline-icon.cancelled {
        background: var(--danger);
    }
    .timeline-content {
        flex: 1;
        background: var(--bg-light);
        padding: 15px;
        border-radius: 8px;
    }
    .timeline-title {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 5px;
    }
    .timeline-date {
        font-size: 12px;
        color: var(--text-muted);
    }
    .evidence-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .evidence-card {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    .evidence-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    }
    .evidence-card img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        cursor: pointer;
    }
    .evidence-card-body {
        padding: 10px;
        background: white;
        font-size: 12px;
        color: var(--text-muted);
    }
    .map-preview {
        height: 300px;
        border-radius: 8px;
        margin-top: 10px;
    }
    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    .alert-info-custom {
        background: #d1ecf1;
        color: #0c5460;
        border-left: 4px solid #17a2b8;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .alert-warning-custom {
        background: #fff3cd;
        color: #856404;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .alert-success-custom {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid #28a745;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
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
                <span class="badge-role">Rol: <?php echo htmlspecialchars($_SESSION['rol_nombre']); ?></span>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="page-header">
            <h1 class="page-title">Detalles de Mantenimiento</h1>
            <p class="page-subtitle">MNT-<?php echo str_pad($tarea['id'], 3, '0', STR_PAD_LEFT); ?></p>
        </div>

        <!-- Header de la Tarea -->
        <div class="detail-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-tools"></i> 
                        MNT-<?php echo str_pad($tarea['id'], 3, '0', STR_PAD_LEFT); ?>
                    </h2>
                    <p class="mb-0 opacity-75">
                        <?php echo htmlspecialchars($tarea['inventario_id']); ?> - 
                        <?php echo htmlspecialchars($tarea['marca']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <span class="badge <?php echo $estado_class[$tarea['estado']] ?? 'badge-secondary'; ?>" 
                          style="font-size: 14px; padding: 8px 16px;">
                        <?php echo ucfirst(str_replace('_', ' ', $tarea['estado'])); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Alerta según estado -->
        <?php if ($tarea['estado'] == 'pendiente'): ?>
        <div class="alert-warning-custom">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Tarea Pendiente:</strong> Esperando que el técnico inicie el mantenimiento.
        </div>
        <?php elseif ($tarea['estado'] == 'en_proceso'): ?>
        <div class="alert-info-custom">
            <i class="fas fa-clock"></i>
            <strong>Tarea en Proceso:</strong> El técnico está trabajando en el mantenimiento.
        </div>
        <?php elseif ($tarea['estado'] == 'completado'): ?>
        <div class="alert-success-custom">
            <i class="fas fa-check-circle"></i>
            <strong>Tarea Completada:</strong> Esperando validación del administrador.
        </div>
        <?php elseif ($tarea['estado'] == 'validado'): ?>
        <div class="alert-success-custom">
            <i class="fas fa-clipboard-check"></i>
            <strong>Tarea Validada:</strong> El mantenimiento ha sido aprobado y cerrado.
        </div>
        <?php elseif ($tarea['estado'] == 'cancelado'): ?>
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i>
            <strong>Tarea Cancelada:</strong> Esta tarea fue cancelada y no requiere acción.
        </div>
        <?php endif; ?>

        <!-- Línea de Tiempo -->
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Línea de Tiempo</h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-icon completed">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">Programada</div>
                            <div class="timeline-date">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($tarea['programado_por'] ?? 'N/A'); ?> | 
                                <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_creacion'])); ?>
                            </div>
                            <div class="mt-2">
                                <strong>Fecha programada:</strong> <?php echo date('d/m/Y', strtotime($tarea['fecha_programada'])); ?> | 
                                <strong>Límite:</strong> <?php echo date('d/m/Y', strtotime($tarea['fecha_limite'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($tarea['fecha_inicio']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon completed">
                            <i class="fas fa-play"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">Iniciada</div>
                            <div class="timeline-date">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($tarea['tecnico_nombre'] ?? 'N/A'); ?> | 
                                <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_inicio'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($tarea['fecha_completado']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon completed">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">Completada</div>
                            <div class="timeline-date">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($tarea['tecnico_nombre'] ?? 'N/A'); ?> | 
                                <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_completado'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($tarea['fecha_validacion']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon <?php echo $tarea['estado'] == 'validado' ? 'completed' : 'cancelled'; ?>">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">Validada</div>
                            <div class="timeline-date">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($tarea['validado_por'] ?? 'N/A'); ?> | 
                                <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_validacion'])); ?>
                            </div>
                            <?php if (!empty($tarea['observaciones_rechazo'])): ?>
                            <div class="mt-2 text-danger">
                                <strong>Motivo del rechazo:</strong> <?php echo nl2br(htmlspecialchars($tarea['observaciones_rechazo'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Información de la Tarea -->
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list"></i> Información de la Tarea</h3>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-card">
                        <div class="detail-card-label">Cámara</div>
                        <div class="detail-card-value"><?php echo htmlspecialchars($tarea['inventario_id']); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-card-label">Marca</div>
                        <div class="detail-card-value"><?php echo htmlspecialchars($tarea['marca']); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-card-label">Modelo</div>
                        <div class="detail-card-value"><?php echo htmlspecialchars($tarea['modelo'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-card-label">Tipo de Cámara</div>
                        <div class="detail-card-value"><?php echo htmlspecialchars($tarea['tipo_camara'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-card-label">Tipo de Mantenimiento</div>
                        <div class="detail-card-value">
                            <span class="badge badge-<?php echo $tarea['tipo'] == 'preventivo' ? 'info' : 'warning'; ?>">
                                <?php echo ucfirst($tarea['tipo']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-card-label">Zona</div>
                        <div class="detail-card-value"><?php echo htmlspecialchars($tarea['zona']); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-card-label">Técnico Asignado</div>
                        <div class="detail-card-value"><?php echo htmlspecialchars($tarea['tecnico_nombre'] ?? 'Sin asignar'); ?></div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-card-label">Email Técnico</div>
                        <div class="detail-card-value"><?php echo htmlspecialchars($tarea['tecnico_email'] ?? 'N/A'); ?></div>
                    </div>
                </div>
                
                <div class="detail-card mt-3">
                    <div class="detail-card-label">Ubicación</div>
                    <div class="detail-card-value">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($tarea['direccion'] . ', ' . ($tarea['colonia'] ?? '')); ?>
                    </div>
                </div>
                
                <div class="detail-card mt-3">
                    <div class="detail-card-label">Número de Serie / IP</div>
                    <div class="detail-card-value">
                        <code><?php echo htmlspecialchars($tarea['numero_serie'] ?? 'N/A'); ?></code>
                    </div>
                </div>
                
                <div class="detail-card mt-3">
                    <div class="detail-card-label">Descripción del Trabajo</div>
                    <div class="detail-card-value"><?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?></div>
                </div>

                <?php if (!empty($tarea['observaciones'])): ?>
                <div class="detail-card mt-3">
                    <div class="detail-card-label">Observaciones del Técnico</div>
                    <div class="detail-card-value"><?php echo nl2br(htmlspecialchars($tarea['observaciones'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Evidencia Fotográfica -->
        <?php if (!empty($evidencias)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-camera"></i> Evidencia Fotográfica</h3>
                <span class="badge badge-primary"><?php echo count($evidencias); ?> fotos</span>
            </div>
            <div class="card-body">
                <div class="evidence-gallery">
                    <?php foreach ($evidencias as $evidencia): ?>
                    <div class="evidence-card">
                        <img src="../../<?php echo htmlspecialchars($evidencia['ruta_archivo']); ?>" 
                             alt="Evidencia"
                             onclick="window.open('../../<?php echo htmlspecialchars($evidencia['ruta_archivo']); ?>', '_blank')">
                        <div class="evidence-card-body">
                            <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($evidencia['fecha_subida'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Documentación Adjunta -->
        <?php if (!empty($tarea['evidencia_ruta'])): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> Documentación Adjunta</h3>
            </div>
            <div class="card-body">
                <a href="../../<?php echo htmlspecialchars($tarea['evidencia_ruta']); ?>" class="btn btn-outline-primary" target="_blank">
                    <i class="fas fa-download"></i> Descargar Documento
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ubicación en Mapa -->
        <?php if ($tarea['latitud'] && $tarea['longitud']): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-map-marked-alt"></i> Ubicación</h3>
            </div>
            <div class="card-body">
                <div id="mapPreview" class="map-preview"></div>
                <div class="mt-2">
                    <strong>Coordenadas:</strong> 
                    <code><?php echo $tarea['latitud']; ?>, <?php echo $tarea['longitud']; ?></code>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botones de Acción -->
        <div class="card">
            <div class="card-body">
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Regresar
                    </a>
                    
                    <?php if ($_SESSION['rol_id'] == 3 && $tarea['estado'] == 'pendiente'): ?>
                    <a href="ejecutar.php?id=<?php echo $tarea['id']; ?>" class="btn btn-success">
                        <i class="fas fa-play"></i> Iniciar Tarea
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['rol_id'] == 3 && $tarea['estado'] == 'en_proceso'): ?>
                    <a href="ejecutar.php?id=<?php echo $tarea['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Continuar / Completar
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['rol_id'] == 1 && $tarea['estado'] == 'completado'): ?>
                    <a href="validar.php?id=<?php echo $tarea['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-clipboard-check"></i> Validar Tarea
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['rol_id'] == 1 && in_array($tarea['estado'], ['pendiente', 'en_proceso'])): ?>
                    <a href="cancelar.php?id=<?php echo $tarea['id']; ?>" class="btn btn-danger"
                       onclick="return confirm('¿Estás seguro de cancelar esta tarea?')">
                        <i class="fas fa-times"></i> Cancelar Tarea
                    </a>
                    <?php endif; ?>
                    
                    <a href="../camaras/ver.php?id=<?php echo $tarea['camara_id']; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-video"></i> Ver Cámara
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Leaflet JS -->
    <?php if ($tarea['latitud'] && $tarea['longitud']): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    const map = L.map('mapPreview').setView([<?php echo $tarea['latitud']; ?>, <?php echo $tarea['longitud']; ?>], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    L.marker([<?php echo $tarea['latitud']; ?>, <?php echo $tarea['longitud']; ?>])
        .addTo(map)
        .bindPopup('<?php echo htmlspecialchars($tarea['inventario_id']); ?>')
        .openPopup();
    </script>
    <?php endif; ?>
    
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>