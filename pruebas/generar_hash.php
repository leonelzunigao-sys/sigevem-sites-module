<?php
// Los 3 usuarios con sus contraseñas
$usuarios = [
    'admin@ecatepec.gob.mx' => 'admin123!',
    'supervisor@ecatepec.gob.mx' => 'sup123!',
    'tecnico@ecatepec.gob.mx' => 'tec123!'
];

echo "<h1>HASHES PARA LOS 3 USUARIOS</h1>";
echo "<pre>";

foreach ($usuarios as $email => $pass) {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    echo "================================\n";
    echo "Email: $email\n";
    echo "Password: $pass\n";
    echo "Hash: $hash\n\n";
}

echo "</pre>";

// Generar SQL
echo "<h3>COPIA ESTO Y PÉGALO EN POSTGRESQL:</h3>";
echo "<pre>";
foreach ($usuarios as $email => $pass) {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    echo "UPDATE usuarios SET password_hash = '$hash' WHERE email = '$email';\n";
}
echo "</pre>";
?>