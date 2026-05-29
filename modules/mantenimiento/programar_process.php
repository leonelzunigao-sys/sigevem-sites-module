<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] == 3) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: programar.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $camara_id         = $_POST['camara_id'];
    $tecnico_id        = $_POST['tecnico_id'];
    $tipo              = $_POST['tipo'];
    $descripcion       = trim($_POST['descripcion']);
    $fecha_programada  = $_POST['fecha_programada'];
    $fecha_limite      = $_POST['fecha_limite'];
    $notificar_email   = isset($_POST['notificar_email']) ? 1 : 0;
    $programado_por_id = $_SESSION['usuario_id'];

    if (empty($camara_id) || empty($tecnico_id) || empty($tipo) ||
        empty($descripcion) || empty($fecha_programada) || empty($fecha_limite)) {
        throw new Exception('Todos los campos marcados con * son obligatorios');
    }

    $check_camara = $pdo->prepare("SELECT id, inventario_id FROM camaras WHERE id = :id");
    $check_camara->execute([':id' => $camara_id]);
    $camara = $check_camara->fetch(PDO::FETCH_ASSOC);
    if (!$camara) throw new Exception('La cámara seleccionada no existe');

    $check_tecnico = $pdo->prepare("SELECT id, nombre_completo, email FROM usuarios WHERE id = :id AND rol_id = 3");
    $check_tecnico->execute([':id' => $tecnico_id]);
    $tecnico = $check_tecnico->fetch(PDO::FETCH_ASSOC);
    if (!$tecnico) throw new Exception('El técnico seleccionado no existe');

    if (strtotime($fecha_limite) < strtotime($fecha_programada)) {
        throw new Exception('La fecha límite debe ser mayor o igual a la fecha programada');
    }

    // Documentación opcional
    $documentacion_ruta = null;
    if (isset($_FILES['documentacion']) && $_FILES['documentacion']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/uploads/mantenimientos/documentos/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

        $allowed_types = ['application/pdf', 'application/msword',
                          'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                          'image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($_FILES['documentacion']['type'], $allowed_types)) {
            throw new Exception('Tipo de archivo no permitido. Solo PDF, DOC, JPG o PNG');
        }
        if ($_FILES['documentacion']['size'] > 10 * 1024 * 1024) {
            throw new Exception('El archivo no debe superar los 10MB');
        }

        $file_extension = pathinfo($_FILES['documentacion']['name'], PATHINFO_EXTENSION);
        $file_name = 'doc_' . time() . '_' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['documentacion']['tmp_name'], $file_path)) {
            throw new Exception('Error al subir el archivo de documentación');
        }

        $documentacion_ruta = 'assets/uploads/mantenimientos/documentos/' . $file_name;
    }

    // Insertar tarea
    $insert_stmt = $pdo->prepare("INSERT INTO mantenimiento_tareas (
        camara_id, tecnico_id, programado_por_id, tipo, descripcion,
        fecha_programada, fecha_limite, estado, evidencia_ruta,
        notificado_email, fecha_creacion
    ) VALUES (
        :camara_id, :tecnico_id, :programado_por_id, :tipo, :descripcion,
        :fecha_programada, :fecha_limite, 'pendiente', :documentacion_ruta,
        :notificado_email, NOW()
    ) RETURNING id");

    $insert_stmt->execute([
        ':camara_id'          => $camara_id,
        ':tecnico_id'         => $tecnico_id,
        ':programado_por_id'  => $programado_por_id,
        ':tipo'               => $tipo,
        ':descripcion'        => $descripcion,
        ':fecha_programada'   => $fecha_programada,
        ':fecha_limite'       => $fecha_limite,
        ':documentacion_ruta' => $documentacion_ruta,
        ':notificado_email'   => $notificar_email,
    ]);

    $tarea_id = $insert_stmt->fetchColumn();
    $tarea_codigo = 'MNT-' . str_pad($tarea_id, 3, '0', STR_PAD_LEFT);

    // Notificación por email
    if ($notificar_email && !empty($tecnico['email'])) {
        $asunto  = "Nueva tarea de mantenimiento asignada - {$tarea_codigo}";
        $mensaje = "Hola {$tecnico['nombre_completo']},\n\n";
        $mensaje .= "Se te ha asignado una nueva tarea de mantenimiento:\n\n";
        $mensaje .= "Tarea: {$tarea_codigo}\n";
        $mensaje .= "Cámara: {$camara['inventario_id']}\n";
        $mensaje .= "Tipo: " . ucfirst($tipo) . "\n";
        $mensaje .= "Fecha programada: " . date('d/m/Y', strtotime($fecha_programada)) . "\n";
        $mensaje .= "Fecha límite: " . date('d/m/Y', strtotime($fecha_limite)) . "\n";
        $mensaje .= "Descripción: {$descripcion}\n\n";
        $mensaje .= "Saludos,\nSistema SIGEVEM";
        $headers  = "From: noreply@sigevem.gob.mx\r\nReply-To: soporte@sigevem.gob.mx\r\n";
        @mail($tecnico['email'], $asunto, $mensaje, $headers);
    }

    $pdo->commit();

    registrar_bitacora(
        $pdo,
        $programado_por_id,
        'programar',
        'mantenimiento',
        "Programó {$tarea_codigo} — Cámara: {$camara['inventario_id']}, Técnico: {$tecnico['nombre_completo']}, Tipo: {$tipo}",
        $tarea_id
    );

    $_SESSION['success_message'] = "Tarea {$tarea_codigo} programada exitosamente" .
                                   ($notificar_email ? '. Se ha notificado al técnico por correo.' : '.');
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: programar.php');
    exit;
}
?>