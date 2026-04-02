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

// Paramètres URL
$admission_id  = (int)($_GET['admission_id'] ?? 0);
$patient_social = $_GET['patient'] ?? '';
$allowed       = ['search', 'secretary'];
$from          = in_array($_GET['from'] ?? '', $allowed) ? $_GET['from'] : 'search';

if (!$admission_id || !$patient_social) {
    die("Paramètres manquants.");
}

$back_url = "patient_details.php?id=" . urlencode($patient_social) . "&from=" . urlencode($from);

// -------------------------------------------------------
// Chargement des listes (chambres, médecins)
// -------------------------------------------------------
$chambres = [];
try {
    $stmt = $pdo->prepare("SELECT chambre_id, type_chambre, private_room FROM ap_chambre ORDER BY chambre_id");
    $stmt->execute();
    $chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur chargement chambres: " . $e->getMessage());
}

$medecins = [];
try {
    $stmt = $pdo->prepare("SELECT personnel_id, personnel_name, service_id FROM ap_personnels WHERE role_id = 3 ORDER BY personnel_name");
    $stmt->execute();
    $medecins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur chargement médecins: " . $e->getMessage());
}

// -------------------------------------------------------
// Chargement de l'admission existante
// -------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT a.*, p.lastname, p.firstname
    FROM ap_admission a
    JOIN ap_patient p ON p.social_number = a.patient_social
    WHERE a.admission_id = :aid
      AND a.patient_social = :social
      AND a.statut = 'pré-admission'
");
$stmt->execute([':aid' => $admission_id, ':social' => $patient_social]);
$admission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admission) {
    die("Pré-admission introuvable ou déjà confirmée.");
}

// -------------------------------------------------------
// Traitement formulaire (POST = modification)
// -------------------------------------------------------
$error   = '';
$success = '';
$updated_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $admission_type       = trim($_POST['admission_type'] ?? '');
    $hospitalisation_date = $_POST['hospitalisation_date'] ?? '';
    $intervention_time    = $_POST['intervention_time'] ?: null;
    $chambre_id           = $_POST['chambre_id'] ?: null;
    $reason               = trim($_POST['reason'] ?? '');
    $notes                = trim($_POST['notes'] ?? '');
    $private_room         = isset($_POST['private_room']) ? 1 : 0;
    $personnel_id         = $_POST['personnel_id'] ?: null;
    $service_id           = $_POST['service_id'] ?: null;
    $personnel_name       = '';

    // Récupérer le nom du médecin
    if ($personnel_id) {
        foreach ($medecins as $m) {
            if ($m['personnel_id'] == $personnel_id) {
                $personnel_name = $m['personnel_name'];
                if (!$service_id) {
                    $service_id = $m['service_id'];
                }
                break;
            }
        }
    }

    // Validations
    if (!$admission_type || !$hospitalisation_date) {
        $error = "Le type d'admission et la date d'hospitalisation sont obligatoires.";
    } elseif ($hospitalisation_date < date('Y-m-d')) {
        $error = "La date d'hospitalisation ne peut pas être dans le passé.";
    } else {
        // Vérifier disponibilité chambre (sauf si c'est la même chambre à la même date)
        if ($chambre_id) {
            $checkChambre = $pdo->prepare("
                SELECT COUNT(*) FROM ap_admission
                WHERE chambre_id = :cid
                  AND hospitalisation_date = :date
                  AND admission_id != :aid
            ");
            $checkChambre->execute([
                ':cid'  => $chambre_id,
                ':date' => $hospitalisation_date,
                ':aid'  => $admission_id
            ]);
            if ($checkChambre->fetchColumn() > 0) {
                $error = "Cette chambre est déjà réservée à cette date.";
            }
        }
    }

    if (!$error) {
        try {
            $upd = $pdo->prepare("
                UPDATE ap_admission SET
                    admission_type       = :type,
                    hospitalisation_date = :date,
                    intervention_time    = :time,
                    chambre_id           = :chambre,
                    reason               = :reason,
                    notes                = :notes,
                    private_room         = :priv,
                    personnel_id         = :pid,
                    personnel_name       = :pname,
                    service_id           = :sid
                WHERE admission_id = :aid
                  AND patient_social = :social
                  AND statut = 'pré-admission'
            ");

            $upd->execute([
                ':type'   => $admission_type,
                ':date'   => $hospitalisation_date,
                ':time'   => $intervention_time,
                ':chambre'=> $chambre_id,
                ':reason' => $reason,
                ':notes'  => $notes,
                ':priv'   => $private_room,
                ':pid'    => $personnel_id,
                ':pname'  => $personnel_name,
                ':sid'    => $service_id,
                ':aid'    => $admission_id,
                ':social' => $patient_social,
            ]);

            $success    = "Pré-admission modifiée avec succès.";
            $updated_id = $admission_id;

            // Recharger les données mises à jour
            $stmt = $pdo->prepare("
                SELECT a.*, p.lastname, p.firstname
                FROM ap_admission a
                JOIN ap_patient p ON p.social_number = a.patient_social
                WHERE a.admission_id = :aid
            ");
            $stmt->execute([':aid' => $admission_id]);
            $admission = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier la pré-admission #<?= $admission_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .card-header-custom { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .section-label { font-size: .75rem; font-weight: 700; text-transform: uppercase;
                         letter-spacing: .08em; color: #6c757d; margin-bottom: .5rem; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5 mb-5" style="max-width: 860px;">

    <!-- Bouton retour -->
    <a href="<?= $back_url ?>" class="btn btn-secondary mb-4">
        <i class="bi bi-arrow-left"></i> Retour au dossier
    </a>

    <h2 class="mb-1">
        <i class="bi bi-pencil-square me-2 text-primary"></i>Modifier la pré-admission
    </h2>
    <p class="text-muted mb-4">
        Patient : <strong><?= htmlspecialchars($admission['firstname'] . ' ' . $admission['lastname']) ?></strong>
        &nbsp;·&nbsp; ID admission : <strong>#<?= $admission_id ?></strong>
    </p>

    <!-- ---- Alertes ---- -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
        </div>

        <!-- Boutons post-modification -->
        <div class="d-flex gap-3 mb-4">
            <a href="generate_pdf.php?admission_id=<?= $updated_id ?>"
               class="btn btn-danger btn-lg" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill me-2"></i>Générer la fiche PDF
            </a>
            <a href="<?= $back_url ?>" class="btn btn-outline-secondary btn-lg">
                <i class="bi bi-arrow-left me-2"></i>Retour au dossier
            </a>
        </div>
    <?php endif; ?>

    <!-- ================================================================
         Formulaire de modification
    ================================================================ -->
    <form method="POST"
          action="edit_admission.php?admission_id=<?= $admission_id ?>&patient=<?= urlencode($patient_social) ?>&from=<?= urlencode($from) ?>">

        <!-- Type & Date -->
        <div class="card mb-4">
            <div class="card-header card-header-custom text-white">
                <i class="bi bi-hospital me-2"></i>Informations d'admission
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Type d'admission <span class="text-danger">*</span></label>
                        <select name="admission_type" class="form-select" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach (['Programmée', 'Urgence', 'Ambulatoire', 'Hospitalisation de jour'] as $t): ?>
                                <option value="<?= $t ?>"
                                    <?= ($admission['admission_type'] === $t) ? 'selected' : '' ?>>
                                    <?= $t ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Date d'hospitalisation <span class="text-danger">*</span></label>
                        <input type="date" name="hospitalisation_date" class="form-control" required
                               min="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars($admission['hospitalisation_date']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Heure d'intervention</label>
                        <input type="time" name="intervention_time" class="form-control"
                               value="<?= htmlspecialchars($admission['intervention_time'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Chambre</label>
                        <select name="chambre_id" class="form-select">
                            <option value="">— Aucune —</option>
                            <?php foreach ($chambres as $ch): ?>
                                <option value="<?= (int)$ch['chambre_id'] ?>"
                                    <?= ($admission['chambre_id'] == $ch['chambre_id']) ? 'selected' : '' ?>>
                                    Chambre <?= (int)$ch['chambre_id'] ?>
                                    (<?= htmlspecialchars($ch['type_chambre']) ?>
                                    <?= $ch['private_room'] ? '– Privée' : '' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="private_room"
                                   id="private_room" <?= $admission['private_room'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="private_room">Chambre privée</label>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Médecin & Service -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-person-badge me-2"></i>Médecin référent
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Médecin</label>
                        <select name="personnel_id" id="medecinSelect" class="form-select">
                            <option value="">— Sélectionner un médecin —</option>
                            <?php foreach ($medecins as $m): ?>
                                <option value="<?= (int)$m['personnel_id'] ?>"
                                        data-service="<?= (int)$m['service_id'] ?>"
                                    <?= ($admission['personnel_id'] == $m['personnel_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['personnel_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Service (ID)</label>
                        <input type="number" name="service_id" id="service_id" class="form-control"
                               value="<?= htmlspecialchars($admission['service_id'] ?? '') ?>"
                               placeholder="Auto depuis médecin">
                    </div>
                </div>
            </div>
        </div>

        <!-- Motif & Notes -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <i class="bi bi-journal-text me-2"></i>Motif & Notes
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Motif d'admission</label>
                        <textarea name="reason" class="form-control" rows="3"
                                  placeholder="Motif d'admission..."><?= htmlspecialchars($admission['reason'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Notes complémentaires</label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Notes..."><?= htmlspecialchars($admission['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons -->
        <div class="d-flex gap-3">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="bi bi-floppy-fill me-2"></i>Enregistrer les modifications
            </button>
            <a href="<?= $back_url ?>" class="btn btn-outline-secondary btn-lg">
                <i class="bi bi-x-circle me-2"></i>Annuler
            </a>
        </div>

    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Synchronise automatiquement le service_id depuis le médecin sélectionné
document.getElementById('medecinSelect').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const serviceId = opt.getAttribute('data-service');
    document.getElementById('service_id').value = serviceId || '';
});
</script>

</body>
</html>