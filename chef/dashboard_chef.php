<?php
session_start();
require_once '../systems/config.php';
$pdo = getPDOConnection();
if (!isset($_SESSION['personnel_id'])) {
    header('Location: index.php');
    exit();
}
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 5) {
    header('Location: dashboard.php');
    exit();
}
// Récupérer le mois sélectionné
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
// Récupération personnel + NOM DU SERVICE ✅
$personnel_id = $_SESSION['personnel_id'];
$stmtPersonnel = $pdo->prepare("
    SELECT p.personnel_name, p.service_id, s.service_name
    FROM ap_personnels p
    LEFT JOIN ap_services s ON p.service_id = s.service_id
    WHERE p.personnel_id = ?
");
$stmtPersonnel->execute([$personnel_id]);
$personnel = $stmtPersonnel->fetch();
$nomPersonnel = $personnel ? htmlspecialchars($personnel['personnel_name']) : 'Inconnu';
$nomService = $personnel ? htmlspecialchars($personnel['service_name']) : 'Service inconnu';
$service_id = $personnel['service_id'] ?? null;

// Compter les admissions FUTURES pour ce service
$stmtFutures = $pdo->prepare("
    SELECT COUNT(*) FROM ap_admission
    WHERE service_id = ? AND hospitalisation_date >= CURDATE()
");
$stmtFutures->execute([$service_id]);
$totalFutures = $stmtFutures->fetchColumn();

// Compter TOUTES les admissions pour ce service
$stmtTotal = $pdo->prepare("
    SELECT COUNT(*) FROM ap_admission WHERE service_id = ?
");
$stmtTotal->execute([$service_id]);
$totalAll = $stmtTotal->fetchColumn();

// Récupération des rendez-vous
$stmt = $pdo->prepare("
    SELECT a.*, p.firstname, p.lastname
    FROM ap_admission a
    JOIN ap_patient p ON a.patient_social = p.social_number
    JOIN ap_personnels per ON per.personnel_name = a.personnel_name
    WHERE per.service_id = ?
    AND DATE_FORMAT(a.hospitalisation_date, '%Y-%m') = ?
    ORDER BY a.hospitalisation_date ASC
");
$stmt->execute([$service_id, $selectedMonth]);
$rendezvous = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rendez-vous du service</title>
    <link rel="stylesheet" href="dashboard_chef.css">
    <link rel="stylesheet" href="header-fixe.css">
    <link rel="stylesheet" href="rendezvous_chef.css">
</head>
<body>
    <!-- Header fixe -->
    <header class="header-fixe">
        <h2>Rendez-vous du service</h2>
        <div class="user-info">
            Connecté : <strong><?= $nomPersonnel ?></strong> | <strong><?= $nomService ?></strong>
        </div>
    </header>

    <!-- Compteurs -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-number"><?= $totalAll ?></div>
            <div class="stat-label">Total admissions du service</div>
        </div>
        <div class="stat-card futures">
            <div class="stat-number"><?= $totalFutures ?></div>
            <div class="stat-label">Admissions futures à venir</div>
        </div>
    </div>

    <div class="container">
        <!-- Filtre mois -->
        <form method="GET" class="filter-form">
            <label for="month">Choisir un mois :</label>
            <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
            <button type="submit">Filtrer</button>
        </form>
        <!-- Tableau -->
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Type</th>
                    <th>Chambre</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rendezvous) > 0): ?>
                    <?php foreach ($rendezvous as $rdv): ?>
                        <tr>
                            <td><?= htmlspecialchars($rdv['firstname'] . ' ' . $rdv['lastname']) ?></td>
                            <td><?= htmlspecialchars($rdv['hospitalisation_date']) ?></td>
                            <td><?= htmlspecialchars($rdv['intervention_time']) ?></td>
                            <td><?= htmlspecialchars($rdv['admission_type']) ?></td>
                            <td><?= htmlspecialchars($rdv['chambre_id']) ?></td>
                            <td><?= htmlspecialchars($rdv['statut']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Aucun rendez-vous trouvé</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>