<?php
session_start();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol_id'] != 3 && $_SESSION['rol_id'] != 1)) {
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

    $id              = $_POST['id'];
    $iniciar_tarea   = isset($_POST['iniciar_tarea']) ? 1 : 0;
    $completar_tarea = isset($_POST['completar_tarea']) ? 1 : 0;
    $observaciones   = trim($_POST['observaciones']);
    $tecnico_id      = $_SESSION['usuario_id'];

    if (empty($observaciones)) {
        throw new Exception('Las observaciones del trabajo realizado son obligatorias');
    }

    // Obtener tarea actual
    $tarea_stmt = $pdo->prepare("SELECT mt.*, c.inventario_id
                                  FROM mantenimiento_tareas mt
                                  LEFT JOIN camaras c ON mt.camara_id = c.id
                                  WHERE mt.id = :id");
    $tarea_stmt->execute([':id' => $id]);
    $tarea = $tarea_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarea) throw new Exception('Tarea no encontrada');

    if ($tarea['tecnico_id'] != $tecnico_id && $_SESSION['rol_id'] != 1) {
        throw new Exception('No tienes permiso para ejecutar esta tarea');
    }

    $tarea_codigo  = 'MNT-' . str_pad($id, 3, '0', STR_PAD_LEFT);
    $nuevo_estado  = $tarea['estado'];
    $fecha_inicio  = null;
    $fecha_completado = null;

    if ($tarea['estado'] == 'pendiente' && $iniciar_tarea) {
        $nuevo_estado = 'en_proceso';
        $fecha_inicio = date('Y-m-d H:i:s');
    }

    if ($tarea['estado'] == 'en_proceso' && $completar_tarea) {
        $nuevo_estado     = 'completado';
        $fecha_completado = date('Y-m-d H:i:s');
    }

    // Validar evidencia si se completa
    if ($completar_tarea && $nuevo_estado == 'completado') {
        $ev_stmt = $pdo->prepare("SELECT COUNT(*) FROM evidencia_fotografica WHERE tarea_mantenimiento_id = :tarea_id");
        $ev_stmt->execute([':tarea_id' => $id]);
        $evidencias_existentes = $ev_stmt->fetchColumn();

        $evidencias_subidas = 0;
        if (isset($_FILES['evidencia']) && is_array($_FILES['evidencia']['name'])) {
            foreach ($_FILES['evidencia']['name'] as $key => $name) {
                if ($_FILES['evidencia']['error'][$key] === UPLOAD_ERR_OK) $evidencias_subidas++;
            }
        }

        if ($evidencias_existentes + $evidencias_subidas == 0) {
            throw new Exception('Debes subir al menos una foto de evidencia para completar la tarea');
        }
    }

    // Procesar evidencias fotográficas
    if (isset($_FILES['evidencia']) && is_array($_FILES['evidencia']['name'])) {
        $upload_dir = '../../assets/uploads/mantenimientos/evidencias/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

        foreach ($_FILES['evidencia']['name'] as $key => $name) {
            if ($_FILES['evidencia']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['evidencia']['type'][$key], $allowed_types)) {
                throw new Exception('Tipo de archivo no permitido. Solo imágenes JPG, PNG o GIF');
            }
            if ($_FILES['evidencia']['size'][$key] > 5 * 1024 * 1024) {
                throw new Exception('Las imágenes no deben superar los 5MB');
            }

            $file_extension = pathinfo($name, PATHINFO_EXTENSION);
            $file_name      = 'evidencia_' . $id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path      = $upload_dir . $file_name;

            if (!move_uploaded_file($_FILES['evidencia']['tmp_name'][$key], $file_path)) {
                throw new Exception('Error al subir la evidencia: ' . $name);
            }

            $pdo->prepare("INSERT INTO evidencia_fotografica (
                tarea_mantenimiento_id, ruta_archivo, nombre_archivo, tipo, fecha_subida, usuario_id
            ) VALUES (
                :tarea_mantenimiento_id, :ruta_archivo, :nombre_archivo, 'mantenimiento', NOW(), :usuario_id
            )")->execute([
                ':tarea_mantenimiento_id' => $id,
                ':ruta_archivo'           => 'assets/uploads/mantenimientos/evidencias/' . $file_name,
                ':nombre_archivo'         => $file_name,
                ':usuario_id'             => $tecnico_id,
            ]);
        }
    }

    // Actualizar tarea
    $pdo->prepare("UPDATE mantenimiento_tareas SET
        estado = :estado,
        observaciones = :observaciones,
        fecha_inicio = COALESCE(fecha_inicio, :fecha_inicio),
        fecha_completado = :fecha_completado
        WHERE id = :id")
    ->execute([
        ':id'               => $id,
        ':estado'           => $nuevo_estado,
        ':observaciones'    => $observaciones,
        ':fecha_inicio'     => $fecha_inicio,
        ':fecha_completado' => $fecha_completado,
    ]);

    // Si se completó, marcar cámara como "mantenimiento"
    if ($nuevo_estado == 'completado' && $tarea['estado'] != 'completado') {
        $pdo->prepare("UPDATE camaras SET estatus = 'mantenimiento', fecha_actualizacion = NOW() WHERE id = :camara_id")
            ->execute([':camara_id' => $tarea['camara_id']]);
    }

    $pdo->commit();

    // Determinar acción y descripción para bitácora
    if ($nuevo_estado == 'completado') {
        $accion      = 'completar';
        $descripcion = "Completó {$tarea_codigo} — Cámara: {$tarea['inventario_id']}";
        $msg         = 'Tarea completada exitosamente. La cámara ha sido marcada como "En Mantenimiento".';
    } elseif ($nuevo_estado == 'en_proceso') {
        $accion      = 'iniciar';
        $descripcion = "Inició {$tarea_codigo} — Cámara: {$tarea['inventario_id']}";
        $msg         = 'Tarea iniciada. Estado cambiado a "En Proceso".';
    } else {
        $accion      = 'editar';
        $descripcion = "Actualizó observaciones de {$tarea_codigo}";
        $msg         = 'Observaciones guardadas exitosamente.';
    }

    registrar_bitacora($pdo, $tecnico_id, $accion, 'mantenimiento', $descripcion, $id);

    $_SESSION['success_message'] = $msg;
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: ejecutar.php?id=' . $_POST['id']);
    exit;
}
?>