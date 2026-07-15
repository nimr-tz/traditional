<?php

declare(strict_types=1);

$imagesDirectory = dirname(__DIR__).'/resources/images';

function loadPng(string $path): GdImage
{
    $image = imagecreatefrompng($path);

    if ($image === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    return $image;
}

function resizeWebp(GdImage $source, int $width, string $destination, int $quality): void
{
    $height = (int) round(imagesy($source) * $width / imagesx($source));
    $resized = imagescale($source, $width, $height, IMG_BICUBIC_FIXED);

    if ($resized === false) {
        throw new RuntimeException("Unable to resize image for {$destination}");
    }

    imagesavealpha($resized, true);

    if (! imagewebp($resized, $destination, $quality)) {
        throw new RuntimeException("Unable to write {$destination}");
    }

    imagedestroy($resized);
}

function resizeJpeg(GdImage $source, int $width, string $destination, int $quality): void
{
    $height = (int) round(imagesy($source) * $width / imagesx($source));
    $resized = imagescale($source, $width, $height, IMG_BICUBIC_FIXED);

    if ($resized === false) {
        throw new RuntimeException("Unable to resize image for {$destination}");
    }

    if (! imagejpeg($resized, $destination, $quality)) {
        throw new RuntimeException("Unable to write {$destination}");
    }

    imagedestroy($resized);
}

$hero = loadPng($imagesDirectory.'/traditional-medicine-hero.png');

foreach ([480, 768, 1200] as $width) {
    resizeWebp(
        $hero,
        $width,
        $imagesDirectory."/traditional-medicine-hero-{$width}.webp",
        80,
    );
}

resizeJpeg($hero, 768, $imagesDirectory.'/traditional-medicine-hero-768.jpg', 82);

imagedestroy($hero);

$logo = loadPng($imagesDirectory.'/nimr-logo.png');
resizeWebp($logo, 128, $imagesDirectory.'/nimr-logo-128.webp', 88);
imagedestroy($logo);
