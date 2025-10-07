<?php
session_start();
require_once 'config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $personnel_name = trim($_POST['personnel_name']);
    $password = trim($_POST['password']);

    if (!empty($personnel_name) && !empty($password)) {
        // Requête pour récupérer les infos du personnel avec le rôle associé
        $sql = "SELECT p.*, r.role_name 
                FROM personnels p
                JOIN roles r ON p.role_id = r.role_id
                WHERE p.personnel_name = :personnel_name";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':personnel_name', $personnel_name);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // Stocker les infos en session
            $_SESSION['personnel_id'] = $user['personnel_id'];
            $_SESSION['personnel_name'] = $user['personnel_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];

            // Exemple : redirection différente selon le rôle
            if ($user['role_name'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Nom ou mot de passe incorrect.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - Clinique</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <img src="assets/logo.png" alt="Logo Clinique">

        <h1>Connexion</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="personnel_name">Nom du personnel</label>
                <input type="text" id="personnel_name" name="personnel_name" placeholder="Votre nom" required>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="Votre mot de passe" required>
            </div>

            <button type="submit" class="btn-login">Se connecter</button>
        </form>
    </div>
</body>
</html>
