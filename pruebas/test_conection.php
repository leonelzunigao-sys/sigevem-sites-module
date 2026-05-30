<?php
// Prueba simple de conexión a PostgreSQL

$host = 'localhost';
$port = '5432';
$dbname = 'sigevem_db';
$user = 'postgres';  // ← Cambia si tu usuario es diferente
$pass = 'password';  // ← Tu password de PostgreSQL

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ ¡Conexión exitosa a PostgreSQL!<br>";
    echo "Host: $host<br>";
    echo "Puerto: $port<br>";
    echo "Base de datos: $dbname<br>";
    echo "Usuario: $user<br>";
    
    // Verificar que las tablas existen
    $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
    $stmt = $pdo->query($query);
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<br>✅ Tablas encontradas: " . count($tablas) . "<br>";
    echo "<pre>" . implode(", ", $tablas) . "</pre>";
    
} catch (PDOException $e) {
    echo "❌ Error de conexión:<br>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<br><strong>Posibles causas:</strong><br>";
    echo "1. Usuario o contraseña incorrectos<br>";
    echo "2. PostgreSQL no está corriendo<br>";
    echo "3. La base de datos 'sigevem' no existe<br>";
    echo "4. El puerto 5432 está bloqueado<br>";
}
?>