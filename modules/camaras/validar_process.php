<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $camara_id              = $_POST['camara_id'];
    $decision               = $_POST['decision'];
    $observaciones_rechazo  = trim($_POST['observaciones_rechazo'] ?? '');
    $usuario_validacion_id  = $_SESSION['usuario_id'];

    // Verificar que la cámara exista y obtener su inventario_id
    $check_stmt = $pdo->prepare("SELECT id, inventario_id FROM camaras WHERE id = :id");
    $check_stmt->execute([':id' => $camara_id]);
    $camara = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$camara) {
        throw new Exception('Cámara no encontrada');
    }

    if ($decision === 'aprobar') {
        $pdo->prepare("UPDATE camaras SET estatus = 'activa', fecha_actualizacion = NOW() WHERE id = :id")
            ->execute([':id' => $camara_id]);

        $pdo->prepare("UPDATE camaras_validacion SET
            estado = 'aprobada',
            usuario_validacion_id = :usuario_validacion_id,
            fecha_validacion = NOW(),
            fecha_activacion = NOW()
            WHERE camara_id = :camara_id")
        ->execute([
            ':camara_id'             => $camara_id,
            ':usuario_validacion_id' => $usuario_validacion_id,
        ]);

        $accion      = 'aprobar';
        $descripcion = "Aprobó y activó cámara: {$camara['inventario_id']}";

    } elseif ($decision === 'rechazar') {
        $pdo->prepare("UPDATE camaras SET fecha_actualizacion = NOW() WHERE id = :id")
            ->execute([':id' => $camara_id]);

        $pdo->prepare("UPDATE camaras_validacion SET
            estado = 'rechazada',
            usuario_validacion_id = :usuario_validacion_id,
            observaciones_rechazo = :observaciones_rechazo,
            fecha_validacion = NOW()
            WHERE camara_id = :camara_id")
        ->execute([
            ':camara_id'              => $camara_id,
            ':usuario_validacion_id'  => $usuario_validacion_id,
            ':observaciones_rechazo'  => $observaciones_rechazo,
        ]);

        $accion      = 'rechazar';
        $descripcion = "Rechazó cámara: {$camara['inventario_id']}";
        if (!empty($observaciones_rechazo)) {
            $descripcion .= " — Motivo: {$observaciones_rechazo}";
        }

    } else {
        throw new Exception('Decisión no válida');
    }

    $pdo->commit();

    registrar_bitacora(
        $pdo,
        $usuario_validacion_id,
        $accion,
        'camaras',
        $descripcion,
        $camara_id
    );

    $accion_msg = $decision === 'aprobar' ? 'aprobada' : 'rechazada';
    $_SESSION['success_message'] = "Cámara {$accion_msg} exitosamente";
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: ver.php?id=' . $_POST['camara_id']);
    exit;
}
?>