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
    header('Location: index.php?error=no_self_delete');
    exit;
}

$stmt = $pdo->prepare("SELECT id, nombre_completo, email FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: index.php?error=not_found');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);

    registrar_bitacora(
        $pdo,
        $_SESSION['usuario_id'],
        'eliminar',
        'usuarios',
        "Eliminó usuario: {$usuario['nombre_completo']} ({$usuario['email']})",
        $id
    );

    header('Location: index.php?eliminado=1');
    exit;

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'foreign key') !== false) {
        header('Location: index.php?error=cannot_delete_has_relations');
    } else {
        header('Location: index.php?error=delete_failed');
    }
    exit;
}
?>