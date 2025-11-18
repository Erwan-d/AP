<?php
require_once 'config.php';
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $personnel_name = trim($_POST['personnel_name']);

    if (!empty($personnel_name)) {
        // Vérifie si le personnel existe
        $stmt = $pdo->prepare("SELECT personnel_id, personnel_name FROM ap_personnels WHERE personnel_name = ?");
        $stmt->execute([$personnel_name]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Génère un token et une date d’expiration
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

            // Enregistre le token dans la base
            $update = $pdo->prepare("UPDATE ap_personnels SET reset_token = ?, reset_expires = ? WHERE personnel_id = ?");
            $update->execute([$token, $expires, $user['personnel_id']]);

            // Génère le lien de réinitialisation
            $resetLink = "http://localhost/clinique/reset_password.php?token=$token";

            $success = "Un lien de réinitialisation a été généré :<br><a href='$resetLink'>$resetLink</a>";
        } else {
            $error = "Aucun utilisateur trouvé avec ce nom.";
        }
    } else {
        $error = "Veuillez entrer votre nom d'utilisateur.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Mot de passe oublié</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <h1>Mot de passe oublié</h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

    <form method="POST" action="">
        <label for="personnel_name">Nom du personnel</label>
        <input type="text" id="personnel_name" name="personnel_name" required>

        <button type="submit">Réinitialiser le mot de passe</button>
    </form>

    <p><a href="login.php">← Retour à la connexion</a></p>
</div>
</body>
</html>
