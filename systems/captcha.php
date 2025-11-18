<?php
session_start();

// Génère un code à 5 chiffres
$code = rand(10000, 99999);
$_SESSION['captcha_code'] = $code;

// Crée une image
$img = imagecreate(100, 40);
$bg = imagecolorallocate($img, 255, 255, 255);
$text = imagecolorallocate($img, 0, 0, 0);
imagestring($img, 5, 25, 10, $code, $text);

// Affiche l’image
header("Content-Type: image/png");
imagepng($img);
imagedestroy($img);
