<?php
$host = "192.168.20.43";
$dbname = "clinique_lpf";
$username = "clinique";
$password = "CliniqueLPF123!";

function getPDOConnection() {
    global $host, $dbname, $username, $password;
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Connexion échouée : " . $e->getMessage());
    }
}