<?php

set_time_limit(0);
$ffprobePath = 'C:\\xampp\\htdocs\\Video-Quality-Sort\\ffmpeg-2025-07-01-git-11d1b71c31-full_build\\bin\\ffprobe.exe';
$baseDir = 'E:\\';
$lowResDir = $baseDir . 'A RETELECHARGER EN BONNE QUALITE\\';
$logFile = $baseDir . 'liste_videos.txt';

$videoExtensions = ['avi', 'mp4', 'mov', 'mkv', 'divx'];
$videos = [];

if (!is_dir($lowResDir)) {
    mkdir($lowResDir, 0777, true);
}

$log = fopen($logFile, 'w');
fwrite($log, "LISTE DES VIDEOS - " . date('d/m/Y H:i:s') . "\n");
fwrite($log, str_repeat("=", 80) . "\n\n");

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($rii as $file) {
    if ($file->isDir()) continue;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $videoExtensions)) continue;

    $path = $file->getPathname();

    if (str_starts_with($path, $lowResDir)) continue;

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

            if ($height < 720) {
                // Recréer l'arborescence dans le dossier de destination
                $relativePath = substr($path, strlen($baseDir));
                $newPath = $lowResDir . $relativePath;
                $newDir = dirname($newPath);

                if (!is_dir($newDir)) {
                    mkdir($newDir, 0777, true);
                }

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

    $movedLabel = $moved ? ' [DEPLACE]' : '';
    fwrite($log, basename($path) . "\n");
    fwrite($log, "  Résolution : " . $resolution . $movedLabel . "\n");
    fwrite($log, "  Chemin     : " . $path . "\n\n");
}

fwrite($log, str_repeat("=", 80) . "\n");
fwrite($log, "TOTAL : " . count($videos) . " vidéo(s) trouvée(s)\n");
$movedCount = count(array_filter($videos, fn($v) => $v['moved']));
fwrite($log, "Déplacées (< 720p) : " . $movedCount . "\n");
fclose($log);
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
        .summary { margin-top: 1em; padding: 0.8em; background: #eef; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Vidéos trouvées (déplacement automatique si &lt; 720p)</h1>
    <p class="summary">
        📄 Fichier texte généré : <strong><?php echo htmlspecialchars($logFile); ?></strong><br>
        📦 Total : <strong><?php echo count($videos); ?></strong> vidéo(s) —
        🔴 Déplacées : <strong><?php echo $movedCount; ?></strong>
    </p>
    <table>
        <thead>
            <tr>
                <th>Chemin complet</th>
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