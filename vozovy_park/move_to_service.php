<?php
require_once 'vehicle.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zkontrolujeme, zda je uživatel admin
$user_email = $_SESSION['email'] ?? '';
if ($user_email !== 'ondra.zbori@seznam.cz') {
    die("Nemáte oprávnění k přístupu na tuto stránku.");
}

// Načteme ID vozidla
if (!isset($_GET['vehicle_id'])) {
    die("Vozidlo nebylo specifikováno.");
}

$vehicle_id = $_GET['vehicle_id'];

// Připojení k databázi
$conn = getDbConnection();
$query = "SELECT * FROM vozidla WHERE vehicle_id = $1";
$result = pg_query_params($conn, $query, [$vehicle_id]);

if (!$result || pg_num_rows($result) === 0) {
    die("Vozidlo nebylo nalezeno.");
}

$vehicle = pg_fetch_assoc($result);

if ($vehicle['status'] === 'in_service') {
    die("Vozidlo je již v servisu.");
}

// Změníme stav vozidla na "in_service"
$update_query = "UPDATE vozidla SET status = 'Servis', service_status = 'V servisu' WHERE vehicle_id = $1";
pg_query_params($conn, $update_query, [$vehicle_id]);

// Přidáme nový záznam do historie servisu
$action = 'Pravidelný servis';
$cost = rand(1000, 5000); // Náhodná cena servisu
$insert_query = "INSERT INTO vehicle_service_history (vehicle_id, action, cost) VALUES ($1, $2, $3)";
pg_query_params($conn, $insert_query, [$vehicle_id, $action, $cost]);

pg_close($conn);

// Přesměrování na admin_notes.php
header("Location: admin_notes.php");
exit;
?>