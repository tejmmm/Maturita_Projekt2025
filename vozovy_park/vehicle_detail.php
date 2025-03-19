<?php
require_once 'vehicle.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Prague');
setlocale(LC_TIME, 'cs_CZ.UTF-8');

// Zkontrolujeme, zda je v $_SESSION definován klíč 'role'
$user_role = $_SESSION['role'] ?? 'user';  // Pokud není role v $_SESSION, nastaví se výchozí hodnota 'user'

// Zkontrolujeme, zda byl předán parametr `vehicle_id`
if (!isset($_GET['vehicle_id'])) {
    $message = "Vozidlo nebylo specifikováno.";
} else {
    $vehicle_id = $_GET['vehicle_id'];
    $user_id = $_SESSION['user_id'] ?? null;

    // Načteme detaily vozidla
    $conn = getDbConnection();
    $query = "SELECT * FROM vozidla WHERE vehicle_id = $1";
    $result = pg_query_params($conn, $query, [$vehicle_id]);

    if (!$result || pg_num_rows($result) === 0) {
        $message = "Vozidlo nebylo nalezeno.";
    } else {
        $vehicle = pg_fetch_assoc($result);
    }

    // Zkontrolujeme, zda má uživatel aktivní rezervaci na toto vozidlo
    $query = "SELECT * FROM vypujcky WHERE vehicle_id = $1 AND user_id = $2 AND status = 'Aktivní'";
    $reservation_result = pg_query_params($conn, $query, [$vehicle_id, $user_id]);
    $has_active_reservation = pg_num_rows($reservation_result) > 0;

    pg_close($conn);
}

// Zpracování formuláře na poznámky a fotografie
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_note']) && $has_active_reservation) {
    $note = trim($_POST['note']);
    $photoPath = null;

    // Pokud byla nahrána fotka
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/";
        $fileName = time() . "_" . basename($_FILES['photo']['name']);
        $targetFilePath = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFilePath)) {
            $photoPath = $targetFilePath;
        } else {
            $message = "Chyba při nahrávání fotografie.";
        }
    }

    // Uložení poznámky a fotky do databáze
    $conn = getDbConnection();
    $query = "INSERT INTO poznámky (user_id, vehicle_id, note, photo_path) VALUES ($1, $2, $3, $4)";
    $result = pg_query_params($conn, $query, [$user_id, $vehicle_id, $note, $photoPath]);

    if ($result) {
        $message = "Poznámka byla uložena.";
    } else {
        $message = "Chyba při ukládání poznámky: " . pg_last_error($conn);
    }

    pg_close($conn);
}

?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail vozidla</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Vlastní CSS -->
    <link href="style.css" rel="stylesheet">

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .vehicle-info {
            font-size: 18px;
        }
        .vehicle-info strong {
            color: #0056b3;
        }
        .back-btn {
            width: 150px;
            display: block;
            margin: 0 auto;
        }
        .vehicle-image {
            max-width: 100%;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Správa vozového parku</a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (isset($vehicle)): ?>
            <div class="card p-4">
                <h2 class="mb-3 text-center"><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></h2>
                
                <div class="row">
                    <div class="col-md-6">
                        <p class="vehicle-info"><strong>📅 Rok výroby:</strong> <?= htmlspecialchars($vehicle['year']) ?></p>
                        <p class="vehicle-info"><strong>🚗 Najeté km:</strong> <?= htmlspecialchars($vehicle['odometer']) ?></p>
                        <p class="vehicle-info"><strong>⛽ Spotřeba:</strong> <?= htmlspecialchars($vehicle['fuel_cons']) ?> l/100km</p>
                        <p class="vehicle-info"><strong>⚙️ Stav:</strong> <?= htmlspecialchars($vehicle['status']) ?></p>
                        <p class="vehicle-info">
                            <strong>🎛️ Výbava:</strong> 
                            <?= !empty($vehicle['vybava']) ? htmlspecialchars($vehicle['vybava']) : "<em>Nezadáno</em>" ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-center">
                        <?php if (!empty($vehicle['photo_path'])): ?>
                            <img src="<?= htmlspecialchars($vehicle['photo_path']) ?>" class="vehicle-image">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tlačítko pro administrátora pro přesun do servisu -->
                <?php if ($user_role === 'admin' && $vehicle['status'] !== 'in_service'): ?>
                    <a href="move_to_service.php?vehicle_id=<?= $vehicle_id ?>" class="btn btn-warning mt-3">Dat auto do servisu</a>
                <?php elseif ($user_role === 'admin' && $vehicle['status'] === 'in_service'): ?>
                    <span class="text-muted">Vozidlo je v servisu.</span>
                <?php endif; ?>

                <a href="index.php" class="btn btn-primary mt-3 back-btn">Zpět</a>
            </div>

            <?php if ($has_active_reservation): ?>
                <div class="card p-4 mt-4">
                    <h3>Přidat poznámku k vozidlu</h3>
                    <form method="post" enctype="multipart/form-data" name="noteForm">
                        <div class="mb-3">
                            <label for="note" class="form-label">Poznámka:</label>
                            <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="photo" class="form-label">Přidat fotografii:</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                        </div>
                        <button type="submit" name="submit_note" class="btn btn-success">Odeslat</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Zobrazení historie servisu pro vozidlo -->
            <div class="mt-4">
                <h3>Historie servisu:</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Akce</th>
                            <th>Datum servisu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $conn = getDbConnection();
                        $history_query = "SELECT repair_description, repair_date FROM service_history WHERE vehicle_id = $1 ORDER BY repair_date DESC";
                        $history_result = pg_query_params($conn, $history_query, [$vehicle_id]);

                        while ($history = pg_fetch_assoc($history_result)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($history['repair_description']) . "</td>";
                            echo "<td>" . date('d.m.Y H:i', strtotime($history['repair_date'] ?? '')) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="alert alert-danger">Vozidlo nebylo nalezeno.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelector("form[name='noteForm']").addEventListener("submit", function () {
                sessionStorage.setItem("noteSubmitted", "true");
            });
        });
    </script>
</body>
</html>

