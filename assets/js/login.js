/**
 * SIGEVEM - Login JavaScript
 * Funcionalidades del formulario de login
 */

// Toggle mostrar/ocultar contraseña
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Seleccionar rol desde las tarjetas demo
function selectRol(rolId) {
    // Actualizar tarjetas visuales
    document.querySelectorAll('.role-card').forEach(card => {
        card.classList.remove('active');
    });
    
    event.currentTarget.classList.add('active');
    
    // Actualizar select
    const selectRol = document.getElementById('rol_demo');
    selectRol.value = rolId;
    
    // Animación suave
    selectRol.style.transform = 'scale(1.05)';
    setTimeout(() => {
        selectRol.style.transform = 'scale(1)';
    }, 200);
}

// Validación del formulario antes de enviar
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    // Validar email
    if (!email || !email.includes('@')) {
        e.preventDefault();
        alert('Por favor ingrese un correo electrónico válido');
        document.getElementById('email').focus();
        return false;
    }
    
    // Validar contraseña
    if (!password || password.length < 6) {
        e.preventDefault();
        alert('La contraseña debe tener al menos 6 caracteres');
        document.getElementById('password').focus();
        return false;
    }
    
    // Mostrar loading
    const btnLogin = document.querySelector('.btn-login');
    btnLogin.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando sesión...';
    btnLogin.disabled = true;
    
    return true;
});

// Auto-focus en el campo de email al cargar
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('email').focus();
});

// Efecto de entrada suave
document.addEventListener('DOMContentLoaded', function() {
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.3s ease';
        document.body.style.opacity = '1';
    }, 100);
});