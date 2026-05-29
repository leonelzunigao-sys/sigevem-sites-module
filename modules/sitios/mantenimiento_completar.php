<?php
session_start();

// Validar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

$mantenimiento_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($mantenimiento_id <= 0) {
    header('Location: mantenimiento_listado.php');
    exit;
}

// Obtener datos de la orden de mantenimiento
$sql = "SELECT 
            sm.*,
            s.inventario_id as sitio_inventario,
            s.nombre as sitio_nombre,
            u.nombre_completo as tecnico_nombre
        FROM sitios_mantenimiento sm
        JOIN sitios s ON sm.sitio_id = s.id
        JOIN usuarios u ON sm.tecnico_id = u.id
        WHERE sm.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $mantenimiento_id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    $_SESSION['error_message'] = 'Orden de mantenimiento no encontrada';
    header('Location: mantenimiento_listado.php');
    exit;
}

// 🛡️ VALIDACIÓN DE PERMISOS POR ROL
// - Admin (1) y Supervisor (2): Pueden completar cualquier orden
// - Técnico (3): Solo puede completar órdenes asignadas a él
if ($_SESSION['rol_id'] == 3) {
    // Si es técnico, verificar que la orden esté asignada a él
    if ($orden['tecnico_id'] != $_SESSION['usuario_id']) {
        $_SESSION['error_message'] = 'No tienes permisos para completar esta orden. No está asignada a ti.';
        header('Location: mantenimiento_listado.php');
        exit;
    }
} elseif ($_SESSION['rol_id'] != 1 && $_SESSION['rol_id'] != 2) {
    // Si no es Admin, Supervisor ni Técnico, denegar acceso
    header('Location: ../dashboard/index.php');
    exit;
}

// Validar que no esté ya completada
if ($orden['estado'] === 'completado') {
    $_SESSION['error_message'] = 'Esta orden ya fue completada';
    header('Location: mantenimiento_listado.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Mantenimiento | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    
    <style>
    .readonly-field {
        background-color: var(--bg-light);
        cursor: not-allowed;
    }
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: bold;
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
            <h1 class="page-title">Completar Orden #<?php echo $orden['id']; ?></h1>
            <p class="page-subtitle">Sitio: <?php echo htmlspecialchars($orden['sitio_inventario'] . ' — ' . $orden['sitio_nombre']); ?></p>
        </div>

        <form action="mantenimiento_completar_process.php" method="POST" enctype="multipart/form-data" id="completarForm">
            <input type="hidden" name="mantenimiento_id" value="<?php echo $orden['id']; ?>">
            <input type="hidden" name="sitio_id" value="<?php echo $orden['sitio_id']; ?>">
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-check"></i> Datos de la Orden</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Técnico Asignado</label>
                            <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($orden['tecnico_nombre']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Tipo de Mantenimiento</label>
                            <input type="text" class="form-control readonly-field" value="<?php echo ucfirst($orden['tipo']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Fecha Programada</label>
                            <input type="text" class="form-control readonly-field" value="<?php echo date('d/m/Y', strtotime($orden['fecha_programada'])); ?>" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Descripción Original</label>
                        <textarea class="form-control readonly-field" rows="2" readonly><?php echo htmlspecialchars($orden['descripcion']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-tools"></i> Reporte de Trabajo</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="observaciones">Observaciones Finales *</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4" 
                                  placeholder="Describa brevemente qué se reparó, cambió o ajustó..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="evidencia">Evidencia Fotográfica</label>
                        <input type="file" class="form-control" id="evidencia" name="evidencia" accept="image/*">
                        <small class="form-text">Suba una foto del trabajo realizado (Opcional pero recomendado)</small>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="mantenimiento_listado.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-check-double"></i> Finalizar y Cerrar Orden
                </button>
            </div>
        </form>
    </main>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
    document.getElementById('completarForm').addEventListener('submit', function(e) {
        const obs = document.getElementById('observaciones').value;
        if (!obs.trim()) {
            e.preventDefault();
            alert('Por favor ingrese observaciones finales antes de cerrar.');
            return false;
        }
        if (!confirm('¿Está seguro de cerrar esta orden? El sitio volverá a estado "Activo".')) {
            e.preventDefault();
            return false;
        }
        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btn.disabled = true;
    });
    </script>
</body>
</html>