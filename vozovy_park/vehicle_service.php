<?php
require_once 'config.php'; // Připojení k databázi

date_default_timezone_set('Europe/Prague');
setlocale(LC_TIME, 'cs_CZ.UTF-8');

// Získání ID vozidla
$vehicle_id = $_GET['vehicle_id'] ?? null;
if (!$vehicle_id) {
    die("Chyba: Vozidlo nebylo specifikováno.");
}

// Připojení k databázi a získání detailů vozidla
$conn = getDbConnection();
$query = "SELECT * FROM vozidla WHERE vehicle_id = $1";
$result = pg_query_params($conn, $query, [$vehicle_id]);

if (!$result || pg_num_rows($result) === 0) {
    die("Vozidlo nebylo nalezeno.");
}

$vehicle = pg_fetch_assoc($result);

// Funkce pro generování náhodných oprav
function getRepairsWithNotes($conn, $vehicle_id) {  
    $staticRepairs = [
        "Výměna brzdových destiček",
        "Výměna oleje",
        "Oprava motoru",
        "Kontrola výfukového systému",
        "Oprava klimatizace",
        "Oprava Spojky",
        "Kontrola a doplnění provozních kapalin",
        "Seřízení geometrie kol",
        "Oprava Světel",
    ];

    shuffle($staticRepairs);
    $selectedRepairs = array_slice($staticRepairs, 0, rand(1, 2));

    $query = "SELECT note FROM poznámky WHERE vehicle_id = $1";
    $result = pg_query_params($conn, $query, [$vehicle_id]);

    $notes = [];
    if ($result && pg_num_rows($result) > 0) {
        while ($row = pg_fetch_assoc($result)) {
            $note = trim($row['note']);
            if (!empty($note)) {
                $notes[] = "Opraveno: " . ucfirst($note);
            }
        }
    }

    return array_merge($notes, $selectedRepairs);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_repair'])) {
    $repairs = getRepairsWithNotes($conn, $vehicle_id);

    foreach ($repairs as $repair) {
        $query = "INSERT INTO service_history (vehicle_id, repair_description, repair_date) VALUES ($1, $2, NOW())";
        pg_query_params($conn, $query, [$vehicle_id, $repair]);
    }

    $query = "UPDATE vozidla SET status = 'Dostupné', service_status = 'Dostupné' WHERE vehicle_id = $1";
    pg_query_params($conn, $query, [$vehicle_id]);

    $_SESSION['repairSuccess'] = "Opravy byly úspěšně dokončeny. Vozidlo je nyní dostupné.";
    header("Location: vehicle_service.php?vehicle_id=" . $vehicle_id);
    exit;
}

$query = "SELECT * FROM service_history WHERE vehicle_id = $1 ORDER BY repair_date DESC";
$result = pg_query_params($conn, $query, [$vehicle_id]);
$service_history = pg_fetch_all($result) ?: [];

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Správa vozového parku</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Správa vozového parku</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Správa servisu vozidla: <?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></h1>
            <a href="index.php" class="btn btn-primary">Zpět na seznam vozidel</a>
        </div>

        <h4>Rok výroby: <?= htmlspecialchars($vehicle['year']) ?></h4>

        <!-- Tlačítko pro zobrazení potvrzovacího modalu -->
        <button type="button" class="btn btn-warning mt-3" data-bs-toggle="modal" data-bs-target="#confirmRepairModal">
            Opravit
        </button>

        <h3 class="mt-4">Historie servisu:</h3>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Popis opravy</th>
                    <th>Datum opravy</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($service_history as $repair): ?>
                    <tr>
                        <td><?= htmlspecialchars($repair['repair_description']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($repair['repair_date'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal pro potvrzení opravy -->
    <div class="modal fade" id="confirmRepairModal" tabindex="-1" aria-labelledby="confirmRepairModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Potvrzení opravy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
                </div>
                <div class="modal-body">
                    Opravdu chcete provést opravu tohoto vozidla?
                </div>
                <div class="modal-footer">
                    <form method="post">
                        <button type="submit" name="confirm_repair" class="btn btn-success">Ano, opravit</button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pro úspěšné dokončení oprav -->
    <div class="modal fade" id="repairSuccessModal" tabindex="-1" aria-labelledby="repairSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Oprava dokončena</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
                </div>
                <div class="modal-body">
                    <?= isset($_SESSION['repairSuccess']) ? htmlspecialchars($_SESSION['repairSuccess']) : '' ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        <?php if (!empty($_SESSION['repairSuccess'])): ?>
            var repairSuccessModal = new bootstrap.Modal(document.getElementById("repairSuccessModal"));
            repairSuccessModal.show();
            setTimeout(function() {
                window.location.href = "vehicle_service.php?vehicle_id=<?= $vehicle_id ?>";
            }, 2000);
            <?php unset($_SESSION['repairSuccess']); ?>
        <?php endif; ?>
    });
    </script>
</body>
</html>
