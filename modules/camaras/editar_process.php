<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] == 3) {
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
    $id                = $_POST['id'];
    $marca             = trim($_POST['marca']);
    $modelo            = trim($_POST['modelo']);
    $tipo_camara       = trim($_POST['tipo_camara']);
    $zona              = trim($_POST['zona']);
    $direccion         = trim($_POST['direccion']);
    $colonia           = trim($_POST['colonia']);
    $latitud           = $_POST['latitud'];
    $longitud          = $_POST['longitud'];
    $numero_serie      = trim($_POST['numero_serie']);
    $fecha_instalacion = $_POST['fecha_instalacion'];
    $estatus           = trim($_POST['estatus']);
    $referencias       = trim($_POST['referencias']);

    if (empty($marca) || empty($tipo_camara) || empty($zona) ||
        empty($direccion) || empty($latitud) || empty($longitud) || empty($estatus)) {
        throw new Exception('Todos los campos marcados con * son obligatorios');
    }

    // Obtener datos anteriores para la descripción
    $anterior = $pdo->prepare("SELECT inventario_id, estatus FROM camaras WHERE id = ?");
    $anterior->execute([$id]);
    $camara_anterior = $anterior->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("UPDATE camaras SET
        marca = :marca, modelo = :modelo, tipo_camara = :tipo_camara,
        zona = :zona, direccion = :direccion, colonia = :colonia,
        latitud = :latitud, longitud = :longitud, numero_serie = :numero_serie,
        fecha_instalacion = :fecha_instalacion, estatus = :estatus,
        referencias = :referencias, fecha_actualizacion = NOW()
        WHERE id = :id")
    ->execute([
        ':id'               => $id,
        ':marca'            => $marca,
        ':modelo'           => $modelo,
        ':tipo_camara'      => $tipo_camara,
        ':zona'             => $zona,
        ':direccion'        => $direccion,
        ':colonia'          => $colonia,
        ':latitud'          => $latitud,
        ':longitud'         => $longitud,
        ':numero_serie'     => $numero_serie,
        ':fecha_instalacion' => $fecha_instalacion,
        ':estatus'          => $estatus,
        ':referencias'      => $referencias,
    ]);

    $descripcion = "Editó cámara: {$camara_anterior['inventario_id']}";
    if ($camara_anterior['estatus'] !== $estatus) {
        $descripcion .= " — estatus: {$camara_anterior['estatus']} → {$estatus}";
    }

    registrar_bitacora(
        $pdo,
        $_SESSION['usuario_id'],
        'editar',
        'camaras',
        $descripcion,
        $id
    );

    $_SESSION['success_message'] = 'Cámara actualizada exitosamente';
    header('Location: ver.php?id=' . $id);
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: editar.php?id=' . $_POST['id']);
    exit;
}
?>