<?php
declare(strict_types=1);
require __DIR__ . '/app-server.php';

header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
if (($_SERVER['HTTP_X_SIGNEMAIL_EVENT'] ?? '') !== '1') {
    http_response_code(403);
    exit;
}

$event = $_POST['event'] ?? null;
$entity = $_POST['entity'] ?? null;
if (!is_string($event) || !is_string($entity) || strlen($entity) > 150) {
    http_response_code(422);
    exit;
}

$connection = db();
if ($event === 'select' && preg_match('/^[1-9][0-9]{0,15}$/D', $entity)) {
    $check = $connection->prepare('SELECT 1 FROM templates WHERE id=? AND visible=1');
    $check->execute([$entity]);
    $valid = $check->fetchColumn();
} elseif ($event === 'view' && in_array($entity, ['editor','blog'], true)) {
    $valid = true;
} elseif ($event === 'view' && preg_match('/^post:([a-z0-9]+(?:-[a-z0-9]+)*)$/D', $entity, $match)) {
    $check = $connection->prepare("SELECT 1 FROM posts WHERE slug=? AND status='published'");
    $check->execute([$match[1]]);
    $valid = $check->fetchColumn();
} else {
    $valid = false;
}
if (!$valid) {
    http_response_code(422);
    exit;
}

$connection->prepare('INSERT INTO activity_counts(day,event,entity,total) VALUES (?,?,?,1)
    ON CONFLICT(event,day,entity) DO UPDATE SET total=total+1')->execute([activity_start(1), $event, $entity]);
http_response_code(204);
