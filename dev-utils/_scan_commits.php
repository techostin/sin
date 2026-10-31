<?php
// Varre todos os objetos commit SOLTOS no .git/objects e lista data+msg
// para encontrar commits orfaos (nao referenciados) mais recentes.
$objDir = dirname(__DIR__) . '/.git/objects';
$commits = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($objDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isDir()) continue;
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($objDir) + 1));
    if (strpos($rel, '/') === false && strpos($rel, 'pack') !== false) continue; // sem pack
    if (strpos($rel, '/') === false) continue;
    $sha = str_replace('/', '', $rel);
    if (strlen($sha) !== 40) continue;
    $raw = @file_get_contents($f->getPathname());
    if ($raw === false) continue;
    $dec = @gzuncompress($raw);
    if ($dec === false) continue;
    $nul = strpos($dec, "\0");
    if ($nul === false) continue;
    $header = substr($dec, 0, $nul);
    if (strpos($header, 'commit ') !== 0) continue; // só commits
    $body = substr($dec, $nul + 1);
    $author = ''; $msg = '';
    $lines = explode("\n", $body);
    $msgStart = false; $msgLines = [];
    foreach ($lines as $ln) {
        if (!$msgStart) {
            if (strpos($ln, 'author ') === 0) $author = trim(substr($ln, 7));
            if ($ln === '') $msgStart = true;
        } else {
            $msgLines[] = $ln;
        }
    }
    $msg = trim(implode(' ', $msgLines));
    if (strlen($msg) > 120) $msg = substr($msg, 0, 120) . '...';
    $ts = 0;
    if (preg_match('/> (\d+) [-+]/', $author, $m)) $ts = (int)$m[1];
    $commits[] = [$ts, $sha, $msg];
}

// ordena por timestamp desc
usort($commits, function($a,$b){ return $b[0] - $a[0]; });

echo 'Total de objetos commit soltos: ' . count($commits) . PHP_EOL . PHP_EOL;
foreach ($commits as $c) {
    $date = $c[0] > 0 ? date('Y-m-d H:i', $c[0]) : '????-??-??';
    echo $date . '  ' . substr($c[1],0,10) . '  ' . $c[2] . PHP_EOL;
}
