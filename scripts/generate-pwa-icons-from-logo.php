<?php

/**
 * Rigenera icone PWA da public/images/finanzamente-logo.webp (logo piatto, senza angoli arrotondati).
 *
 * Uso: php scripts/generate-pwa-icons-from-logo.php
 *      make pwa-icons
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$srcPath = $root.'/public/images/finanzamente-logo.webp';
$pwaDir = $root.'/public/pwa';

$sizes = [
    'icon-192.png' => 192,
    'icon-512.png' => 512,
    'apple-touch-icon.png' => 180,
];

if (! extension_loaded('gd')) {
    fwrite(STDERR, "Estensione PHP GD richiesta.\n");
    exit(1);
}

if (! function_exists('imagecreatefromwebp')) {
    fwrite(STDERR, "GD senza supporto WebP: abilitare ext-gd con WebP.\n");
    exit(1);
}

if (! is_dir($pwaDir)) {
    mkdir($pwaDir, 0755, true);
}

$src = imagecreatefromwebp($srcPath);
if ($src === false) {
    fwrite(STDERR, "Impossibile leggere {$srcPath}\n");
    exit(1);
}

$srcW = imagesx($src);
$srcH = imagesy($src);
$hasAlpha = true;
imagealphablending($src, false);
imagesavealpha($src, true);

foreach ($sizes as $filename => $size) {
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    imagealphablending($canvas, true);

    $scale = min($size / $srcW, $size / $srcH) * 0.88;
    $dstW = (int) round($srcW * $scale);
    $dstH = (int) round($srcH * $scale);
    $dstX = (int) round(($size - $dstW) / 2);
    $dstY = (int) round(($size - $dstH) / 2);

    imagecopyresampled($canvas, $src, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);

    $out = $pwaDir.'/'.$filename;
    imagepng($canvas, $out, 9);
    imagedestroy($canvas);
    echo "Scritto {$out}\n";
}

imagedestroy($src);

passthru('php '.escapeshellarg($root.'/scripts/generate-pwa-maskable-icon.php'), $maskableExit);
if ($maskableExit !== 0) {
    exit($maskableExit);
}

// Splash iOS (logo centrato su sfondo theme)
$splashSizes = [
    'apple-splash-1170x2532.png' => [1170, 2532],
    'apple-splash-1284x2778.png' => [1284, 2778],
];
$bg = [79, 76, 229];
$icon512 = imagecreatefrompng($pwaDir.'/icon-512.png');

foreach ($splashSizes as $filename => [$w, $h]) {
    $canvas = imagecreatetruecolor($w, $h);
    $bgColor = imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]);
    imagefill($canvas, 0, 0, $bgColor);
    $logoSize = (int) round(min($w, $h) * 0.22);
    $dstX = (int) round(($w - $logoSize) / 2);
    $dstY = (int) round(($h - $logoSize) / 2);
    imagecopyresampled($canvas, $icon512, $dstX, $dstY, 0, 0, $logoSize, $logoSize, imagesx($icon512), imagesy($icon512));
    imagepng($canvas, $pwaDir.'/'.$filename, 9);
    imagedestroy($canvas);
    echo "Scritto {$pwaDir}/{$filename}\n";
}

imagedestroy($icon512);

echo "Icone PWA aggiornate da finanzamente-logo.webp\n";
