<?php
declare(strict_types=1);
require __DIR__ . '/app-server.php';

$slot = $_GET['slot'] ?? '';
if (!is_string($slot) || !isset(placements()[$slot]) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(404);
    exit;
}
$db = db();
$target = immediate_write($db, static function () use ($db, $slot): ?string {
    $query = $db->prepare("SELECT target FROM ads WHERE placement=? AND enabled=1 AND mode='banner' AND image<>''");
    $query->execute([$slot]);
    $target = $query->fetchColumn();
    if (!is_string($target) || !web_url($target)) return null;
    $db->prepare('UPDATE ads SET clicks=clicks+1 WHERE placement=?')->execute([$slot]);
    return $target;
});
if ($target === null) {
    http_response_code(404);
    exit;
}
header('Cache-Control: no-store');
redirect($target);
