<?php
//C:\php\php.exe C:\php\php.exe -S localhost:8000
    // Funkce pro připojení k databázi
    function getDbConnection() {
        $host = 'localhost'; // Hostitel serveru (pro lokální server nechej localhost)
        $db = 'SpravaVozParku'; // Název databáze
        $user = 'postgres'; // Uživatelské jméno PostgreSQL
        $pass = '123'; // Heslo k PostgreSQL uživateli
    
        // Připojení k databázi
        $conn = pg_connect("host=$host dbname=$db user=$user password=$pass");
    
        if (!$conn) {
            die("Chyba připojení: " . pg_last_error());
        }
    
        return $conn;
    }
?>
