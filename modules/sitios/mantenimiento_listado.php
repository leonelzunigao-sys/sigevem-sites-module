<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once '../../config/database.php';

// Obtener filtros
$filter_estado = isset($_GET['filter_estado']) ? $_GET['filter_estado'] : '';
$filter_tipo = isset($_GET['filter_tipo']) ? $_GET['filter_tipo'] : '';
$filter_tecnico = isset($_GET['filter_tecnico']) ? $_GET['filter_tecnico'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construir consulta
$sql = "SELECT 
            sm.*,
            s.inventario_id as sitio_inventario,
            s.nombre as sitio_nombre,
            s.zona as sitio_zona,
            u.nombre_completo as tecnico_nombre,
            up.nombre_completo as programado_por
        FROM sitios_mantenimiento sm
        JOIN sitios s ON sm.sitio_id = s.id
        JOIN usuarios u ON sm.tecnico_id = u.id
        JOIN usuarios up ON sm.programado_por_id = up.id";

$params = [];
$where_clauses = [];

// 🛡️ FILTRO POR ROL: Si es Técnico (rol_id = 3), solo ve sus propias órdenes
if ($_SESSION['rol_id'] == 3) {
    $where_clauses[] = "sm.tecnico_id = :user_id";
    $params[':user_id'] = $_SESSION['usuario_id'];
}

if (!empty($filter_estado)) {
    $where_clauses[] = "sm.estado = :estado";
    $params[':estado'] = $filter_estado;
}
if (!empty($filter_tipo)) {
    $where_clauses[] = "sm.tipo = :tipo";
    $params[':tipo'] = $filter_tipo;
}
if (!empty($filter_tecnico)) {
    // Técnicos no pueden filtrar por otros técnicos, solo Admin/Supervisor
    if ($_SESSION['rol_id'] != 3) {
        $where_clauses[] = "sm.tecnico_id = :tecnico";
        $params[':tecnico'] = $filter_tecnico;
    }
}
if (!empty($search)) {
    $where_clauses[] = "(s.inventario_id ILIKE :search OR s.nombre ILIKE :search OR sm.descripcion ILIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY sm.fecha_programada DESC, sm.fecha_creacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mantenimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener técnicos para el filtro (solo si no es técnico)
$tecnicos = [];
if ($_SESSION['rol_id'] != 3) {
    $tecnicos_sql = "SELECT id, nombre_completo FROM usuarios WHERE rol_id = 3 ORDER BY nombre_completo";
    $tecnicos = $pdo->query($tecnicos_sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Calcular estadísticas
$stats = [
    'total' => count($mantenimientos),
    'pendientes' => 0,
    'en_proceso' => 0,
    'completados' => 0
];

foreach ($mantenimientos as $m) {
    $estado = strtolower($m['estado']);
    if ($estado === 'pendiente') $stats['pendientes']++;
    elseif ($estado === 'en_proceso') $stats['en_proceso']++;
    elseif ($estado === 'completado') $stats['completados']++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimientos de Sitios | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?php echo $_SESSION['rol_id'] == 3 ? 'Mis Tareas de Mantenimiento' : 'Mantenimientos de Sitios'; ?>
                </h1>
                <p class="page-subtitle">
                    <?php echo $_SESSION['rol_id'] == 3 ? 'Órdenes asignadas a ti' : 'Gestión y seguimiento de órdenes de mantenimiento'; ?>
                </p>
            </div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Sitios
            </a>
        </div>

        <!-- Mostrar mensajes -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div class="stat-card" style="background: var(--bg-light); padding: 15px; border-radius: 8px; border-left: 4px solid var(--primary);">
                <div style="color: var(--text-muted); font-size: 14px;">Total Órdenes</div>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card" style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                <div style="color: #856404; font-size: 14px;">Pendientes</div>
                <div style="font-size: 28px; font-weight: 700; color: #856404;"><?php echo $stats['pendientes']; ?></div>
            </div>
            <div class="stat-card" style="background: #e2d4f0; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1;">
                <div style="color: #4a148c; font-size: 14px;">En Proceso</div>
                <div style="font-size: 28px; font-weight: 700; color: #4a148c;"><?php echo $stats['en_proceso']; ?></div>
            </div>
            <div class="stat-card" style="background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
                <div style="color: #155724; font-size: 14px;">Completados</div>
                <div style="font-size: 28px; font-weight: 700; color: #155724;"><?php echo $stats['completados']; ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-body">
                <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
                    <div class="form-group" style="flex: 2;">
                        <input type="text" name="search" class="form-control" placeholder="Buscar por ID, nombre o descripción..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <select name="filter_estado" class="form-control">
                            <option value="">Todos los estados</option>
                            <option value="pendiente" <?php echo $filter_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="en_proceso" <?php echo $filter_estado == 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                            <option value="completado" <?php echo $filter_estado == 'completado' ? 'selected' : ''; ?>>Completado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="filter_tipo" class="form-control">
                            <option value="">Todos los tipos</option>
                            <option value="preventivo" <?php echo $filter_tipo == 'preventivo' ? 'selected' : ''; ?>>Preventivo</option>
                            <option value="correctivo" <?php echo $filter_tipo == 'correctivo' ? 'selected' : ''; ?>>Correctivo</option>
                            <option value="emergencia" <?php echo $filter_tipo == 'emergencia' ? 'selected' : ''; ?>>Emergencia</option>
                        </select>
                    </div>
                    
                    <!-- Selector de técnico: SOLO para Admin/Supervisor -->
                    <?php if ($_SESSION['rol_id'] != 3 && !empty($tecnicos)): ?>
                    <div class="form-group">
                        <select name="filter_tecnico" class="form-control">
                            <option value="">Todos los técnicos</option>
                            <?php foreach ($tecnicos as $tec): ?>
                                <option value="<?php echo $tec['id']; ?>" <?php echo $filter_tecnico == $tec['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tec['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                    <a href="mantenimiento_listado.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpiar</a>
                </form>
            </div>
        </div>

        <!-- Tabla de Mantenimientos -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3><i class="fas fa-tools"></i> 
                    <?php echo $_SESSION['rol_id'] == 3 ? 'Mis Órdenes' : 'Órdenes de Mantenimiento'; ?>
                </h3>
                <span class="badge badge-secondary"><?php echo count($mantenimientos); ?> órdenes</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>ID</th>
                                <th>Sitio</th>
                                <th>Tipo</th>
                                <?php if ($_SESSION['rol_id'] != 3): ?>
                                <th>Técnico</th>
                                <?php endif; ?>
                                <th>Fecha Programada</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mantenimientos)): ?>
                            <tr>
                                <td colspan="<?php echo $_SESSION['rol_id'] == 3 ? 6 : 7; ?>" class="text-center py-4 text-muted">
                                    <i class="fas fa-search" style="font-size: 24px; opacity: 0.3;"></i><br>
                                    <?php echo $_SESSION['rol_id'] == 3 ? 'No tienes órdenes asignadas' : 'No se encontraron órdenes de mantenimiento'; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($mantenimientos as $mant): ?>
                                <tr>
                                    <td><strong>#<?php echo $mant['id']; ?></strong></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($mant['sitio_inventario']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($mant['sitio_nombre']); ?></small><br>
                                        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($mant['sitio_zona']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $tipo_badge = match($mant['tipo']) {
                                            'preventivo' => 'badge-outline-success',
                                            'correctivo' => 'badge-outline-warning',
                                            'emergencia' => 'badge-outline-danger',
                                            default => 'badge-outline-secondary'
                                        };
                                        $tipo_icon = match($mant['tipo']) {
                                            'preventivo' => '🟢',
                                            'correctivo' => '🟡',
                                            'emergencia' => '🔴',
                                            default => '⚪'
                                        };
                                        ?>
                                        <span class="badge <?php echo $tipo_badge; ?>">
                                            <?php echo $tipo_icon . ' ' . ucfirst($mant['tipo']); ?>
                                        </span>
                                    </td>
                                    <?php if ($_SESSION['rol_id'] != 3): ?>
                                    <td><?php echo htmlspecialchars($mant['tecnico_nombre']); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($mant['fecha_programada'])); ?>
                                        <?php if (!empty($mant['fecha_limite'])): ?>
                                            <br><small class="text-muted">Límite: <?php echo date('d/m/Y', strtotime($mant['fecha_limite'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $estado_class = match(strtolower($mant['estado'])) {
                                            'pendiente' => 'badge-warning',
                                            'en_proceso' => 'badge-info',
                                            'completado' => 'badge-success',
                                            default => 'badge-secondary'
                                        };
                                        $estado_icon = match(strtolower($mant['estado'])) {
                                            'pendiente' => '⏳',
                                            'en_proceso' => '🔧',
                                            'completado' => '✅',
                                            default => '❓'
                                        };
                                        ?>
                                        <span class="badge <?php echo $estado_class; ?>">
                                            <?php echo $estado_icon . ' ' . ucfirst(str_replace('_', ' ', $mant['estado'])); ?>
                                        </span>
                                    </td>
                                    <td>
    <div class="d-flex gap-1">
        <!-- 1. Ir al Sitio -->
        <a href="ver.php?id=<?php echo $mant['sitio_id']; ?>" 
           class="btn btn-sm btn-icon btn-outline-primary" 
           title="Ver Sitio">
            <i class="fas fa-building"></i>
        </a>
        
        <!-- 2. Completar (Solo si NO está completado) -->
        <?php if (strtolower($mant['estado']) !== 'completado'): ?>
            <a href="mantenimiento_completar.php?id=<?php echo $mant['id']; ?>" 
               class="btn btn-sm btn-icon btn-success" 
               title="Completar Orden">
                <i class="fas fa-check"></i>
            </a>
        <?php endif; ?>
        
        <!-- 3. Ver Detalle de la Orden -->
        <a href="mantenimiento_ver.php?id=<?php echo $mant['id']; ?>" 
           class="btn btn-sm btn-icon btn-outline-secondary" 
           title="Ver Detalle de Orden">
            <i class="fas fa-eye"></i>
        </a>
    </div>
</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>