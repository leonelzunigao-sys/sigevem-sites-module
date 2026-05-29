<?php
session_start();

// Solo Admin (1) y Supervisor (2) pueden registrar
if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol_id'] != 1 && $_SESSION['rol_id'] != 2)) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registro.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Datos principales
    $inventario_id     = strtoupper(trim($_POST['inventario_id']));
    $nombre            = trim($_POST['nombre']);
    $tipo_inmueble     = trim($_POST['tipo_inmueble']);
    $zona              = trim($_POST['zona']);
    
    // Dirección (campos separados)
    $calle             = trim($_POST['calle']);
    $numero_exterior   = trim($_POST['numero_exterior']);
    $colonia           = trim($_POST['colonia']);
    
    // Geolocalización
    $latitud           = $_POST['latitud'];
    $longitud          = $_POST['longitud'];
    
    // Activos tecnológicos
    $activos_computadoras = (int)$_POST['activos_computadoras'];
    $activos_servidores   = (int)$_POST['activos_servidores'];
    $activos_impresoras   = (int)$_POST['activos_impresoras'];
    $activos_otros        = (int)$_POST['activos_otros'];
    
    // Conectividad y fechas
    $tipo_internet     = trim($_POST['tipo_internet']);
    $fecha_registro    = $_POST['fecha_registro'];
    $referencias       = trim($_POST['referencias']); // ← AHORA SÍ EXISTE
    
    $usuario_registro_id = $_SESSION['usuario_id'];

    // Validaciones obligatorias
    if (empty($inventario_id) || empty($nombre) || empty($tipo_inmueble) ||
        empty($zona) || empty($calle) || empty($latitud) || empty($longitud)) {
        throw new Exception('Todos los campos marcados con * son obligatorios');
    }

    // Verificar que el ID de sitio no exista
    $check_stmt = $pdo->prepare("SELECT id FROM sitios WHERE inventario_id = :inventario_id");
    $check_stmt->execute([':inventario_id' => $inventario_id]);
    if ($check_stmt->fetch()) {
        throw new Exception('El ID de sitio ya existe en el sistema');
    }

    // Insertar sitio (CON referencias)
    $insert_stmt = $pdo->prepare("INSERT INTO sitios (
        inventario_id, nombre, tipo_inmueble, zona,
        calle, numero_exterior, colonia,
        latitud, longitud,
        activos_computadoras, activos_servidores, activos_impresoras, activos_otros,
        tipo_internet, fecha_registro, referencias,
        usuario_registro_id, fecha_creacion, fecha_actualizacion
    ) VALUES (
        :inventario_id, :nombre, :tipo_inmueble, :zona,
        :calle, :numero_exterior, :colonia,
        :latitud, :longitud,
        :activos_computadoras, :activos_servidores, :activos_impresoras, :activos_otros,
        :tipo_internet, :fecha_registro, :referencias,
        :usuario_registro_id, NOW(), NOW()
    ) RETURNING id");

    $insert_stmt->execute([
        ':inventario_id'         => $inventario_id,
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
        ':fecha_registro'        => $fecha_registro,
        ':referencias'           => $referencias,
        ':usuario_registro_id'   => $usuario_registro_id,
    ]);

    $sitio_id = $insert_stmt->fetchColumn();

    // Registrar validación inicial
    $pdo->prepare("INSERT INTO sitios_validacion (
        sitio_id, usuario_registro_id, estado, fecha_registro
    ) VALUES (:sitio_id, :usuario_registro_id, 'pendiente', NOW())")
    ->execute([
        ':sitio_id'             => $sitio_id,
        ':usuario_registro_id'  => $usuario_registro_id,
    ]);

    // Subir imagen si existe
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/uploads/sitios/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
        $sitio_dir = $upload_dir . $sitio_id . '/';
        if (!file_exists($sitio_dir)) mkdir($sitio_dir, 0755, true);

        // Validación segura de archivo
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($real_mime, $allowed_types)) {
            throw new Exception('Solo se permiten imágenes JPG, PNG, GIF o WEBP.');
        }
        
        if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
            throw new Exception('La imagen no debe superar los 5MB');
        }

        $file_extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $file_name      = 'registro_' . time() . '.' . $file_extension;
        $file_path      = $sitio_dir . $file_name;

        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $file_path)) {
            $pdo->prepare("INSERT INTO sitios_evidencia_fotografica (
                sitio_id, ruta_archivo, nombre_archivo, tipo, fecha_subida, usuario_id
            ) VALUES (:sitio_id, :ruta_archivo, :nombre_archivo, 'registro', NOW(), :usuario_id)")
            ->execute([
                ':sitio_id'       => $sitio_id,
                ':ruta_archivo'   => 'assets/uploads/sitios/' . $sitio_id . '/' . $file_name,
                ':nombre_archivo' => $file_name,
                ':usuario_id'     => $usuario_registro_id,
            ]);
        }
    }

    $pdo->commit();

    // Bitácora
    registrar_bitacora(
        $pdo,
        $usuario_registro_id,
        'registrar',
        'sitios',
        "Registró sitio: {$inventario_id} — {$nombre} ({$tipo_inmueble}) en {$calle} {$numero_exterior}, Zona: {$zona}",
        $sitio_id
    );

    $_SESSION['success_message'] = 'Sitio registrado exitosamente. Pendiente de validación.';
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: registro.php');
    exit;
}
?>