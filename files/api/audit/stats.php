<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error('Methode non autorisee', 405);
}

try {
    require_auth();
    $pdo = api_db();
    api_success([
        'total_elections' => (int)$pdo->query('SELECT COUNT(*) FROM elections')->fetchColumn(),
        'total_votes' => (int)$pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn(),
        'rules' => [
            'one_vote_per_election' => true,
            'anonymous_tokens' => true,
            'server_side_results' => true,
        ],
    ], 'Statistiques audit recuperees.', 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
}
