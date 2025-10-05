<?php
session_start();

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION['personnel_id'])) {
    header("Location: login.php");
    exit();
}

// Vérifie que c’est bien une secrétaire (et non un admin)
if ($_SESSION['role_id'] == 1) {
    header("Location: admin_dashboard.php");
    exit();
}

$nom = $_SESSION['nom'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Secrétaire</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Bienvenue <?= htmlspecialchars($nom) ?> 👩‍💼</h1>
            <a href="logout.php" class="logout-btn">Se déconnecter</a>
        </header>

        <main>
            <h2>📁 Tableau de bord - Espace Secrétaire</h2>
            <p>Depuis cet espace, vous pouvez :</p>
            <ul>
                <li>📋 Enregistrer une <strong>pré-admission</strong></li>
                <li>🧑‍⚕️ Consulter la liste des <strong>patients</strong></li>
                <li>📆 Gérer les <strong>rendez-vous</strong></li>
            </ul>

            <div class="actions">
                <a href="pre_admission.php" class="btn-action">➕ Nouvelle pré-admission</a>
                <a href="patients.php" class="btn-action">📁 Liste des patients</a>
                <a href="rdv.php" class="btn-action">📅 Gestion des RDV</a>
            </div>
        </main>
    </div>
</body>
</html>
