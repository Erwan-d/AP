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


if (isset($_POST['add']) || isset($_POST['update'])) {
    $service_id = $_POST['service_id'] ?? null;
    $service_name = trim($_POST['service_name'] ?? '');

    if (!empty($service_name)) {
        if (isset($_POST['add'])) {
            $stmt = $pdo->prepare("INSERT INTO services (service_name) VALUES (:service_name)");
            $stmt->execute([':service_name' => $service_name]);
        } else {
            $stmt = $pdo->prepare("UPDATE services SET service_name = :service_name WHERE service_id = :service_id");
            $stmt->execute([':service_name' => $service_name, ':service_id' => $service_id]);
        }
        header("Location: manage_services.php");
        exit;
    } else {
        $errorMsg = "Le nom du service est obligatoire.";
    }
}


if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_services.php");
    exit;
}


$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}


$services = $pdo->query("SELECT * FROM services ORDER BY service_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Services</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<body>
<header>
    <h1>Gestion des services</h1>
    <nav>
        <a href="../systems/logout.php">Déconnexion</a>
        <a href="dashboard_admin.php">Accueil</a>
        <a href="manage_admissions.php">Gérer les admission</a>
        <a href="manage_chambre.php">Gérer les chambres</a>
        <a href="manage_couverture.php">Gérer les couvertures sociales</a>
        <a href="documents.php">Gérer les documents</a>
        <a href="manage_patients.php"> Gérer les patients</a>
        <a href="manage_personne_contact.php">Gérer les contact</a>
        <a href="manage_personnels.php">Gérer le personnels</a>
        <a href="manage_roles.php">Gérer les roles</a>
    </nav>
</header>

<h2>Gestion des Services</h2>

<?php if (!empty($errorMsg)) echo "<p style='color:red;'>$errorMsg</p>"; ?>


<table>
    <tr>
        <th>ID</th>
        <th>Nom du service</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($services as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['service_id']) ?></td>
            <td><?= htmlspecialchars($s['service_name']) ?></td>
            <td>
                <a href="?edit=<?= $s['service_id'] ?>">Modifier</a> |
                <a href="?delete=<?= $s['service_id'] ?>" onclick="return confirm('Supprimer ce service ?')">Supprimer</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>


<form method="post">
    <h3><?= $edit ? "Modifier le service" : "Ajouter un service" ?></h3>
    <?php if ($edit): ?>
        <input type="hidden" name="service_id" value="<?= htmlspecialchars($edit['service_id']) ?>">
    <?php endif; ?>
    <input type="text" name="service_name" placeholder="Nom du service" value="<?= htmlspecialchars($edit['service_name'] ?? '') ?>" required>
    <button type="submit" name="<?= $edit ? 'update' : 'add' ?>">
        <?= $edit ? 'Mettre à jour' : 'Ajouter' ?>
    </button>
</form>

</body>
</html>
