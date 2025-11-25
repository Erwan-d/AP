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


$contacts = $pdo->query("SELECT social_number, name, firstname FROM personne_contact")->fetchAll(PDO::FETCH_ASSOC);


function contactName($contacts, $contact_id) {
    foreach ($contacts as $c) {
        if ($c['social_number'] === $contact_id) {
            return htmlspecialchars($c['firstname'] . ' ' . $c['name']);
        }
    }
    return "";
}


if (isset($_POST['add']) || isset($_POST['update'])) {
    $fields = [
        'social_number', 'civ', 'lastname', 'firstname', 'birthdate', 'marriedname', 'street',
        'number_street', 'zip', 'city', 'email', 'phone', 'contact_confiance', 'contact_prevenir'
    ];

    $data = [];
    foreach ($fields as $f) {
        $data[$f] = $_POST[$f] ?? null;
    }

    if (
        !empty($data['social_number']) &&
        !empty($data['civ']) &&
        !empty($data['lastname']) &&
        !empty($data['firstname']) &&
        !empty($data['birthdate'])
    ) {
        if (isset($_POST['add'])) {
            $sql = "INSERT INTO patient (" . implode(',', $fields) . ")
                    VALUES (" . implode(',', array_map(fn($f) => ":$f", $fields)) . ")";
        } else {
            $sql = "UPDATE patient SET "
                 . implode(', ', array_map(fn($f) => "$f = :$f", array_slice($fields, 1)))
                 . " WHERE social_number = :social_number";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        header("Location: manage_patients.php");
        exit;
    } else {
        $errorMsg = "⚠️ Tous les champs obligatoires doivent être remplis.";
    }
}

// Suppr
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM patient WHERE social_number = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_patients.php");
    exit;
}

// edit
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM patient WHERE social_number = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}


$patients = $pdo->query("SELECT * FROM patient ORDER BY lastname ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Patients</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<body>
<header>
    <h1>Gestion des patients</h1>
    <nav>
        <a href="../systems/logout.php">Déconnexion</a>
        <a href="dashboard_admin.php">Accueil</a>
        <a href="manage_admissions.php">Gérer les admission</a>
        <a href="manage_chambre.php">Gérer les chambres</a>
        <a href="manage_couverture.php">Gérer les couvertures sociales</a>
        <a href="documents.php">Gérer les documents</a>
        <a href="manage_personne_contact.php">Gérer les contact</a>
        <a href="manage_personnels.php">Gérer le personnels</a>
        <a href="manage_roles.php">Gérer les roles</a>
        <a href="manage_services.php">Gérer les services</a>
        
    </nav>
</header>

<h1>Gestion des Patients</h1>

<?php if (!empty($errorMsg)) echo "<p style='color:red;'>$errorMsg</p>"; ?>

<table>
    <tr>
        <th>N° Sécurité Sociale</th>
        <th>Civilité</th>
        <th>Nom</th>
        <th>Prénom</th>
        <th>Date de naissance</th>
        <th>Nom marital</th>
        <th>Rue</th>
        <th>Numéro</th>
        <th>Code postal</th>
        <th>Ville</th>
        <th>Email</th>
        <th>Téléphone</th>
        <th>Contact de confiance</th>
        <th>Contact à prévenir</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($patients as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['social_number']) ?></td>
            <td><?= htmlspecialchars($p['civ']) ?></td>
            <td><?= htmlspecialchars($p['lastname']) ?></td>
            <td><?= htmlspecialchars($p['firstname']) ?></td>
            <td><?= htmlspecialchars($p['birthdate']) ?></td>
            <td><?= htmlspecialchars($p['marriedname']) ?></td>
            <td><?= htmlspecialchars($p['street']) ?></td>
            <td><?= htmlspecialchars($p['number_street']) ?></td>
            <td><?= htmlspecialchars($p['zip']) ?></td>
            <td><?= htmlspecialchars($p['city']) ?></td>
            <td><?= htmlspecialchars($p['email']) ?></td>
            <td><?= htmlspecialchars($p['phone']) ?></td>
            <td><?= contactName($contacts, $p['contact_confiance']) ?></td>
            <td><?= contactName($contacts, $p['contact_prevenir']) ?></td>
            <td>
                <a href="?edit=<?= $p['social_number'] ?>">Modifier</a> |
                <a href="?delete=<?= $p['social_number'] ?>" onclick="return confirm('Supprimer ce patient ?')">Supprimer</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<form method="post">
    <h2><?= $edit ? "Modifier le patient #".htmlspecialchars($edit['social_number']) : "Ajouter un patient" ?></h2>

    <input type="text" name="social_number" maxlength="15" placeholder="N° Sécurité Sociale"
           value="<?= $edit['social_number'] ?? '' ?>" <?= $edit ? 'readonly' : 'required' ?>>

    <input type="text" name="civ" placeholder="Civilité" value="<?= $edit['civ'] ?? '' ?>" required>
    <input type="text" name="lastname" placeholder="Nom" value="<?= $edit['lastname'] ?? '' ?>" required>
    <input type="text" name="firstname" placeholder="Prénom" value="<?= $edit['firstname'] ?? '' ?>" required>
    <input type="date" name="birthdate" value="<?= $edit['birthdate'] ?? '' ?>" required>
    <input type="text" name="marriedname" placeholder="Nom marital" value="<?= $edit['marriedname'] ?? '' ?>">
    <input type="text" name="street" placeholder="Rue" value="<?= $edit['street'] ?? '' ?>">
    <input type="text" name="number_street" placeholder="Numéro de rue" value="<?= $edit['number_street'] ?? '' ?>">
    <input type="text" name="zip" placeholder="Code postal" value="<?= $edit['zip'] ?? '' ?>">
    <input type="text" name="city" placeholder="Ville" value="<?= $edit['city'] ?? '' ?>">
    <input type="email" name="email" placeholder="Email" value="<?= $edit['email'] ?? '' ?>">
    <input type="number" name="phone" placeholder="Téléphone" value="<?= $edit['phone'] ?? '' ?>">
    <input type="text" name="contact_confiance" placeholder="Contact de confiance" value="<?= $edit['contact_confiance'] ?? '' ?>">
    <input type="text" name="contact_prevenir" placeholder="Contact à prévenir" value="<?= $edit['contact_prevenir'] ?? '' ?>">

    <button type="submit" name="<?= $edit ? 'update' : 'add' ?>">
        <?= $edit ? 'Mettre à jour' : 'Ajouter' ?>
    </button>
</form>
</body>
</html>


