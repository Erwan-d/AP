<?php
session_start();
require_once 'systems/config.php';

if (!isset($_SESSION['personnel_id'])) {
    header('Location: ../login.php');
    exit();
}

$error = "";
$success = "";

// Couverture Social
$insurance_number = $_POST['insurance_number'] ?? '';
$insurance_name = $_POST['insurance_name'] ?? '';
$social_org = $_POST['social_org'] ?? '';

if (!empty($insurance_number) && !preg_match('/^\d{4,6}$/', $insurance_number)) {
    $error = "Le numéro d’assurance complémentaire doit contenir uniquement 4 à 6 chiffres.";
}


// Récupération patients existants
$patients = $pdo->query("SELECT social_number, lastname, firstname FROM ap_patient ORDER BY lastname")->fetchAll(PDO::FETCH_ASSOC);

// Récupération chambres
$chambres = $pdo->query("SELECT chambre_id, type_chambre, private_room FROM ap_chambre ORDER BY chambre_id")->fetchAll(PDO::FETCH_ASSOC);

// Vérifier NIR
function isValidNIR($nir) {
    $nir = str_replace(' ', '', $nir);
    if (!preg_match('/^\d{15}$/', $nir)) return false;
    $sexe = (int)$nir[0];
    if ($sexe !== 1 && $sexe !== 2) return false;
    $month = (int)substr($nir, 3, 2);
    if ($month < 1 || $month > 12) return false;
    return true;
}

// Vérifier disponibilité chambre
function isChambreAvailable($pdo, $chambre_id, $date) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ap_admission WHERE chambre_id = :chambre_id AND hospitalisation_date = :date");
    $stmt->execute([':chambre_id' => $chambre_id, ':date' => $date]);
    return $stmt->fetchColumn() == 0;
}

// ------------------------------------------------------
//  TRAITEMENT DU FORMULAIRE
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new_patient = $_POST['new_patient'] ?? 0;

    // --- Création patient si nouveau ---
    if ($new_patient == 1) {
        $social_number = trim($_POST['social_number'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $birthdate = $_POST['birthdate'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $insurance_number = trim($_POST['insurance_number'] ?? '');
        $insurance_name = trim($_POST['insurance_name'] ?? '');

        // --- Nettoyage données ---
        $social_number = preg_replace('/\D/', '', $social_number);
        $phone = preg_replace('/[^\d+]/', '', $phone);
        $insurance_number = preg_replace('/\D/', '', $insurance_number);

        // --- Vérifications ---
        if (!isValidNIR($social_number)) {
            $error = "Numéro de sécurité sociale invalide ou incohérent.";
        } elseif (!$lastname || !$firstname || !$birthdate) {
            $error = "Veuillez remplir tous les champs du patient.";
        } elseif (!empty($phone) && !preg_match('/^\+?\d{10,15}$/', $phone)) {
            $error = "Numéro de téléphone incorrect (10 à 15 chiffres, + optionnel).";
        } elseif (!empty($insurance_number) && !preg_match('/^\d{4,6}$/', $insurance_number)) {
            $error = "Le numéro d’assurance complémentaire doit contenir uniquement 4 à 6 chiffres.";
        } else {
            // Vérification doublon patient
            $check = $pdo->prepare("SELECT COUNT(*) FROM ap_patient WHERE social_number = ?");
            $check->execute([$social_number]);
            if ($check->fetchColumn() > 0) $error = "Un patient avec ce numéro existe déjà.";
        }

        // --- Insertion patient ---
        if (!$error) {
            $stmt = $pdo->prepare("INSERT INTO ap_patient (social_number, lastname, firstname, birthdate, phone)
                                   VALUES (:social, :lastname, :firstname, :birthdate, :phone)");
            $stmt->execute([
                ':social' => $social_number,
                ':lastname' => $lastname,
                ':firstname' => $firstname,
                ':birthdate' => $birthdate,
                ':phone' => $phone
            ]);
            $patient_social = $social_number;
        }

    } else {
        $patient_social = $_POST['patient_social'] ?? '';
        if (!$patient_social) $error = "Veuillez sélectionner un patient existant.";
    }

    // --- Admission ---
    if (!$error) {
        $admission_type = trim($_POST['admission_type'] ?? '');
        $hospitalisation_date = $_POST['hospitalisation_date'] ?? '';
        $intervention_time = $_POST['intervention_time'] ?? null;
        $chambre_id = $_POST['chambre_id'] ?? null;
        $private_room = isset($_POST['private_room']) ? 1 : 0;
        $reason = trim($_POST['reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $is_minor = isset($_POST['is_minor']) ? 1 : 0;

        if ($hospitalisation_date < date('Y-m-d')) {
            $error = "Date d’hospitalisation invalide.";
        } elseif ($chambre_id && !isChambreAvailable($pdo, $chambre_id, $hospitalisation_date)) {
            $error = "Chambre déjà réservée.";
        } elseif (!$admission_type || !$hospitalisation_date) {
            $error = "Veuillez remplir tous les champs d’admission.";
        } else {

            // --- Couverture sociale ---
            $social_org = $_POST['social_org'] ?? null;
            $is_assured = isset($_POST['is_assured']) ? 1 : 0;
            $ald = isset($_POST['ald']) ? 1 : 0;

            $covCheck = $pdo->prepare("SELECT COUNT(*) FROM ap_couverture_sociale WHERE social_number = ?");
            $covCheck->execute([$patient_social]);
            if ($covCheck->fetchColumn() == 0) {
                $insertCov = $pdo->prepare("INSERT INTO ap_couverture_sociale 
                    (social_number, social_org, is_assured, ald, insurance_number, insurance_name)
                    VALUES (:social, :org, :assured, :ald, :num, :name)");
                $insertCov->execute([
                    ':social' => $patient_social,
                    ':org' => $social_org,
                    ':assured' => $is_assured,
                    ':ald' => $ald,
                    ':num' => $insurance_number,
                    ':name' => $insurance_name
                ]);
            }

            // --- Personnes de contact ---
            $contacts = [
                ['type_contact' => 'confiance', 'name' => $_POST['contact_confiance_nom'] ?? null, 'firstname' => $_POST['contact_confiance_prenom'] ?? null, 'phone' => $_POST['contact_confiance_tel'] ?? null],
                ['type_contact' => 'prévenir', 'name' => $_POST['contact_prevenir_nom'] ?? null, 'firstname' => $_POST['contact_prevenir_prenom'] ?? null, 'phone' => $_POST['contact_prevenir_tel'] ?? null]
            ];
            foreach ($contacts as $contact) {
                if ($contact['name'] && $contact['phone']) {
                    $idContact = substr($patient_social, 0, 13) . rand(10, 99);
                    $pdo->prepare("INSERT INTO ap_personne_contact (social_number, type_contact, name, firstname, phone)
                                   VALUES (:social, :type, :nom, :prenom, :phone)")
                        ->execute([
                            ':social' => $idContact,
                            ':type' => $contact['type_contact'],
                            ':nom' => $contact['name'],
                            ':prenom' => $contact['firstname'],
                            ':phone' => $contact['phone']
                        ]);
                }
            }

            // --- Gestion documents PDF ---
            $allowedTypes = ['application/pdf'];
            $docs = [
                'id_card' => 0,
                'vital_card' => 0,
                'insurance_card' => 0,
                'livret_famille' => 0
            ];

            foreach ($docs as $doc => &$flag) {
                if (!empty($_FILES[$doc]['tmp_name'])) {
                    $tmp = $_FILES[$doc]['tmp_name'];
                    $type = mime_content_type($tmp);
                    if (in_array($type, $allowedTypes)) {
                        $file_data = file_get_contents($tmp);
                        $flag = 1;
                        $pdo->prepare("INSERT INTO ap_documents (social_number, doc_type, file_data, id_card, vital_card, insurance_card, livret_famille)
                                       VALUES (:social, :type, :data, :id_card, :vital, :mutuelle, :livret)")
                            ->execute([
                                ':social' => $patient_social,
                                ':type' => $doc,
                                ':data' => $file_data,
                                ':id_card' => $docs['id_card'],
                                ':vital' => $docs['vital_card'],
                                ':mutuelle' => $docs['insurance_card'],
                                ':livret' => $docs['livret_famille']
                            ]);
                    } else {
                        $error = "Le fichier fourni pour $doc doit être un PDF.";
                        break;
                    }
                }
            }

            if ($is_minor && !$docs['livret_famille']) {
                $error = "Le livret de famille est obligatoire pour un mineur.";
            }

            // --- Enregistrement admission ---
            if (!$error) {
                $stmt = $pdo->prepare("INSERT INTO ap_admission 
                    (admission_type, hospitalisation_date, intervention_time, private_room, reason, notes, statut, personnel_name, patient_social, chambre_id)
                    VALUES (:type, :date, :time, :private, :reason, :notes, 'pré-admission', :personnel, :social, :chambre)");
                $stmt->execute([
                    ':type' => $admission_type,
                    ':date' => $hospitalisation_date,
                    ':time' => $intervention_time ?: null,
                    ':private' => $private_room,
                    ':reason' => $reason,
                    ':notes' => $notes,
                    ':personnel' => $_SESSION['personnel_name'],
                    ':social' => $patient_social,
                    ':chambre' => $chambre_id ?: null
                ]);
                $success = "Pré-admission enregistrée avec succès et documents stockés !";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Pré-admission - Clinique LPF</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="pre_admission.css">
</head>
<body>

<div class="container mt-5 mb-5">
  <div class="card p-4">
    <h2 class="text-center mb-4"><i class="bi bi-hospital"></i> Pré-admission - Clinique LPF</h2>

    <div class="progress mb-4"><div id="progress-bar" class="progress-bar" style="width:0%">0%</div></div>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" id="admissionForm" enctype="multipart/form-data">
      <!-- Patient -->
      <h4 class="section-title"><i class="bi bi-person-vcard"></i> Patient</h4>
      <div class="mb-3">
        <input type="radio" name="new_patient" value="0" checked> Patient existant
        <input type="radio" name="new_patient" value="1" class="ms-3"> Nouveau patient
      </div>
      <div id="existing_patient">
        <select name="patient_social" class="form-select">
          <option value="">-- Sélectionner un patient --</option>
          <?php foreach($patients as $p): ?>
            <option value="<?= $p['social_number'] ?>"><?= htmlspecialchars($p['lastname'].' '.$p['firstname']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="new_patient_fields" style="display:none;">
        <div class="row g-3 mt-2">
          <div class="col-md-6"><input type="text" name="social_number" class="form-control" placeholder="Numéro sécurité sociale" pattern="\d{15}"></div>
          <div class="col-md-6"><input type="text" name="lastname" class="form-control" placeholder="Nom"></div>
          <div class="col-md-6"><input type="text" name="firstname" class="form-control" placeholder="Prénom"></div>
          <div class="col-md-6"><input type="date" name="birthdate" class="form-control"></div>
          <div class="col-md-6"><input type="tel" name="phone" class="form-control" placeholder="Téléphone (+336...)"></div>
          <div class="col-md-6"><label><input type="checkbox" name="is_minor" id="is_minor"> Patient mineur</label></div>
        </div>
      </div>

      <!-- Admission -->
      <h4 class="section-title"><i class="bi bi-calendar-check"></i> Admission</h4>
      <div class="row g-3">
        <div class="col-md-6"><input type="text" name="admission_type" class="form-control" placeholder="Type d'admission" required></div>
        <div class="col-md-6"><input type="date" name="hospitalisation_date" class="form-control" min="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-6"><input type="time" name="intervention_time" class="form-control"></div>
        <div class="col-md-6">
          <select name="chambre_id" class="form-select">
            <option value="">-- Pas de chambre --</option>
            <?php foreach($chambres as $c): ?>
              <option value="<?= $c['chambre_id'] ?>"><?= htmlspecialchars($c['type_chambre']) . ($c['private_room'] ? " (privée)" : "") ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6"><label><input type="checkbox" name="private_room"> Chambre privée</label></div>
        <div class="col-12"><textarea name="reason" class="form-control" rows="2" placeholder="Motif de l’admission"></textarea></div>
        <div class="col-12"><textarea name="notes" class="form-control" rows="2" placeholder="Notes complémentaires"></textarea></div>
      </div>

      <!-- Documents -->
      <h4 class="section-title"><i class="bi bi-file-earmark-pdf"></i> Documents à fournir</h4>
      <div class="row g-3">
        <div class="col-md-6"><label>Carte d’identité (PDF)</label><input type="file" name="id_card" accept="application/pdf" class="form-control"></div>
        <div class="col-md-6"><label>Carte Vitale (PDF)</label><input type="file" name="vital_card" accept="application/pdf" class="form-control"></div>
        <div class="col-md-6"><label>Carte de mutuelle (PDF)</label><input type="file" name="insurance_card" accept="application/pdf" class="form-control"></div>
        <div id="minor_fields" style="display:none;">
          <div class="col-md-6"><label>Livret de famille (si mineur)</label><input type="file" name="livret_famille" accept="application/pdf" class="form-control"></div>
        </div>
      </div>

      <!-- Couverture -->
      <h4 class="section-title"><i class="bi bi-shield-check"></i> Couverture sociale</h4>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Organisme social</label>
          <input type="text" name="social_org" class="form-control" placeholder="Ex : CPAM, RSI, MSA...">
        </div>
        <div class="col-md-6">
          <label class="form-label">Numéro d’assurance complémentaire</label>
          <input type="text" name="insurance_number" class="form-control"
                 placeholder="Numéro d’assurance complémentaire (4 à 6 chiffres)"
                 minlength="4" maxlength="6" pattern="\d{4,6}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Mutuelle / Assurance complémentaire</label>
          <input type="text" name="insurance_name" class="form-control" placeholder="Ex : Harmonie Mutuelle, MGEN, etc.">
        </div>
      </div>

      <!-- Contacts -->
      <h4 class="section-title"><i class="bi bi-telephone"></i> Contacts</h4>
      <div class="row g-3">
        <div class="col-md-6">
          <h6>Personne de confiance</h6>
          <input type="text" name="contact_confiance_nom" class="form-control mb-2" placeholder="Nom">
          <input type="text" name="contact_confiance_prenom" class="form-control mb-2" placeholder="Prénom">
          <input type="tel" name="contact_confiance_tel" class="form-control" placeholder="Téléphone">
        </div>
        <div class="col-md-6">
          <h6>Personne à prévenir</h6>
          <input type="text" name="contact_prevenir_nom" class="form-control mb-2" placeholder="Nom">
          <input type="text" name="contact_prevenir_prenom" class="form-control mb-2" placeholder="Prénom">
          <input type="tel" name="contact_prevenir_tel" class="form-control" placeholder="Téléphone">
        </div>
      </div>

      <div class="text-center mt-4">
        <button type="submit" class="btn btn-primary px-5 py-2"><i class="bi bi-check-circle"></i> Enregistrer la pré-admission</button>
      </div>
    </form>
  </div>
</div>
<script>
const form = document.getElementById('admissionForm');
const progressBar = document.getElementById('progress-bar');

form.addEventListener('input', () => {
  const inputs = form.querySelectorAll('input, select, textarea');
  let filled = 0;
  inputs.forEach(i => { if (i.value.trim() !== '' || i.checked) filled++; });
  const percent = Math.round((filled / inputs.length) * 100);
  progressBar.style.width = percent + '%';
  progressBar.innerText = percent + '%';
});

// Toggle nouveau / existant
document.querySelectorAll('input[name="new_patient"]').forEach(el => {
  el.addEventListener('change', function() {
    document.getElementById('new_patient_fields').style.display = this.value === '1' ? 'block' : 'none';
    document.getElementById('existing_patient').style.display = this.value === '0' ? 'block' : 'none';
  });
});

// Checkbox mineur et livret de famille
const birthdateInput = document.querySelector('input[name="birthdate"]');
const minorCheckbox = document.getElementById('is_minor');
const minorFields = document.getElementById('minor_fields');

function checkMinor() {
  const birthdate = birthdateInput.value;
  if (!birthdate) return;
  const birth = new Date(birthdate);
  const today = new Date();
  let age = today.getFullYear() - birth.getFullYear();
  const m = today.getMonth() - birth.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
  
  if (age < 18) {
    minorCheckbox.checked = true;
    minorFields.style.display = 'block';
  } else {
    minorCheckbox.checked = false;
    minorFields.style.display = 'none';
  }
}

birthdateInput.addEventListener('change', checkMinor);
window.addEventListener('DOMContentLoaded', checkMinor);

// Fonction pour créer ou afficher un message d’erreur sous un champ
function showError(input, message) {
  let error = input.nextElementSibling;
  if (!error || !error.classList.contains('error-msg')) {
    error = document.createElement('div');
    error.classList.add('error-msg', 'text-danger', 'small', 'mt-1');
    input.parentNode.appendChild(error);
  }
  error.textContent = message;
}

function clearError(input) {
  let error = input.nextElementSibling;
  if (error && error.classList.contains('error-msg')) {
    error.textContent = '';
  }
}

// Limiter numéro d’assurance complémentaire
const insuranceInput = document.querySelector('input[name="insurance_number"]');
if (insuranceInput) {
  insuranceInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length < 4) showError(this, "Doit contenir entre 4 et 6 chiffres.");
    else clearError(this);
  });
}

// Limiter numéro de sécurité sociale (NIR)
const nirInput = document.querySelector('input[name="social_number"]');
if (nirInput) {
  nirInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 15);
    if (this.value.length !== 15) showError(this, "Le NIR doit contenir exactement 15 chiffres.");
    else clearError(this);
  });
}

// Limiter téléphone
const phoneInputs = document.querySelectorAll('input[name="phone"], input[name="contact_confiance_tel"], input[name="contact_prevenir_tel"]');
phoneInputs.forEach(input => {
  input.addEventListener('input', function() {
    let val = this.value;
    if (val.startsWith('+')) {
      val = '+' + val.slice(1).replace(/\D/g, '');
    } else {
      val = val.replace(/\D/g, '');
    }
    this.value = val.slice(0, 15);

    // Vérification longueur minimale
    if (val.replace('+','').length < 10) showError(this, "Le numéro doit contenir au moins 10 chiffres.");
    else clearError(this);
  });
});
</script>
</body>
</html>