<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../auth/login.php'); exit; }
require_once '../../config/database.php';

$orden_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($orden_id <= 0) { header('Location: mantenimiento_listado.php'); exit; }

$sql = "SELECT sm.*, s.inventario_id, s.nombre as sitio_nombre, s.zona, u.nombre_completo as tecnico, up.nombre_completo as programador
        FROM sitios_mantenimiento sm
        JOIN sitios s ON sm.sitio_id = s.id
        JOIN usuarios u ON sm.tecnico_id = u.id
        JOIN usuarios up ON sm.programado_por_id = up.id
        WHERE sm.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $orden_id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) { header('Location: mantenimiento_listado.php'); exit; }

// Validar permisos: Técnico solo ve si es suya
if ($_SESSION['rol_id'] == 3 && $orden['tecnico_id'] != $_SESSION['usuario_id']) {
    $_SESSION['error_message'] = 'No tienes permisos para ver esta orden.';
    header('Location: mantenimiento_listado.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle Orden #<?php echo $orden['id']; ?> | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body>
    <header class="dashboard-header">
        <div class="header-left"><img src="../../assets/img/logo-ecatepec-largo.png" alt="SIGEVEM" class="header-logo"></div>
        <div class="header-center"><h2 class="system-title">SIGEVEM</h2><p class="system-subtitle">Sistema Integral de Gestión y Geolocalización de Infraestructura de Videovigilancia Municipal</p></div>
        <div class="header-right"><div class="user-badge"><span class="badge-datetime" id="headerDatetime"></span><span class="badge-role">Rol: <?php echo htmlspecialchars($_SESSION['rol_nombre']); ?></span></div></div>
    </header>
    <?php include '../../includes/sidebar.php'; ?>
    <main class="dashboard-content">
        <div class="page-header">
            <h1 class="page-title">Detalle de Orden #<?php echo $orden['id']; ?></h1>
            <a href="mantenimiento_listado.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <p><strong>Sitio:</strong> <?php echo htmlspecialchars($orden['inventario_id'] . ' - ' . $orden['sitio_nombre']); ?></p>
                        <p><strong>Tipo:</strong> <?php echo ucfirst($orden['tipo']); ?></p>
                        <p><strong>Estado:</strong> <span class="badge badge-<?php echo $orden['estado'] == 'completado' ? 'success' : ($orden['estado'] == 'en_proceso' ? 'info' : 'warning'); ?>"><?php echo ucfirst($orden['estado']); ?></span></p>
                        <p><strong>Fecha Programada:</strong> <?php echo date('d/m/Y', strtotime($orden['fecha_programada'])); ?></p>
                        <?php if ($orden['fecha_limite']): ?><p><strong>Fecha Límite:</strong> <?php echo date('d/m/Y', strtotime($orden['fecha_limite'])); ?></p><?php endif; ?>
                    </div>
                    <div>
                        <p><strong>Técnico Asignado:</strong> <?php echo htmlspecialchars($orden['tecnico']); ?></p>
                        <p><strong>Programado por:</strong> <?php echo htmlspecialchars($orden['programador']); ?></p>
                        <p><strong>Prioridad:</strong> <?php echo ucfirst($orden['prioridad'] ?? 'Media'); ?></p>
                    </div>
                </div>
                <hr>
                <h4>Descripción del Trabajo</h4>
                <p><?php echo nl2br(htmlspecialchars($orden['descripcion'])); ?></p>
                
                <?php if (!empty($orden['observaciones'])): ?>
                <hr>
                <h4>Observaciones Finales</h4>
                <p><?php echo nl2br(htmlspecialchars($orden['observaciones'])); ?></p>
                <?php endif; ?>

                <?php if (!empty($orden['evidencia_ruta'])): ?>
                <hr>
                <h4>Evidencia Fotográfica</h4>
                <img src="../../<?php echo htmlspecialchars($orden['evidencia_ruta']); ?>" alt="Evidencia" style="max-width: 100%; border-radius: 8px; max-height: 300px; object-fit: cover;">
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>