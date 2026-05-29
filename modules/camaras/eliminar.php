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
    $check_stmt = $pdo->prepare("SELECT id, inventario_id, marca, direccion FROM camaras WHERE id = :id");
    $check_stmt->execute([':id' => $id]);
    $camara = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$camara) {
        throw new Exception('Cámara no encontrada');
    }

    // Eliminar archivos físicos
    $img_stmt = $pdo->prepare("SELECT ruta_archivo FROM evidencia_fotografica WHERE camara_id = :camara_id");
    $img_stmt->execute([':camara_id' => $id]);
    foreach ($img_stmt->fetchAll(PDO::FETCH_ASSOC) as $imagen) {
        $archivo_path = '../../' . $imagen['ruta_archivo'];
        if (file_exists($archivo_path)) unlink($archivo_path);
    }

    $pdo->prepare("DELETE FROM evidencia_fotografica WHERE camara_id = :camara_id")->execute([':camara_id' => $id]);
    $pdo->prepare("DELETE FROM camaras_validacion WHERE camara_id = :camara_id")->execute([':camara_id' => $id]);
    $pdo->prepare("DELETE FROM camaras WHERE id = :id")->execute([':id' => $id]);

    registrar_bitacora(
        $pdo,
        $_SESSION['usuario_id'],
        'eliminar',
        'camaras',
        "Eliminó cámara: {$camara['inventario_id']} — {$camara['marca']} en {$camara['direccion']}",
        $id
    );

    $_SESSION['success_message'] = 'Cámara eliminada exitosamente';
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
?>