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

// suppr
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM personne_contact WHERE social_number=?")->execute([$id]);
    header("Location: manage_personne_contact.php");
    exit;
}

// ajout
if (isset($_POST['add'])) {
    $pdo->prepare("
        INSERT INTO personne_contact (social_number, name, firstname, address, phone)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([
        $_POST['social_number'],
        $_POST['name'],
        $_POST['firstname'],
        $_POST['address'],
        $_POST['phone']
    ]);

    header("Location: manage_personne_contact.php");
    exit;
}

// modif
if (isset($_POST['edit'])) {
    $pdo->prepare("
        UPDATE personne_contact 
        SET name=?, firstname=?, address=?, phone=?
        WHERE social_number=?
    ")->execute([
        $_POST['name'],
        $_POST['firstname'],
        $_POST['address'],
        $_POST['phone'],
        $_POST['social_number']
    ]);

    header("Location: manage_personne_contact.php");
    exit;
}

// select
$contacts = $pdo->query("SELECT * FROM personne_contact ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM personne_contact WHERE social_number=?");
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des personnes contact</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<body>

<header>
    <h1>Personnes de contact</h1>
    <nav>
        <a href="../systems/logout.php">Déconnexion</a>
        <a href="dashboard_admin.php">Accueil</a>
        <a href="manage_admissions.php">Gérer les admission</a>
        <a href="manage_chambre.php">Gérer les chambres</a>
        <a href="manage_couverture.php">Gérer les couvertures sociales</a>
        <a href="documents.php">Gérer les documents</a>
        <a href="manage_patients.php"> Gérer les patients</a>
        <a href="manage_personnels.php">Gérer le personnels</a>
        <a href="manage_roles.php">Gérer les roles</a>
        <a href="manage_services.php">Gérer les services</a>
    </nav>
</header>


<div class="stats">
    <h2>Liste des personnes de contact</h2>

    <table>
        <tr>
            <th>N° Sécurité Sociale</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Adresse</th>
            <th>Téléphone</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($contacts as $c): ?>
        <tr>
            <?php if ($editing && $editing['social_number'] == $c['social_number']): ?>
                <form method="POST">
                    <td><?= $c['social_number'] ?></td>

                    <td><input type="text" name="name" value="<?= htmlspecialchars($c['name']) ?>"></td>
                    <td><input type="text" name="firstname" value="<?= htmlspecialchars($c['firstname']) ?>"></td>
                    <td><input type="text" name="address" value="<?= htmlspecialchars($c['address']) ?>"></td>
                    <td><input type="text" name="phone" value="<?= htmlspecialchars($c['phone']) ?>"></td>

                    <td>
                        <input type="hidden" name="social_number" value="<?= $c['social_number'] ?>">
                        <button class="card" type="submit" name="edit">Enregistrer</button>
                    </td>
                </form>

            <?php else: ?>
                <td><?= $c['social_number'] ?></td>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['firstname']) ?></td>
                <td><?= htmlspecialchars($c['address']) ?></td>
                <td><?= htmlspecialchars($c['phone']) ?></td>

                <td>
                    <a class="card" style="padding:10px;" href="?edit=<?= $c['social_number'] ?>">Modifier</a>
                    <a class="card" style="padding:10px; background:#e74c3c; color:#fff;"
                       onclick="return confirm('Supprimer ?')"
                       href="?delete=<?= $c['social_number'] ?>">Supprimer</a>
                </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="actions">
    <h2>Ajouter une personne de contact</h2>

    <form method="POST">

        <label>N° Sécurité Sociale :</label>
        <input type="text" name="social_number" required><br><br>

        <label>Nom :</label>
        <input type="text" name="name"><br><br>

        <label>Prénom :</label>
        <input type="text" name="firstname"><br><br>

        <label>Adresse :</label>
        <input type="text" name="address"><br><br>

        <label>Téléphone :</label>
        <input type="text" name="phone"><br><br>

        <button type="submit" name="add" class="card" style="width:200px;margin:auto;display:block;">
            Ajouter
        </button>
    </form>
</div>


</body>
</html>
