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

// ajout/modif
if (isset($_POST['save'])) {

    $admission_id = $_POST['admission_id'] ?? null;

    $type = $_POST['admission_type'];
    $hospitalisation_date = $_POST['hospitalisation_date'];
    $intervention_time = $_POST['intervention_time'];
    $private_room = isset($_POST['private_room']) ? 1 : 0;
    $reason = $_POST['reason'];
    $notes = $_POST['notes'];
    $statut = $_POST['statut'];
    $personnel_name = $_POST['personnel_name'];
    $patient_social = $_POST['patient_social'];
    $chambre_id = $_POST['chambre_id'];

    if ($admission_id) {
        $sql = "UPDATE admission SET 
            admission_type=?, hospitalisation_date=?, intervention_time=?, private_room=?,
            reason=?, notes=?, statut=?, personnel_name=?, patient_social=?, chambre_id=?
            WHERE admission_id=?";
        $pdo->prepare($sql)->execute([
            $type, $hospitalisation_date, $intervention_time, $private_room,
            $reason, $notes, $statut, $personnel_name, $patient_social,
            $chambre_id, $admission_id
        ]);
    } else {
        $sql = "INSERT INTO admission 
            (admission_type, hospitalisation_date, intervention_time, private_room,
             reason, notes, statut, personnel_name, patient_social, chambre_id)
            VALUES (?,?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute([
            $type, $hospitalisation_date, $intervention_time, $private_room,
            $reason, $notes, $statut, $personnel_name, $patient_social, $chambre_id
        ]);
    }

    header("Location: manage_admissions.php");
    exit;
}
// select pour affichage
$q = $pdo->query("
    SELECT 
        admission.*,
        patient.firstname AS patient_firstname,
        patient.lastname AS patient_lastname,
        personnels.personnel_name AS staff_name,
        services.service_name,
        chambre.type_chambre
    FROM admission
    LEFT JOIN patient ON admission.patient_social = patient.social_number
    LEFT JOIN personnels ON admission.personnel_name = personnels.personnel_name
    LEFT JOIN services ON personnels.service_id = services.service_id
    LEFT JOIN chambre ON admission.chambre_id = chambre.chambre_id
");
$admissions = $q->fetchAll(PDO::FETCH_ASSOC);

$patients = $pdo->query("SELECT social_number, firstname, lastname FROM patient")->fetchAll();
$chambres = $pdo->query("SELECT chambre_id, type_chambre FROM chambre")->fetchAll();
$personnels = $pdo->query("SELECT personnel_name FROM personnels")->fetchAll();

// Modif
$editing = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $st = $pdo->prepare("SELECT * FROM admission WHERE admission_id=?");
    $st->execute([$id]);
    $editing = $st->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Gestion des Admissions</title>


    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<body>


<header>
    <h1>Gestion des Admissions</h1>
    <nav>
        <a href="../systems/logout.php">Déconnexion</a>
        <a href="dashboard_admin.php">Accueil</a>
        <a href="manage_chambre.php">Gérer les chambres</a>
        <a href="manage_couverture.php">Gérer les couvertures sociales</a>
        <a href="documents.php">Gérer les documents</a>
        <a href="manage_patients.php"> Gérer les patients</a>
        <a href="manage_personne_contact.php">Gérer les contact</a>
        <a href="manage_personnels.php">Gérer le personnels</a>
        <a href="manage_roles.php">Gérer les roles</a>
        <a href="manage_services.php">Gérer les services</a>
    </nav>
</header>
<div class="stats">
    <h2>Liste des Admissions</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Type</th>
            <th>Patient</th>
            <th>Personnel</th>
            <th>Service</th>
            <th>Chambre</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($admissions as $adm): ?>
        <tr>
            <td><?= $adm['admission_id'] ?></td>
            <td><?= $adm['admission_type'] ?></td>
            <td><?= $adm['patient_lastname']." ".$adm['patient_firstname'] ?></td>
            <td><?= $adm['staff_name'] ?></td>
            <td><?= $adm['service_name'] ?></td>
            <td><?= $adm['type_chambre'] ?></td>

            <td>
                <a class="card" style="padding:10px;" href="?edit=<?= $adm['admission_id'] ?>">Modifier</a>
                <a class="card" style="padding:10px; background:#e74c3c; color:white;"
                   href="?delete=<?= $adm['admission_id'] ?>"
                   onclick="return confirm('Supprimer cette admission ?')">
                   Supprimer
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="actions">
    <h2 id="formAdmission"><?= $editing ? "Modifier une admission" : "Ajouter une admission" ?></h2>

    <form method="POST" class="form-block">

        <input type="hidden" name="admission_id" value="<?= $editing['admission_id'] ?? "" ?>">

        <label>Type :</label>
        <input type="text" name="admission_type" required value="<?= $editing['admission_type'] ?? "" ?>">

        <label>Date d'hospitalisation :</label>
        <input type="date" name="hospitalisation_date" required value="<?= $editing['hospitalisation_date'] ?? "" ?>">

        <label>Heure d'intervention :</label>
        <input type="time" name="intervention_time" value="<?= $editing['intervention_time'] ?? "" ?>">

        <label>Chambre privée :</label>
        <input type="checkbox" name="private_room" <?= (!empty($editing['private_room']) ? "checked" : "") ?>>

        <label>Raison :</label>
        <textarea name="reason"><?= $editing['reason'] ?? "" ?></textarea>

        <label>Notes :</label>
        <textarea name="notes"><?= $editing['notes'] ?? "" ?></textarea>

        <label>Statut :</label>
        <input type="text" name="statut" value="<?= $editing['statut'] ?? "" ?>">

        <label>Personnel :</label>
        <select name="personnel_name">
            <?php foreach ($personnels as $p): ?>
                <option value="<?= $p['personnel_name'] ?>"
                    <?= ($editing && $editing['personnel_name']==$p['personnel_name']) ? "selected" : "" ?>>
                    <?= $p['personnel_name'] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Patient :</label>
        <select name="patient_social">
            <?php foreach ($patients as $pat): ?>
                <option value="<?= $pat['social_number'] ?>"
                    <?= ($editing && $editing['patient_social']==$pat['social_number']) ? "selected" : "" ?>>
                    <?= $pat['lastname']." ".$pat['firstname']." (".$pat['social_number'].")" ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Chambre :</label>
        <select name="chambre_id">
            <?php foreach ($chambres as $c): ?>
                <option value="<?= $c['chambre_id'] ?>"
                    <?= ($editing && $editing['chambre_id']==$c['chambre_id']) ? "selected" : "" ?>>
                    Chambre #<?= $c['chambre_id'] ?> (<?= $c['type_chambre'] ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="save" class="card" style="width:200px; margin:auto;">Enregistrer</button>
    </form>
</div>

</body>
</html>

