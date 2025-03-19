<?php
//C:\php\php.exe C:\php\php.exe -S localhost:8000
    // Funkce pro připojení k databázi
    function getDbConnection() {
        $host = 'dpg-cvd87b5umphs73ea242g-a'; // Hostitel serveru (pro lokální server nechej localhost)
        $db = 'maturitni_db'; // Název databáze
        $user = 'maturitni_db_user'; // Uživatelské jméno PostgreSQL
        $pass = 'qHISdm8VypBWJHPWrq1GNGFuITm7Hb37'
        $pass = '5432'; // Heslo k PostgreSQL uživateli
    
        // Připojení k databázi
        $conn = pg_connect("host=$host dbname=$db user=$user password=$pass");
    
        if (!$conn) {
            die("Chyba připojení: " . pg_last_error());
        }
    
        return $conn;
    }
?>
