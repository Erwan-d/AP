<?php
session_start();
require_once 'systems/config.php';

// Vérifier que le formulaire a bien été soumis
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        // On récupère l'utilisateur dans la table PERSONNELS
        $sql = "SELECT * FROM personnels WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Vérification du mot de passe
        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // On crée la session
            $_SESSION['personnel_id'] = $user['personnel_id'];
            $_SESSION['nom'] = $user['personnel_name'];
            $_SESSION['role_id'] = $user['role_id'];

            // Redirection selon le rôle
            if ($user['role_id'] == 1) {
                header("Location: ../admin/admin_dashboard.php"); // rôle admin
            } else {
                header("Location: ../secretary/secretary_dashboard.php"); // rôle secrétaire
            }
            exit();
        } else {
            $_SESSION['login_error'] = "Email ou mot de passe incorrect.";
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "Veuillez remplir tous les champs.";
        header("Location: ../login.php");
        exit();
    }
} else {
    header("Location: ../login.php");
    exit();
}
