<?php
require_once 'vehicle.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

autoCancelExpiredReservations();

// Nastavení časové zóny
date_default_timezone_set('Europe/Prague');
setlocale(LC_TIME, 'cs_CZ.UTF-8');

// Zpracování odhlášení
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Pokud uživatel není přihlášen, přesměrujeme ho na přihlašovací stránku
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Načtení dat
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email'];
$vehicles = getAllVehiclesWithDetails();
$user_reservations = getUserReservationsWithDetails($user_id);
$user_details = getUserDetails($user_id); // Získání údajů o uživateli



// Zpracování požadavků POST
$message = ''; // Pro zobrazení zprávy o výpočtu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rezervace vozidla
    if (isset($_POST['vehicle_id']) && isset($_POST['reserve_vehicle'])) {
        if ($user_email === 'ondra.zbori@seznam.cz') {
            $_SESSION['reservationError'] = "Admin nemůže rezervovat vozidla.";
            header("Location: index.php");
            exit;
        }

        $active_reservations = array_filter($user_reservations, fn($res) => $res['status'] === 'Aktivní');
        if (!empty($active_reservations)) {
            $_SESSION['reservationError'] = "Nemůžete rezervovat další vozidlo, dokud nemáte ukončenou aktuální rezervaci.";
            header("Location: index.php");
            exit;
        }

        $vehicle_id = $_POST['vehicle_id'];
        reserveVehicle($vehicle_id, $user_id);
        header("Location: index.php");
        exit;
    }



    // Ukončení rezervace
    if (isset($_POST['end_reservation_id'])) {
        $reservation_id = $_POST['end_reservation_id'];
        endReservation($reservation_id);
        header("Location: index.php");
        exit;
    }
    // Aktualizace tachometru u půjčeného auta
    if (isset($_POST['update_odometer']) && isset($_POST['current_odometer']) && isset($_POST['vehicle_id'])) {
        $vehicle_id = $_POST['vehicle_id'];
        $current_odometer = (int) $_POST['current_odometer'];
    
        // Najdeme vozidlo s odpovídajícím ID
        $vehicle = array_filter($vehicles, fn($v) => isset($v['vehicle_id']) && $v['vehicle_id'] == $vehicle_id);
        if (!empty($vehicle)) {
            $vehicle = reset($vehicle);
            $old_odometer = (int) $vehicle['odometer'];
    
            // Kontrola, zda nezadává nižší počet km než aktuální stav tachometru
            if ($current_odometer < $old_odometer) {
                $message = "Nemůžete zadat nižší počet kilometrů než aktuální stav tachometru.";
            } else {
                // Aktualizujeme tachometr a získáme CELKOVÝ rozdíl
                $total_driven_km = updateOdometer($vehicle_id, $current_odometer, $user_id);
    
                // Znovu načteme vozidla, aby se nový stav tachometru zobrazil na stránce
                $vehicles = getAllVehiclesWithDetails();
    
                // Uložíme CELKOVÝ počet najetých km do session
                $_SESSION["najete_km_$vehicle_id"] = $total_driven_km;
            }
        }
    }

    // Mazání ukončené rezervace (pouze uživatele)
    if (isset($_POST['delete_reservation_id'])) {
        $reservation_id = $_POST['delete_reservation_id'];
        deleteReservation($reservation_id);
        header("Location: index.php");
        exit;
    }

    // Mazání vozidla (pouze admin)
    if (isset($_POST['delete_vehicle_id']) && $user_email === 'ondra.zbori@seznam.cz') {
        $vehicle_id = $_POST['delete_vehicle_id'];
        $vehicle = array_filter($vehicles, fn($v) => $v['vehicle_id'] == $vehicle_id);
        $vehicle = reset($vehicle);

        if ($vehicle['rental_status'] === 'Půjčeno') {
            $message = "Vozidlo je rezervováno a nemůže být odstraněno.";
        } else {
            deleteVehicle($vehicle_id);
            header("Location: index.php");
            exit;
        }
    }
    
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Správa vozového parku</title>

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
            <a class="navbar-brand" href="#">Správa vozového parku</a>
            <div class="d-flex align-items-center">
    <div class="text-white me-3">
        <strong><?= htmlspecialchars($user_details['username']) ?></strong><br>
        <small><?= htmlspecialchars($user_details['email']) ?></small>
    </div>
    
    <?php if ($user_email === 'ondra.zbori@seznam.cz'): ?>
        <a href="admin_notes.php" class="btn btn-warning me-4">Poznámky uživatelů</a>
        <a href="history.php" class="btn btn-info me-4">Historie rezervací</a> 
    <?php endif; ?>
    

    <a href="?logout=true" class="btn btn-danger">Odhlásit se</a>
</div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>Správa vozového parku</h1>
            <?php if ($user_email === 'ondra.zbori@seznam.cz'): ?>
                <a href="add_vehicle.php" class="btn btn-primary">+ Přidat vozidlo</a>
            <?php endif; ?>
        </div>

        <div class="card p-4">
            <h2>Seznam vozidel</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Značka</th>
                        <th>Model</th>
                        <th>Rok výroby</th>
                        <th>Najeté km</th>
                        <th>Spotřeba</th>
                        <th>Stav</th>
                        <th>Stav půjčení</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td><?= htmlspecialchars($vehicle['vehicle_id'] ?? '') ?></td>
                            <td>
                                <a href="vehicle_detail.php?vehicle_id=<?= htmlspecialchars($vehicle['vehicle_id']) ?>">
                                    <?= htmlspecialchars($vehicle['brand'] ?? '') ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($vehicle['model'] ?? '') ?></td>
                            <td><?= htmlspecialchars($vehicle['year'] ?? '') ?></td>
                            <td><?= htmlspecialchars($vehicle['odometer'] ?? '') ?></td>
                            <td><?= htmlspecialchars($vehicle['fuel_cons'] ?? '') ?></td>
                            <td><?= htmlspecialchars($vehicle['status'] ?? '') ?></td>
                            <td><?= htmlspecialchars($vehicle['rental_status'] ?? '') ?></td>
                            <td>
                                <?php if ($vehicle['rental_status'] === 'Dostupné' && $user_email !== 'ondra.zbori@seznam.cz'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="vehicle_id" value="<?= $vehicle['vehicle_id'] ?>">
                                        <button class="btn btn-success" name="reserve_vehicle" type="submit">Rezervovat</button>
                                    </form>
                                <?php elseif ($vehicle['rental_status'] === 'Půjčeno'): ?>
                                    <button class="btn btn-warning" disabled>Rezervováno</button>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>Nedostupné</button>
                                <?php endif; ?>

                                <?php if ($user_email === 'ondra.zbori@seznam.cz'): ?>
                                    <?php if ($vehicle['rental_status'] !== 'Půjčeno'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="delete_vehicle_id" value="<?= $vehicle['vehicle_id'] ?>">
                                            <button class="btn btn-danger" type="submit">Smazat</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>Nelze smazat</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        
        

        <div class="card p-4 mt-4">
            <h2>Moje rezervace</h2>
            <p class="text-danger"><strong>Poznámka:</strong> Zapůjčení vozidla může trvat maximálně jeden měsíc.</p>
            <?php $active_reservations = array_filter($user_reservations, fn($res) => $res['status'] === 'Aktivní'); ?>
            <?php $completed_reservations = array_filter($user_reservations, fn($res) => $res['status'] === 'Ukončeno'); ?>

            <h3>Aktivní rezervace</h3>
<?php if (!empty($active_reservations)): ?>
    <ul class="list-group">
        <?php foreach ($active_reservations as $reservation): ?>
            <li class="list-group-item">
                <strong>Vozidlo:</strong> 
                <a href="vehicle_detail.php?vehicle_id=<?= htmlspecialchars($reservation['vehicle_id']) ?>" class="fw-bold text-primary">
                    <?= htmlspecialchars($reservation['brand'] . ' ' . $reservation['model']) ?>
                </a> 
                <span class="badge bg-success ms-2">Moje rezervace</span><br>

                <strong>Začátek rezervace:</strong> <?= date('d.m.Y H:i', strtotime($reservation['start_date'] ?? '')) ?><br>

                <!-- Formulář pro aktualizaci tachometru -->
                <form method="post" class="mt-2 d-flex align-items-center">
    <input type="hidden" name="vehicle_id" value="<?= $reservation['vehicle_id'] ?>">
    <input type="hidden" name="update_odometer" value="1">

    <!-- Menší pole pro zadání tachometru -->
    <div class="me-2">
        <label for="current_odometer" class="form-label">Tachometr:</label>
        <input type="number" class="form-control" name="current_odometer" 
               min="<?= htmlspecialchars($reservation['odometer'] ?? '0') ?>" 
               required style="width: 120px;">
    </div>

    <!-- Zobrazení CELKOVÉHO rozdílu kilometrů vedle pole -->
    <div class="me-3 mt-3">
        <strong>
            Najeto celkem: <?= $_SESSION["najete_km_" . $reservation['vehicle_id']] ?? '0' ?> km
        </strong>
    </div>

    <button class="btn btn-primary btn-sm mt-3">Aktualizovat tachometr</button>
</form>

                <!-- Formulář pro ukončení rezervace -->
                <form method="post" class="mt-2">
    <input type="hidden" name="end_reservation_id" value="<?= $reservation['reservation_id'] ?>">
    <button class="btn btn-danger btn-sm end-reservation-btn">Ukončit rezervaci</button>
</form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>Nemáte žádné aktivní rezervace.</p>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-info mt-4"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

            <h3 class="mt-4">Ukončené rezervace</h3>
            <?php if (!empty($completed_reservations)): ?>
                <ul class="list-group">
                    <?php foreach ($completed_reservations as $reservation): ?>
                        <li class="list-group-item">
                            <strong>Vozidlo:</strong> <?= htmlspecialchars($reservation['brand'] . ' ' . $reservation['model']) ?><br>
                            <strong>Začátek:</strong> <?= date('d.m.Y H:i', strtotime($reservation['start_date'] ?? '')) ?><br>
                            <strong>Konec:</strong> <?= date('d.m.Y H:i', strtotime($reservation['end_date'] ?? '')) ?><br>
                            <form method="post" class="mt-2">
                                <input type="hidden" name="delete_reservation_id" value="<?= $reservation['reservation_id'] ?>">
                                <button class="btn btn-danger btn-sm">Odstranit rezervaci</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Nemáte žádné ukončené rezervace.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pro upozornění -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="alertModalLabel">Upozornění</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
            </div>
            <div class="modal-body">
                Nejdříve musíte odeslat poznámku k vozidlu.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pro chybu rezervace -->
<div class="modal fade" id="reservationErrorModal" tabindex="-1" aria-labelledby="reservationErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reservationErrorModalLabel">Upozornění</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
            </div>
            <div class="modal-body">
                <?= isset($_SESSION['reservationError']) ? htmlspecialchars($_SESSION['reservationError']) : '' ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    <?php if (!empty($_SESSION['reservationError'])): ?>
        var reservationErrorModal = new bootstrap.Modal(document.getElementById("reservationErrorModal"));
        reservationErrorModal.show();
        <?php unset($_SESSION['reservationError']); ?> // Vymažeme chybu po zobrazení
    <?php endif; ?>
});
</script>
</body>
</html>