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

// Načteme poznámky a fotografie
$conn = getDbConnection();
$query = "
    SELECT 
        v.vehicle_id, 
        v.brand, 
        v.model, 
        v.status, 
        p.id AS note_id, 
        u.username, 
        u.email, 
        p.note, 
        p.photo_path, 
        p.created_at 
    FROM vozidla v
    LEFT JOIN poznámky p ON v.vehicle_id = p.vehicle_id
    LEFT JOIN uzivatele u ON p.user_id = u.user_id
    WHERE v.status = 'Servis' 
    ORDER BY p.created_at DESC NULLS LAST";
$result = pg_query($conn, $query);

if (!$result) {
    die("Chyba při načítání poznámek: " . pg_last_error($conn));
}

$notes = pg_fetch_all($result) ?: [];
pg_close($conn);
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
        <h2>Poznámky od uživatelů</h2>

        <?php if (!empty($notes) || !empty($vehiclesInService)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Uživatel</th>
                        <th>Email</th>
                        <th>Vozidlo</th>
                        <th>Poznámka</th>
                        <th>Fotografie</th>
                        <th>Datum</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($notes as $note): ?>
                    <tr>
                    <td><?= htmlspecialchars($note['username'] ?? '') ?></td>
<td><?= htmlspecialchars($note['email'] ?? '') ?></td>
<td><?= htmlspecialchars($note['brand'] . ' ' . $note['model'] ?? '') ?></td>
<td><?= nl2br(htmlspecialchars($note['note'] ?? '')) ?></td>
<td>
    <?php if (!empty($note['photo_path'])): ?>
        <img src="<?= htmlspecialchars($note['photo_path'] ?? '') ?>" alt="Fotografie" class="img-fluid" style="max-width: 150px;">
    <?php else: ?>
        <em>Žádná fotografie</em>
    <?php endif; ?>
</td>
<td><?= !empty($note['created_at']) ? date('d.m.Y H:i', strtotime($note['created_at'])) : 'Neznámé datum' ?></td>
                        <td>
                            <?php 
                                // Kontrola, zda bylo vozidlo po ukončení rezervace a čeká na servis
                                if ($note['status'] == 'Servis'): ?>
                                    <form method="get" action="vehicle_service.php">
                                        <input type="hidden" name="vehicle_id" value="<?= htmlspecialchars($note['vehicle_id']) ?>">
                                        <button type="submit" class="btn btn-primary">Spravovat servis</button>
                                    </form>
                                <?php else: ?>
                                    <em>Nezařazeno do servisu</em>
                                <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Žádné poznámky zatím nebyly přidány.</p>
        <?php endif; ?>
    </div>
</body>
</html>