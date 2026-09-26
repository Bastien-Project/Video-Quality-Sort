<?php
$fichiers = [
    'liste_videos_INTENSO.txt',
    'liste_videos_HDD.txt',
];

function parseFile($path, $diskLabel) {
    $videos = [];
    if (!file_exists($path)) return $videos;

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $i = 0;
    while ($i < count($lines)) {
        $line = trim($lines[$i]);
        if ($line === '' || str_starts_with($line, 'LISTE') || str_starts_with($line, '===')
            || str_starts_with($line, 'Résolution') || str_starts_with($line, 'Chemin')
            || str_starts_with($line, 'TOTAL') || str_starts_with($line, 'Déplacées')) {
            $i++; continue;
        }

        $filename = $line;
        $resolution = 'N/A';
        $moved = false;
        $error = false;
        $filepath = '';
        $width = 0;
        $height = 0;

        if (isset($lines[$i + 1])) {
            $rline = trim($lines[$i + 1]);
            if (str_starts_with($rline, 'Résolution')) {
                $rv = trim(str_replace('Résolution :', '', $rline));
                if (str_contains($rv, '[DEPLACE]'))  { $moved = true;  $rv = trim(str_replace('[DEPLACE]', '', $rv)); }
                if (str_contains($rv, '(ERREUR'))    { $error = true;  $rv = trim(preg_replace('/\(ERREUR.*\)/', '', $rv)); }
                $resolution = $rv;
                if (preg_match('/(\d+)x(\d+)/', $resolution, $m)) {
                    $width  = (int)$m[1];
                    $height = (int)$m[2];
                }
            }
        }
        if (isset($lines[$i + 2])) {
            $pline = trim($lines[$i + 2]);
            if (str_starts_with($pline, 'Chemin')) {
                $filepath = trim(str_replace('Chemin     :', '', $pline));
            }
        }

        // Calcul du statut qualité
        if ($error) {
            $status = 'err';
        } elseif ($width === 0 && $height === 0) {
            $status = 'na';
        } elseif ($height >= 1080 || $width >= 1920) {
            $status = 'ok';       // Full HD ou mieux
        } elseif ($height >= 720 || $width >= 1280) {
            $status = 'passable'; // 720p
        } else {
            $status = 'bad';      // En dessous de 720p
        }

        $videos[] = [
            'disk'       => $diskLabel,
            'filename'   => $filename,
            'resolution' => $resolution,
            'path'       => $filepath,
            'width'      => $width,
            'height'     => $height,
            'moved'      => $moved,
            'error'      => $error,
            'status'     => $status,
        ];
        $i += 4;
    }
    return $videos;
}

$allVideos = [];
$disks = [];
foreach ($fichiers as $f) {
    $label = str_replace(['liste_videos_', '.txt'], '', $f);
    $vids = parseFile(__DIR__ . '/' . $f, $label);
    $allVideos = array_merge($allVideos, $vids);
    if (count($vids)) $disks[] = $label;
}

$total    = count($allVideos);
$nOk      = count(array_filter($allVideos, fn($v) => $v['status'] === 'ok'));
$nPass    = count(array_filter($allVideos, fn($v) => $v['status'] === 'passable'));
$nBad     = count(array_filter($allVideos, fn($v) => $v['status'] === 'bad'));
$nNa      = count(array_filter($allVideos, fn($v) => $v['status'] === 'na' || $v['status'] === 'err'));
$dataJson = json_encode($allVideos, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mes Vidéos</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0f1117; --surface: #1a1d27; --surface2: #22263a;
    --border: #2e334d; --accent: #4f8ef7;
    --ok: #22c55e; --pass: #f59e0b; --bad: #ef4444;
    --text: #e2e8f0; --muted: #94a3b8;
  }
  body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; font-size: 14px; }

  header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 24px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
  header h1 { font-size: 1.3em; font-weight: 700; }
  .stats { display: flex; gap: 10px; flex-wrap: wrap; margin-left: auto; }
  .stat { background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 7px 14px; text-align: center; }
  .stat .n { font-size: 1.3em; font-weight: 700; }
  .stat .l { font-size: 0.72em; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
  .stat.ok .n   { color: var(--ok); }
  .stat.pass .n { color: var(--pass); }
  .stat.bad .n  { color: var(--bad); }

  .toolbar {
    background: var(--surface); border-bottom: 1px solid var(--border);
    padding: 10px 24px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
    position: sticky; top: 0; z-index: 10;
  }
  .toolbar input[type=search] {
    background: var(--surface2); border: 1px solid var(--border); border-radius: 6px;
    color: var(--text); padding: 6px 11px; font-size: 13px; width: 240px; outline: none;
  }
  .toolbar input[type=search]:focus { border-color: var(--accent); }
  .btn-group { display: flex; gap: 3px; }
  .btn { background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--muted); padding: 5px 11px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all .15s; }
  .btn:hover { border-color: var(--accent); color: var(--accent); }
  .btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }
  select { background: var(--surface2); border: 1px solid var(--border); border-radius: 6px; color: var(--text); padding: 5px 9px; font-size: 13px; cursor: pointer; outline: none; }
  .count-badge { margin-left: auto; font-size: 12px; color: var(--muted); }

  .table-wrap { overflow-x: auto; padding: 0 24px 40px; }
  table { width: 100%; border-collapse: collapse; margin-top: 14px; }
  thead th { background: var(--surface); color: var(--muted); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: 9px 12px; border-bottom: 1px solid var(--border); text-align: left; cursor: pointer; user-select: none; white-space: nowrap; }
  thead th:hover { color: var(--accent); }
  thead th.sorted .sort-icon { color: var(--accent); }
  tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
  tbody tr:hover { background: var(--surface2); }
  tbody td { padding: 8px 12px; vertical-align: middle; }
  .filename { font-weight: 500; max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .filename small { display: block; font-size: 11px; color: var(--muted); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 400px; }
  .res { font-family: monospace; font-size: 13px; white-space: nowrap; }
  .badge { display: inline-block; border-radius: 4px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
  .badge.ok   { background: #14532d; color: #86efac; }
  .badge.pass { background: #451a03; color: #fcd34d; }
  .badge.bad  { background: #450a0a; color: #fca5a5; }
  .badge.na   { background: #1c1917; color: #a8a29e; }
  .badge.err  { background: #3b0764; color: #e9d5ff; }
  .disk-tag { background: #1e3a5f; color: #93c5fd; border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 600; white-space: nowrap; }
  .empty { text-align: center; padding: 60px; color: var(--muted); }
  .missing { background: #1a0a0a; border: 1px solid var(--bad); border-radius: 8px; padding: 12px 18px; margin: 20px 24px; font-size: 13px; color: #fca5a5; }
</style>
</head>
<body>

<?php foreach ($fichiers as $f): ?>
  <?php if (!file_exists(__DIR__ . '/' . $f)): ?>
    <div class="missing">⚠️ Fichier introuvable : <strong><?= htmlspecialchars($f) ?></strong> — place-le dans le même dossier que ce fichier PHP.</div>
  <?php endif; ?>
<?php endforeach; ?>

<header>
  <div>
    <h1>🎬 Mes Vidéos — <?= implode(' &amp; ', array_map('htmlspecialchars', $disks)) ?></h1>
    <div style="font-size:12px;color:var(--muted);margin-top:3px"><?= htmlspecialchars(implode(', ', $fichiers)) ?></div>
  </div>
  <div class="stats">
    <div class="stat ok">  <div class="n"><?= $nOk ?></div>  <div class="l">✅ Full HD+</div></div>
    <div class="stat pass"><div class="n"><?= $nPass ?></div><div class="l">🟠 720p</div></div>
    <div class="stat bad"> <div class="n"><?= $nBad ?></div> <div class="l">🔴 &lt; 720p</div></div>
    <div class="stat">     <div class="n"><?= $nNa ?></div>  <div class="l">⚠️ N/A</div></div>
    <div class="stat">     <div class="n"><?= $total ?></div><div class="l">Total</div></div>
  </div>
</header>

<div class="toolbar">
  <input type="search" id="search" placeholder="🔍  Rechercher…">
  <div class="btn-group" id="filter-status">
    <button class="btn active" data-val="all">Tout</button>
    <button class="btn" data-val="ok">✅ Full HD+</button>
    <button class="btn" data-val="passable">🟠 720p</button>
    <button class="btn" data-val="bad">🔴 &lt; 720p</button>
    <button class="btn" data-val="na">⚠️ N/A</button>
  </div>
  <select id="filter-disk">
    <option value="all">Tous les disques</option>
    <?php foreach ($disks as $d): ?>
      <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="count-badge" id="count-badge"></span>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th data-col="filename">Fichier <span class="sort-icon">↕</span></th>
        <th data-col="resolution">Résolution <span class="sort-icon">↕</span></th>
        <th data-col="status">Qualité <span class="sort-icon">↕</span></th>
        <th data-col="disk">Disque <span class="sort-icon">↕</span></th>
      </tr>
    </thead>
    <tbody id="tbody"></tbody>
  </table>
  <div class="empty" id="empty" style="display:none">Aucun résultat</div>
</div>

<script>
const DATA = <?= $dataJson ?>;

const LABELS = {
  ok:       '<span class="badge ok">✅ Full HD+</span>',
  passable: '<span class="badge pass">🟠 720p</span>',
  bad:      '<span class="badge bad">🔴 &lt; 720p</span>',
  na:       '<span class="badge na">⚠️ Non détecté</span>',
  err:      '<span class="badge err">❌ Erreur</span>',
};

const STATUS_ORDER = { ok: 0, passable: 1, bad: 2, na: 3, err: 4 };

let filterStatus = 'all', filterDisk = 'all', search = '', sortCol = 'filename', sortDir = 1;

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function render() {
  let rows = DATA.filter(v => {
    if (filterStatus !== 'all' && v.status !== filterStatus) return false;
    if (filterDisk   !== 'all' && v.disk   !== filterDisk)   return false;
    if (search) {
      const q = search.toLowerCase();
      if (!v.filename.toLowerCase().includes(q) && !v.path.toLowerCase().includes(q)) return false;
    }
    return true;
  });

  rows.sort((a, b) => {
    if (sortCol === 'resolution') {
      const px = v => v.height || 0;
      return (px(a) - px(b)) * sortDir;
    }
    if (sortCol === 'status') {
      return ((STATUS_ORDER[a.status] ?? 9) - (STATUS_ORDER[b.status] ?? 9)) * sortDir;
    }
    return String(a[sortCol]).localeCompare(String(b[sortCol]), 'fr') * sortDir;
  });

  document.getElementById('tbody').innerHTML = rows.map(v => `
    <tr>
      <td class="filename">${esc(v.filename)}<small title="${esc(v.path)}">${esc(v.path)}</small></td>
      <td class="res">${esc(v.resolution)}</td>
      <td>${LABELS[v.status] || ''}</td>
      <td><span class="disk-tag">${esc(v.disk)}</span></td>
    </tr>
  `).join('');

  document.getElementById('empty').style.display = rows.length ? 'none' : 'block';
  document.getElementById('count-badge').textContent = rows.length + ' / ' + DATA.length + ' vidéos';

  document.querySelectorAll('thead th[data-col]').forEach(th => {
    const active = th.dataset.col === sortCol;
    th.classList.toggle('sorted', active);
    th.querySelector('.sort-icon').textContent = active ? (sortDir === 1 ? '↑' : '↓') : '↕';
  });
}

document.getElementById('search').addEventListener('input', e => { search = e.target.value.trim(); render(); });

document.getElementById('filter-status').addEventListener('click', e => {
  const btn = e.target.closest('.btn');
  if (!btn) return;
  filterStatus = btn.dataset.val;
  document.querySelectorAll('#filter-status .btn').forEach(b => b.classList.toggle('active', b === btn));
  render();
});

document.getElementById('filter-disk').addEventListener('change', e => { filterDisk = e.target.value; render(); });

document.querySelectorAll('thead th[data-col]').forEach(th => {
  th.addEventListener('click', () => {
    sortCol = th.dataset.col;
    sortDir = (th.classList.contains('sorted')) ? sortDir * -1 : 1;
    render();
  });
});

render();
</script>
</body>
</html>
