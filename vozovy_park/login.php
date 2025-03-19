<?php
require_once 'config.php'; // Načtení připojení k databázi
// prihlaseni admin - ondra.zbori@seznam.cz heslo 123
// test ucet2 Test4@spravavoz.cz heslo 111

$error = ''; // Pro ukládání chybových zpráv

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Získání připojení k databázi
    $conn = getDbConnection();

    // Ověření uživatele v databázi
    $query = "SELECT user_id, password_hash FROM uzivatele WHERE email = $1";
    $result = pg_query_params($conn, $query, [$email]);

    if (!$result) {
        $error = "Chyba dotazu: " . pg_last_error($conn);
    } else {
        $user = pg_fetch_assoc($result);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Přihlášení úspěšné
            session_start();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $email;

            header("Location: index.php");
            exit;
        } else {
            // Chybné přihlašovací údaje
            $error = "Nesprávný e-mail nebo heslo.";
        }
    }

    pg_close($conn);
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
            <h1 class="mb-3 text-center">Přihlášení</h1>
            <form method="post">
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail:</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Heslo:</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Přihlásit</button>
            </form>
            <div class="mt-3 text-center">
                <p>Nemáte účet? <a href="register.php" class="btn btn-link">Zaregistrujte se</a></p>
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
                    <?= htmlspecialchars($error ?? '') ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zavřít</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!empty($error)): ?>
            var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            messageModal.show();
        <?php endif; ?>
    </script>
</body>
</html>