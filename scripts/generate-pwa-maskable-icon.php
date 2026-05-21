<?php

/**
 * Genera icon-maskable-512.png con safe zone (logo ~72% su sfondo theme).
 * Uso: php scripts/generate-pwa-maskable-icon.php
 */
$srcPath = __DIR__.'/../public/pwa/icon-512.png';
$outPath = __DIR__.'/../public/pwa/icon-maskable-512.png';
$size = 512;
$logoScale = 0.72;
$bg = [79, 76, 229]; // #4f4ce5

if (! extension_loaded('gd')) {
    fwrite(STDERR, "Estensione GD richiesta.\n");
    exit(1);
}

$src = imagecreatefrompng($srcPath);
if ($src === false) {
    fwrite(STDERR, "Impossibile leggere {$srcPath}\n");
    exit(1);
}

$canvas = imagecreatetruecolor($size, $size);
$bgColor = imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]);
imagefill($canvas, 0, 0, $bgColor);

$srcW = imagesx($src);
$srcH = imagesy($src);
$target = (int) round($size * $logoScale);
$dstX = (int) round(($size - $target) / 2);
$dstY = (int) round(($size - $target) / 2);

imagealphablending($canvas, true);
imagesavealpha($canvas, true);
imagecopyresampled($canvas, $src, $dstX, $dstY, 0, 0, $target, $target, $srcW, $srcH);

imagepng($canvas, $outPath);
imagedestroy($src);
imagedestroy($canvas);

echo "Scritto {$outPath}\n";
