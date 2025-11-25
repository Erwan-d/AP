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


$patients = $pdo->query("SELECT social_number, lastname, firstname FROM patient ORDER BY lastname")->fetchAll(PDO::FETCH_ASSOC);

//suppr
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM documents WHERE social_number = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_documents.php");
    exit;
}

//modif
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $social_number = $_POST['social_number'];
    $authorization_minor = isset($_POST['authorization_minor']) ? 1 : 0;

    
    function getFileData($name) {
        if (!empty($_FILES[$name]['tmp_name'])) {
            return file_get_contents($_FILES[$name]['tmp_name']);
        }
        return null;
    }

    $id_card = getFileData('id_card');
    $vital_card = getFileData('vital_card');
    $insurance_card = getFileData('insurance_card');
    $livret_famille = getFileData('livret_famille');

    $exists = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE social_number = ?");
    $exists->execute([$social_number]);
    $exists = $exists->fetchColumn() > 0;

    if ($exists) {
        $sql = "UPDATE documents SET authorization_minor = :authorization_minor";
        $params = [':authorization_minor' => $authorization_minor, ':social_number' => $social_number];

        if ($id_card) { $sql .= ", id_card = :id_card"; $params[':id_card'] = $id_card; }
        if ($vital_card) { $sql .= ", vital_card = :vital_card"; $params[':vital_card'] = $vital_card; }
        if ($insurance_card) { $sql .= ", insurance_card = :insurance_card"; $params[':insurance_card'] = $insurance_card; }
        if ($livret_famille) { $sql .= ", livret_famille = :livret_famille"; $params[':livret_famille'] = $livret_famille; }

        $sql .= " WHERE social_number = :social_number";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("INSERT INTO documents 
            (social_number, id_card, vital_card, insurance_card, livret_famille, authorization_minor)
            VALUES (:social_number, :id_card, :vital_card, :insurance_card, :livret_famille, :authorization_minor)");
        $stmt->execute([
            ':social_number' => $social_number,
            ':id_card' => $id_card,
            ':vital_card' => $vital_card,
            ':insurance_card' => $insurance_card,
            ':livret_famille' => $livret_famille,
            ':authorization_minor' => $authorization_minor
        ]);
    }

    header("Location: manage_documents.php");
    exit;
}


$documents = $pdo->query("
    SELECT d.*, p.lastname, p.firstname
    FROM documents d
    LEFT JOIN patient p ON d.social_number = p.social_number
    ORDER BY p.lastname
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des documents</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<body>
<header>
    <h1>Gestion des documents</h1>
    <nav>
        <a href="../systems/logout.php">Déconnexion</a>
        <a href="dashboard_admin.php">Accueil</a>
        <a href="manage_admissions.php">Gérer les admission</a>
        <a href="manage_chambre.php">Gérer les chambres</a>
        <a href="manage_couverture.php">Gérer les couvertures sociales</a>
        <a href="manage_patients.php"> Gérer les patients</a>
        <a href="manage_personne_contact.php">Gérer les contact</a>
        <a href="manage_personnels.php">Gérer le personnels</a>
        <a href="manage_roles.php">Gérer les roles</a>
        <a href="manage_services.php">Gérer les services</a>
    </nav>
</header>

<main>
    <h2>Documents enregistrés</h2>

    <table>
        <tr>
            <th>Patient</th>
            <th>Carte d'identité</th>
            <th>Carte Vitale</th>
            <th>Carte d'assurance</th>
            <th>Livret de famille</th>
            <th>Autorisation mineur</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($documents as $doc): ?>
            <tr>
                <td><?= htmlspecialchars($doc['lastname'] . ' ' . $doc['firstname']) ?></td>
                <td><?= $doc['id_card'] ? "fichier présent" : "Aucun fichier" ?></td>
                <td><?= $doc['vital_card'] ? "fichier présent" : "Aucun fichier" ?></td>
                <td><?= $doc['insurance_card'] ? "fichier présent" : "Aucun fichier" ?></td>
                <td><?= $doc['livret_famille'] ? "fichier présent" : "Aucun fichier" ?></td>
                <td><?= $doc['authorization_minor'] ? "Oui" : "Non" ?></td>
                <td>
                    <a href="?edit=<?= urlencode($doc['social_number']) ?>">Modifier</a> |
                    <a href="?delete=<?= urlencode($doc['social_number']) ?>" onclick="return confirm('Supprimer les documents de ce patient ?')">Supprimer</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <hr>

    <?php
    $edit = null;
    if (isset($_GET['edit'])) {
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE social_number = ?");
        $stmt->execute([$_GET['edit']]);
        $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    ?>

    <h3><?= $edit ? "Modifier les documents du patient" : "Ajouter des documents" ?></h3>
    <form method="post" enctype="multipart/form-data">
        <label>Patient :</label>
        <select name="social_number" required <?= $edit ? "disabled" : "" ?>>
            <option value="">-- Sélectionner un patient --</option>
            <?php foreach ($patients as $p): ?>
                <option value="<?= htmlspecialchars($p['social_number']) ?>"
                    <?= ($edit && $edit['social_number'] == $p['social_number']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['lastname'] . ' ' . $p['firstname'] . ' (' . $p['social_number'] . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($edit): ?>
            <input type="hidden" name="social_number" value="<?= htmlspecialchars($edit['social_number']) ?>">
        <?php endif; ?>

        <label>Carte d'identité :</label>
        <input type="file" name="id_card" accept=".pdf">

        <label>Carte Vitale :</label>
        <input type="file" name="vital_card" accept=".pdf">

        <label>Carte d'assurance :</label>
        <input type="file" name="insurance_card" accept=".pdf">

        <label>Livret de famille :</label>
        <input type="file" name="livret_famille" accept=".pdf">

        <label>
            <input type="checkbox" name="authorization_minor" <?= !empty($edit['authorization_minor']) ? 'checked' : '' ?>>
            Autorisation parentale (mineur)
        </label>

        <button type="submit"><?= $edit ? "Mettre à jour" : "Ajouter" ?></button>
    </form>
</main>
</body>
</html>
