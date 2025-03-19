<?php
// Funkce pro připojení k databázi
function getDbConnection() {
    $host = getenv('dpg-cvd87b5umphs73ea242g-a'); // Hostitel databáze z Environment Variables
    $db = getenv('maturitni_db'); // Název databáze
    $user = getenv('maturitni_db_user'); // Uživatelské jméno
    $pass = getenv('qHISdm8VypBWJHPWrq1GNGFuITm7Hb37'); // Heslo
    $port = getenv('5432'); // Port databáze (Render používá 5432)

    // Připojení k databázi pomocí PostgreSQL
    $conn = pg_connect("host=$host port=$port dbname=$db user=$user password=$pass");

    if (!$conn) {
        die("Chyba připojení: " . pg_last_error());
    }

    return $conn;
}
?>

