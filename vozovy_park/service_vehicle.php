<?php
require_once 'vehicle.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['vehicle_id'])) {
    $message = "Vozidlo nebylo specifikováno.";
} else {
    $vehicle_id = $_GET['vehicle_id'];

    // Načtení informací o vozidle
    $conn = getDbConnection();
    $query = "SELECT * FROM vozidla WHERE vehicle_id = $1";
    $result = pg_query_params($conn, $query, [$vehicle_id]);

    if (!$result || pg_num_rows($result) === 0) {
        $message = "Vozidlo nebylo nalezeno.";
    } else {
        $vehicle = pg_fetch_assoc($result);
    }
    pg_close($conn);
}

// Zpracování formuláře pro servisní záznam
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_description = $_POST['service_description'];
    $service_date = date('Y-m-d');
    $service_cost = rand(500, 2000); // Generování náhodných nákladů

    // Simulovaná změna stavu vozidla po servisu
    $status = 'V servisu - oprava dokončena';

    // Vložení záznamu do databáze
    $conn = getDbConnection();
    $query = "INSERT INTO vehicle_service_history (vehicle_id, service_description, service_date, service_cost) VALUES ($1, $2, $3, $4)";
    pg_query_params($conn, $query, [$vehicle_id, $service_description, $service_date, $service_cost]);

    // Aktuální stav vozidla
    $update_query = "UPDATE vozidla SET status = $1 WHERE vehicle_id = $2";
    pg_query_params($conn, $update_query, [$status, $vehicle_id]);

    pg_close($conn);

    header("Location: vehicle_detail.php?vehicle_id=" . $vehicle_id);
    exit();
}

?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Servis vozidla</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Správa vozového parku</a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php else: ?>
            <h2>Servis vozidla - <?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></h2>
            <form action="service_vehicle.php?vehicle_id=<?= htmlspecialchars($vehicle['vehicle_id']) ?>" method="POST">
                <div class="mb-3">
                    <label for="service_description" class="form-label">Popis opravy</label>
                    <textarea class="form-control" id="service_description" name="service_description" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Zahájit servis</button>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>