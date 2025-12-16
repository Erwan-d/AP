<?php
declare(strict_types=1);

require_once __DIR__ . '/dompdf/autoload.inc.php';
require_once __DIR__ . '/../systems/config.php';
$pdo = getPDOConnection();

use Dompdf\Dompdf;
use Dompdf\Options;

/* =========================
   1. Sécurité & paramètres
   ========================= */

if (!isset($_GET['admission_id']) || empty($_GET['admission_id'])) {
    die("ID de pré-admission manquant. GET: " . json_encode($_GET));
}

$admission_id = (int) $_GET['admission_id'];
if ($admission_id <= 0) {
    die("ID invalide. ID reçu: " . $_GET['admission_id']);
}


/* =========================
   2. Connexion BDD
   ========================= */

$pdo = getPDOConnection();
if (!$pdo) {
    die("Connexion base de données impossible. <a href='preadmission.php'>Retour</a>");
}

/* =========================
   3. Récupération données
   ========================= */

$stmt = $pdo->prepare("
    SELECT 
        a.admission_id,
        a.admission_type,
        a.hospitalisation_date,
        a.intervention_time,
        a.private_room,
        a.reason,
        a.notes,
        a.statut,
        a.personnel_name,
        a.patient_social,

        p.lastname,
        p.firstname,
        p.birthdate,
        p.phone,
        p.email

    FROM ap_admission a
    JOIN ap_patient p ON p.social_number = a.patient_social
    WHERE a.admission_id = ?
");
$stmt->execute([$admission_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Pré-admission introuvable.");
}

/* =========================
   4. Préparation des données
   ========================= */

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$patient_nom         = h($data['firstname'] . ' ' . $data['lastname']);
$date_naissance = !empty($data['birthdate']) ? date('d/m/Y', strtotime($data['birthdate'])) : '—';
$hospitalisation     = date('d/m/Y', strtotime($data['hospitalisation_date']));
$private_room        = $data['private_room'] ? 'Oui' : 'Non';

$telephone           = h($data['phone']);
$email               = h($data['email']);
$patient_social      = h($data['patient_social']);
$admission_type      = h($data['admission_type']);
$intervention_time   = h($data['intervention_time']);
$statut              = h($data['statut']);
$personnel           = h($data['personnel_name']);

$reason              = nl2br(h($data['reason']));
$notes               = nl2br(h($data['notes']));

$generation_date     = date('d/m/Y à H:i');

/* =========================
   5. Chemin du logo
   ========================= */

$logo_path = __DIR__ . '/../images/LPFS_logo.png';
$logo_base64 = '';

if (file_exists($logo_path)) {
    $logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
}

/* =========================
   6. Template HTML
   ========================= */

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 12px;
        color: #000;
    }

    .header {
        border-bottom: 2px solid #003366;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .header-table {
        width: 100%;
    }

    .logo {
        width: 140px;
    }

    .clinic-info {
        text-align: right;
        font-size: 11px;
    }

    h1 {
        text-align: center;
        font-size: 20px;
        margin: 20px 0;
        color: #003366;
    }

    .section {
        border: 1px solid #000;
        padding: 10px;
        margin-bottom: 15px;
    }

    .section-title {
        font-weight: bold;
        text-decoration: underline;
        margin-bottom: 6px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    td {
        padding: 6px;
        vertical-align: top;
    }

    .label {
        width: 35%;
        font-weight: bold;
    }

    .footer {
        position: fixed;
        bottom: 20px;
        width: 100%;
        text-align: center;
        font-size: 10px;
        color: #555;
    }
</style>
</head>

<body>

<div class="header">
    <table class="header-table">
        <tr>
            <td>
                <img src="{$logo_base64}" class="logo" alt="Logo clinique">
            </td>
            <td class="clinic-info">
                <strong>LPFS – Clinique</strong><br>
                Service des admissions<br>
                Document officiel
            </td>
        </tr>
    </table>
</div>

<h1>FICHE DE PRÉ-ADMISSION</h1>

<div class="section">
    <div class="section-title">Informations du patient</div>
    <table>
        <tr><td class="label">Nom / Prénom</td><td>{$patient_nom}</td></tr>
        <tr><td class="label">Date de naissance</td><td>{$date_naissance}</td></tr>
        <tr><td class="label">Téléphone</td><td>{$telephone}</td></tr>
        <tr><td class="label">Email</td><td>{$email}</td></tr>
        <tr><td class="label">N° Sécurité sociale</td><td>{$patient_social}</td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Détails de la pré-admission</div>
    <table>
        <tr><td class="label">Type d’admission</td><td>{$admission_type}</td></tr>
        <tr><td class="label">Date d’hospitalisation</td><td>{$hospitalisation}</td></tr>
        <tr><td class="label">Heure d’intervention</td><td>{$intervention_time}</td></tr>
        <tr><td class="label">Chambre privée</td><td>{$private_room}</td></tr>
        <tr><td class="label">Statut</td><td>{$statut}</td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Motif d’hospitalisation</div>
    <p>{$reason}</p>
</div>

<div class="section">
    <div class="section-title">Notes complémentaires</div>
    <p>{$notes}</p>
</div>

<div class="section">
    <div class="section-title">Personnel référent</div>
    <p>{$personnel}</p>
</div>

<div class="footer">
    Document généré le {$generation_date} — Pré-admission n° {$admission_id}
</div>

</body>
</html>
HTML;

/* =========================
   7. Génération PDF
   ========================= */

   $options = new Options();
   $options->set('defaultFont', 'DejaVu Sans');
   $options->set('isRemoteEnabled', false);
   
   $dompdf = new Dompdf($options);
   $dompdf->loadHtml($html);
   $dompdf->setPaper('A4', 'portrait');
   $dompdf->render();
   
   /* =========================
      8. Téléchargement sécurisé
      ========================= */
   
   // Nettoyer le buffer pour éviter les sorties accidentelles
   if (ob_get_length()) {
       ob_end_clean();
   }
   
   // Envoi du PDF au navigateur
   $dompdf->stream(
       "preadmission_{$admission_id}.pdf",
       ['Attachment' => true] // true = téléchargement, false = affichage direct
   );
   
   exit;
   