<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../config/database.php';

// ============================================
// CONFIGURACIÓN
// ============================================
$registros_por_pagina = 20;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// ============================================
// FILTROS
// ============================================
$busqueda   = trim($_GET['busqueda'] ?? '');
$accion     = trim($_GET['accion'] ?? '');
$modulo     = trim($_GET['modulo'] ?? '');
$fecha_ini  = trim($_GET['fecha_ini'] ?? '');
$fecha_fin  = trim($_GET['fecha_fin'] ?? '');
$solo_mias  = isset($_GET['solo_mias']) && $_GET['solo_mias'] == '1';

// ============================================
// CONSTRUCCIÓN DE QUERY
// ============================================
$where   = [];
$params  = [];

// Técnico solo ve sus propios registros
if ($_SESSION['rol_id'] == 3) {
    $where[]  = 'b.usuario_id = :forzado_id';
    $params[':forzado_id'] = $_SESSION['usuario_id'];
} elseif ($solo_mias) {
    $where[]  = 'b.usuario_id = :solo_id';
    $params[':solo_id'] = $_SESSION['usuario_id'];
}

if ($busqueda !== '') {
    $where[]  = "(u.nombre_completo ILIKE :busqueda OR b.accion ILIKE :busqueda OR b.modulo ILIKE :busqueda OR b.descripcion ILIKE :busqueda)";
    $params[':busqueda'] = '%' . $busqueda . '%';
}

if ($accion !== '') {
    $where[]  = 'LOWER(b.accion) = LOWER(:accion)';
    $params[':accion'] = $accion;
}

if ($modulo !== '') {
    $where[]  = 'LOWER(b.modulo) = LOWER(:modulo)';
    $params[':modulo'] = $modulo;
}

if ($fecha_ini !== '') {
    $where[]  = 'b.fecha >= :fecha_ini';
    $params[':fecha_ini'] = $fecha_ini . ' 00:00:00';
}

if ($fecha_fin !== '') {
    $where[]  = 'b.fecha <= :fecha_fin';
    $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
}

$sql_where = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Total de registros
$sql_count = "SELECT COUNT(*) FROM bitacora_sistema b 
              LEFT JOIN usuarios u ON b.usuario_id = u.id 
              $sql_where";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_paginas   = max(1, ceil($total_registros / $registros_por_pagina));

// Registros paginados
$sql = "SELECT 
            b.id,
            b.fecha,
            b.accion,
            b.modulo,
            b.descripcion,
            u.nombre_completo AS usuario_nombre,
            r.nombre          AS rol_nombre
        FROM bitacora_sistema b
        LEFT JOIN usuarios u ON b.usuario_id = u.id
        LEFT JOIN roles    r ON u.rol_id = r.id
        $sql_where
        ORDER BY b.fecha DESC
        LIMIT :limite OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limite', $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// OPCIONES PARA FILTROS
// ============================================
$acciones_disponibles = $pdo->query("SELECT DISTINCT accion FROM bitacora_sistema ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);
$modulos_disponibles  = $pdo->query("SELECT DISTINCT modulo FROM bitacora_sistema ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN);

// ============================================
// HELPER: badge de acción
// ============================================
function badge_accion(string $accion): string {
    $mapa = [
        'login'        => ['fas fa-sign-in-alt',  'login'],
        'logout'       => ['fas fa-sign-out-alt', 'logout'],
        'registrar'    => ['fas fa-plus-circle',  'registrar'],
        'crear'        => ['fas fa-plus',          'crear'],
        'aprobar'      => ['fas fa-check-circle',  'aprobar'],
        'completar'    => ['fas fa-check-double',  'completar'],
        'validar'      => ['fas fa-shield-alt',    'validar'],
        'editar'       => ['fas fa-edit',          'editar'],
        'programar'    => ['fas fa-calendar-plus', 'programar'],
        'cambiar'      => ['fas fa-exchange-alt',  'cambiar'],
        'exportar'     => ['fas fa-file-export',   'exportar'],
        'eliminar'     => ['fas fa-trash',         'eliminar'],
        'rechazar'     => ['fas fa-times-circle',  'rechazar'],
        'cancelar'     => ['fas fa-ban',           'cancelar'],
    ];

    $key   = strtolower(trim($accion));
    $icon  = $mapa[$key][0] ?? 'fas fa-circle';
    $clase = $mapa[$key][1] ?? 'default';

    return "<span class='badge-accion badge-{$clase}'><i class='{$icon}'></i> " . htmlspecialchars(ucfirst($accion)) . "</span>";
}

// ============================================
// URL base para paginación conservando filtros
// ============================================
function url_pagina(int $p): string {
    $params = $_GET;
    $params['pagina'] = $p;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora | SIGEVEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/bitacora.css">
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

<!-- Botón menú móvil -->
<button class="mobile-menu-toggle d-md-none" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<?php include '../../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="dashboard-content">

    <!-- Encabezado -->
    <div class="bitacora-header">
        <div class="bitacora-title">
            <h1><i class="fas fa-clipboard-list"></i> Bitácora del Sistema</h1>
            <p>Registro completo de actividades</p>
        </div>
        <a href="exportar.php?<?php echo http_build_query(array_filter([
            'busqueda'  => $busqueda,
            'accion'    => $accion,
            'modulo'    => $modulo,
            'fecha_ini' => $fecha_ini,
            'fecha_fin' => $fecha_fin,
            'solo_mias' => $solo_mias ? '1' : '',
        ])); ?>" class="btn-export">
            <i class="fas fa-download"></i> Exportar CSV
        </a>
    </div>

    <!-- Leyenda -->
    <div class="bitacora-leyenda">
        <div class="leyenda-item">
            <span class="leyenda-dot creacion"></span>
            Creación / Aprobación
        </div>
        <div class="leyenda-item">
            <span class="leyenda-dot modificacion"></span>
            Modificación / Exportación
        </div>
        <div class="leyenda-item">
            <span class="leyenda-dot eliminacion"></span>
            Eliminación / Rechazo
        </div>
        <div class="leyenda-item">
            <span class="leyenda-dot acceso"></span>
            Acceso al Sistema
        </div>
    </div>

    <!-- Filtros -->
    <div class="bitacora-filtros">
        <form method="GET" action="" id="formFiltros">
            <div class="filtros-row">
                <!-- Buscador -->
                <div class="filtro-search">
                    <i class="fas fa-search"></i>
                    <input type="text"
                           name="busqueda"
                           placeholder="Buscar por usuario, acción, módulo..."
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>

                <!-- Acción -->
                <select name="accion" class="filtro-select" onchange="this.form.submit()">
                    <option value="">Todas las acciones</option>
                    <?php foreach ($acciones_disponibles as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>"
                            <?php echo $accion === $a ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($a)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Módulo -->
                <select name="modulo" class="filtro-select" onchange="this.form.submit()">
                    <option value="">Todos los módulos</option>
                    <?php foreach ($modulos_disponibles as $m): ?>
                        <option value="<?php echo htmlspecialchars($m); ?>"
                            <?php echo $modulo === $m ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($m)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Fechas -->
                <input type="date" name="fecha_ini" class="filtro-fecha"
                       value="<?php echo htmlspecialchars($fecha_ini); ?>"
                       title="Fecha inicio" onchange="this.form.submit()">

                <input type="date" name="fecha_fin" class="filtro-fecha"
                       value="<?php echo htmlspecialchars($fecha_fin); ?>"
                       title="Fecha fin" onchange="this.form.submit()">

                <button type="submit" class="btn-export">
                    <i class="fas fa-search"></i> Buscar
                </button>

                <a href="index.php" class="btn-limpiar" title="Limpiar filtros">
                    <i class="fas fa-eraser"></i> Limpiar
                </a>
            </div>

            <!-- Segunda fila -->
            <?php if ($_SESSION['rol_id'] != 3): ?>
            <div class="filtros-segunda-fila">
                <label class="checkbox-label">
                    <input type="checkbox" name="solo_mias" value="1"
                           <?php echo $solo_mias ? 'checked' : ''; ?>
                           onchange="this.form.submit()">
                    Mostrar solo mis acciones
                </label>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabla -->
    <div class="bitacora-tabla-container">

        <div class="tabla-info">
            <span>
                Mostrando <strong><?php echo count($registros); ?></strong>
                de <strong><?php echo number_format($total_registros); ?></strong> registros
            </span>
            <span>Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></span>
        </div>

        <?php if (count($registros) > 0): ?>
        <table class="bitacora-tabla">
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Acción</th>
                    <th>Módulo</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $reg): ?>
                <tr onclick="verDetalle(<?php echo htmlspecialchars(json_encode($reg), ENT_QUOTES); ?>)"
                    title="Click para ver detalle">
                    <td class="td-fecha">
                        <?php
                            $fecha = new DateTime($reg['fecha']);
                            echo $fecha->format('d/m/Y H:i');
                        ?>
                    </td>
                    <td class="td-usuario">
                        <?php echo htmlspecialchars($reg['usuario_nombre'] ?? 'Sistema'); ?>
                    </td>
                    <td class="td-rol">
                        <span><?php echo htmlspecialchars($reg['rol_nombre'] ?? 'Sistema'); ?></span>
                    </td>
                    <td>
                        <?php echo badge_accion($reg['accion']); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars(ucfirst($reg['modulo'])); ?>
                    </td>
                    <td class="td-descripcion" title="<?php echo htmlspecialchars($reg['descripcion'] ?? ''); ?>">
                        <?php echo htmlspecialchars($reg['descripcion'] ?? '—'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="bitacora-paginacion">
            <div class="paginacion-info">
                Registros <?php echo $offset + 1; ?>–<?php echo min($offset + $registros_por_pagina, $total_registros); ?>
                de <?php echo number_format($total_registros); ?>
            </div>
            <div class="paginacion-botones">
                <?php if ($pagina_actual > 1): ?>
                    <a href="<?php echo url_pagina(1); ?>" title="Primera"><i class="fas fa-angle-double-left"></i></a>
                    <a href="<?php echo url_pagina($pagina_actual - 1); ?>" title="Anterior"><i class="fas fa-angle-left"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                    <span class="disabled"><i class="fas fa-angle-left"></i></span>
                <?php endif; ?>

                <?php
                $rango_ini = max(1, $pagina_actual - 2);
                $rango_fin = min($total_paginas, $pagina_actual + 2);
                for ($p = $rango_ini; $p <= $rango_fin; $p++):
                ?>
                    <?php if ($p === $pagina_actual): ?>
                        <span class="activa"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="<?php echo url_pagina($p); ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($pagina_actual < $total_paginas): ?>
                    <a href="<?php echo url_pagina($pagina_actual + 1); ?>" title="Siguiente"><i class="fas fa-angle-right"></i></a>
                    <a href="<?php echo url_pagina($total_paginas); ?>" title="Última"><i class="fas fa-angle-double-right"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-angle-right"></i></span>
                    <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="tabla-vacia">
            <i class="fas fa-clipboard-list"></i>
            <p>No se encontraron registros con los filtros aplicados.</p>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- Modal de Detalle -->
<div class="modal-overlay" id="modalDetalle" onclick="cerrarModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalle del Registro</h3>
            <button class="modal-close" onclick="document.getElementById('modalDetalle').classList.remove('active')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script src="../../assets/js/dashboard.js"></script>
<script>
function verDetalle(reg) {
    const fecha = new Date(reg.fecha);
    const fechaStr = fecha.toLocaleDateString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });

    document.getElementById('modalBody').innerHTML = `
        <div class="modal-row">
            <span class="modal-label">Fecha y Hora</span>
            <span class="modal-value">${fechaStr}</span>
        </div>
        <div class="modal-row">
            <span class="modal-label">Usuario</span>
            <span class="modal-value">${reg.usuario_nombre || 'Sistema'}</span>
        </div>
        <div class="modal-row">
            <span class="modal-label">Rol</span>
            <span class="modal-value">${reg.rol_nombre || 'Sistema'}</span>
        </div>
        <div class="modal-row">
            <span class="modal-label">Acción</span>
            <span class="modal-value">${reg.accion}</span>
        </div>
        <div class="modal-row">
            <span class="modal-label">Módulo</span>
            <span class="modal-value">${reg.modulo}</span>
        </div>
        <div class="modal-row">
            <span class="modal-label">Descripción</span>
            <span class="modal-value">${reg.descripcion || '—'}</span>
        </div>
    `;

    document.getElementById('modalDetalle').classList.add('active');
}

function cerrarModal(e) {
    if (e.target === document.getElementById('modalDetalle')) {
        document.getElementById('modalDetalle').classList.remove('active');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('modalDetalle').classList.remove('active');
    }
});
</script>
</body>
</html>