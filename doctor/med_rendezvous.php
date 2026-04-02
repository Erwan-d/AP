<?php
require_once '../systems/config.php';
$pdo = getPDOConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifie que l'utilisateur est connecté et est médecin
if (!isset($_SESSION['personnel_id']) || $_SESSION['role_id'] != 3) {
    header('Location: ../index.php');
    exit();
}

// 🔹 ID du médecin connecté
$personnel_id = $_SESSION['personnel_id'];

// 🔹 Infos médecin
$stmt = $pdo->prepare("
    SELECT p.personnel_name, p.service_id, s.service_name
    FROM ap_personnels p
    JOIN ap_services s ON p.service_id = s.service_id
    WHERE p.personnel_id = :id
");
$stmt->execute([':id' => $personnel_id]);
$medecin = $stmt->fetch(PDO::FETCH_ASSOC);

// 🔹 Filtre par mois
$selected_month = date('Y-m');
if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $selected_month = $_GET['month'];
}

$month_start = $selected_month . '-01';
$next_month = date('Y-m-d', strtotime($month_start . ' +1 month'));

// 🔥 RÉCUPÉRATION DES RDV DU MÉDECIN UNIQUEMENT
$stmt = $pdo->prepare("
    SELECT pa.admission_id,
           pa.hospitalisation_date,
           pa.intervention_time,
           pa.notes,
           pa.reason,
           pa.chambre_id,
           pt.lastname AS patient_lastname,
           pt.firstname AS patient_firstname
    FROM ap_admission pa
    JOIN ap_patient pt ON pa.patient_social = pt.social_number
    WHERE pa.personnel_id = :personnel_id   -- ✅ FILTRE IMPORTANT
      AND pa.hospitalisation_date >= :month_start
      AND pa.hospitalisation_date < :next_month
    ORDER BY pa.hospitalisation_date, pa.intervention_time
");

$stmt->execute([
    ':personnel_id' => $personnel_id,
    ':month_start' => $month_start,
    ':next_month' => $next_month
]);

$rendezvous = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rendez-vous - Médecin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .past-appointment { background-color: #f8f9fa; }
        .future-appointment { background-color: #e6f7ff; }
    </style>
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">
            <i class="bi bi-hospital"></i> Clinique LPF – Espace Médecin
        </span>

        <span class="text-white">
            <i class="bi bi-person-badge"></i>
            Dr <?= htmlspecialchars($medecin['personnel_name']) ?> |

            <a href="../logout.php" class="text-white text-decoration-none">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </span>
    </div>
</nav>

<div class="container">

    <h2 class="mb-4">
         Rendez-vous : <?= htmlspecialchars($medecin['service_name']) ?>
    </h2>

    <!-- Filtre mois -->
    <form method="GET" class="row g-3 mb-4 align-items-end">
        <div class="col-auto">
            <label class="form-label">Sélectionner le mois :</label>
            <input type="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" class="form-control">
        </div>

        <div class="col-auto">
            <button class="btn btn-primary">Filtrer</button>
            <a href="?month=<?= date('Y-m') ?>" class="btn btn-secondary">Aujourd’hui</a>
        </div>
    </form>

    <!-- TABLE -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th>Patient</th>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Chambre</th>
                    <th>Raison</th>
                    <th>Notes</th>
                </tr>
            </thead>

            <tbody>
            <?php if (!empty($rendezvous)): ?>
                <?php foreach ($rendezvous as $rdv): ?>

                    <?php
                    $datetime = strtotime($rdv['hospitalisation_date'] . ' ' . $rdv['intervention_time']);
                    $rowClass = ($datetime < time()) ? 'past-appointment' : 'future-appointment';
                    ?>

                    <tr class="<?= $rowClass ?>">
                        <td><?= htmlspecialchars($rdv['admission_id']) ?></td>

                        <td>
                            <?= htmlspecialchars($rdv['patient_lastname'] . ' ' . $rdv['patient_firstname']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(date('d/m/Y', strtotime($rdv['hospitalisation_date']))) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($rdv['intervention_time']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($rdv['chambre_id']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($rdv['reason']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($rdv['notes']) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">
                        Aucun rendez-vous trouvé pour ce mois.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>