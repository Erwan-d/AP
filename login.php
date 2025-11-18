<?php
session_start();
require_once 'systems/config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $personnel_name = trim($_POST['personnel_name']);
    $password = trim($_POST['password']);
    $captcha = trim($_POST['captcha']);

    // Vérification du captcha
    if (!isset($_SESSION['captcha_code']) || trim($captcha) !== trim($_SESSION['captcha_code'])) {
        $error = "Captcha incorrect. Veuillez réessayer.";    
    } elseif (!empty($personnel_name) && !empty($password)) {
        $sql = "SELECT p.*, r.role_name 
                FROM ap_personnels p
                JOIN ap_roles r ON p.role_id = r.role_id
                WHERE p.personnel_name = :personnel_name";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':personnel_name', $personnel_name, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['personnel_id'] = $user['personnel_id'];
            $_SESSION['personnel_name'] = $user['personnel_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];

            if ($user['role_name'] === 'admin') {
                header("Location: admin_dashboard.php");
                exit();
            } elseif ($user['role_name'] === 'secretaire') {
                header("Location: secretary/secretary_dashboard.php");
                exit();
            } else {
                $error = "Votre rôle n'est pas autorisé à accéder au système.";
            }
        } else {
            $error = "Nom d'utilisateur ou mot de passe incorrect.";
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
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <img src="images/LPFS_logo.png" alt="Logo Clinique">
    <h1>Connexion</h1>

    <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="personnel_name">Nom du personnel</label>
            <input type="text" id="personnel_name" name="personnel_name" required>
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Captcha :</label><br>
            <img src="systems/captcha.php" alt="Captcha"><br>
            <input type="text" name="captcha" placeholder="Entrez le code ci-dessus" required>
        </div>

        <button type="submit" class="btn-login">Se connecter</button>

        <p style="margin-top:10px;">
            <a href="forgot_password.php">Mot de passe oublié ?</a>
        </p>
    </form>
</div>
</body>
</html>
