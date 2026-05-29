<?php
// Verificar que sea técnico
if ($_SESSION['rol_id'] != 3) {
    header('Location: index.php');
    exit;
}

// ============================================
// KPIs REALES - TÉCNICO
// ============================================
$stmt = $pdo->prepare("SELECT COUNT(*) FROM mantenimiento_tareas WHERE tecnico_id = ? AND estado = 'pendiente'");
$stmt->execute([$_SESSION['usuario_id']]);
$tareas_pendientes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM mantenimiento_tareas WHERE tecnico_id = ? AND estado = 'en_proceso'");
$stmt->execute([$_SESSION['usuario_id']]);
$tareas_en_proceso = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM mantenimiento_tareas WHERE tecnico_id = ? AND estado IN ('completado', 'validado')");
$stmt->execute([$_SESSION['usuario_id']]);
$tareas_completadas = $stmt->fetchColumn();

// ============================================
// MIS TAREAS ASIGNADAS (REALES)
// ============================================
$tareas_stmt = $pdo->prepare("
    SELECT mt.id, mt.estado, mt.tipo, mt.descripcion, mt.fecha_limite,
           c.inventario_id
    FROM mantenimiento_tareas mt
    LEFT JOIN camaras c ON mt.camara_id = c.id
    WHERE mt.tecnico_id = ?
    AND mt.estado IN ('pendiente', 'en_proceso', 'completado')
    ORDER BY
        CASE mt.estado
            WHEN 'en_proceso' THEN 1
            WHEN 'pendiente'  THEN 2
            WHEN 'completado' THEN 3
        END,
        mt.fecha_limite ASC
    LIMIT 5
");
$tareas_stmt->execute([$_SESSION['usuario_id']]);
$mis_tareas = $tareas_stmt->fetchAll(PDO::FETCH_ASSOC);
$total_tareas = $tareas_pendientes + $tareas_en_proceso;

// ============================================
// MIS CÁMARAS REGISTRADAS (REALES)
// ============================================
$camaras_stmt = $pdo->prepare("
    SELECT c.id, c.inventario_id, c.estatus, c.zona, c.direccion,
           c.fecha_registro
    FROM camaras c
    WHERE c.usuario_registro_id = ?
    ORDER BY c.fecha_registro DESC
    LIMIT 5
");
$camaras_stmt->execute([$_SESSION['usuario_id']]);
$mis_camaras = $camaras_stmt->fetchAll(PDO::FETCH_ASSOC);
$total_camaras = $pdo->prepare("SELECT COUNT(*) FROM camaras WHERE usuario_registro_id = ?");
$total_camaras->execute([$_SESSION['usuario_id']]);
$total_camaras = $total_camaras->fetchColumn();

// Fecha actual
//$fecha_actual = date('l, j \d\e F Y — H:i');

// Helper color estatus cámara
function estatus_class(string $estatus): string {
    $mapa = [
        'activa'          => 'success',
        'mantenimiento'   => 'warning',
        'fuera_servicio'  => 'danger',
        'pendiente'       => 'warning',
    ];
    return $mapa[strtolower($estatus)] ?? 'info';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Técnico | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
            <h1 class="page-title">Panel de Control</h1>
            <p class="page-subtitle">Tus tareas y cámaras asignadas</p>
            
        </div>

        <!-- KPI Cards -->
        <div class="kpi-container tecnico mb-4">
            <div class="kpi-card kpi-warning">
                <div class="kpi-content">
                    <div>
                        <div class="kpi-number"><?php echo $tareas_pendientes; ?></div>
                        <div class="kpi-label">Tareas Pendientes</div>
                        <div class="kpi-subtitle">Por ejecutar</div>
                    </div>
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
            <div class="kpi-card kpi-info">
                <div class="kpi-content">
                    <div>
                        <div class="kpi-number"><?php echo $tareas_en_proceso; ?></div>
                        <div class="kpi-label">En Proceso</div>
                        <div class="kpi-subtitle">Ejecutando</div>
                    </div>
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="kpi-card kpi-success">
                <div class="kpi-content">
                    <div>
                        <div class="kpi-number"><?php echo $tareas_completadas; ?></div>
                        <div class="kpi-label">Completadas</div>
                        <div class="kpi-subtitle">Total</div>
                    </div>
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="quick-actions">
            <h3>Acciones Rápidas</h3>
            <div class="actions-grid">
                <a href="../camaras/registro.php" class="btn-action btn-primary">
                    <i class="fas fa-plus"></i>
                    <span>Registrar Cámara</span>
                </a>
                <a href="../mapa/index.php" class="btn-action btn-secondary">
                    <i class="fas fa-map"></i>
                    <span>Ver Mapa</span>
                </a>
                <a href="../mantenimiento/index.php" class="btn-action btn-warning">
                    <i class="fas fa-tools"></i>
                    <span>Mis Mantenimientos</span>
                </a>
                <a href="../bitacora/index.php" class="btn-action btn-outline">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Mi Bitácora</span>
                </a>
            </div>
        </div>

        <!-- Two Columns -->
        <div class="dashboard-grid">

            <!-- Mis Tareas Asignadas -->
            <div class="card tasks-card">
                <div class="card-header">
                    <h3><i class="fas fa-tasks"></i> Mis Tareas Asignadas</h3>
                    <span class="badge-count"><?php echo $total_tareas; ?></span>
                </div>
                <div class="card-body">
                    <?php if (count($mis_tareas) > 0): ?>
                        <?php foreach ($mis_tareas as $tarea): ?>
                        <?php
                            $estado       = $tarea['estado'];
                            $status_class = $estado === 'en_proceso' ? 'status-warning' :
                                           ($estado === 'pendiente'  ? 'status-pending'  : 'status-success');
                            $estado_label = $estado === 'en_proceso' ? 'En Proceso' :
                                           ($estado === 'pendiente'  ? 'Pendiente'   : 'Completado');
                            $tarea_codigo = 'MNT-' . str_pad($tarea['id'], 3, '0', STR_PAD_LEFT);
                        ?>
                        <div class="task-item">
                            <div class="task-info">
                                <h4><?php echo htmlspecialchars($tarea['inventario_id'] ?? '—'); ?>
                                    <small class="text-muted" style="font-size:11px; margin-left:6px;"><?php echo $tarea_codigo; ?></small>
                                </h4>
                                <p><?php echo htmlspecialchars(substr($tarea['descripcion'], 0, 50)); ?> · <?php echo ucfirst($tarea['tipo']); ?></p>
                                <p class="task-deadline">
                                    <i class="fas fa-calendar"></i>
                                    Vence: <?php echo date('d/M/Y', strtotime($tarea['fecha_limite'])); ?>
                                </p>
                            </div>
                            <div class="task-status <?php echo $status_class; ?>">
                                <span class="badge"><?php echo $estado_label; ?></span>
                            </div>
                            
                        </div>
                        <?php endforeach; ?>
                        <a href="../mantenimiento/index.php" class="view-all">Ver todas mis tareas →</a>
                    <?php else: ?>
                        <p class="text-muted" style="text-align:center; padding: 30px 0;">
                            <i class="fas fa-tasks" style="font-size:32px; opacity:0.3; display:block; margin-bottom:10px;"></i>
                            No tienes tareas asignadas actualmente
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mis Cámaras Registradas -->
            <div class="card activity-card">
                <div class="card-header">
                    <h3><i class="fas fa-camera"></i> Mis Cámaras Registradas</h3>
                    <span class="badge-count"><?php echo $total_camaras; ?></span>
                </div>
                <div class="card-body">
                    <?php if (count($mis_camaras) > 0): ?>
                        <?php foreach ($mis_camaras as $cam): ?>
                        <div class="activity-item">
                            <div class="activity-dot <?php echo estatus_class($cam['estatus']); ?>"></div>
                            <div class="activity-content">
                                <p>
                                    <strong><?php echo htmlspecialchars($cam['inventario_id']); ?></strong>
                                    · <span class="text-<?php echo estatus_class($cam['estatus']); ?>">
                                        <?php echo ucfirst($cam['estatus']); ?>
                                    </span>
                                </p>
                                <p class="activity-detail">
                                    <?php echo htmlspecialchars($cam['zona'] . ' — ' . $cam['direccion']); ?>
                                </p>
                            </div>
                            <div class="activity-time">
                                <?php echo $cam['fecha_registro'] ? date('d/m', strtotime($cam['fecha_registro'])) : '—'; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <a href="../camaras/index.php" class="view-all">Ver todas mis cámaras →</a>
                    <?php else: ?>
                        <p class="text-muted" style="text-align:center; padding: 30px 0;">
                            <i class="fas fa-camera" style="font-size:32px; opacity:0.3; display:block; margin-bottom:10px;"></i>
                            No has registrado cámaras aún
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <script src="../../assets/js/dashboard.js"></script>

                            <script>
function actualizarFecha() {
    const ahora = new Date();
    const opciones = {
        weekday: 'short', day: 'numeric',
        month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
        hour12: false
    };
    document.getElementById('headerDatetime').textContent =
        ahora.toLocaleDateString('es-MX', opciones);
}
actualizarFecha();
setInterval(actualizarFecha, 60000);
</script>


</body>
</html>