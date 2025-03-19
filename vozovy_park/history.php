<?php
require_once 'vehicle.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Prague');
setlocale(LC_TIME, 'cs_CZ.UTF-8');

// Kontrola, zda je uživatel přihlášen a je admin
if (!isset($_SESSION['user_id']) || $_SESSION['email'] !== 'ondra.zbori@seznam.cz') {
    header("Location: index.php");
    exit;
}

// Načtení všech ukončených rezervací
$completed_reservations = getCompletedReservations();

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
    
    
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Správa vozového parku - Admin</a>
        </div>
        
    </nav>
    <div class="container mt-4">
        <h1>Historie rezervací</h1>
        <a href="index.php" class="btn btn-secondary mb-3">Zpět na hlavní stránku</a>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Uživatel</th>
                    <th>Vozidlo</th>
                    <th>Začátek rezervace</th>
                    <th>Konec rezervace</th>
                    <th>Najeté km</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($completed_reservations as $reservation): ?>
                    <tr>
                        <td><?= htmlspecialchars($reservation['username']) ?> (<?= htmlspecialchars($reservation['email']) ?>)</td>
                        <td><?= htmlspecialchars($reservation['brand'] . ' ' . $reservation['model']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($reservation['start_date'])) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($reservation['end_date'])) ?></td>
                        <td><?= htmlspecialchars($reservation['najeto_km']) ?> km</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
