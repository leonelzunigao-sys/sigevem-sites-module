<?php
session_start();

// Solo Admin puede validar tareas
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
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
    c.direccion,
    c.colonia,
    c.zona,
    c.latitud,
    c.longitud,
    u.nombre_completo as tecnico_nombre,
    u2.nombre_completo as programado_por
FROM mantenimiento_tareas mt
LEFT JOIN camaras c ON mt.camara_id = c.id
LEFT JOIN usuarios u ON mt.tecnico_id = u.id
LEFT JOIN usuarios u2 ON mt.programado_por_id = u2.id
WHERE mt.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$tarea = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tarea) {
    $_SESSION['error_message'] = 'Tarea no encontrada';
    header('Location: index.php');
    exit;
}

// Verificar que la tarea esté completada (lista para validar)
if ($tarea['estado'] != 'completado') {
    $_SESSION['error_message'] = 'Esta tarea no está lista para validar (estado: ' . $tarea['estado'] . ')';
    header('Location: index.php');
    exit;
}

// Obtener evidencias fotográficas
$evidencias_sql = "SELECT * FROM evidencia_fotografica WHERE tarea_mantenimiento_id = :tarea_id ORDER BY fecha_subida ASC";
$evidencias_stmt = $pdo->prepare($evidencias_sql);
$evidencias_stmt->execute([':tarea_id' => $id]);
$evidencias = $evidencias_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener documentación adjunta (si existe)
$documentacion = null;
if (!empty($tarea['evidencia_ruta'])) {
    $documentacion = $tarea['evidencia_ruta'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validar Mantenimiento | SIGEVEM</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/mantenimiento.css">
    <style>
    .validation-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 30px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .task-detail-grid {
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
        height: 250px;
        border-radius: 8px;
        margin-top: 10px;
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
    .validation-actions {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }
    .validation-actions .btn {
        flex: 1;
        padding: 15px;
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
                <span class="badge-role">Rol: <?php echo htmlspecialchars($_SESSION['rol_nombre']); ?></span>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <div class="page-header">
            <h1 class="page-title">Validar Mantenimiento</h1>
            <p class="page-subtitle">MNT-<?php echo str_pad($tarea['id'], 3, '0', STR_PAD_LEFT); ?></p>
        </div>

        <!-- Header de Validación -->
        <div class="validation-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-tools"></i> 
                        <?php echo htmlspecialchars($tarea['inventario_id']); ?>
                    </h2>
                    <p class="mb-0 opacity-75">
                        <?php echo ucfirst($tarea['tipo']); ?> - 
                        <?php echo htmlspecialchars($tarea['tecnico_nombre']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <span class="badge badge-success" style="font-size: 14px; padding: 8px 16px;">
                        <i class="fas fa-check-circle"></i> Completado
                    </span>
                </div>
            </div>
        </div>

        <form action="validar_process.php" method="POST" id="validarForm">
            <input type="hidden" name="id" value="<?php echo $tarea['id']; ?>">
            
            <!-- Timeline del Proceso -->
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
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($tarea['programado_por']); ?> | 
                                    <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_creacion'])); ?>
                                </div>
                                <div class="mt-2">
                                    <strong>Fecha programada:</strong> <?php echo date('d/m/Y', strtotime($tarea['fecha_programada'])); ?> | 
                                    <strong>Límite:</strong> <?php echo date('d/m/Y', strtotime($tarea['fecha_limite'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-icon completed">
                                <i class="fas fa-play"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Iniciada</div>
                                <div class="timeline-date">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($tarea['tecnico_nombre']); ?> | 
                                    <i class="fas fa-clock"></i> <?php echo $tarea['fecha_inicio'] ? date('d/m/Y H:i', strtotime($tarea['fecha_inicio'])) : 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-icon completed">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Completada</div>
                                <div class="timeline-date">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($tarea['tecnico_nombre']); ?> | 
                                    <i class="fas fa-clock"></i> <?php echo $tarea['fecha_completado'] ? date('d/m/Y H:i', strtotime($tarea['fecha_completado'])) : 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-icon pending">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Validación Pendiente</div>
                                <div class="timeline-date">
                                    <i class="fas fa-clock"></i> Esperando validación del administrador
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información de la Tarea -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Información de la Tarea</h3>
                </div>
                <div class="card-body">
                    <div class="task-detail-grid">
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
                            <div class="detail-card-label">Técnico</div>
                            <div class="detail-card-value"><?php echo htmlspecialchars($tarea['tecnico_nombre']); ?></div>
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
                        <div class="detail-card-label">Descripción del Trabajo</div>
                        <div class="detail-card-value"><?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Reporte del Técnico -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fas fa-comment-alt"></i> Reporte del Técnico</h3>
                </div>
                <div class="card-body">
                    <div class="detail-card">
                        <div class="detail-card-label">Observaciones</div>
                        <div class="detail-card-value"><?php echo nl2br(htmlspecialchars($tarea['observaciones'] ?? 'Sin observaciones')); ?></div>
                    </div>
                </div>
            </div>

            <!-- Evidencia Fotográfica -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fas fa-camera"></i> Evidencia Fotográfica</h3>
                    <span class="badge badge-primary"><?php echo count($evidencias); ?> fotos</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($evidencias)): ?>
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
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No hay evidencia fotográfica registrada
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documentación Adjunta -->
            <?php if ($documentacion): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fas fa-file-alt"></i> Documentación Adjunta</h3>
                </div>
                <div class="card-body">
                    <a href="../../<?php echo htmlspecialchars($documentacion); ?>" class="btn btn-outline-primary" target="_blank">
                        <i class="fas fa-download"></i> Descargar Documento
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ubicación en Mapa -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3><i class="fas fa-map-marked-alt"></i> Ubicación</h3>
                </div>
                <div class="card-body">
                    <div id="mapPreview" class="map-preview"></div>
                </div>
            </div>

            <!-- Acciones de Validación -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-check"></i> Validación</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Decisión de Validación *</label>
                        <div class="d-flex gap-3">
                            <label class="radio-inline">
                                <input type="radio" name="decision" value="aprobar" required>
                                <span class="badge badge-success" style="font-size: 14px;">
                                    <i class="fas fa-check-circle"></i> Aprobar y Cerrar Tarea
                                </span>
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="decision" value="rechazar" required>
                                <span class="badge badge-danger" style="font-size: 14px;">
                                    <i class="fas fa-times-circle"></i> Rechazar y Devolver
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="rechazoMotivo" style="display: none;">
                        <label>Motivo del Rechazo *</label>
                        <textarea class="form-control" name="observaciones_rechazo" rows="3" 
                                  placeholder="Especifica el motivo del rechazo y lo que debe corregir el técnico..."></textarea>
                        <small class="form-text">El técnico será notificado y podrá corregir la tarea</small>
                    </div>

                    <div class="validation-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-check-circle"></i> Procesar Validación
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    // Mapa de ubicación
    <?php if ($tarea['latitud'] && $tarea['longitud']): ?>
    const map = L.map('mapPreview').setView([<?php echo $tarea['latitud']; ?>, <?php echo $tarea['longitud']; ?>], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    L.marker([<?php echo $tarea['latitud']; ?>, <?php echo $tarea['longitud']; ?>])
        .addTo(map)
        .bindPopup('<?php echo htmlspecialchars($tarea['inventario_id']); ?>')
        .openPopup();
    <?php else: ?>
    document.getElementById('mapPreview').innerHTML = '<div class="alert alert-warning">Sin coordenadas disponibles</div>';
    <?php endif; ?>

    // Mostrar/ocultar campo de rechazo
    document.querySelectorAll('input[name="decision"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const motivoDiv = document.getElementById('rechazoMotivo');
            const textarea = motivoDiv.querySelector('textarea');
            if (this.value === 'rechazar') {
                motivoDiv.style.display = 'block';
                textarea.required = true;
            } else {
                motivoDiv.style.display = 'none';
                textarea.required = false;
            }
        });
    });

    // Validar formulario
    document.getElementById('validarForm').addEventListener('submit', function(e) {
        const decision = document.querySelector('input[name="decision"]:checked');
        if (!decision) {
            e.preventDefault();
            alert('Por favor selecciona una decisión (Aprobar o Rechazar)');
            return false;
        }
        
        if (decision.value === 'rechazar') {
            const motivo = document.querySelector('textarea[name="observaciones_rechazo"]').value.trim();
            if (!motivo) {
                e.preventDefault();
                alert('El motivo de rechazo es obligatorio');
                return false;
            }
        }

        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        btn.disabled = true;
    });
    </script>
</body>
</html>