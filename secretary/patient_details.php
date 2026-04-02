<?php
require_once '../systems/config.php';
$pdo = getPDOConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autorisation : secrétaire (2)
if (!isset($_SESSION['personnel_id']) || !in_array($_SESSION['role_id'], [2])) {
    header('Location: ../index.php');
    exit();
}

// Vérifie l'ID du patient
if (!isset($_GET['id'])) {
    die("Patient introuvable.");
}

$social_number = $_GET['id'];

$allowed = ['med', 'search', 'secretary'];
$from = in_array($_GET['from'] ?? '', $allowed) ? $_GET['from'] : 'search';

switch ($from) {
    case 'secretary':
        $back_url = 'secretary_dashboard.php';
        break;
    case 'search':
    default:
        $back_url = 'search_patient.php';
        break;
}

// -------------------------------------------------------
// Suppression d'une admission (POST)
// -------------------------------------------------------
$delete_success = '';
$delete_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $admission_id_to_delete = (int)($_POST['admission_id'] ?? 0);

    if ($admission_id_to_delete > 0) {
        // Vérifier que l'admission appartient bien à ce patient ET qu'elle est en pré-admission
        $check = $pdo->prepare("
            SELECT admission_id FROM ap_admission
            WHERE admission_id = :aid
              AND patient_social = :social
              AND statut = 'pré-admission'
        ");
        $check->execute([':aid' => $admission_id_to_delete, ':social' => $social_number]);

        if ($check->fetch()) {
            $del = $pdo->prepare("DELETE FROM ap_admission WHERE admission_id = :aid");
            $del->execute([':aid' => $admission_id_to_delete]);
            $delete_success = "La pré-admission #$admission_id_to_delete a été supprimée avec succès.";
        } else {
            $delete_error = "Suppression impossible : admission introuvable ou déjà confirmée.";
        }
    } else {
        $delete_error = "Identifiant d'admission invalide.";
    }
}

// -------------------------------------------------------
// Récupération infos patient
// -------------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM ap_patient WHERE social_number = :id");
$stmt->execute([':id' => $social_number]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die("Patient non trouvé.");
}

// -------------------------------------------------------
// Récupération des admissions (avec statut et type)
// -------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT admission_id, admission_type, hospitalisation_date, intervention_time, notes, statut
    FROM ap_admission
    WHERE patient_social = :id
    ORDER BY hospitalisation_date DESC, intervention_time DESC
");
$stmt->execute([':id' => $social_number]);
$admissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dossier Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <!-- Bouton retour -->
    <a href="<?= $back_url ?>" class="btn btn-secondary mb-4">
        <i class="bi bi-arrow-left"></i> Retour
    </a>

    <h2 class="mb-4">Dossier patient</h2>

    <!-- Alertes suppression -->
    <?php if ($delete_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($delete_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($delete_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($delete_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Infos patient -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-person-fill me-2"></i>Informations personnelles
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Nom :</strong> <?= htmlspecialchars($patient['lastname']) ?></p>
                    <p><strong>Prénom :</strong> <?= htmlspecialchars($patient['firstname']) ?></p>
                    <p><strong>Date de naissance :</strong> <?= htmlspecialchars($patient['birthdate']) ?></p>
                    <p><strong>N° de sécurité sociale :</strong> <?= htmlspecialchars($patient['social_number']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Adresse :</strong>
                        <?= htmlspecialchars($patient['number_street'] . ' ' . $patient['street']) ?>,
                        <?= htmlspecialchars($patient['zip'] . ' ' . $patient['city']) ?>
                    </p>
                    <p><strong>Email :</strong> <?= htmlspecialchars($patient['email']) ?></p>
                    <p><strong>Téléphone :</strong> <?= htmlspecialchars($patient['phone']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Historique des admissions -->
    <div class="card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clipboard2-pulse me-2"></i>Historique des admissions / rendez-vous</span>
        </div>
        <div class="card-body">

            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Notes</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($admissions)): ?>
                    <?php foreach ($admissions as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['admission_id']) ?></td>
                            <td><?= htmlspecialchars($a['admission_type'] ?? '—') ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($a['hospitalisation_date']))) ?></td>
                            <td><?= htmlspecialchars($a['intervention_time'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($a['notes'] ?? '—') ?></td>
                            <td>
                                <?php if ($a['statut'] === 'pré-admission'): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-hourglass-split me-1"></i>Pré-admission
                                    </span>
                                <?php elseif ($a['statut'] === 'confirmée'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i>Confirmée
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($a['statut'] ?? '—') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($a['statut'] === 'pré-admission'): ?>
                                    <!-- Bouton Modifier -->
                                    <a href="edit_admission.php?admission_id=<?= (int)$a['admission_id'] ?>&from=<?= htmlspecialchars($from) ?>&patient=<?= htmlspecialchars($social_number) ?>"
                                       class="btn btn-sm btn-primary me-1"
                                       title="Modifier">
                                        <i class="bi bi-pencil-fill"></i> Modifier
                                    </a>

                                    <!-- Bouton Supprimer (déclenche modal) -->
                                    <button type="button"
                                            class="btn btn-sm btn-danger"
                                            title="Supprimer"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal"
                                            data-admission-id="<?= (int)$a['admission_id'] ?>">
                                        <i class="bi bi-trash-fill"></i> Supprimer
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Aucun historique</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

        </div>
    </div>

</div>

<!-- ======================================================
     Modal de confirmation de suppression
====================================================== -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmer la suppression
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                Êtes-vous sûr de vouloir supprimer la pré-admission <strong>#<span id="modalAdmissionId"></span></strong> ?
                <br><span class="text-danger small">Cette action est irréversible.</span>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Annuler
                </button>

                <!-- Formulaire POST de suppression -->
                <form method="POST" action="patient_details.php?id=<?= htmlspecialchars($social_number) ?>&from=<?= htmlspecialchars($from) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="admission_id" id="formAdmissionId" value="">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash-fill me-1"></i>Supprimer
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Injecter l'ID de l'admission dans le modal
const deleteModal = document.getElementById('deleteModal');
deleteModal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const admissionId = btn.getAttribute('data-admission-id');
    document.getElementById('modalAdmissionId').textContent = admissionId;
    document.getElementById('formAdmissionId').value = admissionId;
});
</script>

</body>
</html>