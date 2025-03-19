<?php
// Funkce pro připojení k databázi
function getDbConnection() {
    $host = getenv('DB_HOST'); // Hostitel databáze
    $db = getenv('DB_NAME'); // Název databáze
    $user = getenv('DB_USER'); // Uživatelské jméno
    $pass = getenv('DB_PASS'); // Heslo
    $port = getenv('DB_PORT'); // Port databáze (obvykle 5432)

    // Ověříme, zda všechny proměnné byly správně načteny
    if (!$host || !$db || !$user || !$pass || !$port) {
        die("Chyba: Chybí environment variables!");
    }

    // Připojení k databázi pomocí PostgreSQL
    $conn = pg_connect("host=$host port=$port dbname=$db user=$user password=$pass");

    if (!$conn) {
        die("Chyba připojení k databázi: " . pg_last_error($conn));
    }

    return $conn;
}
?>

