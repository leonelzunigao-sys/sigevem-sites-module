<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

$id = $_GET['id'] ?? 0;

if ($id == 0) {
    header('Location: index.php?error=invalid_id');
    exit;
}

if ($id == $_SESSION['usuario_id']) {
    header('Location: index.php?error=no_self_deactivate');
    exit;
}

$stmt = $pdo->prepare("SELECT id, nombre_completo, estatus FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: index.php?error=not_found');
    exit;
}

$nuevo_estado = ($usuario['estatus'] == 'activo') ? 'inactivo' : 'activo';

try {
    $stmt = $pdo->prepare("UPDATE usuarios SET estatus = ? WHERE id = ?");
    $stmt->execute([$nuevo_estado, $id]);

    $accion = $nuevo_estado === 'activo' ? 'activar' : 'desactivar';

    registrar_bitacora(
        $pdo,
        $_SESSION['usuario_id'],
        $accion,
        'usuarios',
        ucfirst($accion) . " usuario: {$usuario['nombre_completo']} → estado: {$nuevo_estado}",
        $id
    );

    header('Location: index.php?toggle=1&estado=' . $nuevo_estado);
    exit;

} catch (PDOException $e) {
    header('Location: index.php?error=toggle_failed');
    exit;
}
?>