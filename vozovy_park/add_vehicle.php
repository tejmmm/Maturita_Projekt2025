<?php
require_once 'vehicle.php'; // Připojení k databázi

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$user_email = $_SESSION['email'] ?? ''; // Získání emailu aktuálního uživatele

// Zpracování POST požadavků
$message = ''; // Pro chybová a informační hlášení

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Přidání vozidla
    if (isset($_POST['add_vehicle'])) {
        $brand = $_POST['brand'];
        $model = $_POST['model'];
        $year = $_POST['year'];
        $odometer = $_POST['odometer'];
        $fuel_cons = $_POST['fuel_cons'];
        $status = $_POST['status'];
        $next_due_km = isset($_POST['next_due_km']) ? $_POST['next_due_km'] : 0;
        $vybava = isset($_POST['vybava']) ? implode(", ", $_POST['vybava']) : null;

        // Zpracování nahrání fotografie
        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "uploads/";
            $fileName = basename($_FILES['photo']['name']);
            $targetFilePath = $targetDir . $fileName;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFilePath)) {
                $photoPath = $targetFilePath;
            } else {
                $message = "Chyba při nahrávání fotografie.";
            }
        }

        $conn = getDbConnection();
        $query = "
    INSERT INTO vozidla (brand, model, year, odometer, fuel_cons, status, next_due_km, photo_path, vybava) 
    VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)
";
$result = pg_query_params($conn, $query, [$brand, $model, $year, $odometer, $fuel_cons, $status, $next_due_km, $photoPath, $vybava]);

        if (!$result) {
            $message = "Chyba při přidávání vozidla: " . pg_last_error($conn);
        } else {
            $message = "Vozidlo bylo úspěšně přidáno.";
        }

        pg_close($conn);
    }

    // Ukončení rezervace
    if (isset($_POST['end_reservation_id'])) {
        $reservation_id = $_POST['end_reservation_id'];
        endReservation($reservation_id); // Ukončí rezervaci a aktualizuje databázi
        header("Location: add_vehicle.php");
        exit;
    }
}

// Funkce pro získání všech aktivních rezervací
function getAllActiveReservations() {
    $conn = getDbConnection();
    $query = "
        SELECT 
            r.reservation_id,
            u.username AS user_name,
            u.email AS user_email,
            v.brand,
            v.model,
            r.start_date,
            r.end_date
        FROM vypujcky r
        JOIN vozidla v ON r.vehicle_id = v.vehicle_id
        JOIN uzivatele u ON r.user_id = u.user_id
        WHERE r.status = 'Aktivní'
        ORDER BY r.start_date DESC;
    ";
    $result = pg_query($conn, $query);

    if (!$result) {
        die("Chyba při načítání aktivních rezervací: " . pg_last_error($conn));
    }

    $reservations = pg_fetch_all($result) ?: [];
    pg_close($conn);
    return $reservations;
}

// Načtení aktivních rezervací (pouze pro admina)
$active_reservations = $user_email === 'ondra.zbori@seznam.cz' ? getAllActiveReservations() : [];
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přidat nové vozidlo</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Vlastní CSS -->
    <link href="style.css" rel="stylesheet">

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

    <nav class="navbar navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Správa vozového parku</a>
            <a href="?logout=true" class="btn btn-danger">Odhlásit se</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card mx-auto p-4" style="max-width: 600px;">
            <h2 class="mb-4 text-center">🚗 Přidat nové vozidlo</h2>

            <?php if (!empty($message)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="brand" class="form-label">Značka</label>
                    <input type="text" class="form-control" id="brand" name="brand" required>
                </div>
                <div class="mb-3">
                    <label for="model" class="form-label">Model</label>
                    <input type="text" class="form-control" id="model" name="model" required>
                </div>
                <div class="mb-3">
                    <label for="year" class="form-label">Rok výroby</label>
                    <input type="number" class="form-control" id="year" name="year" required>
                </div>
                <div class="mb-3">
                    <label for="odometer" class="form-label">Najeté km</label>
                    <input type="number" class="form-control" id="odometer" name="odometer" required>
                </div>
                <div class="mb-3">
                    <label for="fuel_cons" class="form-label">Spotřeba (l/100 km)</label>
                    <input type="number" step="0.1" class="form-control" id="fuel_cons" name="fuel_cons" required>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Stav vozidla</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="Dostupné">Dostupné</option>
                        <option value="Servis">Servis</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="photo" class="form-label">Fotografie vozidla</label>
                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                </div>

                <div class="mb-3">
                    <label class="form-label">Výbava vozidla:</label>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="vybava[]" value="Vyhřívaná sedadla" id="sedacky">
                                <label class="form-check-label" for="sedacky">Vyhřívaná sedadla</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="vybava[]" value="Klimatizace" id="klima">
                                <label class="form-check-label" for="klima">Klimatizace</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="vybava[]" value="Navigace" id="navigace">
                                <label class="form-check-label" for="navigace">Navigace</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="vybava[]" value="Tempomat" id="tempomat">
                                <label class="form-check-label" for="tempomat">Tempomat</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="vybava[]" value="Parkovací senzory" id="park_senzory">
                                <label class="form-check-label" for="park_senzory">Parkovací senzory</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="vybava[]" value="4x4 pohon" id="pohon4x4">
                                <label class="form-check-label" for="pohon4x4">4x4 pohon</label>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="add_vehicle" class="btn btn-primary w-100">Přidat vozidlo</button>
            </form>
        </div>
    </div>

</body>
</html>

        <!-- Zobrazení aktivních rezervací pro admina -->
        <?php if ($user_email === 'ondra.zbori@seznam.cz' && !empty($active_reservations)): ?>
    <div class="card mt-4 mx-auto" style="max-width: 800px;">
        <h3 class="mb-3 text-center">📅 Aktivní rezervace</h3>
        <table class="table table-hover table-sm text-center">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Uživatel</th>
                    <th>Vozidlo</th>
                    <th>Začátek</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_reservations as $reservation): ?>
                    <tr>
                        <td><?= htmlspecialchars($reservation['reservation_id']) ?></td>
                        <td>
                            <small><?= htmlspecialchars($reservation['user_name']) ?></small><br>
                            <span class="text-muted" style="font-size: 12px;"><?= htmlspecialchars($reservation['user_email']) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($reservation['brand'] . ' ' . $reservation['model']) ?></strong>
                        </td>
                        <td>
                            <small><?= date('d.m.Y', strtotime($reservation['start_date'])) ?></small>
                        </td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="end_reservation_id" value="<?= $reservation['reservation_id'] ?>">
                                <button class="btn btn-outline-danger btn-sm" title="Ukončit rezervaci">
                                    ❌
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>