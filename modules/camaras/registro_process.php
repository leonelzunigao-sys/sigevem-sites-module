<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] == 2) {
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

    $inventario_id       = strtoupper(trim($_POST['inventario_id']));
    $numero_camara       = trim($_POST['numero_camara']);
    $marca               = trim($_POST['marca']);
    $modelo              = trim($_POST['modelo']);
    $tipo_camara         = trim($_POST['tipo_camara']);
    $zona                = trim($_POST['zona']);
    $direccion           = trim($_POST['direccion']);
    $colonia             = trim($_POST['colonia']);
    $latitud             = $_POST['latitud'];
    $longitud            = $_POST['longitud'];
    $numero_serie        = trim($_POST['numero_serie']);
    $fecha_instalacion   = $_POST['fecha_instalacion'];
    $referencias         = trim($_POST['referencias']);
    $usuario_registro_id = $_SESSION['usuario_id'];

    if (empty($inventario_id) || empty($marca) || empty($tipo_camara) ||
        empty($zona) || empty($direccion) || empty($latitud) || empty($longitud)) {
        throw new Exception('Todos los campos marcados con * son obligatorios');
    }

    $check_stmt = $pdo->prepare("SELECT id FROM camaras WHERE inventario_id = :inventario_id");
    $check_stmt->execute([':inventario_id' => $inventario_id]);
    if ($check_stmt->fetch()) {
        throw new Exception('El ID de cámara ya existe en el sistema');
    }

    $insert_stmt = $pdo->prepare("INSERT INTO camaras (
        inventario_id, numero_camara, marca, modelo, tipo_camara,
        direccion, colonia, zona, latitud, longitud,
        numero_serie, fecha_instalacion, referencias,
        usuario_registro_id, estatus, fecha_registro, fecha_actualizacion
    ) VALUES (
        :inventario_id, :numero_camara, :marca, :modelo, :tipo_camara,
        :direccion, :colonia, :zona, :latitud, :longitud,
        :numero_serie, :fecha_instalacion, :referencias,
        :usuario_registro_id, 'pendiente', NOW(), NOW()
    ) RETURNING id");

    $insert_stmt->execute([
        ':inventario_id'       => $inventario_id,
        ':numero_camara'       => $numero_camara,
        ':marca'               => $marca,
        ':modelo'              => $modelo,
        ':tipo_camara'         => $tipo_camara,
        ':direccion'           => $direccion,
        ':colonia'             => $colonia,
        ':zona'                => $zona,
        ':latitud'             => $latitud,
        ':longitud'            => $longitud,
        ':numero_serie'        => $numero_serie,
        ':fecha_instalacion'   => $fecha_instalacion,
        ':referencias'         => $referencias,
        ':usuario_registro_id' => $usuario_registro_id,
    ]);

    $camara_id = $insert_stmt->fetchColumn();

    // Registro de validación
    $pdo->prepare("INSERT INTO camaras_validacion (
        camara_id, usuario_registro_id, estado, fecha_registro
    ) VALUES (:camara_id, :usuario_registro_id, 'pendiente', NOW())")
    ->execute([':camara_id' => $camara_id, ':usuario_registro_id' => $usuario_registro_id]);

    // Subir imagen si existe
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/uploads/camaras/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
        $camara_dir = $upload_dir . $camara_id . '/';
        if (!file_exists($camara_dir)) mkdir($camara_dir, 0755, true);

        // ============================================
        // VALIDACIÓN MEJORADA DE ARCHIVO (SEGURA)
        // ============================================
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Verificar MIME type REAL del archivo (no el que reporta el navegador)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($real_mime, $allowed_types)) {
            throw new Exception('Solo se permiten imágenes JPG, PNG, GIF o WEBP. Archivo rechazado.');
        }
        
        if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
            throw new Exception('La imagen no debe superar los 5MB');
        }
        // ============================================

        $file_extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $file_name      = 'registro_' . time() . '.' . $file_extension;
        $file_path      = $camara_dir . $file_name;

        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $file_path)) {
            $pdo->prepare("INSERT INTO evidencia_fotografica (
                camara_id, ruta_archivo, nombre_archivo, tipo, fecha_subida, usuario_id
            ) VALUES (:camara_id, :ruta_archivo, :nombre_archivo, 'registro', NOW(), :usuario_id)")
            ->execute([
                ':camara_id'     => $camara_id,
                ':ruta_archivo'  => 'assets/uploads/camaras/' . $camara_id . '/' . $file_name,
                ':nombre_archivo' => $file_name,
                ':usuario_id'    => $usuario_registro_id,
            ]);
        }
    }

    $pdo->commit();

    // Registrar en bitácora
    registrar_bitacora(
        $pdo,
        $usuario_registro_id,
        'registrar',
        'camaras',
        "Registró cámara: {$inventario_id} — {$tipo_camara} {$marca} en {$direccion}, Zona: {$zona}",
        $camara_id
    );

    $_SESSION['success_message'] = 'Cámara registrada exitosamente. Pendiente de validación.';
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: registro.php');
    exit;
}
?>