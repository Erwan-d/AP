<?php
require_once 'config.php';
$error = "";
$success = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Vérifie si le token est valide
    $stmt = $pdo->prepare("SELECT personnel_id FROM ap_personnels WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $password = trim($_POST['password']);
            $confirm = trim($_POST['confirm']);

            if ($password === $confirm && strlen($password) >= 6) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                // Met à jour le mot de passe et supprime le token
                $update = $pdo->prepare("UPDATE ap_personnels SET password = ?, reset_token = NULL, reset_expires = NULL WHERE personnel_id = ?");
                $update->execute([$hashed, $user['personnel_id']]);

                $success = "Votre mot de passe a été réinitialisé avec succès. <a href='login.php'>Se connecter</a>";
            } else {
                $error = "Les mots de passe ne correspondent pas ou sont trop courts.";
            }
        }
    } else {
        $error = "Le lien de réinitialisation est invalide ou expiré.";
    }
} else {
    $error = "Aucun token fourni.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Réinitialiser le mot de passe</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <h1>Réinitialisation du mot de passe</h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

    <?php if (isset($user) && $user && !$success): ?>
    <form method="POST" action="">
        <label for="password">Nouveau mot de passe</label>
        <input type="password" name="password" required>

        <label for="confirm">Confirmer le mot de passe</label>
        <input type="password" name="confirm" required>

        <button type="submit">Changer le mot de passe</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
