<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_admin();

$pdo = db();
$error = '';
$info = '';
$selectedElectionId = isset($_GET['e']) ? (int)$_GET['e'] : 0;
// Note: on garde les contrôles de schéma côté serveur (sans affichage UI).

function db_has_columns(PDO $pdo, string $table, array $cols): bool
{
    try {
        $db = (string)($pdo->query('SELECT DATABASE() AS d')->fetch()['d'] ?? '');
        if ($db === '') {
            return false;
        }
        $in = implode(',', array_fill(0, count($cols), '?'));
        $sql = "SELECT COUNT(*) AS c
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ($in)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$db, $table], $cols));
        $count = (int)($stmt->fetch()['c'] ?? 0);
        return $count === count($cols);
    } catch (Throwable $e) {
        return false;
    }
}

$hasOrdre = db_has_columns($pdo, 'candidats', ['ordre']);
$hasImageCols = db_has_columns($pdo, 'candidats', ['image_filename', 'image_mime', 'image_size', 'image_data']);
$hasNumero = db_has_columns($pdo, 'candidats', ['numero']);
$maxImageBytes = 10_000_000; // 10 MB

function normalize_datetime_local(string $value): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    // HTML datetime-local => "YYYY-MM-DDTHH:MM" (parfois avec secondes)
    $v = str_replace('T', ' ', $v);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v)) {
        return $v . ':00';
    }
    return $v;
}

// Vérification d'unicité en temps réel (AJAX)
if (($_POST['action'] ?? '') === 'check_unique') {
    header('Content-Type: application/json');
    $type = $_POST['type'] ?? '';
    $idElection = (int)($_POST['id_election'] ?? 0);
    $value = trim($_POST['value'] ?? '');
    $exists = false;
    if ($type === 'nom' && $idElection > 0 && $value !== '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM candidats WHERE id_election = ? AND nom_candidat = ?');
        $stmt->execute([$idElection, $value]);
        $exists = (int)$stmt->fetch()['c'] > 0;
    } elseif ($type === 'numero' && $hasNumero && $idElection > 0 && $value !== '') {
        $numeroVal = (int)$value;
        if ($numeroVal > 0) {
            $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM candidats WHERE id_election = ? AND numero = ?');
            $stmt->execute([$idElection, $numeroVal]);
            $exists = (int)$stmt->fetch()['c'] > 0;
        }
    }
    echo json_encode(['exists' => $exists]);
    exit;
}

// Suppression d'un candidat
if (($_POST['action'] ?? '') === 'delete_candidat') {
    $idElection = (int)($_POST['id_election'] ?? 0);
    $idCandidat = (int)($_POST['id_candidat'] ?? 0);
    if ($idElection <= 0 || $idCandidat <= 0) {
        $error = "Action invalide.";
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM candidats WHERE id_candidat = :c AND id_election = :e');
            $stmt->execute([':c' => $idCandidat, ':e' => $idElection]);
            header('Location: admin.php?e=' . $idElection . '#bulletin');
            exit;
        } catch (PDOException $e) {
            $error = "Erreur serveur lors de la suppression du candidat.";
        }
    }
}

// Mise à jour d'un candidat (nom/bio/photo)
if (($_POST['action'] ?? '') === 'update_candidat') {
    $idElection = (int)($_POST['id_election'] ?? 0);
    $idCandidat = (int)($_POST['id_candidat'] ?? 0);
    $nomCandidat = trim((string)($_POST['nom_candidat'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $removeImage = (string)($_POST['remove_image'] ?? '') === '1';

    if ($idElection <= 0 || $idCandidat <= 0 || $nomCandidat === '') {
        $error = "Veuillez saisir un nom valide.";
    } else {
        try {
            // Vérifier l'unicité du nom du candidat par élection (en excluant le candidat actuel)
            $stmtName = $pdo->prepare('SELECT COUNT(*) AS c FROM candidats WHERE id_election = :e AND nom_candidat = :n AND id_candidat != :c');
            $stmtName->execute([':e' => $idElection, ':n' => $nomCandidat, ':c' => $idCandidat]);
            if ((int)$stmtName->fetch()['c'] > 0) {
                throw new RuntimeException("Un candidat avec ce nom existe déjà pour cette élection.");
            }
            $image = $_FILES['image'] ?? null;
            $imageFilename = null;
            $imageMime = null;
            $imageSize = null;
            $imageData = null;
            $hasNewImage = false;

            if (is_array($image) && isset($image['tmp_name']) && (int)($image['error'] ?? 0) === UPLOAD_ERR_OK) {
                $tmp = (string)$image['tmp_name'];
                $size = (int)($image['size'] ?? 0);
                $name = (string)($image['name'] ?? '');

                if ($size <= 0 || $size > $maxImageBytes) {
                    throw new RuntimeException('Image trop volumineuse (max 10 MB).');
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file($tmp);
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mime, $allowed, true)) {
                    throw new RuntimeException('Format image non supporté (JPG/PNG/WEBP).');
                }

                $imageFilename = $name !== '' ? $name : 'candidat';
                $imageMime = $mime;
                $imageSize = $size;
                $imageData = file_get_contents($tmp);
                if ($imageData === false) {
                    throw new RuntimeException("Impossible de lire l'image.");
                }
                $hasNewImage = true;
            }

            if (!$hasImageCols && ($removeImage || $hasNewImage)) {
                throw new RuntimeException("Fonctionnalité indisponible pour le moment.");
            }

            if ($removeImage && !$hasNewImage) {
                $stmt = $pdo->prepare(
                    'UPDATE candidats
                     SET nom_candidat = :n, bio = :b,
                         image_filename = NULL, image_mime = NULL, image_size = NULL, image_data = NULL
                     WHERE id_candidat = :c AND id_election = :e'
                );
                $stmt->execute([':n' => $nomCandidat, ':b' => $bio !== '' ? $bio : null, ':c' => $idCandidat, ':e' => $idElection]);
            } elseif ($hasNewImage) {
                $stmt = $pdo->prepare(
                    'UPDATE candidats
                     SET nom_candidat = :n, bio = :b,
                         image_filename = :fn, image_mime = :mime, image_size = :size, image_data = :data
                     WHERE id_candidat = :c AND id_election = :e'
                );
                $stmt->execute([
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                    ':fn' => $imageFilename,
                    ':mime' => $imageMime,
                    ':size' => $imageSize,
                    ':data' => $imageData,
                    ':c' => $idCandidat,
                    ':e' => $idElection,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE candidats SET nom_candidat = :n, bio = :b WHERE id_candidat = :c AND id_election = :e');
                $stmt->execute([':n' => $nomCandidat, ':b' => $bio !== '' ? $bio : null, ':c' => $idCandidat, ':e' => $idElection]);
            }

            header('Location: admin.php?e=' . $idElection . '#bulletin');
            exit;
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : "Erreur serveur lors de la mise à jour du candidat.";
        }
    }
}

// Réorganisation des candidats (drag & drop)
if (($_POST['action'] ?? '') === 'reorder_candidats') {
    $idElection = (int)($_POST['id_election'] ?? 0);
    $order = trim((string)($_POST['order'] ?? ''));
    if ($idElection <= 0 || $order === '') {
        $error = "Ordre invalide.";
    } elseif (!$hasOrdre) {
        $error = "Réorganisation indisponible pour le moment.";
    } else {
        $ids = array_values(array_filter(array_map('intval', explode(',', $order))));
        if (empty($ids)) {
            $error = "Ordre invalide.";
        } else {
            try {
                $pdo->beginTransaction();
                // Vérifie que les IDs appartiennent bien à l'élection
                $in = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM candidats WHERE id_election = ? AND id_candidat IN ($in)");
                $stmt->execute(array_merge([$idElection], $ids));
                $count = (int)$stmt->fetch()['c'];
                if ($count !== count($ids)) {
                    throw new RuntimeException("Ordre invalide.");
                }

                $stmt = $pdo->prepare('UPDATE candidats SET ordre = :o WHERE id_election = :e AND id_candidat = :c');
                $o = 1;
                foreach ($ids as $cid) {
                    $stmt->execute([':o' => $o, ':e' => $idElection, ':c' => $cid]);
                    $o++;
                }
                $pdo->commit();
                header('Location: admin.php?e=' . $idElection . '#bulletin');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e instanceof RuntimeException ? $e->getMessage() : "Erreur serveur lors de la réorganisation.";
            }
        }
    }
}

// Changer rapidement le statut d'une élection
if (($_POST['action'] ?? '') === 'set_status') {
    $idElection = (int)($_POST['id_election'] ?? 0);
    $statut = (string)($_POST['statut'] ?? '');
    if ($idElection <= 0 || !in_array($statut, ['programmé', 'actif', 'fermé'], true)) {
        $error = "Action invalide.";
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE elections SET statut = :s WHERE id_election = :id');
            $stmt->execute([':s' => $statut, ':id' => $idElection]);
            $info = "Statut de l'élection mis à jour.";
        } catch (PDOException $e) {
            $error = "Erreur serveur lors de la mise à jour du statut.";
        }
    }
}

// Ajout d'une élection
if (($_POST['action'] ?? '') === 'add_election') {
    $titre = trim((string)($_POST['titre'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $date_debut = normalize_datetime_local((string)($_POST['date_debut'] ?? ''));
    $date_fin = normalize_datetime_local((string)($_POST['date_fin'] ?? ''));
    $statut = (string)($_POST['statut'] ?? 'programmé');

    if ($titre === '' || $date_debut === '' || $date_fin === '') {
        $error = "Veuillez remplir les champs obligatoires de l'élection.";
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO elections (titre, description, date_debut, date_fin, statut) VALUES (:t, :d, :dd, :df, :s)');
            $stmt->execute([
                ':t' => $titre,
                ':d' => $description !== '' ? $description : null,
                ':dd' => $date_debut,
                ':df' => $date_fin,
                ':s' => in_array($statut, ['programmé', 'actif', 'fermé'], true) ? $statut : 'programmé',
            ]);
            $newId = (int)$pdo->lastInsertId();
            header('Location: admin.php?e=' . $newId . '#bulletin');
            exit;
        } catch (PDOException $e) {
            $error = "Erreur serveur lors de l'ajout de l'élection.";
        }
    }
}

// Ajout d'un candidat
if (($_POST['action'] ?? '') === 'add_candidat') {
    $idElection = (int)($_POST['id_election'] ?? 0);
    $nomCandidat = trim((string)($_POST['nom_candidat'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $numero = trim((string)($_POST['numero'] ?? ''));
    $numeroVal = $numero === '' ? null : (int)$numero;

    if ($idElection <= 0 || $nomCandidat === '') {
        $error = "Veuillez sélectionner une élection et saisir le nom du candidat.";
    } else {
        try {
            if (!$hasNumero && $numero !== '') {
                throw new RuntimeException("Fonctionnalité indisponible pour le moment.");
            }
            if ($hasNumero && $numeroVal !== null && $numeroVal <= 0) {
                throw new RuntimeException("Numéro invalide (doit être > 0).");
            }
            if ($hasNumero && $numeroVal !== null) {
                $stmtN = $pdo->prepare('SELECT COUNT(*) AS c FROM candidats WHERE id_election = :e AND numero = :n');
                $stmtN->execute([':e' => $idElection, ':n' => $numeroVal]);
                if ((int)($stmtN->fetch()['c'] ?? 0) > 0) {
                    throw new RuntimeException("Ce numéro est déjà utilisé pour cette élection.");
                }
            }

            // Vérifier l'unicité du nom du candidat par élection
            $stmtName = $pdo->prepare('SELECT COUNT(*) AS c FROM candidats WHERE id_election = :e AND nom_candidat = :n');
            $stmtName->execute([':e' => $idElection, ':n' => $nomCandidat]);
            if ((int)$stmtName->fetch()['c'] > 0) {
                throw new RuntimeException("Un candidat avec ce nom existe déjà pour cette élection.");
            }

            $image = $_FILES['image'] ?? null;
            $imageFilename = null;
            $imageMime = null;
            $imageSize = null;
            $imageData = null;

            if (!$hasImageCols && is_array($image) && isset($image['tmp_name']) && (int)($image['error'] ?? 0) === UPLOAD_ERR_OK) {
                throw new RuntimeException("Fonctionnalité indisponible pour le moment.");
            }

            if (is_array($image) && isset($image['tmp_name']) && (int)($image['error'] ?? 0) === UPLOAD_ERR_OK) {
                $tmp = (string)$image['tmp_name'];
                $size = (int)($image['size'] ?? 0);
                $name = (string)($image['name'] ?? '');

                if ($size <= 0 || $size > $maxImageBytes) {
                    throw new RuntimeException('Image trop volumineuse (max 10 MB).');
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file($tmp);
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mime, $allowed, true)) {
                    throw new RuntimeException('Format image non supporté (JPG/PNG/WEBP).');
                }

                $imageFilename = $name !== '' ? $name : 'candidat';
                $imageMime = $mime;
                $imageSize = $size;
                $imageData = file_get_contents($tmp);
                if ($imageData === false) {
                    throw new RuntimeException("Impossible de lire l'image.");
                }
            }

            $ordre = 0;
            if ($hasOrdre) {
                $stmtOrd = $pdo->prepare('SELECT COALESCE(MAX(ordre), 0) AS m FROM candidats WHERE id_election = :e');
                $stmtOrd->execute([':e' => $idElection]);
                $ordre = (int)($stmtOrd->fetch()['m'] ?? 0) + 1;
            }

            if ($hasOrdre && $hasImageCols && $hasNumero) {
                $stmt = $pdo->prepare(
                    'INSERT INTO candidats (id_election, nom_candidat, bio, numero, ordre, image_filename, image_mime, image_size, image_data)
                     VALUES (:e, :n, :b, :num, :o, :fn, :mime, :size, :data)'
                );
                $stmt->execute([
                    ':e' => $idElection,
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                    ':num' => $numeroVal,
                    ':o' => $ordre,
                    ':fn' => $imageFilename,
                    ':mime' => $imageMime,
                    ':size' => $imageSize,
                    ':data' => $imageData,
                ]);
            } elseif ($hasOrdre && $hasImageCols && !$hasNumero) {
                $stmt = $pdo->prepare(
                    'INSERT INTO candidats (id_election, nom_candidat, bio, ordre, image_filename, image_mime, image_size, image_data)
                     VALUES (:e, :n, :b, :o, :fn, :mime, :size, :data)'
                );
                $stmt->execute([
                    ':e' => $idElection,
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                    ':o' => $ordre,
                    ':fn' => $imageFilename,
                    ':mime' => $imageMime,
                    ':size' => $imageSize,
                    ':data' => $imageData,
                ]);
            } elseif ($hasOrdre && !$hasImageCols && $hasNumero) {
                $stmt = $pdo->prepare(
                    'INSERT INTO candidats (id_election, nom_candidat, bio, numero, ordre)
                     VALUES (:e, :n, :b, :num, :o)'
                );
                $stmt->execute([
                    ':e' => $idElection,
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                    ':num' => $numeroVal,
                    ':o' => $ordre,
                ]);
            } elseif ($hasOrdre && !$hasImageCols && !$hasNumero) {
                $stmt = $pdo->prepare(
                    'INSERT INTO candidats (id_election, nom_candidat, bio, ordre)
                     VALUES (:e, :n, :b, :o)'
                );
                $stmt->execute([
                    ':e' => $idElection,
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                    ':o' => $ordre,
                ]);
            } elseif (!$hasOrdre && $hasImageCols && $hasNumero) {
                $stmt = $pdo->prepare(
                    'INSERT INTO candidats (id_election, nom_candidat, bio, numero, image_filename, image_mime, image_size, image_data)
                     VALUES (:e, :n, :b, :num, :fn, :mime, :size, :data)'
                );
                $stmt->execute([
                    ':e' => $idElection,
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                    ':num' => $numeroVal,
                    ':fn' => $imageFilename,
                    ':mime' => $imageMime,
                    ':size' => $imageSize,
                    ':data' => $imageData,
                ]);
            } elseif (!$hasOrdre && $hasImageCols && !$hasNumero) {
                $stmt = $pdo->prepare(
                    'INSERT INTO candidats (id_election, nom_candidat, bio, image_filename, image_mime, image_size, image_data)
                     VALUES (:e, :n, :b, :fn, :mime, :size, :data)'
                );
                $stmt->execute([
                    ':e' => $idElection,
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                    ':fn' => $imageFilename,
                    ':mime' => $imageMime,
                    ':size' => $imageSize,
                    ':data' => $imageData,
                ]);
            } elseif (!$hasOrdre && !$hasImageCols && $hasNumero) {
                $stmt = $pdo->prepare(
                    'INSERT INTO candidats (id_election, nom_candidat, bio, numero)
                     VALUES (:e, :n, :b, :num)'
                );
                $stmt->execute([
                    ':e' => $idElection,
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                    ':num' => $numeroVal,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO candidats (id_election, nom_candidat, bio)
                     VALUES (:e, :n, :b)'
                );
                $stmt->execute([
                    ':e' => $idElection,
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                ]);
            }
            header('Location: admin.php?e=' . $idElection . '#bulletin');
            exit;
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : "Erreur serveur lors de l'ajout du candidat.";
        }
    }
}

$qe = trim((string)($_GET['qe'] ?? ''));
$es = trim((string)($_GET['es'] ?? '')); // statut filtre: actif|programmé|fermé|''
$es = in_array($es, ['actif', 'programmé', 'fermé'], true) ? $es : '';
$sortE = (string)($_GET['sortE'] ?? 'id_desc'); // id_desc|debut_desc|fin_desc|debut_asc|fin_asc
$allowedSortE = ['id_desc', 'debut_desc', 'fin_desc', 'debut_asc', 'fin_asc'];
$sortE = in_array($sortE, $allowedSortE, true) ? $sortE : 'id_desc';
$pe = max(1, (int)($_GET['pe'] ?? 1));
$perPageE = 15;
$offsetE = ($pe - 1) * $perPageE;

// Pagination + recherche élections
$totalElections = (int)$pdo->query('SELECT COUNT(*) AS c FROM elections')->fetch()['c'];
$whereE = [];
$paramsE = [];
if ($qe !== '') {
    $whereE[] = '(titre LIKE :q OR description LIKE :q OR statut LIKE :q)';
    $paramsE[':q'] = '%' . $qe . '%';
}
if ($es !== '') {
    $whereE[] = 'statut = :es';
    $paramsE[':es'] = $es;
}
$whereSqlE = empty($whereE) ? '' : ('WHERE ' . implode(' AND ', $whereE));

$stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM elections $whereSqlE");
$stmt->execute($paramsE);
$filteredElections = (int)$stmt->fetch()['c'];

$totalPagesE = max(1, (int)ceil($filteredElections / $perPageE));
$pe = min($pe, $totalPagesE);
$offsetE = ($pe - 1) * $perPageE;

// NOTE: avec PDO::ATTR_EMULATE_PREPARES=false, on évite de binder LIMIT/OFFSET.
$limitE = (int)$perPageE;
$offE = (int)$offsetE;
$orderSqlE = 'id_election DESC';
if ($sortE === 'debut_desc') $orderSqlE = 'date_debut DESC, id_election DESC';
if ($sortE === 'fin_desc') $orderSqlE = 'date_fin DESC, id_election DESC';
if ($sortE === 'debut_asc') $orderSqlE = 'date_debut ASC, id_election ASC';
if ($sortE === 'fin_asc') $orderSqlE = 'date_fin ASC, id_election ASC';

$stmt = $pdo->prepare("SELECT id_election, titre, statut, date_debut, date_fin FROM elections $whereSqlE ORDER BY $orderSqlE LIMIT $limitE OFFSET $offE");
$stmt->execute($paramsE);
$elections = $stmt->fetchAll();
$selectedElectionId = $selectedElectionId > 0 ? $selectedElectionId : (int)($elections[0]['id_election'] ?? 0);

$candidats = [];
if ($selectedElectionId > 0) {
    try {
        if ($hasOrdre) {
            $stmt = $pdo->prepare('SELECT id_candidat, nom_candidat, bio, ordre, image_mime FROM candidats WHERE id_election = :e ORDER BY ordre ASC, id_candidat ASC');
        } else {
            $stmt = $pdo->prepare('SELECT id_candidat, nom_candidat, bio, image_mime FROM candidats WHERE id_election = :e ORDER BY id_candidat ASC');
        }
        $stmt->execute([':e' => $selectedElectionId]);
        $candidats = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Impossible de charger les candidats pour le moment.";
        $candidats = [];
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$us = (string)($_GET['us'] ?? 'id_desc'); // tri électeurs
$allowedUs = ['id_desc', 'nom_asc', 'nom_desc', 'date_desc', 'date_asc'];
$us = in_array($us, $allowedUs, true) ? $us : 'id_desc';
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Compteurs + pagination scalable
$totalUsers = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
if ($q !== '') {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM users WHERE nom LIKE :q OR email LIKE :q');
    $stmt->execute([':q' => '%' . $q . '%']);
    $filteredUsers = (int)$stmt->fetch()['c'];
} else {
    $filteredUsers = $totalUsers;
}
$totalPages = max(1, (int)ceil($filteredUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$orderU = 'id_user DESC';
if ($us === 'nom_asc') $orderU = 'nom ASC, id_user DESC';
if ($us === 'nom_desc') $orderU = 'nom DESC, id_user DESC';
if ($us === 'date_desc') $orderU = 'date_inscription DESC, id_user DESC';
if ($us === 'date_asc') $orderU = 'date_inscription ASC, id_user ASC';

if ($q !== '') {
    $limitU = (int)$perPage;
    $offU = (int)$offset;
    $stmt = $pdo->prepare("SELECT id_user, nom, email, date_inscription FROM users WHERE nom LIKE :q OR email LIKE :q ORDER BY $orderU LIMIT $limitU OFFSET $offU");
    $stmt->execute([':q' => '%' . $q . '%']);
    $users = $stmt->fetchAll();
} else {
    $limitU = (int)$perPage;
    $offU = (int)$offset;
    $users = $pdo->query("SELECT id_user, nom, email, date_inscription FROM users ORDER BY $orderU LIMIT $limitU OFFSET $offU")->fetchAll();
}

?>
<?php
votigo_layout_start('Administration — VOTIGO', 'admin', [
    'userName' => auth_user_name(),
    'verified' => true,
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
    'logoAlt' => 'Logo Votigo',
    'secondaryLogo' => '../../images/logo_ispm.png',
    'secondaryLogoAlt' => 'Logo ISPM',
    'navMode' => 'admin',
    'isAdmin' => true,
]);
?>

<div class="h1">Administration</div>
<div class="muted">Ajoutez des élections et des candidats. Les électeurs verront automatiquement les élections actives.</div>

<?php if ($info !== ''): ?>
  <div data-flash-type="ok" data-flash-title="Administration" data-flash-message="<?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div data-flash-type="err" data-flash-title="Administration" data-flash-message="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>

<style>
  /* Admin UI polish (scoped) */
  #adminPage{--admin-gap:16px}
  #adminPage .pill{display:inline-flex;gap:10px;align-items:center;padding:10px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.10);background:rgba(5,18,28,.22)}
  #adminPage .hint{font-size:12px;color:var(--muted);margin-top:6px;line-height:1.35}

  #adminPage .admin-toolbar{
    position:sticky; top:14px; z-index:20;
    margin-top:12px;
    display:flex; gap:10px; align-items:center; flex-wrap:wrap;
    padding:10px 10px;
    border-radius:18px;
    background:linear-gradient(180deg, rgba(10,28,42,.78), rgba(10,28,42,.55));
    border:1px solid rgba(255,255,255,.08);
    backdrop-filter: blur(10px);
  }
  #adminPage .admin-toolbar .spacer{margin-left:auto}
  #adminPage .btn-group{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  #adminPage .btn-group .btn{white-space:nowrap}

  #adminPage .admin-grid{margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:var(--admin-gap)}
  #adminPage .row{align-items:start}
  #adminPage .split{display:grid;grid-template-columns:1.25fr .75fr;gap:16px}
  #adminPage textarea{resize:vertical}
  /* Make the two create-cards same height + align CTAs */
  #adminPage .admin-grid > .card{display:flex;flex-direction:column}
  #adminPage .admin-grid > .card .bd{display:flex;flex-direction:column;flex:1}
  #adminPage .admin-grid > .card form{display:flex;flex-direction:column;flex:1}
  #adminPage .admin-grid > .card form .tools{margin-top:auto}
  #adminPage .admin-grid > .card form .tools{padding-top:2px}
  #adminPage .create-footer{margin-top:auto;display:grid;gap:8px}
  #adminPage .create-note{margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

  #adminPage .upload{display:flex;gap:12px;align-items:center}
  #adminPage .thumb{width:54px;height:54px;border-radius:14px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);display:grid;place-items:center;overflow:hidden;flex:0 0 auto}
  #adminPage .thumb.lg{width:78px;height:78px;border-radius:22px;box-shadow:0 14px 60px rgba(0,0,0,.28);transform:translateZ(0);transition:transform .18s ease, filter .18s ease, box-shadow .18s ease;position:relative;cursor:pointer}
  #adminPage .thumb.lg::after{content:"";position:absolute;inset:-30%;background:radial-gradient(circle at 30% 30%, rgba(255,255,255,.22), rgba(255,255,255,0) 55%);opacity:.0;transition:opacity .18s ease;pointer-events:none}
  #adminPage .thumb.lg:hover{transform:translateY(-2px) scale(1.02);filter:brightness(1.08);box-shadow:0 18px 80px rgba(26,169,184,.10)}
  #adminPage .thumb.lg:hover::after{opacity:.6}
  @media (prefers-reduced-motion: reduce){#adminPage .thumb.lg, #adminPage .thumb.lg:hover{transition:none;transform:none}#adminPage .thumb.lg::after{transition:none}}
  #adminPage .thumb img{width:100%;height:100%;object-fit:cover}
  #adminPage .file-pill{
    margin-top:4px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 14px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.16);
    background:rgba(5,18,28,.85);
    cursor:pointer;
    font-size:13px;
    position:relative;
    overflow:hidden;
  }
  #adminPage .file-pill input[type=file]{
    position:absolute;
    inset:0;
    opacity:0;
    cursor:pointer;
  }
  #adminPage .file-pill:hover{
    filter:brightness(1.07);
    border-color:rgba(26,169,184,.45);
  }

  #adminPage details.admin-acc{
    border-radius:var(--r22);
    background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
    border:1px solid rgba(255,255,255,.10);
    box-shadow:var(--shadow);
    overflow:hidden;
    margin-top:16px;
  }
  #adminPage details.admin-acc > summary{
    list-style:none;
    cursor:pointer;
    display:flex;align-items:center;gap:10px;
    padding:16px;
    user-select:none;
  }
  #adminPage details.admin-acc > summary::-webkit-details-marker{display:none}
  #adminPage details.admin-acc > summary .k{font-weight:900}
  #adminPage details.admin-acc > summary .meta{margin-left:auto;color:var(--muted);font-size:13px}
  #adminPage details.admin-acc .acc-body{padding:0 16px 16px}

  #adminPage .candidate{display:flex;gap:12px;align-items:center}
  #adminPage .candidate.dragging{opacity:.55}
  #adminPage .handle{cursor:grab;user-select:none;opacity:.85}
  #adminPage .handle:active{cursor:grabbing}
  #adminPage .tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  #adminPage .mini .badge{margin-left:0}

  /* Compact mode: shorter page */
  #adminPage.compact .card .bd{padding:14px}
  #adminPage.compact .mini{padding:12px}
  #adminPage.compact textarea{min-height:72px}

  /* Better list rows */
  #adminPage .list-row{
    display:grid;
    grid-template-columns: 72px 1fr auto;
    gap:12px;
    align-items:center;
  }
  #adminPage .list-row .right{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
  #adminPage .list-row .id{font-weight:900;min-width:72px}
  #adminPage .list-row .sub{color:var(--muted);font-size:13px;margin-top:4px}
  #adminPage .list-row .meta2{color:var(--muted);font-size:12px;white-space:nowrap}

  #adminPage .filters{
    margin:0 0 12px 0;
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
  }
  #adminPage .filters .grow{flex:1;min-width:240px}
  #adminPage .filters .sel{min-width:220px}

  #adminPage .seg{
    display:flex;gap:8px;align-items:center;flex-wrap:wrap;
    margin-bottom:12px;
  }
  #adminPage .seg .btn{padding:9px 14px}

  @media (max-width: 980px){
    #adminPage .admin-grid{grid-template-columns:1fr}
    #adminPage .split{grid-template-columns:1fr}
    #adminPage .list-row{grid-template-columns: 72px 1fr}
    #adminPage .list-row .right{grid-column: 1 / -1; justify-content:flex-start}
  }

  @media (max-width: 768px){
    #adminPage{--admin-gap:12px}
    #adminPage .admin-toolbar{
      flex-direction: column;
      align-items: stretch;
      gap: 12px;
      padding: 12px;
    }
    #adminPage .admin-toolbar .spacer{display: none}
    #adminPage .btn-group{justify-content: center; flex-wrap: wrap}
    #adminPage .admin-grid{margin-top:12px}
    #adminPage .card .hd{font-size: 16px}
    #adminPage .h1{font-size: 24px}
    #adminPage .thumb.lg{width: 60px; height: 60px}
    #adminPage .filters .grow{min-width: 200px}
    #adminPage .filters .sel{min-width: 180px}
  }

  @media (max-width: 480px){
    #adminPage .admin-toolbar{padding: 8px}
    #adminPage .btn-group{gap: 6px}
    #adminPage .btn-group .btn{font-size: 13px; padding: 8px 12px}
    #adminPage .card .bd{padding: 12px}
    #adminPage .split{gap: 12px}
    #adminPage .upload{flex-direction: column; align-items: flex-start; gap: 8px}
    #adminPage .thumb{width: 48px; height: 48px}
    #adminPage .file-pill{font-size: 12px; padding: 6px 10px}
    #adminPage .list-row{grid-template-columns: 60px 1fr; gap: 8px}
    #adminPage .list-row .id{min-width: 60px; font-size: 14px}
    #adminPage .filters{gap: 8px}
    #adminPage .filters .grow{min-width: 150px}
    #adminPage .filters .sel{min-width: 140px}
    #adminPage .seg{gap: 6px; margin-bottom: 8px}
    #adminPage .seg .btn{font-size: 13px; padding: 7px 10px}
  }
</style>

<div id="adminPage">
  <div class="admin-toolbar">
    <div class="btn-group" aria-label="Raccourcis">
      <a class="btn ghost" href="election.php?e=<?= (int)$selectedElectionId ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Candidats</a>
      <a class="btn ghost" href="elections.php?e=<?= (int)$selectedElectionId ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Élections</a>
      <a class="btn ghost" href="electeurs.php?e=<?= (int)$selectedElectionId ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Électeurs</a>
    </div>
    <div class="spacer"></div>
    <div class="btn-group" aria-label="Options">
      <a class="btn ghost" href="logout.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Déconnexion</a>
    </div>
  </div>

<div class="admin-grid" id="create">
  <section class="card">
    <div class="hd">
      <h3>Ajouter une élection</h3>
      <div class="spacer"></div>
      <span class="muted" style="font-size:13px">Planification</span>
    </div>
    <div class="bd">
      <form method="post" novalidate>
        <input type="hidden" name="action" value="add_election" />
        <div class="row">
          <div>
            <label for="titre">Titre</label>
            <input id="titre" name="titre" type="text" required />
          </div>
          <div>
            <label for="statut">Statut</label>
            <select id="statut" name="statut">
              <option value="programmé">programmé</option>
              <option value="actif">actif</option>
              <option value="fermé">fermé</option>
            </select>
          </div>
        </div>

        <div class="row" style="margin-top:12px">
          <div>
            <label for="date_debut">Date début</label>
            <input id="date_debut" name="date_debut" type="datetime-local" required />
            
          </div>
          <div>
            <label for="date_fin">Date fin</label>
            <input id="date_fin" name="date_fin" type="datetime-local" required />
            <label class="muted" style="display:flex;gap:8px;align-items:center;margin-top:10px">
              <input type="checkbox" id="activateNow" style="width:auto" />
              Activer maintenant
            </label>
          </div>
        </div>

        <div style="margin-top:12px">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="(optionnel)"></textarea>
        </div>

        <div class="create-footer">
          <div class="tools">
            <button type="submit" class="btn primary" data-loading="Ajout...">Ajouter</button>
          </div>
          
        </div>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="hd">
      <h3>Ajouter un candidat</h3>
      <div class="spacer"></div>
      <span class="muted" style="font-size:13px">Contenu</span>
    </div>
    <div class="bd">
      <form method="post" novalidate enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_candidat" />
        <div>
          <label for="id_election">Élection</label>
          <select id="id_election" name="id_election" required>
            <option value="">-- sélectionner --</option>
            <?php foreach ($elections as $e): ?>
              <option value="<?= (int)$e['id_election'] ?>" <?= (int)$e['id_election'] === (int)$selectedElectionId ? 'selected' : '' ?>>
                #<?= (int)$e['id_election'] ?> — <?= htmlspecialchars((string)$e['titre'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="split" style="margin-top:12px">
          <div>
            <?php if ($hasNumero): ?>
            <label for="numero">Numéro du candidat (manuel)</label>
            <input id="numero" name="numero" type="number" inputmode="numeric" min="1" placeholder="Ex: 7" />
            <div class="hint">Doit être unique par élection.</div>
            <span id="error-numero" class="error-msg" style="display:none;color:red;"></span>
            <?php endif; ?>

            <div style="margin-top:12px">
              <label for="nom_candidat">Nom du candidat *</label>
              <input id="nom_candidat" name="nom_candidat" type="text" required maxlength="200" placeholder="Ex: Jean Dupont" />
              <div class="hint">Doit être unique par élection.</div>
              <span id="error-nom" class="error-msg" style="display:none;color:red;"></span>
            </div>
              <label for="image">Photo (optionnel)</label>
              <div class="upload">
                <div class="thumb lg js-zoom-img" id="thumb" data-full-src=""><span class="muted">IMG</span></div>
                <div style="flex:1">
                  <label class="file-pill">
                    <span>Parcourir…</span>
                    <input id="image" name="image" type="file" accept="image/png,image/jpeg,image/webp" />
                  </label>
                  <div class="muted" style="font-size:12px;margin-top:6px">Formats acceptés: PNG, JPEG, WebP (max 10MB). Aperçu ci-dessus après sélection.</div>
                </div>
              </div>
            </div>
          </div>
          <div>
            <label for="bio">Biographie</label>
            <textarea id="bio" name="bio" placeholder="Une description courte et convaincante… (optionnel)" maxlength="1000"></textarea>
          </div>
        </div>

        <div class="create-footer">
          <div class="tools">
            <button type="submit" class="btn primary" data-loading="Ajout...">Ajouter</button>
          </div>
          
        </div>
      </form>
    </div>
  </section>
</div>

<script>
  (function(){
    const input = document.getElementById('image');
    const thumb = document.getElementById('thumb');
    if (!input || !thumb) return;
    input.addEventListener('change', () => {
      const f = input.files && input.files[0];
      if (!f) {
        thumb.setAttribute('data-full-src', '');
        thumb.innerHTML = '<span class="muted">IMG</span>';
        return;
      }
      if (!f.type || !f.type.startsWith('image/')) {
        thumb.setAttribute('data-full-src', '');
        thumb.innerHTML = '<span class="muted">IMG</span>';
        return;
      }
      const url = URL.createObjectURL(f);
      thumb.setAttribute('data-full-src', url);
      thumb.innerHTML = '<img alt="" src="' + url + '">';
    });
  })();

  // Vérification d'unicité en temps réel
  (function(){
    const nomInput = document.getElementById('nom_candidat');
    const numeroInput = document.getElementById('numero');
    const electionSelect = document.getElementById('id_election');
    let checkTimeout;

    function checkUnique(type, value, idElection) {
      if (!value || !idElection) return;
      fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=check_unique&type=${type}&value=${encodeURIComponent(value)}&id_election=${idElection}`
      })
      .then(r => r.json())
      .then(data => {
        const errorEl = document.getElementById(`error-${type}`);
        if (data.exists) {
          errorEl.textContent = type === 'nom' ? 'Ce nom est déjà utilisé pour cette élection.' : 'Ce numéro est déjà utilisé pour cette élection.';
          errorEl.style.display = 'block';
        } else {
          errorEl.style.display = 'none';
        }
      })
      .catch(() => {
        // Ignore errors
      });
    }

    if (nomInput) {
      nomInput.addEventListener('input', () => {
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => {
          checkUnique('nom', nomInput.value.trim(), electionSelect.value);
        }, 500);
      });
      electionSelect.addEventListener('change', () => {
        if (nomInput.value.trim()) checkUnique('nom', nomInput.value.trim(), electionSelect.value);
        if (numeroInput && numeroInput.value.trim()) checkUnique('numero', numeroInput.value.trim(), electionSelect.value);
      });
    }

    if (numeroInput) {
      numeroInput.addEventListener('input', () => {
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => {
          checkUnique('numero', numeroInput.value.trim(), electionSelect.value);
        }, 500);
      });
    }
  })();
</script>

<script>
  (function(){
    const start = document.getElementById('date_debut');
    const end = document.getElementById('date_fin');
    const activateNow = document.getElementById('activateNow');
    const statut = document.getElementById('statut');
    if (!start || !end || !activateNow || !statut) return;

    function addDays(dt, days){
      const d = new Date(dt.getTime());
      d.setDate(d.getDate() + days);
      return d;
    }
    function toLocalValue(dt){
      const pad = (n) => String(n).padStart(2,'0');
      return dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate()) + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
    }

    activateNow.addEventListener('change', () => {
      if (activateNow.checked) statut.value = 'actif';
    });

    start.addEventListener('change', () => {
      if (!start.value) return;
      const dt = new Date(start.value);
      if (isNaN(dt.getTime())) return;
      end.value = toLocalValue(addDays(dt, 7));
    });
  })();
</script>

</div>

<?php votigo_layout_end(); ?>

