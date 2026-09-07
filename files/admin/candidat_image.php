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
    $stmt = $pdo->prepare('SELECT image_mime, image_data FROM CANDIDATS WHERE id_candidat = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row || empty($row['image_mime']) || empty($row['image_data'])) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . (string)$row['image_mime']);
    header('Cache-Control: private, max-age=3600');
    echo $row['image_data'];
} catch (Throwable $e) {
    http_response_code(500);
    exit;
}
