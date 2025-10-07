<?php
session_start();
require_once 'config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $personnel_name = trim($_POST['personnel_name']);
    $password = trim($_POST['password']);

    if (!empty($personnel_name) && !empty($password)) {
        // 1️⃣ Requête avec JOIN sur les rôles
        $sql = "SELECT p.*, r.role_name 
                FROM personnels p
                JOIN roles r ON p.role_id = r.role_id
                WHERE p.personnel_name = :personnel_name";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':personnel_name', $personnel_name, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2️⃣ Vérification utilisateur et mot de passe
        if ($user) {
            if (password_verify($password, $user['password'])) {

                // 3️⃣ Stockage des infos en session
                $_SESSION['personnel_id'] = $user['personnel_id'];
                $_SESSION['personnel_name'] = $user['personnel_name'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];

                // 4️⃣ Redirection selon rôle
                if ($user['role_name'] === 'admin') {
                    header("Location: admin_dashboard.php");
                    exit();
                } elseif ($user['role_name'] === 'secretaire') {
                    header("Location: secretary_dashboard.php");
                    exit();
                } else {
                    $error = "Votre rôle n'est pas autorisé à accéder au système.";
                }

            } else {
                $error = "Mot de passe incorrect.";
            }
        } else {
            $error = "Utilisateur introuvable.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>

<!DOCTYPE html> <html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion - Clinique</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
<img src="LPFS_logo.png" alt="Logo Clinique">
<h1>Connexion</h1>
<?php if ($error): ?>
<div class="error"><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>
<form method="POST" action="">
<div class="form-group">
<label for="personnel_name">Nom du personnel</label>
<input type="text" id="personnel_name" name="personnel_name" placeholder="Votre nom" required>
</div>
<div class="form-group"> <label for="password">Mot de passe</label>
<input type="password" id="password" name="password" placeholder="Votre mot de passe" required>
</div> 
<button type="submit" class="btn-login">Se connecter</button>
</form>
</div>
</body>
</html>
