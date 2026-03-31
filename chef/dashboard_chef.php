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

// Récupérer le mois sélectionné (format YYYY-MM)
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Service du chef connecté
$personnel_id = $_SESSION['personnel_id'];

$stmtService = $pdo->prepare("
    SELECT service_id 
    FROM ap_personnels 
    WHERE personnel_id = ?
");
$stmtService->execute([$personnel_id]);
$service = $stmtService->fetch();

$service_id = $service['service_id'] ?? null;

// Récupération des rendez-vous filtrés par mois et service
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
</head>
<body>

<div class="container">
    <h1>Rendez-vous programmés</h1>

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