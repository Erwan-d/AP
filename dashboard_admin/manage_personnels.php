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
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM personnels WHERE personnel_id=?")->execute([$id]);
    header("Location: manage_personnels.php");
    exit;
}


//ajout
if (isset($_POST['save'])) {

    $personnel_id = $_POST['personnel_id'] ?? null;
    $name = $_POST['personnel_name'];
    $password = $_POST['password'];
    $role_id = $_POST['role_id'];
    $service_id = $_POST['service_id'];

    if ($personnel_id) {
        //modif
        $sql = "UPDATE personnels SET personnel_name=?, password=?, role_id=?, service_id=? 
                WHERE personnel_id=?";
        $pdo->prepare($sql)->execute([$name, $password, $role_id, $service_id, $personnel_id]);

    } else {
        
        $sql = "INSERT INTO personnels (personnel_name, password, role_id, service_id)
                VALUES (?,?,?,?)";
        $pdo->prepare($sql)->execute([$name, $password, $role_id, $service_id]);
    }

    header("Location: manage_personnels.php");
    exit;
}


//select
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();
$services = $pdo->query("SELECT * FROM services")->fetchAll();
$personnels = $pdo->query("
    SELECT personnels.*, roles.role_name, services.service_name 
    FROM personnels
    LEFT JOIN roles ON personnels.role_id = roles.role_id
    LEFT JOIN services ON personnels.service_id = services.service_id
")->fetchAll();


//edit
$editing = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $st = $pdo->prepare("SELECT * FROM personnels WHERE personnel_id=?");
    $st->execute([$id]);
    $editing = $st->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Gestion du Personnel</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>

<body>

<header>
    <h1>Gestion du personnels</h1>
    <nav>
        <a href="../systems/logout.php">Déconnexion</a>
        <a href="dashboard_admin.php">Accueil</a>
        <a href="manage_admissions.php">Gérer les admission</a>
        <a href="manage_chambre.php">Gérer les chambres</a>
        <a href="manage_couverture.php">Gérer les couvertures sociales</a>
        <a href="documents.php">Gérer les documents</a>
        <a href="manage_patients.php"> Gérer les patients</a>
        <a href="manage_personne_contact.php">Gérer les contact</a>
        <a href="manage_roles.php">Gérer les roles</a>
        <a href="manage_services.php">Gérer les services</a>
    </nav>
</header>


<div class="stats">
    <h2>Liste du personnel</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Rôle</th>
            <th>Service</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($personnels as $p): ?>
        <tr>
            <td><?= $p['personnel_id'] ?></td>
            <td><?= $p['personnel_name'] ?></td>
            <td><?= $p['role_name'] ?></td>
            <td><?= $p['service_name'] ?></td>

            <td>
                <a class="card" style="padding:10px;" href="?edit=<?= $p['personnel_id'] ?>">Modifier</a>
                <a class="card" style="padding:10px; background:#e74c3c; color:white;"
                   href="?delete=<?= $p['personnel_id'] ?>"
                   onclick="return confirm('Supprimer ce membre ?')">
                   Supprimer
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="actions">
    <h2><?= $editing ? "Modifier un membre" : "Ajouter un membre du personnel" ?></h2>

    <form method="POST">

        <input type="hidden" name="personnel_id" value="<?= $editing['personnel_id'] ?? "" ?>">

        <label>Nom :</label>
        <input type="text" name="personnel_name" required
               value="<?= $editing['personnel_name'] ?? "" ?>">

        <label>Mot de passe :</label>
        <input type="password" name="password" required
               value="<?= $editing['password'] ?? "" ?>">

        <label>Rôle :</label>
        <select name="role_id">
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['role_id'] ?>"
                    <?= ($editing && $editing['role_id']==$r['role_id']) ? "selected" : "" ?>>
                    <?= $r['role_name'] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Service :</label>
        <select name="service_id">
            <?php foreach ($services as $s): ?>
                <option value="<?= $s['service_id'] ?>"
                    <?= ($editing && $editing['service_id']==$s['service_id']) ? "selected" : "" ?>>
                    <?= $s['service_name'] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="save" class="card" style="width:200px; margin:auto;">
            Enregistrer
        </button>
    </form>
</div>

</body>
</html>

