<aside class="sidebar" id="sidebar">
    <div class="sidebar-menu">
        <!-- Dashboard - Visible para todos -->
        <a href="../dashboard/index.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'dashboard') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-th"></i>
            <span>Dashboard</span>
            <i class="fas fa-chevron-right"></i>
        </a>
        
        <!-- Mapa - Visible para todos -->
        <a href="../mapa/index.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'mapa') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-map"></i>
            <span>Mapa</span>
        </a>
        
        <!-- Cámaras - Visible para todos -->
        <a href="../camaras/index.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'camaras') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-camera"></i>
            <span>Cámaras</span>
        </a>
        
        <!-- Sitios - Visible para todos (NUEVO) -->
        <a href="../sitios/index.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'sitios') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Sitios</span>
        </a>
        
        <!-- Mantenimiento - Visible para todos -->
        <a href="../mantenimiento/index.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'mantenimiento') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-tools"></i>
            <span>Mantenimiento</span>
        </a>
        
        <!-- Reportes - OCULTO para Técnicos (rol_id = 3) -->
        <?php if ($_SESSION['rol_id'] != 3): ?>
        <a href="../reportes/index.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'reportes') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reportes</span>
        </a>
        <?php endif; ?>
        
        <!-- Bitácora - Visible para todos -->
        <a href="../bitacora/index.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'bitacora') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Bitácora</span>
        </a>
        
        <!-- Usuarios - SOLO para Administrador (rol_id = 1) -->
        <?php if ($_SESSION['rol_id'] == 1): ?>
        <a href="../usuarios/index.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'usuarios') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Usuarios</span>
        </a>
        <?php endif; ?>
    </div>
    
    <!-- Footer del Sidebar -->
    <div class="sidebar-footer">
        <div class="user-info">
            <span class="user-role-badge"><?php echo htmlspecialchars($_SESSION['rol_nombre']); ?></span>
            <p class="user-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
            <p class="user-email"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
        </div>
        <a href="../auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</aside>