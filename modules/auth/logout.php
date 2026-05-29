<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

// Guardar datos ANTES de destruir la sesión
$usuario_id  = $_SESSION['usuario_id']  ?? null;
$rol_nombre  = $_SESSION['rol_nombre']  ?? 'Desconocido';

// Registrar logout en bitácora ANTES de destruir sesión
if ($usuario_id) {
    registrar_bitacora(
        $pdo,
        $usuario_id,
        'logout',
        'auth',
        'Cierre de sesión — Rol: ' . $rol_nombre
    );
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit;
?>