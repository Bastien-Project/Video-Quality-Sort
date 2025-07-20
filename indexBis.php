<?php
// TEST CODE SUR UN FICHIER SPÉCIFIQUE

$ffprobePath = 'C:\\xampp\\htdocs\\videosDDext\\ffmpeg-2025-07-01-git-11d1b71c31-full_build\\bin\\ffprobe.exe';
$videoFile = 'I:\\White House Down\\White House Down.avi';

$cmd = '"' . $ffprobePath . '" -v error -select_streams v:0 -show_entries stream=width,height -of json ' . escapeshellarg($videoFile);
$output = shell_exec($cmd);

echo "<h2>Commande exécutée :</h2>";
echo "<pre>" . htmlspecialchars($cmd) . "</pre>";

echo "<h2>Sortie brute de ffprobe :</h2>";
if ($output === null) {
    echo "<p><strong>Erreur :</strong> shell_exec a retourné null</p>";
} else {
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
}
