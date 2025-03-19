<?php
require_once 'config.php'; // Připojení k databázi

$message = ''; // Pro zobrazení zpráv

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = 'zakaznik'; // Výchozí role

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Neplatný email.";
    } else {
        // Kontrola, zda už email existuje
        $conn = getDbConnection();
        $query = "SELECT * FROM uzivatele WHERE email = $1";
        $result = pg_query_params($conn, $query, [$email]);

        if (pg_num_rows($result) > 0) {
            $message = "Tento email je již registrován.";
        } else {
            // Hash hesla
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Uložení uživatele do databáze
            $query = "INSERT INTO uzivatele (username, email, password_hash, role) VALUES ($1, $2, $3, $4)";
            $result = pg_query_params($conn, $query, [$username, $email, $passwordHash, $role]);

            if ($result) {
                $message = "Registrace proběhla úspěšně. Nyní se můžete přihlásit.";
            } else {
                $message = "Chyba při registraci: " . pg_last_error($conn);
            }
        }

        pg_close($conn);
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
        </div>
    </nav>
    <div class="container mt-5">
        <div class="card p-4 mx-auto" style="max-width: 400px;">
            <h1 class="mb-3 text-center">Registrace</h1>
            <form method="post">
                <div class="mb-3">
                    <label for="username" class="form-label">Uživatelské jméno:</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Heslo:</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Registrovat</button>
            </form>
            <div class="mt-3 text-center">
                <p>Už máte účet? <a href="login.php" class="btn btn-link">Přihlaste se</a></p>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel">Upozornění</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
                </div>
                <div class="modal-body">
                    <?= htmlspecialchars($message ?? '') ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zavřít</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!empty($message)): ?>
            var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            messageModal.show();
        <?php endif; ?>
    </script>
</body>
</html>