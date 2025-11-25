<?php
session_start();
require_once 'systems/config.php';
require "../config/config.php";

// Verif co
if (!isset($_SESSION['personnel_id']) || !isset($_SESSION['role_name'])) {
    header("Location: index.php");
    exit();
}

// Vérifier si admin
if ($_SESSION['role_name'] !== 'admin') {
    header("Location: ../systems/login.php");
    exit();
}
 


// Récupération stats
$count_patients     = $pdo->query("SELECT COUNT(*) FROM PATIENT")->fetchColumn();
$count_personnels   = $pdo->query("SELECT COUNT(*) FROM PERSONNELS")->fetchColumn();
$count_admissions   = $pdo->query("SELECT COUNT(*) FROM ADMISSION")->fetchColumn();
$count_services     = $pdo->query("SELECT COUNT(*) FROM SERVICES")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<body>
    <header>
        <h1> Tableau de Bord Administrateur</h1>
        <nav>
            <a href="admin_dashboard.php">Accueil</a>
            <a href="../systems/logout.php">Déconnexion</a>
        </nav>
    </header>

    <main>
        <section class="stats">
            <h2>Statistiques rapides</h2>
            <div class="stat">
                <div class="stat-box">Patients<br><strong><?= $count_patients ?></strong></div>
                <div class="stat-box">Personnels<br><strong><?= $count_personnels ?></strong></div>
                <div class="stat-box">Admissions<br><strong><?= $count_admissions ?></strong></div>
                <div class="stat-box">Services<br><strong><?= $count_services ?></strong></div>
            </div>
        </section>

        <section class="actions">
            <h2>Gestion des données</h2>
            <div class="cards">
                <a class="card" href="manage_patients.php">Gérer les Patients</a>
                <a class="card" href="manage_couverture.php"> Gérer la couverture sociale</a>
                <a class="card" href="manage_personnels.php"> Gérer les Personnels</a>
                <a class="card" href="manage_services.php">Gérer les Services</a>
                <a class="card" href="manage_admissions.php"> Gérer les Admissions</a>
                <a class="card" href="manage_documents.php"> Gérer les Documents</a>
                <a class="card" href="manage_chambre.php"> Gérer les chambres</a>
                <a class="card" href="manage_personne_contact.php"> Gérer les contact</a>
                <a class="card" href="manage_roles.php"> Gérer les roles</a>
            </div>
        </section>
    </main>
</body>
</html>
