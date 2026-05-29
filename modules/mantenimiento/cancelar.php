<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

$id = $_GET['id'] ?? 0;

try {
    $tarea_stmt = $pdo->prepare("SELECT mt.*, c.inventario_id, c.estatus as camara_estatus,
                                          u.nombre_completo as tecnico_nombre, u.email as tecnico_email
                                  FROM mantenimiento_tareas mt
                                  LEFT JOIN camaras c ON mt.camara_id = c.id
                                  LEFT JOIN usuarios u ON mt.tecnico_id = u.id
                                  WHERE mt.id = :id");
    $tarea_stmt->execute([':id' => $id]);
    $tarea = $tarea_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarea) throw new Exception('Tarea no encontrada');

    if (!in_array($tarea['estado'], ['pendiente', 'en_proceso'])) {
        throw new Exception('Solo se pueden cancelar tareas en estado "Pendiente" o "En Proceso"');
    }

    $pdo->beginTransaction();

    $tarea_codigo = 'MNT-' . str_pad($id, 3, '0', STR_PAD_LEFT);

    $pdo->prepare("UPDATE mantenimiento_tareas SET estado = 'cancelado', fecha_completado = NOW() WHERE id = :id")
        ->execute([':id' => $id]);

    // Si la cámara estaba en mantenimiento, regresarla a activa
    if ($tarea['camara_estatus'] == 'mantenimiento') {
        $pdo->prepare("UPDATE camaras SET estatus = 'activa', fecha_actualizacion = NOW() WHERE id = :camara_id")
            ->execute([':camara_id' => $tarea['camara_id']]);
    }

    // Notificación por email al técnico
    if (!empty($tarea['tecnico_email'])) {
        $asunto  = "Tarea cancelada - {$tarea_codigo}";
        $mensaje = "Hola {$tarea['tecnico_nombre']},\n\n";
        $mensaje .= "La tarea {$tarea_codigo} ha sido CANCELADA.\n";
        $mensaje .= "Cámara: {$tarea['inventario_id']} — Estado: CANCELADO\n\n";
        $mensaje .= "Si tienes dudas, contacta al administrador.\n\nSaludos,\nSistema SIGEVEM";
        $headers  = "From: noreply@sigevem.gob.mx\r\nReply-To: soporte@sigevem.gob.mx\r\n";
        @mail($tarea['tecnico_email'], $asunto, $mensaje, $headers);
    }

    $pdo->commit();

    registrar_bitacora(
        $pdo,
        $_SESSION['usuario_id'],
        'cancelar',
        'mantenimiento',
        "Canceló {$tarea_codigo} — Cámara: {$tarea['inventario_id']}, Técnico: {$tarea['tecnico_nombre']}",
        $id
    );

    $_SESSION['success_message'] = 'Tarea cancelada exitosamente';
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
?>