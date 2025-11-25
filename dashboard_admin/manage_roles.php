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
    $pdo->prepare("DELETE FROM roles WHERE role_id=?")->execute([$id]);
    header("Location: manage_roles.php");
    exit;
}


//ajout
if (isset($_POST['add'])) {
    $pdo->prepare("
        INSERT INTO roles (role_name, description)
        VALUES (?, ?)
    ")->execute([
        $_POST['role_name'],
        $_POST['description']
    ]);

    header("Location: manage_roles.php");
    exit;
}


//modif
if (isset($_POST['edit'])) {
    $pdo->prepare("
        UPDATE roles 
        SET role_name=?, description=?
        WHERE role_id=?
    ")->execute([
        $_POST['role_name'],
        $_POST['description'],
        $_POST['role_id']
    ]);

    header("Location: manage_roles.php");
    exit;
}

//select
$roles = $pdo->query("SELECT * FROM roles ORDER BY role_name ASC")->fetchAll(PDO::FETCH_ASSOC);



$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE role_id=?");
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des rôles</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<body>

<header>
    <h1>Gestion des rôles</h1>
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
        <a href="manage_services.php">Gérer les services</a>
    </nav>
</header>



<div class="stats">
    <h2>Liste des rôles</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Nom du rôle</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($roles as $r): ?>
        <tr>
            <?php if ($editing && $editing['role_id'] == $r['role_id']): ?>
                <form method="POST">
                    <td><?= $r['role_id'] ?></td>

                    <td><input type="text" name="role_name" value="<?= htmlspecialchars($r['role_name']) ?>"></td>
                    <td><textarea name="description"><?= htmlspecialchars($r['description']) ?></textarea></td>

                    <td>
                        <input type="hidden" name="role_id" value="<?= $r['role_id'] ?>">
                        <button class="card" type="submit" name="edit">Enregistrer</button>
                    </td>
                </form>

            <?php else: ?>
                <td><?= $r['role_id'] ?></td>
                <td><?= htmlspecialchars($r['role_name']) ?></td>
                <td><?= nl2br(htmlspecialchars($r['description'])) ?></td>

                <td>
                    <a class="card" style="padding:10px;" href="?edit=<?= $r['role_id'] ?>">Modifier</a>
                    <a class="card" style="padding:10px; background:#e74c3c; color:white;"
                       href="?delete=<?= $r['role_id'] ?>"
                       onclick="return confirm('Supprimer ce rôle ?')">Supprimer</a>
                </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="actions">
    <h2>Ajouter un rôle</h2>

    <form method="POST">

        <label>Nom du rôle :</label>
        <input type="text" name="role_name" required><br><br>

        <label>Description :</label>
        <textarea name="description"></textarea><br><br>

        <button class="card" type="submit" name="add" style="width:200px;margin:auto;display:block;">
            Ajouter
        </button>
    </form>
</div>

</body>
</html>
