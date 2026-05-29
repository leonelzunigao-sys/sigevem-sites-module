<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../config/database.php';

// ============================================
// CONFIGURACIÓN DE PAGINACIÓN
// ============================================
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$pagina_actual = max(1, $pagina_actual);
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Búsqueda
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// ============================================
// QUERY CON FILTROS Y PAGINACIÓN
// ============================================
$where = "WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $where .= " AND (
        c.inventario_id ILIKE :busqueda 
        OR c.marca ILIKE :busqueda 
        OR c.direccion ILIKE :busqueda 
        OR c.colonia ILIKE :busqueda 
        OR c.zona ILIKE :busqueda
        OR c.tipo_camara ILIKE :busqueda
    )";
    $params[':busqueda'] = "%{$busqueda}%";
}

// Contar total de registros
$sql_count = "SELECT COUNT(*) as total FROM camaras c {$where}";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener registros con LIMIT y OFFSET
$sql = "SELECT 
    c.*,
    cv.estado as estado_validacion
FROM camaras c
LEFT JOIN camaras_validacion cv ON c.id = cv.camara_id
{$where}
ORDER BY c.inventario_id ASC
LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

// Bind parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$camaras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cámaras | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
    /* Estilos de paginación */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 5px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .pagination-link {
        padding: 8px 12px;
        border: 1px solid var(--gray-300);
        border-radius: 4px;
        text-decoration: none;
        color: var(--primary);
        background: #fff;
        transition: all 0.3s;
        font-size: 14px;
    }
    
    .pagination-link:hover:not(.disabled) {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    
    .pagination-link.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
        font-weight: 600;
    }
    
    .pagination-link.disabled {
        color: #ccc;
        cursor: not-allowed;
        opacity: 0.5;
    }
    
    .pagination-numbers {
        display: flex;
        gap: 5px;
    }
    
    .results-info {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        color: var(--text-muted);
        font-size: 14px;
    }
    
    @media (max-width: 768px) {
        .pagination {
            gap: 3px;
        }
        
        .pagination-link {
            padding: 6px 8px;
            font-size: 12px;
        }
        
        .pagination-numbers {
            display: none;
        }
        
        .results-info {
            flex-direction: column;
            gap: 5px;
            text-align: center;
        }
    }
    </style>
</head>
<body>
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

    <!-- Botón menú móvil -->
    <button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="page-header">
            <h1 class="page-title">Registro de Cámaras</h1>
            <p class="page-subtitle">Gestión del inventario de videovigilancia</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success mb-3">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger mb-3">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="" class="d-flex align-items-center gap-2">
                    <input 
                        type="text" 
                        name="busqueda" 
                        class="form-control" 
                        placeholder="Buscar por ID, marca, ubicación..." 
                        value="<?php echo htmlspecialchars($busqueda); ?>"
                        style="max-width: 400px;"
                    >
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if (!empty($busqueda)): ?>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                    <?php endif; ?>
                    
                    <div class="ml-auto">
                        <?php if ($_SESSION['rol_id'] != 2): ?>
                        <a href="registro.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Registrar Cámara
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Inventario de Cámaras</h3>
                <span class="badge badge-primary"><?php echo count($camaras); ?> de <?php echo $total_registros; ?> cámaras</span>
            </div>
            <div class="card-body p-0">
                <div class="results-info">
                    <span>Mostrando <?php echo count($camaras); ?> de <?php echo $total_registros; ?> registros</span>
                    <span>Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre / Ubicación</th>
                                <th>Zona</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($camaras)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3;"></i>
                                    <p class="mt-2">No se encontraron cámaras</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($camaras as $camara): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($camara['inventario_id']); ?></strong></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($camara['marca']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($camara['direccion']); ?></small>
                                    </td>
                                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars($camara['zona']); ?></span></td>
                                    <td><?php echo htmlspecialchars($camara['tipo_camara']); ?></td>
                                    <td>
                                        <?php
                                        $estado_class = [
                                            'activa' => 'badge-success',
                                            'mantenimiento' => 'badge-warning',
                                            'fuera_servicio' => 'badge-danger',
                                            'pendiente' => 'badge-info'
                                        ];
                                        $estado_label = [
                                            'activa' => 'Activa',
                                            'mantenimiento' => 'Mantenimiento',
                                            'fuera_servicio' => 'Fuera de Servicio',
                                            'pendiente' => 'Pendiente'
                                        ];
                                        $estado = strtolower($camara['estatus']);
                                        ?>
                                        <span class="badge <?php echo $estado_class[$estado] ?? 'badge-secondary'; ?>">
                                            <?php echo $estado_label[$estado] ?? $camara['estatus']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="ver.php?id=<?php echo $camara['id']; ?>" class="btn btn-outline-primary" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($_SESSION['rol_id'] != 3): ?>
                                            <a href="editar.php?id=<?php echo $camara['id']; ?>" class="btn btn-outline-secondary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($_SESSION['rol_id'] == 1): ?>
                                            <a href="eliminar.php?id=<?php echo $camara['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirm('¿Eliminar permanentemente esta cámara?')"
                                               title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
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

        <!-- PAGINACIÓN -->
        <?php if ($total_paginas > 1): ?>
        <nav class="pagination">
            <!-- Primera página -->
            <?php if ($pagina_actual > 1): ?>
                <a href="?pagina=1&busqueda=<?php echo urlencode($busqueda); ?>" class="pagination-link">« Primera</a>
            <?php else: ?>
                <span class="pagination-link disabled">« Primera</span>
            <?php endif; ?>

            <!-- Anterior -->
            <?php if ($pagina_actual > 1): ?>
                <a href="?pagina=<?php echo $pagina_actual - 1; ?>&busqueda=<?php echo urlencode($busqueda); ?>" class="pagination-link">‹ Anterior</a>
            <?php else: ?>
                <span class="pagination-link disabled">‹ Anterior</span>
            <?php endif; ?>

            <!-- Números de página -->
            <div class="pagination-numbers">
                <?php
                $inicio = max(1, $pagina_actual - 2);
                $fin = min($total_paginas, $pagina_actual + 2);
                
                if ($inicio > 1) {
                    echo '<span class="pagination-link disabled">...</span>';
                }
                
                for ($i = $inicio; $i <= $fin; $i++): ?>
                    <?php if ($i == $pagina_actual): ?>
                        <span class="pagination-link active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?pagina=<?php echo $i; ?>&busqueda=<?php echo urlencode($busqueda); ?>" class="pagination-link"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($fin < $total_paginas) {
                    echo '<span class="pagination-link disabled">...</span>';
                } ?>
            </div>

            <!-- Siguiente -->
            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="?pagina=<?php echo $pagina_actual + 1; ?>&busqueda=<?php echo urlencode($busqueda); ?>" class="pagination-link">Siguiente ›</a>
            <?php else: ?>
                <span class="pagination-link disabled">Siguiente ›</span>
            <?php endif; ?>

            <!-- Última página -->
            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="?pagina=<?php echo $total_paginas; ?>&busqueda=<?php echo urlencode($busqueda); ?>" class="pagination-link">Última »</a>
            <?php else: ?>
                <span class="pagination-link disabled">Última »</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </main>

    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>