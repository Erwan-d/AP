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

// Récupération du "from"
$from = $_GET['from'] ?? 'secretary';

$search = "";
$patients = [];

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);

    $stmt = $pdo->prepare("
        SELECT *
        FROM ap_patient
        WHERE lastname LIKE :search
           OR firstname LIKE :search
           OR social_number LIKE :search
    ");

    $stmt->execute([
        ':search' => '%' . $search . '%'
    ]);

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Gestion du bouton retour
switch ($from) {
    case 'secretary':
        $back_url = 'secretary_dashboard.php';
        break;

    case 'search':
    default:
        $back_url = 'search_patient.php';
        break;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recherche Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <!-- Bouton retour -->
    <a href="<?= $back_url ?>" class="btn btn-secondary mb-4">
        ⬅ Retour
    </a>

    <h2>Rechercher un patient</h2>

    <form method="GET" class="mb-4">
        <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">

        <input type="text" name="search" class="form-control"
               placeholder="Nom, prénom ou numéro de sécurité sociale"
               value="<?= htmlspecialchars($search) ?>">
    </form>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Date de naissance</th>
                <th>Ville</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
        <?php if (!empty($patients)): ?>
            <?php foreach ($patients as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['lastname']) ?></td>
                    <td><?= htmlspecialchars($p['firstname']) ?></td>
                    <td><?= htmlspecialchars($p['birthdate']) ?></td>
                    <td><?= htmlspecialchars($p['city']) ?></td>
                    <td>
                        <a href="patient_details.php?id=<?= $p['social_number'] ?>&from=<?= $from ?>" 
                           class="btn btn-primary">
                            Voir Dossier
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php elseif ($search !== ""): ?>
            <tr>
                <td colspan="5" class="text-center">Aucun résultat</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>

</body>
</html>