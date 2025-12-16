<?php
session_start();
require_once '../systems/config.php';

if (!isset($_SESSION['personnel_id'])) {
    header('Location: login.php');
    exit();
}

// Vérification rôle médecin
if ($_SESSION['role_id'] != 3) {
    header('Location: med_dashboard.php');
    exit();
}

// Récupération info médecin
$personnel_id = $_SESSION['personnel_id'];

$stmt = $pdo->prepare("SELECT p.personnel_name, p.service_id, s.service_name
                       FROM ap_personnels p
                       JOIN ap_services s ON p.service_id = s.service_id
                       WHERE p.personnel_id = :id");
$stmt->execute([':id' => $personnel_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Tableau de bord Médecin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="med_dashboard.css">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <span class="navbar-brand">
      <i class="bi bi-hospital"></i> Clinique LPF – Espace Médecin
    </span>
    <span class="text-white">
      <i class="bi bi-person-badge"></i> Dr <?= htmlspecialchars($medecin['personnel_name']) ?> |
      <a href="../logout.php" class="text-white text-decoration-none"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </span>
  </div>
</nav>

<div class="container">

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <h3 class="text-primary"><i class="bi bi-person-video3"></i> Tableau de bord médecin</h3>
      <p class="text-muted">Bienvenue, Dr <?= htmlspecialchars($medecin['personnel_name']) ?></p>

      <ul class="list-group">
        <li class="list-group-item">
          <strong>Service :</strong> <?= htmlspecialchars($medecin['service_name']) ?>
        </li>
      </ul>
    </div>
  </div>

  <div class="row g-4">

    <div class="col-md-4">
      <a href="med_rendezvous.php" class="card text-center dashboard-card">
        <div class="card-body">
          <i class="bi bi-calendar-event display-4 text-primary"></i>
          <h5 class="mt-3">Rendez-vous du service</h5>
          <p class="text-muted small">Voir les rendez-vous filtrés par mois</p>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a href="med_admissions.php" class="card text-center dashboard-card">
        <div class="card-body">
          <i class="bi bi-clipboard-heart display-4 text-danger"></i>
          <h5 class="mt-3">Pré-admissions</h5>
          <p class="text-muted small">Consulter les pré-admissions liées au service</p>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a href="search_patient.php" class="card text-center dashboard-card">
        <div class="card-body">
          <i class="bi bi-search-heart display-4 text-success"></i>
          <h5 class="mt-3">Dossier patient</h5>
          <p class="text-muted small">Rechercher un patient</p>
        </div>
      </a>
    </div>

  </div>

</div>

</body>
</html>

<?php
session_start();
require_once '../systems/config.php';

if (!isset($_SESSION['personnel_id'])) {
    header('Location: login.php');
    exit();
}

// Vérification rôle médecin
if ($_SESSION['role_id'] != 3) {
    header('Location: med_dashboard.php');
    exit();
}

// Récupération info médecin
$personnel_id = $_SESSION['personnel_id'];

$stmt = $pdo->prepare("SELECT p.personnel_name, p.service_id, s.service_name
                       FROM ap_personnels p
                       JOIN ap_services s ON p.service_id = s.service_id
                       WHERE p.personnel_id = :id");
$stmt->execute([':id' => $personnel_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Tableau de bord Médecin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="med_dashboard.css">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <span class="navbar-brand">
      <i class="bi bi-hospital"></i> Clinique LPF – Espace Médecin
    </span>
    <span class="text-white">
      <i class="bi bi-person-badge"></i> Dr <?= htmlspecialchars($medecin['personnel_name']) ?> |
      <a href="../logout.php" class="text-white text-decoration-none"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </span>
  </div>
</nav>

<div class="container">

  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
      <h3 class="text-primary"><i class="bi bi-person-video3"></i> Tableau de bord médecin</h3>
      <p class="text-muted">Bienvenue, Dr <?= htmlspecialchars($medecin['personnel_name']) ?></p>

      <ul class="list-group">
        <li class="list-group-item">
          <strong>Service :</strong> <?= htmlspecialchars($medecin['service_name']) ?>
        </li>
      </ul>
    </div>
  </div>

  <div class="row g-4">

    <div class="col-md-4">
      <a href="med_rendezvous.php" class="card text-center dashboard-card">
        <div class="card-body">
          <i class="bi bi-calendar-event display-4 text-primary"></i>
          <h5 class="mt-3">Rendez-vous du service</h5>
          <p class="text-muted small">Voir les rendez-vous filtrés par mois</p>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a href="med_admissions.php" class="card text-center dashboard-card">
        <div class="card-body">
          <i class="bi bi-clipboard-heart display-4 text-danger"></i>
          <h5 class="mt-3">Pré-admissions</h5>
          <p class="text-muted small">Consulter les pré-admissions liées au service</p>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a href="search_patient.php" class="card text-center dashboard-card">
        <div class="card-body">
          <i class="bi bi-search-heart display-4 text-success"></i>
          <h5 class="mt-3">Dossier patient</h5>
          <p class="text-muted small">Rechercher un patient</p>
        </div>
      </a>
    </div>

  </div>

</div>

</body>
</html>
