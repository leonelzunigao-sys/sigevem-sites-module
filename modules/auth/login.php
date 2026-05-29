<?php
session_start();

// Si ya hay sesión, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

// Inicializar variable de error
$error = '';
if (isset($_SESSION['error_login'])) {
    $error = $_SESSION['error_login'];
    unset($_SESSION['error_login']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIGEVEM | Ecatepec</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    
    <!-- CSS Específico de Login -->
    <link rel="stylesheet" href="../../assets/css/login.css">
</head>
<body class="login-page">
    <!-- Header con Logo Largo -->
    <header class="login-header">
        <div class="login-header-content">
            <img src="../../assets/img/logo-ecatepec-largo.png" alt="Ecatepec" class="logo-largo">
        </div>
    </header>

    <!-- Contenedor Principal -->
    <div class="login-container">
        <!-- Fondo con overlay -->
        <div class="login-background">
            <img src="../../assets/img/fondo-institucional.jpg" alt="Fondo" class="bg-image">
            <div class="bg-overlay"></div>
        </div>

        <!-- Card de Login -->
        <div class="login-card">
            <!-- Logo Corto y Título -->
            <div class="login-card-header">
                <div class="logo-corto-container">
                    <img src="../../assets/img/logo-ecatepec-corto.png" alt="SIGEVEM" class="logo-corto">
                </div>
                <h1 class="login-title">Acceso al Sistema</h1>
                <p class="login-subtitle">Ingrese sus credenciales para acceder</p>
            </div>

            <!-- Formulario -->
            <form action="login_process.php" method="POST" class="login-form" id="loginForm" autocomplete="off">
                
                <!-- Campo: Correo Electrónico -->
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> CORREO ELECTRÓNICO
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="usuario@ecatepec.gob.mx"
                            required
                            autocomplete="email"
                            value=""
                        >
                    </div>
                </div>

                <!-- Campo: Contraseña -->
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> CONTRASEÑA
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="••••••••••••"
                            required
                            autocomplete="current-password"
                            value=""
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Recordar sesión y Olvidaste contraseña -->
                <div class="form-row-inline">
                    <label class="checkbox-container">
                        <input type="checkbox" name="recordar" id="recordar">
                        <span class="checkmark"></span>
                        Recordar sesión
                    </label>
                    <a href="#" class="link-forgot">¿Olvidaste tu contraseña?</a>
                </div>

                <!-- Info Box sobre Roles -->
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <p>Cada rol tiene acceso a <strong>módulos diferentes</strong> según sus funciones. El sistema detectará automáticamente tu rol al iniciar sesión.</p>
                </div>

                <!-- Alerta de Error -->
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Botón de Inicio de Sesión -->
                <button type="submit" class="btn btn-login">
                    Iniciar Sesión
                    <i class="fas fa-arrow-right"></i>
                </button>

            </form>

            <!-- Tarjetas de Roles (Solo informativas) -->
            <div class="roles-demo">
                <div class="role-card">
                    <h4>Administrador</h4>
                    <p>CRUD completo</p>
                </div>
                <div class="role-card">
                    <h4>Técnico</h4>
                    <p>Tareas + registro</p>
                </div>
                <div class="role-card">
                    <h4>Supervisor</h4>
                    <p>Reportes + validar</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> SIGEVEM — Gobierno Municipal de Ecatepec de Morelos</p>
            <p class="footer-small">Sistema Integral de Gestión y Geolocalización de Infraestructura de Videovigilancia Municipal</p>
        </footer>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/login.js"></script>
</body>
</html>