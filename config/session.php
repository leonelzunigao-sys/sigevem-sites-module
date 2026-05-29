<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión activa
function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: /sigevem/modules/auth/login.php');
        exit;
    }
}

// Verificar rol
function verificarRol($roles_permitidos) {
    if (!in_array($_SESSION['rol_id'], $roles_permitidos)) {
        header('Location: /sigevem/modules/dashboard/index.php');
        exit;
    }
}
?>