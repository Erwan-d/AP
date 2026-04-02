<?php
require_once '../systems/config.php';
$pdo = getPDOConnection();

session_start();

// Sécurité
if (!isset($_GET['admission_id'])) {
    die("ID manquant");
}

$admission_id = (int) $_GET['admission_id'];

// Vérifier que l'admission existe
$stmt = $pdo->prepare("
    SELECT a.admission_id, p.lastname, p.firstname
    FROM ap_admission a
    JOIN ap_patient p ON p.social_number = a.patient_social
    WHERE a.admission_id = ?
");
$stmt->execute([$admission_id]);
$data = $stmt->fetch();

if (!$data) {
    die("Pré-admission introuvable");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmation pré-admission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card shadow">
        <div class="card-body text-center">

            <h3 class="mb-4">Confirmation</h3>

            <p>
                Voulez-vous générer la fiche de pré-admission ?
            </p>

            <h5 class="mb-4">
                <?= htmlspecialchars($data['firstname'] . ' ' . $data['lastname']) ?>
            </h5>

            <div class="d-flex justify-content-center gap-3">

                <!-- Générer PDF -->
                <a href="generate_pdf.php?admission_id=<?= $admission_id ?>" 
                   class="btn btn-success">
                   ✅ Oui, télécharger le PDF
                </a>

                <!-- Retour -->
                <a href="preadmission.php" class="btn btn-secondary">
                    ❌ Annuler
                </a>

            </div>

        </div>
    </div>

</div>

</body>
</html>