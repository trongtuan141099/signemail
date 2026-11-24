<?php
declare(strict_types=1);

function track_page(string $entity, string $path): void
{
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || $agent === '' ||
        preg_match('/bot|spider|crawler|slurp|facebookexternalhit|bingpreview|googleother|google-inspectiontool|headlesschrome|lighthouse/i', $agent)) return;

    try {
        $db = db();
        immediate_write($db, static function () use ($db, $entity, $path, $agent): void {
            $day = activity_start(1);
            $lookup = $db->prepare('SELECT salt FROM traffic_days WHERE day=?');
            $lookup->execute([$day]);
            $salt = $lookup->fetchColumn();
            if (!$salt) {
                $salt = bin2hex(random_bytes(32));
                $db->prepare('INSERT INTO traffic_days(day,salt) VALUES (?,?) ON CONFLICT(day) DO UPDATE SET salt=excluded.salt')->execute([$day,$salt]);
                $db->prepare('DELETE FROM traffic_seen WHERE day<?')->execute([$day]);
                $db->prepare('UPDATE traffic_days SET salt=NULL WHERE day<? AND salt IS NOT NULL')->execute([$day]);
            }
            $fingerprint = hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . "\n" . $agent, $salt);
            $seen = $db->prepare('INSERT OR IGNORE INTO traffic_seen(day,fingerprint) VALUES (?,?)');
            $seen->execute([$day,$fingerprint]);
            if ($seen->rowCount()) $db->prepare('UPDATE traffic_days SET visitors=visitors+1 WHERE day=?')->execute([$day]);
            $db->prepare('INSERT INTO traffic_paths(entity,path) VALUES (?,?) ON CONFLICT(entity) DO UPDATE SET path=excluded.path WHERE path<>excluded.path')->execute([$entity,$path]);
            $db->prepare("INSERT INTO activity_counts(day,event,entity,total) VALUES (?,'view',?,1) ON CONFLICT(event,day,entity) DO UPDATE SET total=total+1")->execute([$day,$entity]);
        });
    } catch (Throwable $error) {
        error_log('Traffic tracker: ' . $error->getMessage());
    }
}
