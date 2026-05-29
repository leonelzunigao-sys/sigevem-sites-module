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

    $id                    = $_POST['id'];
    $decision              = $_POST['decision'];
    $observaciones_rechazo = trim($_POST['observaciones_rechazo'] ?? '');
    $validado_por_id       = $_SESSION['usuario_id'];

    if (!in_array($decision, ['aprobar', 'rechazar'])) {
        throw new Exception('Decisión de validación no válida');
    }

    if ($decision === 'rechazar' && empty($observaciones_rechazo)) {
        throw new Exception('El motivo de rechazo es obligatorio');
    }

    // Obtener tarea con datos relacionados
    $tarea_stmt = $pdo->prepare("SELECT mt.*, c.inventario_id,
                                         u.nombre_completo as tecnico_nombre,
                                         u.email as tecnico_email
                                  FROM mantenimiento_tareas mt
                                  LEFT JOIN camaras c ON mt.camara_id = c.id
                                  LEFT JOIN usuarios u ON mt.tecnico_id = u.id
                                  WHERE mt.id = :id");
    $tarea_stmt->execute([':id' => $id]);
    $tarea = $tarea_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarea) throw new Exception('Tarea no encontrada');

    if ($tarea['estado'] != 'completado') {
        throw new Exception('Esta tarea no está lista para validar (estado: ' . $tarea['estado'] . ')');
    }

    $tarea_codigo = 'MNT-' . str_pad($id, 3, '0', STR_PAD_LEFT);

    if ($decision === 'aprobar') {
        $pdo->prepare("UPDATE mantenimiento_tareas SET
            estado = 'validado', validado_por_id = :validado_por_id, fecha_validacion = NOW()
            WHERE id = :id")
        ->execute([':id' => $id, ':validado_por_id' => $validado_por_id]);

        $pdo->prepare("UPDATE camaras SET estatus = 'activa', fecha_actualizacion = NOW() WHERE id = :camara_id")
            ->execute([':camara_id' => $tarea['camara_id']]);

        $accion      = 'validar';
        $descripcion = "Validó {$tarea_codigo} — Cámara: {$tarea['inventario_id']}, Técnico: {$tarea['tecnico_nombre']}";
        $msg         = 'Tarea validada exitosamente. La cámara ha sido marcada como "Activa".';

    } else {
        $pdo->prepare("UPDATE mantenimiento_tareas SET
            estado = 'en_proceso', validado_por_id = :validado_por_id,
            fecha_validacion = NOW(), observaciones_rechazo = :observaciones_rechazo
            WHERE id = :id")
        ->execute([
            ':id'                    => $id,
            ':validado_por_id'       => $validado_por_id,
            ':observaciones_rechazo' => $observaciones_rechazo,
        ]);

        $accion      = 'rechazar';
        $descripcion = "Rechazó {$tarea_codigo} — Cámara: {$tarea['inventario_id']} — Motivo: {$observaciones_rechazo}";
        $msg         = 'Tarea rechazada. Ha sido devuelta al técnico para corrección.';
    }

    // Notificación por email al técnico
    if (!empty($tarea['tecnico_email'])) {
        $asunto  = ($decision === 'aprobar' ? 'Tarea validada' : 'Tarea rechazada') . " - {$tarea_codigo}";
        $mensaje = "Hola {$tarea['tecnico_nombre']},\n\n";

        if ($decision === 'aprobar') {
            $mensaje .= "Tu tarea {$tarea_codigo} ha sido VALIDADA exitosamente.\n";
            $mensaje .= "Cámara: {$tarea['inventario_id']} — Estado: VALIDADO\n\n¡Gracias por tu trabajo!\n";
        } else {
            $mensaje .= "Tu tarea {$tarea_codigo} ha sido RECHAZADA.\n";
            $mensaje .= "Cámara: {$tarea['inventario_id']} — Estado: EN PROCESO (requiere corrección)\n\n";
            $mensaje .= "Motivo: {$observaciones_rechazo}\n\nPor favor corrige los puntos mencionados.\n";
        }

        $mensaje .= "\nSaludos,\nSistema SIGEVEM";
        $headers  = "From: noreply@sigevem.gob.mx\r\nReply-To: soporte@sigevem.gob.mx\r\n";
        @mail($tarea['tecnico_email'], $asunto, $mensaje, $headers);
    }

    $pdo->commit();

    registrar_bitacora($pdo, $validado_por_id, $accion, 'mantenimiento', $descripcion, $id);

    $_SESSION['success_message'] = $msg;
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: validar.php?id=' . $_POST['id']);
    exit;
}
?>