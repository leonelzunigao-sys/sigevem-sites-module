<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT ef.*, c.inventario_id FROM evidencia_fotografica ef
                        LEFT JOIN camaras c ON ef.camara_id = c.id
                        WHERE ef.id = :id");
$stmt->execute([':id' => $id]);
$imagen = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$imagen) {
    $_SESSION['error_message'] = 'Imagen no encontrada';
    header('Location: index.php');
    exit;
}

// Eliminar archivo físico
$archivo_path = '../../' . $imagen['ruta_archivo'];
if (file_exists($archivo_path)) {
    unlink($archivo_path);
}

$pdo->prepare("DELETE FROM evidencia_fotografica WHERE id = :id")->execute([':id' => $id]);

registrar_bitacora(
    $pdo,
    $_SESSION['usuario_id'],
    'eliminar',
    'camaras',
    "Eliminó imagen de cámara: {$imagen['inventario_id']} — archivo: {$imagen['nombre_archivo']}",
    $imagen['camara_id']
);

$_SESSION['success_message'] = 'Imagen eliminada exitosamente';
header('Location: editar.php?id=' . $imagen['camara_id']);
exit;
?>