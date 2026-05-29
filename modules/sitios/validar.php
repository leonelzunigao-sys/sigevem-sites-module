<?php
session_start();

// Solo Admin (1) y Supervisor (2) pueden validar
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol_id'] != 1 && $_SESSION['rol_id'] != 2)) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

$sitio_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$accion   = isset($_GET['accion']) ? $_GET['accion'] : '';

if ($sitio_id <= 0 || !in_array($accion, ['aprobar', 'rechazar'])) {
    $_SESSION['error_message'] = 'Acción no válida';
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $usuario_id = $_SESSION['usuario_id'];
    $nuevo_estado_val = ($accion === 'aprobar') ? 'aprobada' : 'rechazada';
    $nuevo_estado_sitio = ($accion === 'aprobar') ? 'activo' : 'pendiente';

    // 1. Actualizar tabla principal de sitios
    $update_sql = "UPDATE sitios SET 
                    validacion_estado = :val_estado, 
                    estado = :estado, 
                    fecha_actualizacion = NOW() 
                   WHERE id = :id";
    
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute([
        ':val_estado' => $nuevo_estado_val,
        ':estado'     => $nuevo_estado_sitio,
        ':id'         => $sitio_id
    ]);

    // 2. Registrar historial en sitios_validacion
    $val_sql = "INSERT INTO sitios_validacion (
                    sitio_id, usuario_validacion_id, estado, fecha_validacion
                ) VALUES (:sitio_id, :usuario_id, :estado, NOW())";
                
    $pdo->prepare($val_sql)->execute([
        ':sitio_id'    => $sitio_id,
        ':usuario_id'  => $usuario_id,
        ':estado'      => $nuevo_estado_val
    ]);

    // 3. Bitácora
    $mensaje_bitacora = ($accion === 'aprobar') 
        ? "Aprobó sitio SIT-{$sitio_id}" 
        : "Rechazó sitio SIT-{$sitio_id}";
        
    registrar_bitacora($pdo, $usuario_id, 'validar', 'sitios', $mensaje_bitacora, $sitio_id);

    $pdo->commit();

    $_SESSION['success_message'] = ($accion === 'aprobar') 
        ? 'Sitio aprobado correctamente.' 
        : 'Sitio rechazado correctamente.';
        
    header('Location: ver.php?id=' . $sitio_id);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error al procesar la validación: ' . $e->getMessage();
    header('Location: ver.php?id=' . $sitio_id);
    exit;
}
?>