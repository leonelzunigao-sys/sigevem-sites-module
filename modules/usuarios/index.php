<?php
session_start();

// Solo Admin puede gestionar usuarios
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

// ============================================
// KPIs REALES - USUARIOS
// ============================================
$kpi_total = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$kpi_admins = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol_id = 1")->fetchColumn();
$kpi_tecnicos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol_id = 3")->fetchColumn();
$kpi_supervisores = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol_id = 2")->fetchColumn();
$kpi_activos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estatus = 'activo'")->fetchColumn();
$kpi_inactivos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estatus = 'inactivo'")->fetchColumn();

// ============================================
// OBTENER USUARIOS (CON FILTROS)
// ============================================
$search = $_GET['search'] ?? '';
$filter_rol = $_GET['rol'] ?? '';

$sql = "SELECT 
    u.*,
    r.nombre as rol_nombre
FROM usuarios u
LEFT JOIN roles r ON u.rol_id = r.id
WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.nombre_completo ILIKE :search OR u.email ILIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($filter_rol)) {
    $sql .= " AND u.rol_id = :rol";
    $params[':rol'] = $filter_rol;
}

$sql .= " ORDER BY u.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// OBTENER ROLES PARA FILTRO
// ============================================
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Fecha actual
//$fecha_actual = date('l, j \d\e F Y — H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | SIGEVEM</title>
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

    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Gestión de Usuarios</h1>
                <p class="page-subtitle">Administra roles y accesos del sistema</p>
                
            </div>
            <a href="crear.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuevo Usuario
            </a>
        </div>

        <!-- Mensajes de éxito/error -->
<?php if (isset($_GET['creado']) && $_GET['creado'] == 1): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <strong>¡Usuario creado exitosamente!</strong>
</div>
<?php endif; ?>

<?php if (isset($_GET['editado']) && $_GET['editado'] == 1): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <strong>¡Usuario actualizado exitosamente!</strong>
</div>
<?php endif; ?>

<?php if (isset($_GET['eliminado']) && $_GET['eliminado'] == 1): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <strong>¡Usuario eliminado exitosamente!</strong>
</div>
<?php endif; ?>

<?php if (isset($_GET['toggle']) && $_GET['toggle'] == 1): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <strong>Estado cambiado a: <?php echo ucfirst($_GET['estado']); ?></strong>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <strong>Error:</strong>
    <?php
    switch ($_GET['error']) {
        case 'no_self_edit':
            echo "No puedes editarte a ti mismo.";
            break;
        case 'no_self_deactivate':
            echo "No puedes desactivar tu propia cuenta.";
            break;
        case 'no_self_delete':  // ← AGREGA ESTO
            echo "No puedes eliminar tu propia cuenta.";
            break;
        case 'not_found':
            echo "Usuario no encontrado.";
            break;
        case 'invalid_id':
            echo "ID de usuario inválido.";
            break;
        case 'toggle_failed':
            echo "Error al cambiar el estado.";
            break;
        case 'cannot_delete_has_relations':
            echo "No se puede eliminar. El usuario tiene registros asociados (cámaras, mantenimientos o bitácora).";
            break;
        case 'delete_failed':
            echo "No se pudo eliminar el usuario. Intenta de nuevo.";
            break;
        default:
    echo "Ocurrió un error inesperado.";
    }
    ?>
</div>
<?php endif; ?>

        <!-- KPI Cards -->
        <div class="kpi-container">
            <div class="kpi-card kpi-primary">
                <div class="kpi-content">
                    <div>
                        <div class="kpi-number"><?php echo $kpi_total; ?></div>
                        <div class="kpi-label">Total Usuarios</div>
                    </div>
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="kpi-card kpi-warning">
                <div class="kpi-content">
                    <div>
                        <div class="kpi-number"><?php echo $kpi_admins; ?></div>
                        <div class="kpi-label">Administradores</div>
                    </div>
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
            <div class="kpi-card kpi-info">
                <div class="kpi-content">
                    <div>
                        <div class="kpi-number"><?php echo $kpi_tecnicos; ?></div>
                        <div class="kpi-label">Técnicos</div>
                    </div>
                    <i class="fas fa-user-cog"></i>
                </div>
            </div>
            <div class="kpi-card kpi-purple">
                <div class="kpi-content">
                    <div>
                        <div class="kpi-number"><?php echo $kpi_supervisores; ?></div>
                        <div class="kpi-label">Supervisores</div>
                    </div>
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
        </div>

        <!-- Filtros y Búsqueda -->
        <div class="filter-bar">
            <form method="GET" class="search-form">
                <div class="input-with-icon">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Buscar por nombre o correo..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="rol" class="form-control" onchange="this.form.submit()">
                    <option value="">Todos los roles</option>
                    <?php foreach ($roles as $rol): ?>
                    <option value="<?php echo $rol['id']; ?>" <?php echo $filter_rol == $rol['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($rol['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($search) || !empty($filter_rol)): ?>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Limpiar
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabla de Usuarios -->
        <div class="card">
            <div class="card-header">
                <h3>Usuarios del Sistema</h3>
                <span class="badge-count"><?php echo count($usuarios); ?> USUARIOS</span>
            </div>
            <div class="card-body">
                <?php if (!empty($usuarios)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th>Último Acceso</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($usuario['nombre_completo'], 0, 1)); ?>
                                            <?php echo strtoupper(substr(strstr($usuario['nombre_completo'], ' '), 0, 1)); ?>
                                        </div>
                                        <div class="user-info">
                                            <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $usuario['rol_id'] == 1 ? 'warning' : 
                                            ($usuario['rol_id'] == 2 ? 'purple' : 'info'); 
                                    ?>">
                                        <?php echo htmlspecialchars($usuario['rol_nombre']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($usuario['fecha_ultimo_acceso'])): ?>
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('d/m/Y H:i', strtotime($usuario['fecha_ultimo_acceso'])); ?>
                                    <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $usuario['estatus'] == 'activo' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($usuario['estatus']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="ver.php?id=<?php echo $usuario['id']; ?>" class="btn-icon" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="editar.php?id=<?php echo $usuario['id']; ?>" class="btn-icon" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                        <a href="toggle_estado.php?id=<?php echo $usuario['id']; ?>" class="btn-icon <?php echo $usuario['estatus'] == 'activo' ? 'btn-danger' : 'btn-success'; ?>" 
                                           title="<?php echo $usuario['estatus'] == 'activo' ? 'Desactivar' : 'Activar'; ?>"
                                           onclick="return confirm('¿Estás seguro de cambiar el estado de este usuario?')">
                                            <i class="fas fa-<?php echo $usuario['estatus'] == 'activo' ? 'ban' : 'check'; ?>"></i>
                                        </a>
                                        <a href="eliminar.php?id=<?php echo $usuario['id']; ?>" class="btn-icon btn-danger" title="Eliminar"
                                           onclick="return confirm('¿Estás seguro de eliminar este usuario? Esta acción no se puede deshacer.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No se encontraron usuarios</h3>
                    <p>Intenta con otros filtros o crea un nuevo usuario.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>