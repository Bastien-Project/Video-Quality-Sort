<?php

set_time_limit(0); // autorise un temps d'exécution illimité

$ffprobePath = 'C:\\xampp\\htdocs\\videosDDext\\ffmpeg-2025-07-01-git-11d1b71c31-full_build\\bin\\ffprobe.exe';
$baseDir = 'E:\\'; // dossier à scanner
$lowResDir = $baseDir . 'A RETELECHARGER EN BONNE QUALITE\\'; // dossier pour les résolutions < 720

// Extensions vidéo acceptées
$videoExtensions = ['avi', 'mp4', 'mov', 'mkv', 'divx'];
$videos = [];

// Crée le dossier "A RETELECHARGER EN BONNE QUALITE" s'il n'existe pas
if (!is_dir($lowResDir)) {
    mkdir($lowResDir, 0777, true);
}

// Recherche récursive
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($rii as $file) {
    if ($file->isDir()) continue;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $videoExtensions)) continue;

    $path = $file->getPathname();

    // Ignore déjà les vidéos dans A RETELECHARGER
    if (str_starts_with($path, $lowResDir)) continue;

    // Analyse avec ffprobe
    $cmd = '"' . $ffprobePath . '" -v error -select_streams v:0 -show_entries stream=width,height -of json ' . escapeshellarg($path);
    $output = shell_exec($cmd);

    $resolution = 'N/A';
    $moved = false;

    if ($output) {
        $data = json_decode($output, true);
        if (!empty($data['streams'][0]['width']) && !empty($data['streams'][0]['height'])) {
            $width = $data['streams'][0]['width'];
            $height = $data['streams'][0]['height'];
            $resolution = "{$width}x{$height}";

            // Si la hauteur est < 720, on déplace
            if ($height < 480) {
                $newPath = $lowResDir . basename($path);
                if (@rename($path, $newPath)) {
                    $moved = true;
                    $path = $newPath;
                } else {
                    $resolution .= " (ERREUR de déplacement)";
                }
            }
        }
    }

    $videos[] = [
        'file' => $path,
        'resolution' => $resolution,
        'moved' => $moved
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des vidéos</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 1em; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px 12px; border: 1px solid #ccc; }
        th { background-color: #f0f0f0; }
        tr.low-res { background-color: #ffe5e5; }
        tr:hover { background-color: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Vidéos trouvées (déplacement automatique si < 720p)</h1>
    <table>
        <thead>
            <tr>
                <th>Nom du fichier</th>
                <th>Résolution</th>
                <th>Déplacée ?</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($videos as $vid): ?>
                <tr class="<?= $vid['moved'] ? 'low-res' : '' ?>">
                    <td><?php echo htmlspecialchars($vid['file']); ?></td>
                    <td><?php echo htmlspecialchars($vid['resolution']); ?></td>
                    <td><?php echo $vid['moved'] ? '✅' : ''; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
