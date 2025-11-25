<?php
require "../config/config.php";
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

// ajout/modif
if (isset($_POST['add']) || isset($_POST['update'])) {
    $fields = ['social_number', 'social_org', 'is_assured', 'ald', 'insurance_number', 'insurance_name'];
    $data = [];

    foreach ($fields as $f) {
        $data[$f] = $_POST[$f] ?? null;
    }

    
    $data['is_assured'] = isset($_POST['is_assured']) ? 1 : 0;
    $data['ald'] = isset($_POST['ald']) ? 1 : 0;

    if (!empty($data['social_number']) && !empty($data['social_org'])) {
        if (isset($_POST['add'])) {
            $sql = "INSERT INTO couverture_sociale 
                    (social_number, social_org, is_assured, ald, insurance_number, insurance_name)
                    VALUES (:social_number, :social_org, :is_assured, :ald, :insurance_number, :insurance_name)";
        } else {
            $sql = "UPDATE couverture_sociale 
                    SET social_org = :social_org, is_assured = :is_assured, ald = :ald,
                        insurance_number = :insurance_number, insurance_name = :insurance_name
                    WHERE social_number = :social_number";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        header("Location: manage_couverture.php");
        exit;
    } else {
        $errorMsg = "Tous les champs obligatoires doivent être remplis.";
    }
}

// --- Suppr
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM couverture_sociale WHERE social_number = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_couverture.php");
    exit;
}

//edit
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM couverture_sociale WHERE social_number = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}


$couvertures = $pdo->query("SELECT * FROM couverture_sociale ORDER BY social_number ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Couvertures Sociales</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<body>
<header>
    <h1>Gestion des couvertures sociales</h1>
    <nav>
        <a href="../systems/logout.php">Déconnexion</a>
        <a href="dashboard_admin.php">Accueil</a>
        <a href="manage_admissions.php">Gérer les admission</a>
        <a href="manage_chambre.php">Gérer les chambres</a>
        <a href="documents.php">Gérer les documents</a>
        <a href="manage_patients.php"> Gérer les patients</a>
        <a href="manage_personne_contact.php">Gérer les contact</a>
        <a href="manage_personnels.php">Gérer le personnels</a>
        <a href="manage_roles.php">Gérer les roles</a>
        <a href="manage_services.php">Gérer les services</a>
    </nav>
</header>

<h2>Gestion des Couvertures Sociales</h2>

<?php if (!empty($errorMsg)) echo "<p style='color:red;'>$errorMsg</p>"; ?>


<table>
    <tr>
        <th>N° Sécurité Sociale</th>
        <th>Organisme social</th>
        <th>Assuré</th>
        <th>ALD</th>
        <th>N° d'assurance</th>
        <th>Nom de l'assurance</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($couvertures as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['social_number']) ?></td>
            <td><?= htmlspecialchars($c['social_org']) ?></td>
            <td><?= $c['is_assured'] ? 'Oui' : 'Non' ?></td>
            <td><?= $c['ald'] ? 'Oui' : 'Non' ?></td>
            <td><?= htmlspecialchars($c['insurance_number']) ?></td>
            <td><?= htmlspecialchars($c['insurance_name']) ?></td>
            <td>
                <a href="?edit=<?= $c['social_number'] ?>">Modifier</a> |
                <a href="?delete=<?= $c['social_number'] ?>" onclick="return confirm('Supprimer cette couverture ?')">Supprimer</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>


<form method="post">
    <h3><?= $edit ? "Modifier une couverture sociale" : "Ajouter une couverture sociale" ?></h3>

    <input type="text" name="social_number" placeholder="N° Sécurité Sociale"
           value="<?= htmlspecialchars($edit['social_number'] ?? '') ?>" <?= $edit ? 'readonly' : 'required' ?>>

    <input type="text" name="social_org" placeholder="Organisme social"
           value="<?= htmlspecialchars($edit['social_org'] ?? '') ?>" required>

    <label>
        <input type="checkbox" name="is_assured" <?= !empty($edit['is_assured']) ? 'checked' : '' ?>> Assuré
    </label>

    <label>
        <input type="checkbox" name="ald" <?= !empty($edit['ald']) ? 'checked' : '' ?>> ALD
    </label>

    <input type="text" name="insurance_number" placeholder="N° d'assurance"
           value="<?= htmlspecialchars($edit['insurance_number'] ?? '') ?>">

    <input type="text" name="insurance_name" placeholder="Nom de l'assurance"
           value="<?= htmlspecialchars($edit['insurance_name'] ?? '') ?>">

    <button type="submit" name="<?= $edit ? 'update' : 'add' ?>">
        <?= $edit ? 'Mettre à jour' : 'Ajouter' ?>
    </button>
</form>

</body>
</html>
