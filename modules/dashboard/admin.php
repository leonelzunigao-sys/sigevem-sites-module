<?php
// Verificar que sea administrador
if ($_SESSION['rol_id'] != 1) {
    header('Location: index.php');
    exit;
}

// ============================================
// KPIs REALES - CÁMARAS
// ============================================
$kpi_total          = $pdo->query("SELECT COUNT(*) FROM camaras")->fetchColumn();
$kpi_activas        = $pdo->query("SELECT COUNT(*) FROM camaras WHERE estatus = 'activa'")->fetchColumn();
$kpi_mantenimiento  = $pdo->query("SELECT COUNT(*) FROM camaras WHERE estatus = 'mantenimiento'")->fetchColumn();
$kpi_fuera_servicio = $pdo->query("SELECT COUNT(*) FROM camaras WHERE estatus = 'fuera_servicio'")->fetchColumn();

// ============================================
// CÁMARAS PENDIENTES DE VALIDACIÓN (REALES)
// ============================================
$pendientes_stmt = $pdo->query("
    SELECT c.id, c.inventario_id, c.direccion, c.zona, c.referencias,
           cv.fecha_registro,
           u.nombre_completo AS tecnico_nombre
    FROM camaras_validacion cv
    LEFT JOIN camaras c ON cv.camara_id = c.id
    LEFT JOIN usuarios u ON cv.usuario_registro_id = u.id
    WHERE cv.estado = 'pendiente'
    ORDER BY cv.fecha_registro DESC
    LIMIT 5
");
$camaras_pendientes = $pendientes_stmt->fetchAll(PDO::FETCH_ASSOC);
$total_pendientes   = $pdo->query("SELECT COUNT(*) FROM camaras_validacion WHERE estado = 'pendiente'")->fetchColumn();

// ============================================
// ACTIVIDAD RECIENTE (REAL - DESDE BITÁCORA)
// ============================================
$actividad_stmt = $pdo->query("
    SELECT b.accion, b.modulo, b.descripcion, b.fecha,
           u.nombre_completo AS usuario_nombre
    FROM bitacora_sistema b
    LEFT JOIN usuarios u ON b.usuario_id = u.id
    ORDER BY b.fecha DESC
    LIMIT 5
");
$actividad_reciente = $actividad_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fecha actual
//$fecha_actual = date('l, j \d\e F Y — H:i');

// ============================================
// COLOR DEL DOT SEGÚN ACCIÓN
// ============================================
function dot_class(string $accion): string {
    $mapa = [
        'login'     => '',
        'logout'    => '',
        'registrar' => '',
        'crear'     => 'success',
        'aprobar'   => 'success',
        'completar' => 'success',
        'validar'   => 'success',
        'editar'    => 'info',
        'programar' => 'info',
        'iniciar'   => 'info',
        'exportar'  => 'info',
        'eliminar'  => 'danger',
        'rechazar'  => 'danger',
        'cancelar'  => 'danger',
    ];
    return $mapa[strtolower($accion)] ?? '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Administrador | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Panel de Control</h1>
            <p class="page-subtitle">Vista general del sistema de videovigilancia</p>
            
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-primary">
                <div class="kpi-number"><?php echo $kpi_total; ?></div>
                <div class="kpi-label">Cámaras</div>
                <div class="kpi-subtitle">Total de Cámaras</div>
                <div class="kpi-icon"><i class="fas fa-camera"></i></div>
            </div>
            <div class="kpi-card kpi-success">
                <div class="kpi-number"><?php echo $kpi_activas; ?></div>
                <div class="kpi-label">Cámaras Activas</div>
                <div class="kpi-subtitle">Operativas</div>
                <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="kpi-card kpi-warning">
                <div class="kpi-number"><?php echo $kpi_mantenimiento; ?></div>
                <div class="kpi-label">En Mantenimiento</div>
                <div class="kpi-subtitle">Programadas</div>
                <div class="kpi-icon"><i class="fas fa-tools"></i></div>
            </div>
            <div class="kpi-card kpi-danger">
                <div class="kpi-number"><?php echo $kpi_fuera_servicio; ?></div>
                <div class="kpi-label">Fuera de Servicio</div>
                <div class="kpi-subtitle">Requieren atención</div>
                <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="quick-actions">
            <h3>Acciones Rápidas</h3>
            <div class="actions-grid">
                <a href="../camaras/registro.php" class="btn-action btn-primary">
                    <i class="fas fa-camera"></i>
                    <span>Registrar Cámara</span>
                </a>
                <a href="../mapa/index.php" class="btn-action btn-secondary">
                    <i class="fas fa-map"></i>
                    <span>Ver Mapa</span>
                </a>
                <a href="../mantenimiento/programar.php" class="btn-action btn-outline">
                    <i class="fas fa-tools"></i>
                    <span>Programar Mant.</span>
                </a>
                <a href="../usuarios/index.php" class="btn-action btn-outline">
                    <i class="fas fa-users"></i>
                    <span>Gestionar Usuarios</span>
                </a>
            </div>
        </div>

        <!-- Two Columns -->
        <div class="dashboard-grid">

            <!-- Cámaras Pendientes de Validación -->
            <div class="card validation-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-clock"></i>
                        Cámaras Pendientes de Validación
                    </h3>
                    <span class="badge-count"><?php echo $total_pendientes; ?></span>
                </div>
                <div class="card-body">
                    <?php if (count($camaras_pendientes) > 0): ?>
                        <?php foreach ($camaras_pendientes as $cam): ?>
                        <div class="validation-item">
                            <div class="validation-info">
                                <h4><?php echo htmlspecialchars($cam['inventario_id']); ?></h4>
                                <p>Técnico: <?php echo htmlspecialchars($cam['tecnico_nombre'] ?? 'Sin asignar'); ?>
                                   · <?php echo $cam['fecha_registro'] ? date('d/m/Y', strtotime($cam['fecha_registro'])) : '—'; ?>
                                </p>
                                <p><?php echo htmlspecialchars($cam['zona'] . ' — ' . ($cam['referencias'] ?: $cam['direccion'])); ?></p>
                            </div>
                            
                        </div>
                        <?php endforeach; ?>
                        <a href="../camaras/index.php?filtro=pendiente" class="view-all">Ver todas las pendientes →</a>
                    <?php else: ?>
                        <p class="text-muted" style="text-align:center; padding: 30px 0;">
                            <i class="fas fa-check-circle" style="font-size:32px; opacity:0.3; display:block; margin-bottom:10px;"></i>
                            No hay cámaras pendientes de validación
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="card activity-card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-chart-line"></i>
                        Actividad Reciente
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (count($actividad_reciente) > 0): ?>
                        <?php foreach ($actividad_reciente as $act): ?>
                        <div class="activity-item">
                            <div class="activity-dot <?php echo dot_class($act['accion']); ?>"></div>
                            <div class="activity-content">
                                <p>
                                    <strong><?php echo htmlspecialchars($act['usuario_nombre'] ?? 'Sistema'); ?></strong>
                                    · <span class="text-primary"><?php echo htmlspecialchars(ucfirst($act['accion'])); ?></span>
                                </p>
                                <p class="activity-detail">
                                    <?php echo htmlspecialchars(ucfirst($act['modulo']) . ' — ' . $act['descripcion']); ?>
                                </p>
                            </div>
                            <div class="activity-time">
                                <?php echo (new DateTime($act['fecha']))->format('d/m H:i'); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <a href="../bitacora/index.php" class="view-all">Ver bitácora completa →</a>
                    <?php else: ?>
                        <p class="text-muted" style="text-align:center; padding: 30px 0;">
                            <i class="fas fa-history" style="font-size:32px; opacity:0.3; display:block; margin-bottom:10px;"></i>
                            No hay actividad reciente
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- Mobile Menu Toggle -->
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