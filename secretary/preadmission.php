<?php
session_start();
require_once '../systems/config.php';

if (!isset($_SESSION['personnel_id'])) {
    header('Location: ../login.php');
    exit();
}

$error = "";
$success = "";
$admission_id = null;

// ------------------------------
// Fonctions utilitaires
// ------------------------------
function isValidNIR($nir)
{
    $nir = str_replace(' ', '', $nir);
    return preg_match('/^[12]\d{2}(0[1-9]|1[0-2])\d{10}$/', $nir);
}

function isChambreAvailable($pdo, $chambre_id, $date)
{
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ap_admission WHERE chambre_id = :id AND hospitalisation_date = :date");
        $stmt->execute([':id' => $chambre_id, ':date' => $date]);
        return $stmt->fetchColumn() == 0;
    } catch (PDOException $e) {
        error_log("Erreur vérification chambre: " . $e->getMessage());
        return false;
    }
}

// ------------------------------
// Récupération données
// ------------------------------
$patients = [];
$chambres = [];

try {
    $pdo = getPDOConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT social_number, lastname, firstname FROM ap_patient ORDER BY lastname");
        $stmt->execute();
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT chambre_id, type_chambre, private_room FROM ap_chambre ORDER BY chambre_id");
        $stmt->execute();
        $chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "Impossible de se connecter à la base de données";
    }
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}

// ------------------------------
// Traitement formulaire
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $patient_social = null;
    $admission_type = null;
    $hospitalisation_date = null;
    $intervention_time = null;
    $chambre_id = null;
    $reason = null;
    $notes = null;
    $private_room = 0;

    $new_patient = $_POST['new_patient'] ?? '0';

    // ---------------- New patient ----------------
    if ($new_patient === '1') {
        $social_number = preg_replace('/\D/', '', $_POST['social_number'] ?? '');
        $lastname      = trim($_POST['lastname'] ?? '');
        $firstname     = trim($_POST['firstname'] ?? '');
        $mariedname    = trim($_POST['mariedname'] ?? '');
        $civ           = $_POST['civ'] ?? '';
        $birthdate     = $_POST['birthdate'] ?? '';
        $phone         = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $is_minor      = isset($_POST['is_minor']) ? 1 : 0;
        $email         = trim($_POST['email'] ?? '');
        $number_street = trim($_POST['number_street'] ?? '');
        $street        = trim($_POST['street'] ?? '');
        $zip           = trim($_POST['zip'] ?? '');
        $city          = trim($_POST['city'] ?? '');

        // Validations
        if (!isValidNIR($social_number)) {
            $error = "Numéro de sécurité sociale invalide.";
        } elseif (!$lastname || !$firstname || !$birthdate) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif (!preg_match('/^\d{10}$/', $phone)) {
            $error = "Le numéro de téléphone doit comporter 10 chiffres.";
        } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Adresse email invalide.";
        } elseif ($zip && !preg_match('/^\d{5}$/', $zip)) {
            $error = "Le code postal doit comporter 5 chiffres.";
        } elseif ($city && !preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\s\-]+$/', $city)) {
            $error = "La ville est invalide.";
        } else {
            try {
                $pdo = getPDOConnection();
                if ($pdo) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM ap_patient WHERE social_number = ?");
                    $check->execute([$social_number]);
                    if ($check->fetchColumn() > 0) {
                        $error = "Un patient avec ce numéro existe déjà.";
                    }
                } else {
                    $error = "Erreur de connexion à la base de données";
                }
            } catch (PDOException $e) {
                $error = "Erreur de vérification du patient: " . $e->getMessage();
            }
        }

        // Insertion
        if (!$error) {
            try {
                $pdo = getPDOConnection();
                if ($pdo) {
                    $stmt = $pdo->prepare("
                        INSERT INTO ap_patient 
                        (social_number, civ, lastname, firstname, mariedname, birthdate, phone, email, number_street, street, zip, city, is_minor)
                        VALUES (:social, :civ, :ln, :fn, :mariedname, :birth, :phone, :email, :sn, :street, :zip, :city, :minor)
                    ");
                    
                    $result = $stmt->execute([
                        ':social'  => $social_number,
                        ':civ'     => $civ,
                        ':ln'      => $lastname,
                        ':fn'      => $firstname,
                        ':mariedname' => $mariedname,
                        ':birth'   => $birthdate,
                        ':phone'   => $phone,
                        ':email'   => $email,
                        ':sn'      => $number_street,
                        ':street'  => $street,
                        ':zip'     => $zip,
                        ':city'    => $city,
                        ':minor'   => $is_minor
                    ]);
                    
                    if ($result) {
                        $patient_social = $social_number;
                    } else {
                        $error = "Erreur lors de l'insertion du patient.";
                    }
                } else {
                    $error = "Erreur de connexion à la base de données";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de la création du patient: " . $e->getMessage();
            }
        }

    } else {
        // ---------------- Existing patient ----------------
        $patient_social = $_POST['patient_social'] ?? '';
        if (!$patient_social) {
            $error = "Veuillez sélectionner un patient.";
        } else {
            try {
                $pdo = getPDOConnection();
                if ($pdo) {
                    $stmt = $pdo->prepare("SELECT phone, birthdate, is_minor FROM ap_patient WHERE social_number = ?");
                    $stmt->execute([$patient_social]);
                    $existing_patient = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$existing_patient) {
                        $error = "Patient sélectionné introuvable.";
                    } else {
                        $phone = $existing_patient['phone'] ?? '';
                        $birthdate = $existing_patient['birthdate'] ?? '';
                        $is_minor = $existing_patient['is_minor'] ?? 0;
                    }
                } else {
                    $error = "Erreur de connexion à la base de données";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de la récupération du patient: " . $e->getMessage();
            }
        }
    }

    // ---------------- Admission ----------------
    if (!$error && $patient_social) {
        $admission_type        = trim($_POST['admission_type'] ?? '');
        $hospitalisation_date  = $_POST['hospitalisation_date'] ?? '';
        $intervention_time     = $_POST['intervention_time'] ?? null;
        $chambre_id            = $_POST['chambre_id'] ?? null;
        $reason                = trim($_POST['reason'] ?? '');
        $notes                 = trim($_POST['notes'] ?? '');
        $private_room          = isset($_POST['private_room']) ? 1 : 0;

        if (!$admission_type || !$hospitalisation_date) {
            $error = "Veuillez remplir le type et la date d'hospitalisation.";
        } elseif ($hospitalisation_date < date('Y-m-d')) {
            $error = "Date d'hospitalisation invalide.";
        } elseif ($chambre_id) {
            try {
                $pdo = getPDOConnection();
                if ($pdo) {
                    if (!isChambreAvailable($pdo, $chambre_id, $hospitalisation_date)) {
                        $error = "Chambre déjà réservée.";
                    }
                } else {
                    $error = "Erreur de connexion à la base de données";
                }
            } catch (PDOException $e) {
                $error = "Erreur de vérification de la chambre: " . $e->getMessage();
            }
        }

        // Vérification chambre privée
        if (!$error && $chambre_id && $private_room) {
            $c = array_filter($chambres, function($x) use ($chambre_id) {
                return $x['chambre_id'] == $chambre_id;
            });
            $c = array_values($c)[0] ?? null;
            if ($c && !$c['private_room']) {
                $error = "Cette chambre n'est pas une chambre privée.";
            }
        }
    }

    // ---------------- Couverture sociale ----------------
    if (!$error && $patient_social) {
        $social_org       = trim($_POST['social_org'] ?? '');
        $is_assured       = isset($_POST['is_assured']) ? 1 : 0;
        $ald              = isset($_POST['ald']) ? 1 : 0;
        $insurance_number = preg_replace('/\D/', '', $_POST['insurance_number'] ?? '');
        $insurance_name   = trim($_POST['insurance_name'] ?? '');
        if ($insurance_name === 'Autre') {
            $insurance_name = trim($_POST['insurance_name_other'] ?? '');
        }

        if (!$error && ($social_org || $insurance_number || $insurance_name)) {
            try {
                $pdo = getPDOConnection();
                if ($pdo) {
                    $covCheck = $pdo->prepare("SELECT COUNT(*) FROM ap_couverture_sociale WHERE social_number = ?");
                    $covCheck->execute([$patient_social]);

                    if ($covCheck->fetchColumn() == 0) {
                        $insertCov = $pdo->prepare("
                            INSERT INTO ap_couverture_sociale
                            (social_number, social_org, is_assured, ald, insurance_number, insurance_name)
                            VALUES (:s, :org, :ass, :ald, :num, :name)
                        ");
                        $insertCov->execute([
                            ':s'    => $patient_social,
                            ':org'  => $social_org,
                            ':ass'  => $is_assured,
                            ':ald'  => $ald,
                            ':num'  => $insurance_number ?: null,
                            ':name' => $insurance_name ?: null
                        ]);
                    }
                }
            } catch (PDOException $e) {
                error_log("Erreur couverture sociale: " . $e->getMessage());
            }
        }
    }

    // ---------------- Contacts ----------------
    if (!$error && $patient_social) {
        try {
            $pdo = getPDOConnection();
            if ($pdo) {
                // Personne de confiance
                $contact_confiance_nom = trim($_POST['contact_confiance_nom'] ?? '');
                $contact_confiance_prenom = trim($_POST['contact_confiance_prenom'] ?? '');
                $contact_confiance_address = trim($_POST['contact_confiance_address'] ?? '');
                $contact_confiance_tel = trim($_POST['contact_confiance_tel'] ?? '');
                
                if ($contact_confiance_nom || $contact_confiance_tel) {
                    $checkConfiance = $pdo->prepare("SELECT COUNT(*) FROM ap_personne_confiance WHERE social_number = ?");
                    $checkConfiance->execute([$patient_social]);
                    
                    if ($checkConfiance->fetchColumn() == 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO ap_personne_confiance
                            (social_number, nom, prenom, adresse, telephone)
                            VALUES (:s, :n, :p, :a, :t)
                        ");
                        $stmt->execute([
                            ':s' => $patient_social,
                            ':n' => $contact_confiance_nom,
                            ':p' => $contact_confiance_prenom,
                            ':a' => $contact_confiance_address,
                            ':t' => $contact_confiance_tel
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE ap_personne_confiance 
                            SET nom = :n, prenom = :p, adresse = :a, telephone = :t 
                            WHERE social_number = :s
                        ");
                        $stmt->execute([
                            ':s' => $patient_social,
                            ':n' => $contact_confiance_nom,
                            ':p' => $contact_confiance_prenom,
                            ':a' => $contact_confiance_address,
                            ':t' => $contact_confiance_tel
                        ]);
                    }
                }

                // Personne à prévenir
                $contact_prevenir_nom = trim($_POST['contact_prevenir_nom'] ?? '');
                $contact_prevenir_prenom = trim($_POST['contact_prevenir_prenom'] ?? '');
                $contact_prevenir_address = trim($_POST['contact_prevenir_address'] ?? '');
                $contact_prevenir_tel = trim($_POST['contact_prevenir_tel'] ?? '');
                
                if ($contact_prevenir_nom || $contact_prevenir_tel) {
                    $checkPrevenir = $pdo->prepare("SELECT COUNT(*) FROM ap_personne_prevenir WHERE social_number = ?");
                    $checkPrevenir->execute([$patient_social]);
                    
                    if ($checkPrevenir->fetchColumn() == 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO ap_personne_prevenir
                            (social_number, nom, prenom, adresse, telephone)
                            VALUES (:s, :n, :p, :a, :t)
                        ");
                        $stmt->execute([
                            ':s' => $patient_social,
                            ':n' => $contact_prevenir_nom,
                            ':p' => $contact_prevenir_prenom,
                            ':a' => $contact_prevenir_address,
                            ':t' => $contact_prevenir_tel
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE ap_personne_prevenir 
                            SET nom = :n, prenom = :p, adresse = :a, telephone = :t 
                            WHERE social_number = :s
                        ");
                        $stmt->execute([
                            ':s' => $patient_social,
                            ':n' => $contact_prevenir_nom,
                            ':p' => $contact_prevenir_prenom,
                            ':a' => $contact_prevenir_address,
                            ':t' => $contact_prevenir_tel
                        ]);
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur contacts: " . $e->getMessage());
        }
    }

    // ---------------- Documents ----------------
    if (!$error && $patient_social) {
        $allowedTypes = ['application/pdf'];
        $docs = [
            'id_card' => 'Carte d\'identité',
            'vital_card' => 'Carte Vitale',
            'insurance_card' => 'Carte de mutuelle'
        ];
        
        if ($is_minor ?? 0) {
            $docs['livret_famille'] = 'Livret de famille';
        }
        
        foreach ($docs as $doc => $doc_name) {
            if (!empty($_FILES[$doc]['tmp_name']) && is_uploaded_file($_FILES[$doc]['tmp_name'])) {
                try {
                    $file_tmp = $_FILES[$doc]['tmp_name'];
                    $file_size = $_FILES[$doc]['size'];
                    $filename = $_FILES[$doc]['name'];
                    
                    if ($file_size > 5 * 1024 * 1024) {
                        $error = "Le fichier $doc_name dépasse 5 Mo.";
                        break;
                    }
                    
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $file_tmp);
                    finfo_close($finfo);
                    
                    if ($mime_type !== 'application/pdf') {
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        if ($ext !== 'pdf') {
                            $error = "Le fichier $doc_name doit être un PDF.";
                            break;
                        }
                    }
                    
                    $file_data = file_get_contents($file_tmp);
                    if ($file_data === false) {
                        $error = "Erreur de lecture du fichier $doc_name.";
                        break;
                    }
                    
                    $pdo = getPDOConnection();
                    if ($pdo) {
                        $stmt = $pdo->prepare("INSERT INTO ap_documents (social_number, doc_type, file_name, file_size, file_type, file_data) 
                                              VALUES (:s, :t, :fn, :fs, :ft, :d)");
                        $stmt->execute([
                            ':s' => $patient_social,
                            ':t' => $doc,
                            ':fn' => $filename,
                            ':fs' => $file_size,
                            ':ft' => $mime_type,
                            ':d' => $file_data
                        ]);
                        
                        $file_data = null;
                        unset($file_data);
                    }
                    
                } catch (PDOException $e) {
                    error_log("Erreur document $doc: " . $e->getMessage());
                } catch (Exception $e) {
                    error_log("Erreur générale document $doc: " . $e->getMessage());
                }
            }
        }
    }

    // ---------------- Insertion admission ----------------
    if (!$error && $patient_social && $admission_type && $hospitalisation_date) {
        try {
            $pdo = getPDOConnection();
            if (!$pdo) {
                throw new Exception("Connexion à la base de données perdue");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO ap_admission
                (admission_type, hospitalisation_date, intervention_time, private_room, reason, notes, statut, personnel_name, patient_social, chambre_id)
                VALUES (:t, :hd, :it, :pr, :r, :n, 'pré-admission', :p, :s, :c)
            ");
            
            $result = $stmt->execute([
                ':t' => $admission_type,
                ':hd' => $hospitalisation_date,
                ':it' => $intervention_time,
                ':pr' => $private_room,
                ':r' => $reason,
                ':n' => $notes,
                ':p' => $_SESSION['personnel_name'] ?? 'Système',
                ':s' => $patient_social,
                ':c' => $chambre_id
            ]);
            
            if ($result) {
                $admission_id = $pdo->lastInsertId();
                $success = "Pré-admission enregistrée avec succès !";

                try {
                    if (isset($_SESSION['personnel_id'])) {
                        $pdo->prepare("INSERT INTO ap_logs (personnel_id, action, timestamp) VALUES (?, ?, NOW())")
                            ->execute([$_SESSION['personnel_id'], "Pré-admission du patient $patient_social (ID: $admission_id)"]);
                    }
                } catch (PDOException $e) {
                    error_log("Erreur log: " . $e->getMessage());
                }
            } else {
                $error = "Erreur lors de l'insertion de l'admission.";
            }
                
        } catch (PDOException $e) {
            $error_code = $e->getCode();
            $error_message = $e->getMessage();
            
            if (strpos($error_message, 'MySQL server has gone away') !== false || 
                strpos($error_message, '2006') !== false ||
                $error_code == 2006) {
                
                try {
                    $pdo = getNewPDOConnection();
                    if ($pdo) {
                        $stmt = $pdo->prepare("
                            INSERT INTO ap_admission
                            (admission_type, hospitalisation_date, intervention_time, private_room, reason, notes, statut, personnel_name, patient_social, chambre_id)
                            VALUES (:t, :hd, :it, :pr, :r, :n, 'pré-admission', :p, :s, :c)
                        ");
                        
                        $result = $stmt->execute([
                            ':t' => $admission_type,
                            ':hd' => $hospitalisation_date,
                            ':it' => $intervention_time,
                            ':pr' => $private_room,
                            ':r' => $reason,
                            ':n' => $notes,
                            ':p' => $_SESSION['personnel_name'] ?? "Système",
                            ':s' => $patient_social,
                            ':c' => $chambre_id
                        ]);
                        
                        if ($result) {
                            $admission_id = $pdo->lastInsertId();
                            $success = "Pré-admission enregistrée avec succès (après reconnexion) !";
                        } else {
                            $error = "Erreur lors de l'insertion de l'admission après reconnexion.";
                        }
                    } else {
                        $error = "La connexion à la base de données a expiré. Veuillez réessayer.";
                    }
                } catch (Exception $retry_exception) {
                    $error = "Erreur après tentative de reconnexion: " . $retry_exception->getMessage();
                }
            } elseif (strpos($error_message, 'prepare() on null') !== false) {
                $error = "Erreur de connexion à la base de données. La connexion a été perdue.";
            } else {
                $error = "Erreur lors de l'enregistrement de l'admission: " . $error_message;
            }
        } catch (Exception $e) {
            $error = "Erreur générale: " . $e->getMessage();
        }
    }
}

// Vérifier à nouveau les données pour l'affichage du formulaire
if (empty($patients) && empty($error)) {
    try {
        $pdo = getPDOConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT social_number, lastname, firstname FROM ap_patient ORDER BY lastname");
            $stmt->execute();
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT chambre_id, type_chambre, private_room FROM ap_chambre ORDER BY chambre_id");
            $stmt->execute();
            $chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        if (empty($error)) {
            $error = "Erreur lors du chargement des données: " . $e->getMessage();
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
<style>
    .form-step {
        display: none;
        animation: fadeIn 0.5s ease;
    }
    
    .form-step.active {
        display: block;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .step-indicator {
        display: flex;
        justify-content: space-between;
        margin: 2rem 0;
        position: relative;
    }
    
    .step-indicator::before {
        content: '';
        position: absolute;
        top: 15px;
        left: 0;
        right: 0;
        height: 2px;
        background-color: #e9ecef;
        z-index: 1;
    }
    
    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        z-index: 2;
    }
    
    .step-number {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background-color: #e9ecef;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        margin-bottom: 0.5rem;
        border: 3px solid white;
        transition: all 0.3s ease;
    }
    
    .step.active .step-number {
        background-color: #007bff;
        color: white;
        transform: scale(1.1);
    }
    
    .step.completed .step-number {
        background-color: #28a745;
        color: white;
    }
    
    .step-label {
        font-size: 0.85rem;
        color: #6c757d;
        text-align: center;
        max-width: 80px;
    }
    
    .step.active .step-label {
        color: #007bff;
        font-weight: 600;
    }
    
    .form-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e9ecef;
    }
    
    .required::after {
        content: " *";
        color: #dc3545;
    }
    
    .form-group-enhanced {
        margin-bottom: 1.5rem;
    }
    
    .form-group-enhanced label {
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: #495057;
    }
    
    .info-badge {
        background-color: #e7f1ff;
        border-left: 4px solid #007bff;
        padding: 0.75rem;
        border-radius: 0.25rem;
        margin-bottom: 1.5rem;
    }
    
    .document-upload {
        border: 2px dashed #dee2e6;
        border-radius: 0.5rem;
        padding: 1.5rem;
        text-align: center;
        background-color: #f8f9fa;
        transition: all 0.3s ease;
    }
    
    .document-upload:hover {
        border-color: #007bff;
        background-color: #f0f8ff;
    }
    
    .contact-card {
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1.5rem;
        border: 1px solid #e9ecef;
    }
    
    .field-error {
        border-color: #dc3545 !important;
    }
    
    .error-message {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        display: none;
    }
</style>
</head>
<body>

<div class="container mt-4 mb-5">
    <div class="card p-4">
        <div class="text-center mb-4">
            <h2 class="text-primary"><i class="bi bi-hospital"></i> Formulaire de Pré-admission</h2>
            <p class="text-muted">Clinique LPF - Remplissez le formulaire étape par étape</p>
        </div>
        
        <div class="step-indicator">
            <div class="step active" data-step="1">
                <div class="step-number">1</div>
                <span class="step-label">Patient</span>
            </div>
            <div class="step" data-step="2">
                <div class="step-number">2</div>
                <span class="step-label">Admission</span>
            </div>
            <div class="step" data-step="3">
                <div class="step-number">3</div>
                <span class="step-label">Documents</span>
            </div>
            <div class="step" data-step="4">
                <div class="step-number">4</div>
                <span class="step-label">Assurance</span>
            </div>
            <div class="step" data-step="5">
                <div class="step-number">5</div>
                <span class="step-label">Contacts</span>
            </div>
        </div>
        
        <div class="progress mb-4" style="height: 8px;">
            <div id="progress-bar" class="progress-bar" style="width: 20%; transition: width 0.5s ease;"></div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($patients) && empty($error)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Aucun patient trouvé dans la base de données.
            </div>
        <?php endif; ?>
        
        <form method="POST" id="admissionForm" enctype="multipart/form-data" onsubmit="return validateForm()">
            <div class="form-step active" id="step1">
                <div class="info-badge">
                    <i class="bi bi-info-circle me-2"></i>
                    Sélectionnez un patient existant ou créez un nouveau profil
                </div>
                
                <h4 class="section-title mb-4">
                    <i class="bi bi-person-vcard"></i> Informations Patient
                </h4>
                
                <div class="mb-4">
                    <label class="form-label fw-bold required">Type de patient</label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="new_patient" value="0" id="existingPatient" checked>
                            <label class="form-check-label" for="existingPatient">
                                <i class="bi bi-person-check"></i> Patient existant
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="new_patient" value="1" id="newPatient">
                            <label class="form-check-label" for="newPatient">
                                <i class="bi bi-person-plus"></i> Nouveau patient
                            </label>
                        </div>
                    </div>
                </div>
                
                <div id="existing_patient_section">
                    <div class="form-group-enhanced">
                        <label class="form-label required">Sélectionner un patient</label>
                        <select name="patient_social" class="form-select" id="patientSelect">
                            <option value="">-- Rechercher un patient --</option>
                            <?php foreach($patients as $p): ?>
                                <option value="<?= htmlspecialchars($p['social_number']) ?>">
                                    <?= htmlspecialchars($p['lastname'] . ' ' . $p['firstname'] . ' (' . $p['social_number'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-message" id="patientSelectError">Veuillez sélectionner un patient</div>
                    </div>
                </div>
                
                <div id="new_patient_fields" style="display:none;">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label required">Civilité</label>
                            <select name="civ" class="form-select">
                                <option value="">Choisir...</option>
                                <option value="M">M.</option>
                                <option value="Mme">Mme</option>
                                <option value="Mlle">Mlle</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label required">Numéro de sécurité sociale</label>
                            <input type="text" name="social_number" class="form-control" placeholder="1 05 01 94 068 055 34">
                            <small class="text-muted">Format : 1 05 01 94 068 055 34</small>
                            <div class="error-message" id="nirError">Numéro de sécurité sociale invalide</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Date de naissance</label>
                            <input type="date" name="birthdate" class="form-control" max="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label required">Nom</label>
                            <input type="text" name="lastname" class="form-control" placeholder="Dupont">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Prénom</label>
                            <input type="text" name="firstname" class="form-control" placeholder="Jean">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Nom marital</label>
                            <input type="text" name="mariedname" class="form-control" placeholder="Le cas échéant">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label required">Téléphone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="06 12 34 56 78">
                            <div class="error-message" id="phoneError">Format invalide (10 chiffres)</div>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="jean.dupont@email.com">
                            <div class="error-message" id="emailError">Email invalide</div>
                        </div>
                        
                        <div class="col-md-12">
                            <h6 class="mt-3 mb-2"><i class="bi bi-geo-alt"></i> Adresse</h6>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">N°</label>
                            <input type="text" name="number_street" class="form-control" placeholder="12">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">Rue</label>
                            <input type="text" name="street" class="form-control" placeholder="Avenue des Champs-Élysées">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Code postal</label>
                            <input type="text" name="zip" class="form-control" placeholder="75008">
                            <div class="error-message" id="zipError">Code postal invalide</div>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Ville</label>
                            <input type="text" name="city" class="form-control" placeholder="Paris">
                        </div>
                        
                        <div class="col-md-12 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_minor" id="is_minor">
                                <label class="form-check-label" for="is_minor">
                                    <i class="bi bi-person-fill-exclamation"></i> Patient mineur (moins de 18 ans)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-step" id="step2">
                <h4 class="section-title mb-4">
                    <i class="bi bi-calendar-check"></i> Informations d'Admission
                </h4>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Type d'admission</label>
                        <select name="admission_type" class="form-select">
                            <option value="">Sélectionner...</option>
                            <option value="Programmée">Programmée</option>
                            <option value="Urgente">Urgente</option>
                            <option value="Chirurgie ambulatoire">Chirurgie ambulatoire</option>
                            <option value="Maternité">Maternité</option>
                            <option value="Observation">Observation</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label required">Date d'hospitalisation</label>
                        <input type="date" name="hospitalisation_date" class="form-control" min="<?= date('Y-m-d') ?>">
                        <small class="text-muted">À partir d'aujourd'hui</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Heure d'intervention</label>
                        <input type="time" name="intervention_time" class="form-control">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Chambre</label>
                        <select name="chambre_id" class="form-select" id="chambreSelect">
                            <option value="">-- Sans chambre assignée --</option>
                            <?php foreach($chambres as $c): ?>
                                <option value="<?= htmlspecialchars($c['chambre_id']) ?>" data-private="<?= $c['private_room'] ?>">
                                    Chambre <?= htmlspecialchars($c['chambre_id']) ?> - <?= htmlspecialchars($c['type_chambre']) ?>
                                    <?= $c['private_room'] ? ' <span class="badge bg-info">Privée</span>' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-12 mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="private_room" id="private_room">
                            <label class="form-check-label" for="private_room">
                                <i class="bi bi-shield-check"></i> Demande de chambre privée
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label required">Motif de l'admission</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Décrivez le motif de l'hospitalisation..."></textarea>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Notes complémentaires</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Informations supplémentaires..."></textarea>
                    </div>
                </div>
            </div>
            
            <div class="form-step" id="step3">
                <div class="info-badge">
                    <i class="bi bi-info-circle me-2"></i>
                    Tous les documents doivent être au format PDF (max 5 Mo chacun)
                </div>
                
                <h4 class="section-title mb-4">
                    <i class="bi bi-file-earmark-pdf"></i> Documents Obligatoires
                </h4>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="document-upload">
                            <i class="bi bi-credit-card-2-front fs-1 text-primary mb-3"></i>
                            <h6>Carte d'identité</h6>
                            <p class="text-muted small">Recto et verso si possible</p>
                            <input type="file" name="id_card" accept=".pdf,application/pdf" class="form-control">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="document-upload">
                            <i class="bi bi-heart-pulse fs-1 text-danger mb-3"></i>
                            <h6>Carte Vitale</h6>
                            <p class="text-muted small">À jour avec photo</p>
                            <input type="file" name="vital_card" accept=".pdf,application/pdf" class="form-control">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="document-upload">
                            <i class="bi bi-shield-check fs-1 text-success mb-3"></i>
                            <h6>Carte de mutuelle</h6>
                            <p class="text-muted small">Recto et verso</p>
                            <input type="file" name="insurance_card" accept=".pdf,application/pdf" class="form-control">
                        </div>
                    </div>
                    
                    <div id="minor_fields" style="display: none;" class="col-md-6">
                        <div class="document-upload">
                            <i class="bi bi-people fs-1 text-warning mb-3"></i>
                            <h6>Livret de famille</h6>
                            <p class="text-muted small">Uniquement pour les mineurs</p>
                            <input type="file" name="livret_famille" accept=".pdf,application/pdf" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Note :</strong> Les documents sont facultatifs pour la pré-admission.
                </div>
            </div>
            
            <div class="form-step" id="step4">
                <h4 class="section-title mb-4">
                    <i class="bi bi-shield-check"></i> Couverture Sociale
                </h4>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">Organisme social</label>
                        <select name="social_org" class="form-select">
                            <option value="">Sélectionner...</option>
                            <option value="CPAM">CPAM - Caisse Primaire d'Assurance Maladie</option>
                            <option value="MSA">MSA - Mutualité Sociale Agricole</option>
                            <option value="RSI">RSI - Régime Social des Indépendants</option>
                            <option value="Autre">Autre organisme</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Numéro d'assurance complémentaire</label>
                        <input type="text" name="insurance_number" class="form-control" placeholder="Ex: 123456">
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label">Mutuelle / Assurance</label>
                        <select id="insurance_name" name="insurance_name" class="form-select">
                            <option value="">-- Choisir une mutuelle --</option>
                            <option value="Harmonie Mutuelle">Harmonie Mutuelle</option>
                            <option value="MGEN">MGEN</option>
                            <option value="AG2R La Mondiale">AG2R La Mondiale</option>
                            <option value="Malakoff Humanis">Malakoff Humanis</option>
                            <option value="Autre">Autre...</option>
                        </select>
                        <input type="text" id="insurance_name_other" name="insurance_name_other" 
                               placeholder="Précisez le nom de votre mutuelle" style="display:none;" class="form-control mt-2">
                    </div>
                    
                    <div class="col-md-12 mt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_assured" id="is_assured" checked>
                            <label class="form-check-label" for="is_assured">
                                Patient assuré social
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ald" id="ald">
                            <label class="form-check-label" for="ald">
                                Affection de Longue Durée (ALD)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-step" id="step5">
                <h4 class="section-title mb-4">
                    <i class="bi bi-telephone"></i> Personnes à Contacter
                </h4>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="contact-card">
                            <h6 class="text-primary mb-3">
                                <i class="bi bi-person-heart"></i> Personne de confiance
                            </h6>
                            <div class="mb-3">
                                <label class="form-label">Nom</label>
                                <input type="text" name="contact_confiance_nom" class="form-control" placeholder="Nom">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Prénom</label>
                                <input type="text" name="contact_confiance_prenom" class="form-control" placeholder="Prénom">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Adresse</label>
                                <input type="text" name="contact_confiance_address" class="form-control" placeholder="Adresse">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" name="contact_confiance_tel" class="form-control" placeholder="06 12 34 56 78">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="contact-card">
                            <h6 class="text-primary mb-3">
                                <i class="bi bi-bell"></i> Personne à prévenir (urgence)
                            </h6>
                            <div class="mb-3">
                                <label class="form-label">Nom</label>
                                <input type="text" name="contact_prevenir_nom" class="form-control" placeholder="Nom">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Prénom</label>
                                <input type="text" name="contact_prevenir_prenom" class="form-control" placeholder="Prénom">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Adresse</label>
                                <input type="text" name="contact_prevenir_address" class="form-control" placeholder="Adresse">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" name="contact_prevenir_tel" class="form-control" placeholder="06 12 34 56 78">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-5">
                    <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
                        <i class="bi bi-check-circle me-2"></i> Finaliser la Pré-admission
                    </button>
                    <p class="text-muted mt-2 small">
                        <i class="bi bi-exclamation-triangle"></i>
                        Vérifiez toutes les informations avant soumission
                    </p>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" id="prevBtn" class="btn btn-outline-secondary" disabled>
                    <i class="bi bi-chevron-left me-1"></i> Précédent
                </button>
                
                <div>
                    <span id="stepIndicator" class="me-3">Étape 1 sur 5</span>
                    <button type="button" id="nextBtn" class="btn btn-primary">
                        Suivant <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
            
            <?php if(!empty($admission_id)): ?>
            <div class="alert alert-success text-center mt-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                Pré-admission enregistrée avec succès (ID: <?= htmlspecialchars($admission_id) ?>)
                <div class="mt-2">
                    <a href="generate_pdf.php?admission_id=<?= htmlspecialchars($admission_id) ?>" class="btn btn-danger" target="_blank">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Générer la fiche PDF
                    </a>
                    <a href="preadmission.php" class="btn btn-primary ms-2">
                        <i class="bi bi-plus-circle me-1"></i> Nouvelle pré-admission
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="pre_admission.js"></script>
<script>
function validateForm() {
    var submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Traitement en cours...';
    }
    
    var fileInputs = document.querySelectorAll('input[type="file"]');
    var hasLargeFiles = false;
    
    fileInputs.forEach(function(input) {
        if (input.files.length > 0) {
            var file = input.files[0];
            if (file.size > 2 * 1024 * 1024) {
                hasLargeFiles = true;
            }
        }
    });
    
    if (hasLargeFiles) {
        return confirm("Certains fichiers sont volumineux. Le traitement peut prendre quelques secondes. Voulez-vous continuer?");
    }
    
    return true;
}
</script>
</body>

<?php
session_start();
require_once '../systems/config.php';

if (!isset($_SESSION['personnel_id'])) {
    header('Location: ../login.php');
    exit();
}

$error = "";
$success = "";

//////////////////////////////////////////////
//             UTILITAIRES
//////////////////////////////////////////////

function isValidNIR($nir)
{
    $nir = str_replace(' ', '', $nir);
    return preg_match('/^[12]\d{2}(0[1-9]|1[0-2])\d{10}$/', $nir);
}

function isChambreAvailable($pdo, $chambre_id, $date)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) 
                           FROM ap_admission 
                           WHERE chambre_id = :id 
                           AND hospitalisation_date = :date");
    $stmt->execute([':id' => $chambre_id, ':date' => $date]);
    return $stmt->fetchColumn() == 0;
}

//////////////////////////////////////////////
//             RÉCUPÉRATION DONNÉES
//////////////////////////////////////////////

$patients = $pdo->query("SELECT social_number, lastname, firstname 
                         FROM ap_patient ORDER BY lastname")
                ->fetchAll(PDO::FETCH_ASSOC);

$chambres = $pdo->query("SELECT chambre_id, type_chambre, private_room 
                         FROM ap_chambre ORDER BY chambre_id")
                ->fetchAll(PDO::FETCH_ASSOC);

//////////////////////////////////////////////
//             TRAITEMENT FORMULAIRE
//////////////////////////////////////////////

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new_patient = $_POST['new_patient'] ?? '0';

    //////////////////////////////////////////////
    //        CRÉATION NOUVEAU PATIENT
    //////////////////////////////////////////////
    if ($new_patient === '1') {

        // Données patient
        $social_number = preg_replace('/\D/', '', $_POST['social_number'] ?? '');
        $lastname      = trim($_POST['lastname'] ?? '');
        $firstname     = trim($_POST['firstname'] ?? '');
        $birthdate     = $_POST['birthdate'] ?? '';
        $phone         = preg_replace('/[^\d+]/', '', $_POST['phone'] ?? '');
        $is_minor      = isset($_POST['is_minor']) ? 1 : 0;

        // Champs BDD manquants dans ton code initial
        $sexe          = $_POST['sexe'] ?? null;
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');

        // --- Vérifications
        if (!isValidNIR($social_number)) {
            $error = "Numéro de sécurité sociale invalide.";
        } elseif (!$lastname || !$firstname || !$birthdate) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } else {
            // Vérifier si patient existe déjà
            $check = $pdo->prepare("SELECT COUNT(*) FROM ap_patient WHERE social_number = ?");
            $check->execute([$social_number]);
            if ($check->fetchColumn() > 0) {
                $error = "Un patient avec ce numéro existe déjà.";
            }
        }

        // --- Insertion patient
        if (!$error) {
            $stmt = $pdo->prepare("
                INSERT INTO ap_patient 
                (social_number, lastname, firstname, birthdate, phone, sexe, email, address)
                VALUES (:social, :ln, :fn, :birth, :phone, :sexe, :email, :address)
            ");
            $stmt->execute([
                ':social'  => $social_number,
                ':ln'      => $lastname,
                ':fn'      => $firstname,
                ':birth'   => $birthdate,
                ':phone'   => $phone,
                ':sexe'    => $sexe,
                ':email'   => $email,
                ':address' => $address
            ]);

            $patient_social = $social_number;
        }

    //////////////////////////////////////////////
    //        PATIENT EXISTANT
    //////////////////////////////////////////////
    } else {
        $patient_social = $_POST['patient_social'] ?? '';
        if (!$patient_social) {
            $error = "Veuillez sélectionner un patient.";
        }
    }

    //////////////////////////////////////////////
    //          ADMISSION
    //////////////////////////////////////////////
    if (!$error) {

        $admission_type        = trim($_POST['admission_type'] ?? '');
        $hospitalisation_date  = $_POST['hospitalisation_date'] ?? '';
        $intervention_time     = $_POST['intervention_time'] ?? null;
        $chambre_id            = $_POST['chambre_id'] ?? null;
        $reason                = trim($_POST['reason'] ?? '');
        $notes                 = trim($_POST['notes'] ?? '');

        if ($hospitalisation_date < date('Y-m-d')) {
            $error = "Date d’hospitalisation invalide.";
        } elseif ($chambre_id && !isChambreAvailable($pdo, $chambre_id, $hospitalisation_date)) {
            $error = "Chambre déjà réservée.";
        }

        //////////////////////////////////////////////
        //        COUVERTURE SOCIALE
        //////////////////////////////////////////////
        if (!$error) {

            $social_org       = trim($_POST['social_org'] ?? '');
            $is_assured       = isset($_POST['is_assured']) ? 1 : 0;
            $ald              = isset($_POST['ald']) ? 1 : 0;
            $insurance_number = preg_replace('/\D/', '', $_POST['insurance_number'] ?? '');
            $insurance_name   = trim($_POST['insurance_name'] ?? '');
            $prise_en_charge = $_POST['prise_en_charge'] ?? null; // présent dans ta BDD

            $covCheck = $pdo->prepare("SELECT COUNT(*) FROM ap_couverture_sociale WHERE social_number = ?");
            $covCheck->execute([$patient_social]);

            if ($covCheck->fetchColumn() == 0) {
                $insertCov = $pdo->prepare("
                    INSERT INTO ap_couverture_sociale
                    (social_number, social_org, is_assured, ald, insurance_number, insurance_name, prise_en_charge)
                    VALUES (:s, :org, :ass, :ald, :num, :name, :pec)
                ");
                $insertCov->execute([
                    ':s'    => $patient_social,
                    ':org'  => $social_org,
                    ':ass'  => $is_assured,
                    ':ald'  => $ald,
                    ':num'  => $insurance_number,
                    ':name' => $insurance_name,
                    ':pec'  => $prise_en_charge
                ]);
            }
        }

        //////////////////////////////////////////////
        //        PERSONNES DE CONTACT
        //////////////////////////////////////////////
        if (!$error) {

            $contacts = [
                [
                    'type'      => 'confiance',
                    'name'      => trim($_POST['contact_confiance_nom'] ?? ''),
                    'firstname' => trim($_POST['contact_confiance_prenom'] ?? ''),
                    'phone'     => trim($_POST['contact_confiance_tel'] ?? '')
                ],
                [
                    'type'      => 'prévenir',
                    'name'      => trim($_POST['contact_prevenir_nom'] ?? ''),
                    'firstname' => trim($_POST['contact_prevenir_prenom'] ?? ''),
                    'phone'     => trim($_POST['contact_prevenir_tel'] ?? '')
                ]
            ];

            foreach ($contacts as $c) {
                if ($c['name'] && $c['phone']) {
                    $pdo->prepare("
                        INSERT INTO ap_personne_contact 
                        (social_number, type_contact, name, firstname, phone)
                        VALUES (:s, :t, :n, :f, :p)
                    ")->execute([
                        ':s' => $patient_social,
                        ':t' => $c['type'],
                        ':n' => $c['name'],
                        ':f' => $c['firstname'],
                        ':p' => $c['phone']
                    ]);
                }
            }
        }

        //////////////////////////////////////////////
        //        DOCUMENTS
        //////////////////////////////////////////////
        if (!$error) {

            $allowedTypes = ['application/pdf'];
            $docs = ['id_card', 'vital_card', 'insurance_card', 'livret_famille'];

            foreach ($docs as $doc) {
                if (!empty($_FILES[$doc]['tmp_name'])) {
                    $type = mime_content_type($_FILES[$doc]['tmp_name']);

                    // Vérification obligatoire livret de famille si mineur
                    if ($is_minor && empty($_FILES['livret_famille']['tmp_name'])) {
                      $error = "Le livret de famille est obligatoire pour un patient mineur.";
                  }

                    if (!in_array($type, $allowedTypes)) {
                        $error = "Le fichier $doc doit être un PDF.";
                        break;
                    }


                    // Stockage propre
                    $file_data = file_get_contents($_FILES[$doc]['tmp_name']);

                    $pdo->prepare("
                        INSERT INTO ap_documents (social_number, doc_type, file_data)
                        VALUES (:s, :t, :d)
                    ")->execute([
                        ':s' => $patient_social,
                        ':t' => $doc,
                        ':d' => $file_data
                    ]);
                }
            }
        }

        //////////////////////////////////////////////
        //        INSERTION ADMISSION
        //////////////////////////////////////////////
        if (!$error) {
            $stmt = $pdo->prepare("
                INSERT INTO ap_admission
                (admission_type, hospitalisation_date, intervention_time, private_room, reason, notes, statut, personnel_name, patient_social, chambre_id)
                VALUES (:t, :hd, :it, :pr, :r, :n, 'pré-admission', :p, :s, :c)
            ");
            $stmt->execute([
                ':t' => $admission_type,
                ':hd' => $hospitalisation_date,
                ':it' => $intervention_time,
                ':pr' => isset($_POST['private_room']) ? 1 : 0,
                ':r'  => $reason,
                ':n'  => $notes,
                ':p'  => $_SESSION['personnel_name'],
                ':s'  => $patient_social,
                ':c'  => $chambre_id
            ]);

            $success = "Pré-admission enregistrée avec succès !";
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
// ------------------------------
// Barre de progression
// ------------------------------
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


// ------------------------------
// GESTION PATIENT NOUVEAU / EXISTANT
// ------------------------------
document.querySelectorAll('input[name="new_patient"]').forEach(el => {
  el.addEventListener('change', function() {
    const isNew = this.value === '1';

    document.getElementById('new_patient_fields').style.display = isNew ? 'block' : 'none';
    document.getElementById('existing_patient').style.display = isNew ? 'none' : 'block';
  });
});


// ------------------------------
// GESTION DES MINEURS
// ------------------------------
const birthdateInput = document.querySelector('input[name="birthdate"]');
const minorCheckbox  = document.getElementById('is_minor');
const minorFields    = document.getElementById('minor_fields');
const livretInput    = document.querySelector('input[name="livret_famille"]');

function checkMinor() {
    const birthdate = birthdateInput.value;
    if (!birthdate) return;

    const birth = new Date(birthdate);
    const today = new Date();

    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();

    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
        age--;
    }

    if (age < 18) {
        // Patient mineur
        minorCheckbox.checked = true;
        minorCheckbox.disabled = true;

        minorFields.style.display = 'block';
        livretInput.setAttribute('required', 'required');

    } else {
        // Patient majeur
        minorCheckbox.checked = false;
        minorCheckbox.disabled = false;

        minorFields.style.display = 'none';
        livretInput.removeAttribute('required');
        livretInput.value = "";
    }
}

// Exécuter quand la date change & au chargement
birthdateInput.addEventListener('change', checkMinor);
window.addEventListener('DOMContentLoaded', checkMinor);


// ------------------------------
// BLOCAGE SI DOCUMENT MANQUANT (mineur)
// ------------------------------
form.addEventListener('submit', function(event) {

    // Mineur : livret obligatoire
    if (minorCheckbox.checked && livretInput.value === "") {
        alert("⚠ Le livret de famille est obligatoire pour un patient mineur.");
        event.preventDefault();
        return false;
    }
});


// --------------------------------------------------------------
// VÉRIFICATIONS : Mutuelle / NIR / Téléphone (inchangé mais propre)
// --------------------------------------------------------------
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

// Mutuelle
const insuranceInput = document.querySelector('input[name="insurance_number"]');
if (insuranceInput) {
  insuranceInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length < 4) showError(this, "Doit contenir entre 4 et 6 chiffres.");
    else clearError(this);
  });
}

// NIR
const nirInput = document.querySelector('input[name="social_number"]');
if (nirInput) {
  nirInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 15);
    if (this.value.length !== 15) showError(this, "Le NIR doit contenir exactement 15 chiffres.");
    else clearError(this);
  });
}

// Téléphone
const phoneInputs = document.querySelectorAll('input[name="phone"], input[name="contact_confiance_tel"], input[name="contact_prevenir_tel"]');
phoneInputs.forEach(input => {
  input.addEventListener('input', function() {
    let val = this.value;

    // Autoriser un + au début, sinon uniquement chiffres
    if (val.startsWith('+')) {
      val = '+' + val.slice(1).replace(/\D/g, '');
    } else {
      val = val.replace(/\D/g, '');
    }

    // Limiter à 10 chiffres (hors '+')
    if (val.startsWith('+')) {
      this.value = '+' + val.slice(1, 11);
    } else {
      this.value = val.slice(0, 10);
    }

    // Vérification minimum
    if (val.replace('+','').length < 10) {
      showError(this, "Le numéro doit contenir exactement 10 chiffres.");
    } else {
      clearError(this);
    }
  });
});
</script>
</body>
</html>