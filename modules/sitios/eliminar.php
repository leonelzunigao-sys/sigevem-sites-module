<?php
session_start();

// Solo Admin (1) puede eliminar
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

$sitio_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sitio_id <= 0) {
    $_SESSION['error_message'] = 'ID de sitio no válido';
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Obtener datos para la bitácora antes de borrar
    $sql_data = "SELECT inventario_id, nombre FROM sitios WHERE id = :id";
    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->execute([':id' => $sitio_id]);
    $sitio_data = $stmt_data->fetch(PDO::FETCH_ASSOC);

    if (!$sitio_data) {
        throw new Exception('El sitio que intentas eliminar no existe');
    }

    // Eliminar sitio (Las tablas relacionadas se borran automáticamente por CASCADE)
    $delete_stmt = $pdo->prepare("DELETE FROM sitios WHERE id = :id");
    $delete_stmt->execute([':id' => $sitio_id]);

    // Bitácora
    registrar_bitacora(
        $pdo,
        $_SESSION['usuario_id'],
        'eliminar',
        'sitios',
        "Eliminó sitio: {$sitio_data['inventario_id']} - {$sitio_data['nombre']}",
        $sitio_id
    );

    $pdo->commit();

    $_SESSION['success_message'] = "Sitio {$sitio_data['inventario_id']} eliminado correctamente.";
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
?>