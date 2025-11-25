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

//suppr
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM chambre WHERE chambre_id = ?");
    $stmt->execute([$id]);
    header("Location: manage_chambres.php");
    exit;
}

//ajout
if (isset($_POST['add'])) {
    $type = $_POST['type_chambre'];
    $private = isset($_POST['private_room']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO chambre (type_chambre, private_room) VALUES (?, ?)");
    $stmt->execute([$type, $private]);

    header("Location: manage_chambres.php");
    exit;
}

//modif
if (isset($_POST['edit'])) {
    $id = intval($_POST['chambre_id']);
    $type = $_POST['type_chambre'];
    $private = isset($_POST['private_room']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE chambre SET type_chambre = ?, private_room = ? WHERE chambre_id = ?");
    $stmt->execute([$type, $private, $id]);

    header("Location: manage_chambres.php");
    exit;
}

// select
$chambres = $pdo->query("SELECT * FROM chambre ORDER BY chambre_id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des chambres</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<header>
    <h1>Gestion des Admissions</h1>
    <nav>
        <a href="../systems/logout.php">Déconnexion</a>
        <a href="dashboard_admin.php">Accueil</a>
        <a href="manage_admissions.php">Gérer les admission</a>
        <a href="manage_couverture.php">Gérer les couvertures sociales</a>
        <a href="documents.php">Gérer les documents</a>
        <a href="manage_patients.php"> Gérer les patients</a>
        <a href="manage_personne_contact.php">Gérer les contact</a>
        <a href="manage_personnels.php">Gérer le personnels</a>
        <a href="manage_roles.php">Gérer les roles</a>
        <a href="manage_services.php">Gérer les services</a>
    </nav>
</header>
<body>

<h1>Gestion des chambres</h1>

<!-- ===== Tableau des chambres ===== -->
<table border="1" cellpadding="8">
    <tr>
        <th>ID</th>
        <th>Type de chambre</th>
        <th>Privée</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($chambres as $ch): ?>
        <tr>

            <?php if (isset($_GET['edit']) && $_GET['edit'] == $ch['chambre_id']): ?>
                <!-- Mode édition -->
                <form method="POST">
                    <td><?= $ch['chambre_id'] ?></td>
                    <td><input type="text" name="type_chambre" value="<?= htmlspecialchars($ch['type_chambre']) ?>" required></td>
                    <td>
                        <input type="checkbox" name="private_room" <?= $ch['private_room'] ? 'checked' : '' ?>>
                    </td>
                    <td>
                        <input type="hidden" name="chambre_id" value="<?= $ch['chambre_id'] ?>">
                        <button type="submit" name="edit">Enregistrer</button>
                    </td>
                </form>

            <?php else: ?>
                <!-- Mode normal -->
                <td><?= $ch['chambre_id'] ?></td>
                <td><?= htmlspecialchars($ch['type_chambre']) ?></td>
                <td><?= $ch['private_room'] ? "Oui" : "Non" ?></td>
                <td>
                    <a href="manage_chambres.php?edit=<?= $ch['chambre_id'] ?>">Modifier</a>
                    <a href="manage_chambres.php?delete=<?= $ch['chambre_id'] ?>" onclick="return confirm('Supprimer cette chambre ?')">Supprimer</a>
                </td>
            <?php endif; ?>

        </tr>
    <?php endforeach; ?>
</table>

<!-- ===== Formulaire d'ajout ===== -->
<h2>Ajouter une chambre</h2>

<form method="POST">
    <label>Type :</label>
    <input type="text" name="type_chambre" required>

    <label>Privée :</label>
    <input type="checkbox" name="private_room">

    <button type="submit" name="add">Ajouter</button>
</form>

</body>
</html>
