/**
 * SIGEVEM - Dashboard JavaScript
 * Funcionalidades del dashboard
 */

// Toggle sidebar en móvil
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}

// Cerrar sidebar al hacer clic fuera en móvil
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.mobile-menu-toggle');
    
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    }
});

// Marcar menú activo según la página actual
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop();
    const menuItems = document.querySelectorAll('.menu-item');
    
    menuItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href === currentPage || (currentPage === '' && href === 'index.php')) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
});

// Botones de aprobación/rechazo (demo)
document.addEventListener('DOMContentLoaded', function() {
    const approveButtons = document.querySelectorAll('.btn-approve');
    const rejectButtons = document.querySelectorAll('.btn-reject');
    
    approveButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.validation-item');
            card.style.opacity = '0.5';
            alert('Cámara aprobada (demo)');
        });
    });
    
    rejectButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.validation-item');
            card.style.opacity = '0.5';
            alert('Cámara rechazada (demo)');
        });
    });
});

// Fecha y hora en tiempo real en el header
function actualizarFecha() {
    const el = document.getElementById('headerDatetime');
    if (!el) return;
    const ahora = new Date();
    const opciones = {
        weekday: 'short', day: 'numeric',
        month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
        hour12: false
    };
    el.textContent = ahora.toLocaleDateString('es-MX', opciones);
}
actualizarFecha();
setInterval(actualizarFecha, 60000);