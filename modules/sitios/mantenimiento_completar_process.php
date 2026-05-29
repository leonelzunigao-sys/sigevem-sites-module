<?php
session_start();

// Validar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mantenimiento_listado.php');
    exit;
}

$mantenimiento_id = 0; // valor por defecto

try {
    $pdo->beginTransaction();

    // Datos recibidos
    $mantenimiento_id = intval($_POST['mantenimiento_id']);
    $sitio_id         = intval($_POST['sitio_id']);
    $observaciones    = trim($_POST['observaciones']);
    $usuario_id       = $_SESSION['usuario_id'];
    $rol_id           = $_SESSION['rol_id'];

    if (empty($observaciones)) {
        throw new Exception('Las observaciones finales son obligatorias.');
    }

    // Verificar que la orden exista y no esté ya completada
    $check = $pdo->prepare("SELECT id, estado, tecnico_id FROM sitios_mantenimiento WHERE id = :id");
    $check->execute([':id' => $mantenimiento_id]);
    $orden = $check->fetch(PDO::FETCH_ASSOC);
    
    if (!$orden) {
        throw new Exception('La orden de mantenimiento no existe.');
    }
    if ($orden['estado'] === 'completado') {
        throw new Exception('Esta orden ya fue completada anteriormente.');
    }

    // 🛡️ VALIDACIÓN DE PERMISOS POR ROL
    // - Admin (1) y Supervisor (2): Pueden completar cualquier orden
    // - Técnico (3): Solo puede completar órdenes asignadas a él
    if ($rol_id == 3) {
        // Si es técnico, verificar que la orden esté asignada a él
        if ($orden['tecnico_id'] != $usuario_id) {
            throw new Exception('No tienes permisos para completar esta orden. No está asignada a ti.');
        }
    } elseif ($rol_id != 1 && $rol_id != 2) {
        // Si no es Admin, Supervisor ni Técnico, denegar acceso
        throw new Exception('Acceso no autorizado.');
    }

    // Procesar evidencia fotográfica (si se subió)
    $evidencia_ruta = null;
    if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/uploads/sitios/mantenimiento/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Validación segura de MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $_FILES['evidencia']['tmp_name']);
        finfo_close($finfo);

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($real_mime, $allowed_types)) {
            throw new Exception('Solo se permiten imágenes JPG, PNG o WEBP.');
        }

        if ($_FILES['evidencia']['size'] > 5 * 1024 * 1024) {
            throw new Exception('La imagen no debe superar los 5MB.');
        }

        $extension   = pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION);
        $file_name   = 'mant_' . $mantenimiento_id . '_' . time() . '.' . $extension;
        $file_path   = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $file_path)) {
            $evidencia_ruta = 'assets/uploads/sitios/mantenimiento/' . $file_name;
        }
    }

    // 1. Actualizar orden de mantenimiento a "completado"
    $pdo->prepare("UPDATE sitios_mantenimiento SET
        estado = 'completado',
        observaciones = :observaciones,
        evidencia_ruta = :evidencia_ruta,
        fecha_completado = NOW()
    WHERE id = :id")->execute([
        ':observaciones'   => $observaciones,
        ':evidencia_ruta'  => $evidencia_ruta,
        ':id'              => $mantenimiento_id
    ]);

    // 2. Restaurar estado del sitio a "activo" (ya terminó el mantenimiento)
    $pdo->prepare("UPDATE sitios SET estado = 'activo', fecha_actualizacion = NOW() WHERE id = :id")
        ->execute([':id' => $sitio_id]);

    // 3. Bitácora
    registrar_bitacora(
        $pdo,
        $usuario_id,
        'completar_mantenimiento',
        'sitios',
        "Completó orden #{$mantenimiento_id} para sitio ID {$sitio_id}. El sitio vuelve a estado Activo.",
        $sitio_id
    );

    $pdo->commit();

    $_SESSION['success_message'] = 'Mantenimiento completado exitosamente. El sitio ha vuelto a estado Activo.';
    header('Location: mantenimiento_listado.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    // @phpstan-ignore-next-line
    header('Location: mantenimiento_completar.php?id=' . $mantenimiento_id);
    exit;
}
?>