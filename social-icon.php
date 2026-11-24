<?php
declare(strict_types=1);

$colors = ['linkedin'=>'22588c','facebook'=>'2864d4','zalo'=>'0965e8','site'=>'5142bc',
    'tiktok'=>'241c35','instagram'=>'963778','youtube'=>'b81c35'];
$name = $_GET['network'] ?? '';
$tint = $_GET['color'] ?? null;
$animated = ($_GET['animated'] ?? '') === '1';
if (!is_string($name) || !isset($colors[$name])
    || ($tint !== null && (!is_string($tint) || !preg_match('/^[0-9a-f]{6}$/iD', $tint)))) {
    http_response_code(404);
    exit;
}

$hex = $tint ?? $colors[$name];
$rgb = array_map('hexdec', str_split($hex, 2));
$linear = array_map(static fn(int $v): float => ($v / 255 <= .04045 ? $v / 255 / 12.92 : (($v / 255 + .055) / 1.055) ** 2.4), $rgb);
$sum = $linear[0] * .2126 + $linear[1] * .7152 + $linear[2] * .0722;
$ink = $sum > .179 ? [10,25,25] : [255,255,255];
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');
if ($animated) {
    $source = file_get_contents(__DIR__ . '/assets/motion/social-' . $name . '.gif');
    if ($source === false) {
        http_response_code(404);
        exit;
    }
    // The bundled four-color GIFs share palette positions for badge, glyph and orbit.
    $pulse = array_map(static fn(int $v): int => $v > 180 ? (int) ($v * .65) : (int) ($v + (255 - $v) * .55), $rgb);
    $gif = substr_replace($source, pack('C*', ...array_merge($rgb, $ink, $pulse)), 16, 9);
    header('Content-Type: image/gif');
    echo $gif;
    exit;
}

header('Content-Type: image/png');
if (!extension_loaded('gd')) {
    readfile(__DIR__ . '/assets/icons/' . $name . '.png');
    exit;
}

$face = imagecreatetruecolor(88, 88);
imagealphablending($face, false);
$clear = imagecolorallocatealpha($face, 0, 0, 0, 127);
$bg = imagecolorallocate($face, ...$rgb);
$fg = imagecolorallocate($face, ...$ink);
imagefill($face, 0, 0, $clear);
imagefilledellipse($face, 44, 44, 84, 84, $bg);
imagefilledellipse($face, 69, 24, 5, 5, $fg);

function icon_trace($canvas, int $color, array $points, int $weight = 5): void
{
    imagesetthickness($canvas, $weight);
    foreach ($points as $i => [$x, $y]) {
        if ($i) imageline($canvas, $points[$i-1][0], $points[$i-1][1], $x, $y, $color);
        imagefilledellipse($canvas, $x, $y, $weight, $weight, $color);
    }
}

switch ($name) {
    case 'linkedin':
        imagefilledellipse($face, 29, 30, 7, 7, $fg);
        icon_trace($face,$fg,[[29,40],[29,59]],6);
        icon_trace($face,$fg,[[42,59],[42,40],[51,39],[56,42],[57,59]],6);
        break;
    case 'facebook':
        icon_trace($face,$fg,[[42,63],[42,32],[45,25],[50,23],[57,23]],7);
        icon_trace($face,$fg,[[34,41],[54,41]],6);
        break;
    case 'zalo':
        icon_trace($face,$fg,[[31,32],[57,32],[31,56],[57,56]],6);
        break;
    case 'site':
        imagesetthickness($face,4);
        imageellipse($face,44,45,40,40,$fg);
        imageellipse($face,44,45,18,40,$fg);
        icon_trace($face,$fg,[[25,45],[64,45]],4);
        break;
    case 'instagram':
        icon_trace($face,$fg,[[32,27],[55,27],[61,32],[61,55],[55,61],[32,61],[26,55],[26,32],[32,27]],4);
        imagesetthickness($face,5);
        imageellipse($face,44,44,16,16,$fg);
        imagefilledellipse($face,54,34,5,5,$fg);
        break;
    case 'tiktok':
        icon_trace($face,$fg,[[46,54],[46,25],[51,34],[60,38]],6);
        imagefilledellipse($face,38,55,17,14,$fg);
        break;
    case 'youtube':
        imagefilledrectangle($face,32,32,57,56,$fg);
        imagefilledellipse($face,31,44,14,24,$fg);
        imagefilledellipse($face,57,44,14,24,$fg);
        for ($y = 37; $y <= 51; $y++) imageline($face,42,$y,53 - (int) ceil(abs(44 - $y) * 11 / 7),$y,$bg);
        break;
}

$output = imagecreatetruecolor(44,44);
imagealphablending($output,false);
imagesavealpha($output,true);
imagecopyresampled($output,$face,0,0,0,0,44,44,88,88);
imagepng($output);
