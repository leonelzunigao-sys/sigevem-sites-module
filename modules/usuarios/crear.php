<?php
session_start();

// Solo Admin puede crear usuarios
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/bitacora_helper.php';

// ============================================
// OBTENER ROLES PARA EL SELECT
// ============================================
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$errores = [];
$exito = '';

// ============================================
// PROCESAR FORMULARIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_completo  = trim($_POST['nombre_completo'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $rol_id           = $_POST['rol_id'] ?? '';
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($nombre_completo)) $errores[] = "El nombre completo es obligatorio.";
    if (empty($email))           $errores[] = "El correo electrónico es obligatorio.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El correo electrónico no es válido.";

    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) $errores[] = "El correo electrónico ya está registrado.";
    }

    if (empty($rol_id))                  $errores[] = "El rol es obligatorio.";
    if (empty($password))                $errores[] = "La contraseña es obligatoria.";
    elseif (strlen($password) < 6)       $errores[] = "La contraseña debe tener al menos 6 caracteres.";
    if ($password !== $password_confirm) $errores[] = "Las contraseñas no coinciden.";

    if (empty($errores)) {
        try {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nombre_completo, email, password_hash, rol_id, estatus, fecha_creacion)
                VALUES (?, ?, ?, ?, 'activo', NOW())
            ");
            $stmt->execute([$nombre_completo, $email, $password_hash, $rol_id]);
            $nuevo_id = $pdo->lastInsertId();

            // Obtener nombre del rol para la descripción
            $stmt_rol = $pdo->prepare("SELECT nombre FROM roles WHERE id = ?");
            $stmt_rol->execute([$rol_id]);
            $rol_nombre = $stmt_rol->fetchColumn();

            registrar_bitacora(
                $pdo,
                $_SESSION['usuario_id'],
                'crear',
                'usuarios',
                "Creó usuario: {$nombre_completo} ({$email}) — Rol: {$rol_nombre}",
                $nuevo_id
            );

            header('Location: index.php?creado=1');
            exit;

        } catch (PDOException $e) {
            $errores[] = "Error al crear el usuario: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Usuario | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/usuarios.css">
</head>
<body>
    <!-- Header -->
    <header class="dashboard-header">
        <div class="header-left">
            <img src="../../assets/img/logo-ecatepec-largo.png" alt="SIGEVEM" class="header-logo">
        </div>
        <div class="header-center">
            <h2 class="system-title">SIGEVEM</h2>
            <p class="system-subtitle">Sistema Integral de Gestión y Geolocalización de Infraestructura de Videovigilancia Municipal</p>
        </div>
        <div class="header-right">
            <div class="user-badge">
                <span class="badge-datetime" id="headerDatetime"></span>
                <span class="badge-role">Rol: <?php echo htmlspecialchars($_SESSION['rol_nombre']); ?></span>
            </div>
        </div>
    </header>

    <?php include '../../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Nuevo Usuario</h1>
                <p class="page-subtitle">Registrar un nuevo usuario en el sistema</p>
                
            </div>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>

        <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Error:</strong>
            <ul>
                <?php foreach ($errores as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Información del Usuario</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-usuarios">
                    <div class="form-group">
                        <label for="nombre_completo"><i class="fas fa-user"></i> Nombre Completo *</label>
                        <input type="text" id="nombre_completo" name="nombre_completo" class="form-control"
                               placeholder="Ej: Juan Pérez García"
                               value="<?php echo htmlspecialchars($_POST['nombre_completo'] ?? ''); ?>" required>
                        <small class="form-text">Nombre completo del usuario</small>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Correo Electrónico *</label>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="usuario@ecatepec.gob.mx"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <small class="form-text">Correo institucional único</small>
                    </div>

                    <div class="form-group">
                        <label for="rol_id"><i class="fas fa-user-tag"></i> Rol del Sistema *</label>
                        <select id="rol_id" name="rol_id" class="form-control" required>
                            <option value="">Seleccionar rol...</option>
                            <?php foreach ($roles as $rol): ?>
                            <option value="<?php echo $rol['id']; ?>"
                                    <?php echo ($_POST['rol_id'] ?? '') == $rol['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Define los permisos del usuario</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Contraseña *</label>
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="Mínimo 6 caracteres" required>
                            <small class="form-text">Contraseña temporal</small>
                        </div>
                        <div class="form-group">
                            <label for="password_confirm"><i class="fas fa-lock"></i> Confirmar Contraseña *</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                                   placeholder="Repite la contraseña" required>
                            <small class="form-text">Confirma la contraseña</small>
                        </div>
                    </div>

                    <div class="info-box" id="infoPermisos" style="display: none;">
                        <h4><i class="fas fa-info-circle"></i> PERMISOS DEL ROL: <span id="rolNombre"></span></h4>
                        <ul id="listaPermisos"></ul>
                    </div>

                    <div class="form-actions">
                        <a href="index.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <button class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
    const permisosPorRol = {
        '1': { nombre: 'ADMINISTRADOR', permisos: [
            { text: 'Dashboard (completo)', allowed: true },
            { text: 'Cámaras (CRUD completo)', allowed: true },
            { text: 'Mantenimiento (programar/ejecutar/validar)', allowed: true },
            { text: 'Usuarios (gestión completa)', allowed: true },
            { text: 'Bitácora (todas las acciones)', allowed: true },
            { text: 'Reportes (todos)', allowed: true }
        ]},
        '2': { nombre: 'SUPERVISOR', permisos: [
            { text: 'Dashboard (completo)', allowed: true },
            { text: 'Cámaras (CRUD completo)', allowed: true },
            { text: 'Mantenimiento (programar/validar)', allowed: true },
            { text: 'Usuarios (solo ver)', allowed: false },
            { text: 'Bitácora (todas las acciones)', allowed: true },
            { text: 'Reportes (todos)', allowed: true }
        ]},
        '3': { nombre: 'TÉCNICO', permisos: [
            { text: 'Dashboard (sus tareas)', allowed: true },
            { text: 'Cámaras (crear/editar)', allowed: true },
            { text: 'Mantenimiento (ejecutar)', allowed: true },
            { text: 'Usuarios (no acceso)', allowed: false },
            { text: 'Bitácora (solo sus acciones)', allowed: true },
            { text: 'Reportes (no acceso)', allowed: false }
        ]}
    };

    document.getElementById('rol_id').addEventListener('change', function() {
        const rolId = this.value;
        const infoBox = document.getElementById('infoPermisos');
        const rolNombre = document.getElementById('rolNombre');
        const listaPermisos = document.getElementById('listaPermisos');
        if (rolId && permisosPorRol[rolId]) {
            const rol = permisosPorRol[rolId];
            rolNombre.textContent = rol.nombre;
            listaPermisos.innerHTML = '';
            rol.permisos.forEach(p => {
                const li = document.createElement('li');
                li.innerHTML = `<i class="fas fa-${p.allowed ? 'check-circle text-success' : 'times-circle text-danger'}"></i> ${p.text}`;
                listaPermisos.appendChild(li);
            });
            infoBox.style.display = 'block';
        } else {
            infoBox.style.display = 'none';
        }
    });

    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');
    function validarPasswords() {
        passwordConfirm.setCustomValidity(
            passwordConfirm.value && password.value !== passwordConfirm.value ? 'Las contraseñas no coinciden' : ''
        );
    }
    password.addEventListener('input', validarPasswords);
    passwordConfirm.addEventListener('input', validarPasswords);
    </script>
</body>
</html>