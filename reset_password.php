<?php
require_once 'systems/config.php';
$pdo = getPDOConnection();

$error   = "";
$success = "";
$user    = null;

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    die("Token manquant.");
}

// -------------------------------------------------------
// Récupération du token — sans filtrer sur la date en SQL
// pour éviter les problèmes de timezone PHP vs MariaDB
// -------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT personnel_id, personnel_name, reset_token, reset_expires
    FROM ap_personnels
    WHERE reset_token = ?
");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérification en PHP (tout en UTC pour cohérence)
$tokenValid = false;
if ($user && !empty($user['reset_expires'])) {
    $now     = new DateTime('now', new DateTimeZone('UTC'));
    $expires = new DateTime($user['reset_expires'], new DateTimeZone('UTC'));
    $tokenValid = ($expires > $now);
}

if (!$user) {
    $error = "Ce lien de réinitialisation est invalide.";
} elseif (!$tokenValid) {
    $error = "Ce lien a expiré. Veuillez en générer un nouveau.";
}

// -------------------------------------------------------
// Traitement du formulaire POST
// -------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = ($_POST['password'] ?? '');
    $confirm  = ($_POST['confirm']   ?? '');

    $errors = [];

    if (strlen($password) < 12) {
        $errors[] = "au moins 12 caractères";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "au moins une majuscule";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "au moins une minuscule";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "au moins un chiffre";
    }
    if (!preg_match('/[\W_]/', $password)) {
        $errors[] = "au moins un caractère spécial (!@#$%^&*...)";
    }
    if ($password !== $confirm) {
        $errors[] = "les mots de passe ne correspondent pas";
    }

    if (!empty($errors)) {
        $error = "Le mot de passe doit contenir : " . implode(", ", $errors) . ".";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("
            UPDATE ap_personnels
            SET password = ?, reset_token = NULL, reset_expires = NULL
            WHERE personnel_id = ?
        ");
        $update->execute([$hashed, $user['personnel_id']]);

        $success    = "Mot de passe réinitialisé avec succès. <a href='index.php'>Se connecter</a>";
        $tokenValid = false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation du mot de passe</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <h1>Nouveau mot de passe</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <p><a href="forgot_password.php">← Générer un nouveau lien</a></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($tokenValid && !$success): ?>
        <form method="POST" action="">
        <label for="password">Nouveau mot de passe</label>
        <input type="password" id="password" name="password" required minlength="12">
        <ul class="password-rules" style="font-size:.85rem; color:#666; margin-top:.4rem; padding-left:1.2rem;">
            <li>12 caractères minimum</li>
            <li>Une majuscule (A–Z)</li>
            <li>Une minuscule (a–z)</li>
            <li>Un chiffre (0–9)</li>
            <li>Un caractère spécial (!@#$%^&amp;*...)</li>
        </ul>

            <label for="confirm">Confirmer le mot de passe</label>
            <input type="password" id="confirm" name="confirm" required minlength="12">

            <button type="submit">Réinitialiser</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>