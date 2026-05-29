<?php
/** @psalm-suppress UnnecessaryVarAnnotation */
session_start();

// Solo Admin (1) y Supervisor (2) pueden editar
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol_id'] != 1 && $_SESSION['rol_id'] != 2)) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$sitio_id = 0; // valor por defecto

try {
    $pdo->beginTransaction();

    // Obtener datos del formulario
    $sitio_id              = intval($_POST['sitio_id']);
    $nombre                = trim($_POST['nombre']);
    $tipo_inmueble         = trim($_POST['tipo_inmueble']);
    $zona                  = trim($_POST['zona']);
    
    // Dirección
    $calle                 = trim($_POST['calle']);
    $numero_exterior       = trim($_POST['numero_exterior']);
    $colonia               = trim($_POST['colonia']);
    
    // Geolocalización
    $latitud               = $_POST['latitud'];
    $longitud              = $_POST['longitud'];
    
    // Activos
    $activos_computadoras  = (int)$_POST['activos_computadoras'];
    $activos_servidores    = (int)$_POST['activos_servidores'];
    $activos_impresoras    = (int)$_POST['activos_impresoras'];
    $activos_otros         = (int)$_POST['activos_otros'];
    
    // Otros
    $tipo_internet         = trim($_POST['tipo_internet']);
    $estado                = trim($_POST['estado']);
    $referencias           = trim($_POST['referencias']);
    $usuario_modificacion  = $_SESSION['usuario_id'];

    // Validaciones básicas
    if (empty($nombre) || empty($tipo_inmueble) || empty($zona) || empty($calle) || empty($latitud)) {
        throw new Exception('Todos los campos marcados con * son obligatorios');
    }

    // Verificar que el sitio exista
    $check_stmt = $pdo->prepare("SELECT id FROM sitios WHERE id = :id");
    $check_stmt->execute([':id' => $sitio_id]);
    if (!$check_stmt->fetch()) {
        throw new Exception('El sitio que intentas editar no existe');
    }

    // Actualizar sitio
    $update_stmt = $pdo->prepare("UPDATE sitios SET
        nombre = :nombre,
        tipo_inmueble = :tipo_inmueble,
        zona = :zona,
        calle = :calle,
        numero_exterior = :numero_exterior,
        colonia = :colonia,
        latitud = :latitud,
        longitud = :longitud,
        activos_computadoras = :activos_computadoras,
        activos_servidores = :activos_servidores,
        activos_impresoras = :activos_impresoras,
        activos_otros = :activos_otros,
        tipo_internet = :tipo_internet,
        estado = :estado,
        referencias = :referencias,
        fecha_actualizacion = NOW()
    WHERE id = :id");

    $update_stmt->execute([
        ':nombre'                => $nombre,
        ':tipo_inmueble'         => $tipo_inmueble,
        ':zona'                  => $zona,
        ':calle'                 => $calle,
        ':numero_exterior'       => $numero_exterior,
        ':colonia'               => $colonia,
        ':latitud'               => $latitud,
        ':longitud'              => $longitud,
        ':activos_computadoras'  => $activos_computadoras,
        ':activos_servidores'    => $activos_servidores,
        ':activos_impresoras'    => $activos_impresoras,
        ':activos_otros'         => $activos_otros,
        ':tipo_internet'         => $tipo_internet,
        ':estado'                => $estado,
        ':referencias'           => $referencias,
        ':id'                    => $sitio_id
    ]);

    $pdo->commit();

    // Registrar en bitácora
    registrar_bitacora(
        $pdo,
        $usuario_modificacion,
        'editar',
        'sitios',
        "Actualizó datos del sitio: {$nombre} (ID: {$sitio_id})",
        $sitio_id
    );

    $_SESSION['success_message'] = 'Sitio actualizado exitosamente.';
    header('Location: ver.php?id=' . $sitio_id);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: editar.php?id=' . $sitio_id);
    exit;
}
?>