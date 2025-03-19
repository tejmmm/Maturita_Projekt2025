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

// Načteme detaily vozidla
$conn = getDbConnection();
$query = "SELECT * FROM vozidla WHERE vehicle_id = $1";
$result = pg_query_params($conn, $query, [$vehicle_id]);

if (!$result || pg_num_rows($result) === 0) {
    die("Vozidlo nebylo nalezeno.");
} else {
    $vehicle = pg_fetch_assoc($result);
}

if ($vehicle['status'] !== 'in_service') {
    die("Vozidlo není v servisu.");
}

// Změníme stav vozidla na "available"
$update_query = "UPDATE vozidla SET status = 'Dostupné' WHERE vehicle_id = $1";
pg_query_params($conn, $update_query, [$vehicle_id]);

pg_close($conn);

// Přesměrujeme admina zpět na stránku vozidla
header("Location: vehicle_detail.php?vehicle_id=" . $vehicle_id);
exit;
?>