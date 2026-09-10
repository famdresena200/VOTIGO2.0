<?php
// Serve candidate images publicly (no authentication required).
// This endpoint is used by both the voting UI and the admin UI.
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT image_mime, image_data FROM candidats WHERE id_candidat = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    $mime = (string)($row['image_mime'] ?? '');
    $data = $row['image_data'] ?? null;
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mime, $allowedMimes, true) || !is_string($data) || strlen($data) === 0) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)strlen($data));
    header('Cache-Control: private, max-age=3600');
    echo $data;
} catch (Throwable $e) {
    error_log('VOTIGO: candidate image error: ' . $e->getMessage());
    http_response_code(500);
    exit;
}
