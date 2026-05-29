<?php
session_start();

// Solo técnicos pueden ejecutar tareas
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 3) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

$id = $_GET['id'] ?? 0;

// Obtener tarea con información de cámara
$sql = "SELECT 
    mt.*,
    c.inventario_id,
    c.marca,
    c.modelo,
    c.direccion,
    c.colonia,
    c.zona,
    u.nombre_completo as programado_por
FROM mantenimiento_tareas mt
LEFT JOIN camaras c ON mt.camara_id = c.id
LEFT JOIN usuarios u ON mt.programado_por_id = u.id
WHERE mt.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$tarea = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tarea) {
    $_SESSION['error_message'] = 'Tarea no encontrada';
    header('Location: index.php');
    exit;
}

// Verificar que la tarea esté asignada a este técnico o sea Admin
if ($tarea['tecnico_id'] != $_SESSION['usuario_id'] && $_SESSION['rol_id'] != 1) {
    $_SESSION['error_message'] = 'No tienes permiso para ejecutar esta tarea';
    header('Location: index.php');
    exit;
}

// Verificar que la tarea esté en estado pendiente o en proceso
if (!in_array($tarea['estado'], ['pendiente', 'en_proceso'])) {
    $_SESSION['error_message'] = 'Esta tarea no puede ser ejecutada (estado: ' . $tarea['estado'] . ')';
    header('Location: index.php');
    exit;
}

// Obtener evidencias existentes
$evidencias_sql = "SELECT * FROM evidencia_fotografica WHERE tarea_mantenimiento_id = :tarea_id ORDER BY fecha_subida DESC";
$evidencias_stmt = $pdo->prepare($evidencias_sql);
$evidencias_stmt->execute([':tarea_id' => $id]);
$evidencias = $evidencias_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ejecutar Mantenimiento | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/mantenimiento.css">
    <style>
    .task-info {
        background: var(--bg-light);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .task-info-row {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid var(--gray-200);
    }
    .task-info-row:last-child {
        border-bottom: none;
    }
    .task-info-label {
        font-weight: 600;
        color: var(--text-muted);
        width: 150px;
        flex-shrink: 0;
    }
    .task-info-value {
        flex: 1;
        color: var(--text-dark);
    }
    .evidence-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .evidence-item {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .evidence-item img {
        width: 100%;
        height: 150px;
        object-fit: cover;
    }
    .evidence-item .btn {
        position: absolute;
        top: 8px;
        right: 8px;
        padding: 4px 8px;
        font-size: 12px;
    }
    .status-alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .status-alert.pending {
        background: #fff3cd;
        color: #856404;
        border-left: 4px solid #ffc107;
    }
    .status-alert.in_progress {
        background: #d1ecf1;
        color: #0c5460;
        border-left: 4px solid #17a2b8;
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
            <h1 class="page-title">Ejecutar Mantenimiento</h1>
            <p class="page-subtitle">MNT-<?php echo str_pad($tarea['id'], 3, '0', STR_PAD_LEFT); ?></p>
        </div>

        <!-- Alerta de estado -->
        <div class="status-alert <?php echo $tarea['estado'] == 'pendiente' ? 'pending' : 'in_progress'; ?>">
            <i class="fas fa-info-circle"></i>
            <strong>Estado actual: <?php echo ucfirst(str_replace('_', ' ', $tarea['estado'])); ?></strong>
            <?php if ($tarea['estado'] == 'pendiente'): ?>
            <p class="mb-0 mt-2">Inicia la tarea para comenzar a registrar el mantenimiento.</p>
            <?php else: ?>
            <p class="mb-0 mt-2">La tarea está en proceso. Completa el mantenimiento y sube la evidencia.</p>
            <?php endif; ?>
        </div>

        <form action="ejecutar_process.php" method="POST" enctype="multipart/form-data" id="ejecutarForm">
            <input type="hidden" name="id" value="<?php echo $tarea['id']; ?>">
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Información de la Tarea</h3>
                    <a href="index.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Regresar
                    </a>
                </div>
                <div class="card-body">
                    
                    <div class="task-info">
                        <div class="task-info-row">
                            <div class="task-info-label">Cámara:</div>
                            <div class="task-info-value">
                                <strong><?php echo htmlspecialchars($tarea['inventario_id']); ?></strong>
                                - <?php echo htmlspecialchars($tarea['marca']); ?>
                            </div>
                        </div>
                        <div class="task-info-row">
                            <div class="task-info-label">Ubicación:</div>
                            <div class="task-info-value">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($tarea['direccion'] . ', ' . ($tarea['colonia'] ?? '')); ?>
                            </div>
                        </div>
                        <div class="task-info-row">
                            <div class="task-info-label">Zona:</div>
                            <div class="task-info-value">
                                <span class="badge badge-secondary"><?php echo htmlspecialchars($tarea['zona']); ?></span>
                            </div>
                        </div>
                        <div class="task-info-row">
                            <div class="task-info-label">Tipo:</div>
                            <div class="task-info-value">
                                <span class="badge badge-<?php echo $tarea['tipo'] == 'preventivo' ? 'info' : 'warning'; ?>">
                                    <?php echo ucfirst($tarea['tipo']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="task-info-row">
                            <div class="task-info-label">Descripción:</div>
                            <div class="task-info-value"><?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?></div>
                        </div>
                        <div class="task-info-row">
                            <div class="task-info-label">Fecha Programada:</div>
                            <div class="task-info-value"><?php echo date('d/m/Y', strtotime($tarea['fecha_programada'])); ?></div>
                        </div>
                        <div class="task-info-row">
                            <div class="task-info-label">Fecha Límite:</div>
                            <div class="task-info-value">
                                <?php echo date('d/m/Y', strtotime($tarea['fecha_limite'])); ?>
                                <?php 
                                $dias_restantes = (strtotime($tarea['fecha_limite']) - time()) / 86400;
                                if ($dias_restantes < 3 && $tarea['estado'] != 'completado'): 
                                ?>
                                <span class="badge badge-danger ml-2">⚠️ Vence en <?php echo floor($dias_restantes); ?> días</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="task-info-row">
                            <div class="task-info-label">Programado por:</div>
                            <div class="task-info-value"><?php echo htmlspecialchars($tarea['programado_por']); ?></div>
                        </div>
                    </div>

                    <!-- Sección: Acción -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-play-circle"></i> Acción
                        </div>
                        
                        <?php if ($tarea['estado'] == 'pendiente'): ?>
                        <div class="form-group">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="iniciar_tarea" value="1">
                                <span><strong>Iniciar tarea</strong> (cambiará el estado a "En Proceso")</span>
                            </label>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="completar_tarea" value="1" 
                                       <?php echo $tarea['estado'] == 'completado' ? 'checked disabled' : ''; ?>>
                                <span><strong>Marcar como completada</strong> (requiere evidencia)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Sección: Evidencia -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-camera"></i> Evidencia Fotográfica
                        </div>
                        
                        <div class="form-group">
                            <label>Subir fotos del mantenimiento *</label>
                            <input type="file" class="form-control" name="evidencia[]" multiple accept="image/*">
                            <small class="form-text">Puedes seleccionar múltiples imágenes (JPG, PNG - Máx. 5MB cada una)</small>
                        </div>

                        <?php if (!empty($evidencias)): ?>
                        <label class="mt-3">Evidencia existente:</label>
                        <div class="evidence-grid">
                            <?php foreach ($evidencias as $evidencia): ?>
                            <div class="evidence-item">
                                <img src="../../<?php echo htmlspecialchars($evidencia['ruta_archivo']); ?>" alt="Evidencia">
                                <a href="eliminar_evidencia.php?id=<?php echo $evidencia['id']; ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('¿Eliminar esta evidencia?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sección: Observaciones -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-comment-alt"></i> Observaciones del Técnico
                        </div>
                        
                        <div class="form-group">
                            <label for="observaciones">Reporte del trabajo realizado *</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="4" 
                                      placeholder="Describe detalladamente el trabajo realizado, piezas cambiadas, problemas encontrados, etc." required></textarea>
                        </div>
                    </div>

                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </main>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
    document.getElementById('ejecutarForm').addEventListener('submit', function(e) {
        const completarTarea = document.querySelector('input[name="completar_tarea"]').checked;
        const evidenciaInput = document.querySelector('input[name="evidencia[]"]');
        const observaciones = document.getElementById('observaciones').value.trim();
        
        if (completarTarea && evidenciaInput.files.length === 0) {
            const evidenciasExistentes = document.querySelectorAll('.evidence-item').length;
            if (evidenciasExistentes === 0) {
                e.preventDefault();
                alert('Debes subir al menos una foto de evidencia para completar la tarea');
                return false;
            }
        }
        
        if (!observaciones) {
            e.preventDefault();
            alert('Las observaciones del trabajo realizado son obligatorias');
            document.getElementById('observaciones').focus();
            return false;
        }

        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btn.disabled = true;
    });
    </script>
</body>
</html>