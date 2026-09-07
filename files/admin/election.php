<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_admin();

$pdo = db();
$error = '';
$info = '';
$maxImageBytes = 10_000_000; // 10 MB

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

// Liste d'élections (pour le sélecteur)
$elections = [];
try {
    $stmt = $pdo->query('SELECT id_election, titre, statut FROM elections ORDER BY id_election DESC');
    $elections = $stmt->fetchAll();
} catch (PDOException $e) {
    $elections = [];
}

$selectedElectionId = isset($_GET['e']) ? (int)$_GET['e'] : 0;
if ($selectedElectionId <= 0) {
    $selectedElectionId = (int)($elections[0]['id_election'] ?? 0);
}
if ($selectedElectionId <= 0) {
    header('Location: elections.php');
    exit;
}

// Suppression d'un candidat
if (($_POST['action'] ?? '') === 'delete_candidat') {
    $idElection = (int)($_POST['id_election'] ?? 0);
    $idCandidat = (int)($_POST['id_candidat'] ?? 0);
    if ($idElection !== $selectedElectionId || $idCandidat <= 0) {
        $error = "Action invalide.";
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM candidats WHERE id_candidat = :c AND id_election = :e');
            $stmt->execute([':c' => $idCandidat, ':e' => $idElection]);
            header('Location: election.php?e=' . $idElection . '#candidats');
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
    $numero = trim((string)($_POST['numero'] ?? ''));
    $numeroVal = $numero === '' ? null : (int)$numero;
    $removeImage = (string)($_POST['remove_image'] ?? '') === '1';

    if ($idElection !== $selectedElectionId || $idCandidat <= 0 || $nomCandidat === '') {
        $error = "Veuillez saisir un nom valide.";
    } else {
        try {
            if (!$hasNumero && $numero !== '') {
                throw new RuntimeException("Fonctionnalité indisponible pour le moment.");
            }
            if ($hasNumero && $numeroVal !== null && $numeroVal <= 0) {
                throw new RuntimeException("Numéro invalide (doit être > 0).");
            }
            if ($hasNumero && $numeroVal !== null) {
                $stmtN = $pdo->prepare('SELECT COUNT(*) AS c FROM candidats WHERE id_election = :e AND numero = :n AND id_candidat <> :c');
                $stmtN->execute([':e' => $idElection, ':n' => $numeroVal, ':c' => $idCandidat]);
                if ((int)($stmtN->fetch()['c'] ?? 0) > 0) {
                    throw new RuntimeException("Ce numéro est déjà utilisé pour cette élection.");
                }
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

            $setNumeroSql = $hasNumero ? ', numero = :num' : '';
            $numParam = $hasNumero ? [':num' => $numeroVal] : [];

            if ($removeImage && !$hasNewImage) {
                $stmt = $pdo->prepare(
                    'UPDATE candidats
                     SET nom_candidat = :n, bio = :b,
                         image_filename = NULL, image_mime = NULL, image_size = NULL, image_data = NULL' . $setNumeroSql . '
                     WHERE id_candidat = :c AND id_election = :e'
                );
                $stmt->execute(array_merge([':n' => $nomCandidat, ':b' => $bio !== '' ? $bio : null, ':c' => $idCandidat, ':e' => $idElection], $numParam));
            } elseif ($hasNewImage) {
                $stmt = $pdo->prepare(
                    'UPDATE candidats
                     SET nom_candidat = :n, bio = :b,
                         image_filename = :fn, image_mime = :mime, image_size = :size, image_data = :data' . $setNumeroSql . '
                     WHERE id_candidat = :c AND id_election = :e'
                );
                $stmt->execute(array_merge([
                    ':n' => $nomCandidat,
                    ':b' => $bio !== '' ? $bio : null,
                    ':fn' => $imageFilename,
                    ':mime' => $imageMime,
                    ':size' => $imageSize,
                    ':data' => $imageData,
                    ':c' => $idCandidat,
                    ':e' => $idElection,
                ], $numParam));
            } else {
                $stmt = $pdo->prepare('UPDATE candidats SET nom_candidat = :n, bio = :b' . $setNumeroSql . ' WHERE id_candidat = :c AND id_election = :e');
                $stmt->execute(array_merge([':n' => $nomCandidat, ':b' => $bio !== '' ? $bio : null, ':c' => $idCandidat, ':e' => $idElection], $numParam));
            }

            $info = "Candidat mis à jour.";
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : "Erreur serveur lors de la mise à jour du candidat.";
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

    if ($idElection !== $selectedElectionId || $nomCandidat === '') {
        $error = "Veuillez saisir le nom du candidat.";
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

            header('Location: election.php?e=' . $idElection . '#candidats');
            exit;
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : "Erreur serveur lors de l'ajout du candidat.";
        }
    }
}

// Réorganisation des candidats (drag & drop)
if (($_POST['action'] ?? '') === 'reorder_candidats') {
    $idElection = (int)($_POST['id_election'] ?? 0);
    $order = trim((string)($_POST['order'] ?? ''));
    if ($idElection !== $selectedElectionId || $order === '') {
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
                header('Location: election.php?e=' . $idElection . '#candidats');
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

// Données élection
try {
    $stmt = $pdo->prepare('SELECT id_election, titre, description, statut, date_debut, date_fin FROM elections WHERE id_election = :e');
    $stmt->execute([':e' => $selectedElectionId]);
    $election = $stmt->fetch();
    if (!$election) {
        header('Location: elections.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "Erreur serveur lors du chargement de l'élection.";
    $election = ['id_election' => $selectedElectionId, 'titre' => 'Élection', 'description' => null, 'statut' => '', 'date_debut' => '', 'date_fin' => ''];
}

// Candidats
$candidats = [];
try {
    if ($hasOrdre) {
        $stmt = $pdo->prepare('SELECT id_candidat, nom_candidat, bio, numero, ordre, image_mime FROM candidats WHERE id_election = :e ORDER BY ordre ASC, id_candidat ASC');
    } else {
        $stmt = $pdo->prepare('SELECT id_candidat, nom_candidat, bio, numero, image_mime FROM candidats WHERE id_election = :e ORDER BY id_candidat ASC');
    }
    $stmt->execute([':e' => $selectedElectionId]);
    $candidats = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Impossible de charger les candidats pour le moment.";
    $candidats = [];
}

votigo_layout_start('Candidats — Administration — VOTIGO', 'admin', [
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

<div class="h1">Gestion des candidats</div>
<div class="muted">
  Élection #<?= (int)$election['id_election'] ?> — <?= htmlspecialchars((string)$election['titre'], ENT_QUOTES, 'UTF-8') ?>
  • <span class="badge"><?= htmlspecialchars((string)$election['statut'], ENT_QUOTES, 'UTF-8') ?></span>
</div>

<?php if ($info !== ''): ?>
  <div data-flash-type="ok" data-flash-title="Candidats" data-flash-message="<?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div data-flash-type="err" data-flash-title="Candidats" data-flash-message="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>

<style>
  #candidatsPage .toolbar{
    position:sticky;top:14px;z-index:20;margin-top:12px;
    display:flex;gap:10px;align-items:center;flex-wrap:wrap;
    padding:10px;border-radius:18px;
    background:linear-gradient(180deg, rgba(10,28,42,.78), rgba(10,28,42,.55));
    border:1px solid rgba(255,255,255,.08);
    backdrop-filter: blur(10px);
  }
  #candidatsPage .toolbar .spacer{margin-left:auto}
  #candidatsPage .upload{display:flex;gap:12px;align-items:center}
  #candidatsPage .thumb{width:54px;height:54px;border-radius:14px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);display:grid;place-items:center;overflow:hidden;flex:0 0 auto}
  #candidatsPage .thumb.lg{width:78px;height:78px;border-radius:22px}
  #candidatsPage .thumb img{width:100%;height:100%;object-fit:cover}
  /* Bigger current-photo inside modal */
  #candidatsPage #editThumb.thumb.lg{
    width:100%;
    height:340px;
    border-radius:22px;
  }
  #candidatsPage #editThumb.thumb.lg img{object-fit:cover}
  /* Photo hover animation */
  #candidatsPage .thumb.lg{
    box-shadow:0 14px 60px rgba(0,0,0,.28);
    transform:translateZ(0);
    transition:transform .18s ease, filter .18s ease, box-shadow .18s ease;
    position:relative;
  }
  #candidatsPage .thumb.lg::after{
    content:"";
    position:absolute;inset:-30%;
    background:radial-gradient(circle at 30% 30%, rgba(255,255,255,.22), rgba(255,255,255,0) 55%);
    opacity:.0;
    transition:opacity .18s ease;
    pointer-events:none;
  }
  #candidatsPage .candidate:hover .thumb.lg{
    transform:translateY(-2px) scale(1.02);
    filter:brightness(1.08);
    box-shadow:0 18px 80px rgba(26,169,184,.10);
  }
  #candidatsPage .candidate:hover .thumb.lg::after{opacity:.6}
  @media (prefers-reduced-motion: reduce){
    #candidatsPage .thumb.lg,
    #candidatsPage .candidate:hover .thumb.lg{transition:none;transform:none}
    #candidatsPage .thumb.lg::after{transition:none}
  }
  #candidatsPage .hint{font-size:12px;color:var(--muted);margin-top:6px;line-height:1.35}
  #candidatsPage .split{display:grid;grid-template-columns:1.25fr .75fr;gap:16px}
  #candidatsPage .candidate{display:flex;gap:12px;align-items:center}
  #candidatsPage .candidate.dragging{opacity:.55}
  #candidatsPage .handle{cursor:grab;user-select:none;opacity:.85}
  #candidatsPage .handle:active{cursor:grabbing}
  #candidatsPage .tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  #candidatsPage .toolbar select{
    width:auto;
    min-width:260px;
    padding:10px 12px;
    border-radius:999px;
  }
  #candidatsPage .btn.sm{padding:8px 12px;font-size:12px}
  /* Modal edit (keeps list compact) */
  #candidatsPage .cand-actions{margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  #candidatsPage .btn.xs{padding:8px 12px;font-size:12px}
  #candidatsPage .modal{
    position:fixed;inset:0;z-index:60;
    display:none;align-items:center;justify-content:center;
    padding:18px;
    background:rgba(0,0,0,.55);
    backdrop-filter: blur(8px);
    overflow:auto;
    overscroll-behavior: contain;
  }
  #candidatsPage .modal.open{display:flex}
  #candidatsPage .modal-card{
    width:min(920px, 100%);
    max-height: calc(100vh - 84px);
    overflow:auto;
    -webkit-overflow-scrolling: touch;
    border-radius:22px;
    background:linear-gradient(180deg, rgba(10,28,42,.96), rgba(10,28,42,.86));
    border:1px solid rgba(255,255,255,.12);
    box-shadow:0 30px 120px rgba(0,0,0,.55);
  }
  #candidatsPage .modal-hd{
    position:sticky;top:0;z-index:1;
    padding:14px 16px;
    display:flex;gap:10px;align-items:center;
    background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02));
    border-bottom:1px solid rgba(255,255,255,.08);
  }
  #candidatsPage .modal-title{font-weight:900}
  #candidatsPage .modal-bd{padding:16px}
  #candidatsPage .modal-grid{display:grid;grid-template-columns: 260px 1fr;gap:16px;align-items:start}
  #candidatsPage .modal-grid textarea{min-height:96px}
  #candidatsPage .modal-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px}
  /* Lightbox for photos */
  #candidatsPage .js-zoom-img{cursor:pointer}
  #candidatsPage .img-lightbox{
    position:fixed;inset:0;z-index:80;
    display:none;align-items:center;justify-content:center;
    padding:18px;
    background:rgba(0,0,0,.82);
    backdrop-filter: blur(10px);
  }
  #candidatsPage .img-lightbox.open{display:flex}
  #candidatsPage .img-lightbox-card{
    max-width:min(900px,100%);
    max-height:min(90vh,900px);
    border-radius:22px;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.18);
    background:rgba(0,0,0,.95);
    box-shadow:0 30px 140px rgba(0,0,0,.75);
    display:flex;flex-direction:column;
  }
  #candidatsPage .img-lightbox-hd{
    padding:10px 14px;
    display:flex;align-items:center;gap:8px;
    border-bottom:1px solid rgba(255,255,255,.12);
  }
  #candidatsPage .img-lightbox-hd .title{font-weight:900}
  #candidatsPage .img-lightbox-hd .spacer{margin-left:auto}
  #candidatsPage .img-lightbox-body{
    padding:12px;
    display:flex;align-items:center;justify-content:center;
    background:radial-gradient(circle at 50% 0%, rgba(26,169,184,.25), rgba(0,0,0,0) 55%);
  }
  #candidatsPage .img-lightbox-body img{
    max-width:100%;
    max-height:78vh;
    border-radius:18px;
  }
  #candidatsPage .file-pill{
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
  #candidatsPage .file-pill input[type=file]{
    position:absolute;
    inset:0;
    opacity:0;
    cursor:pointer;
  }
  #candidatsPage .file-pill:hover{
    filter:brightness(1.07);
    border-color:rgba(26,169,184,.45);
  }
  @media (max-width: 980px){
    #candidatsPage .modal-grid{grid-template-columns:1fr}
  }
  /* Prefer top-ish placement on small screens */
  @media (max-width: 640px){
    #candidatsPage .modal.open{align-items:flex-start}
    #candidatsPage .modal-card{margin-top:18px}
  }
  @media (max-width: 980px){ #candidatsPage .split{grid-template-columns:1fr} }
</style>

<div id="candidatsPage">
  <div class="toolbar">
    <a class="btn ghost" href="elections.php?e=<?= (int)$selectedElectionId ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">← Retour élections</a>
    <a class="btn ghost" href="admin.php?e=<?= (int)$selectedElectionId ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Admin</a>
    <form method="get" action="election.php" style="margin:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <label class="muted" style="margin:0;font-size:12px">Élection</label>
      <select name="e" onchange="this.form.submit()">
        <?php foreach ($elections as $el): ?>
          <option value="<?= (int)$el['id_election'] ?>" <?= (int)$el['id_election'] === (int)$selectedElectionId ? 'selected' : '' ?>>
            #<?= (int)$el['id_election'] ?> — <?= htmlspecialchars((string)$el['titre'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <div class="spacer"></div>
    <span class="pill muted"><?= count($candidats) ?> candidat(s)</span>
  </div>

  <section class="card" style="margin-top:16px" id="ajout">
    <div class="hd">
      <h3>Ajouter un candidat</h3>
      <div class="spacer"></div>
      <span class="muted" style="font-size:13px">Élection #<?= (int)$selectedElectionId ?></span>
    </div>
    <div class="bd">
      <form method="post" novalidate enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_candidat" />
        <input type="hidden" name="id_election" value="<?= (int)$selectedElectionId ?>" />

        <div class="split">
          <div>
            <label for="numero">Numéro du candidat (manuel)</label>
            <input id="numero" name="numero" type="number" inputmode="numeric" min="1" placeholder="Ex: 7" />
            

            <div style="margin-top:12px">
            <label for="nom_candidat">Nom du candidat</label>
            <input id="nom_candidat" name="nom_candidat" type="text" required />
            </div>

            <div style="margin-top:12px">
              <label for="image">Photo (optionnel)</label>
              <div class="upload">
                <div class="thumb lg js-zoom-img" id="thumb" data-full-src=""><span class="muted">IMG</span></div>
                <div style="flex:1">
                  <label class="file-pill">
                    <span>Parcourir…</span>
                    <input id="image" name="image" type="file" accept="image/png,image/jpeg,image/webp" />
                  </label>
                  <div class="muted" style="font-size:12px;margin-top:6px">Aperçu ci-dessus après sélection.</div>
                </div>
              </div>
            </div>
          </div>
          <div>
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" placeholder="Une description courte et convaincante… (optionnel)"></textarea>
          </div>
        </div>

        <div class="tools" style="margin-top:14px">
          <button type="submit" class="btn primary" data-loading="Ajout...">Ajouter</button>
          <span class="pill muted">Ajout direct dans la liste ci-dessous</span>
        </div>
      </form>
    </div>
  </section>

  <section class="card" style="margin-top:16px" id="candidats">
    <div class="hd">
      <h3>Liste des candidats</h3>
      <div class="spacer"></div>
      <a class="btn ghost sm" href="bulletin.php?e=<?= (int)$selectedElectionId ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Bulletin</a>
      <span class="muted" style="font-size:13px"><?= count($candidats) ?> candidat(s)</span>
    </div>
    <div class="bd">
      <?php if (empty($candidats)): ?>
        <div class="muted">Aucun candidat pour l’instant.</div>
      <?php else: ?>
        <form method="post" style="margin:0 0 12px 0" id="reorderForm">
          <input type="hidden" name="action" value="reorder_candidats" />
          <input type="hidden" name="id_election" value="<?= (int)$selectedElectionId ?>" />
          <input type="hidden" name="order" id="orderInput" value="" />

          
          <div class="tools">
            <button class="btn primary" type="submit" data-loading="Enregistrement...">Enregistrer l’ordre</button>
            
          </div>
        </form>

        <div style="display:grid;gap:10px" id="candList">
          <?php foreach ($candidats as $c): ?>
            <div class="mini candidate" draggable="true"
                 data-cid="<?= (int)$c['id_candidat'] ?>"
                 data-cand-id="<?= (int)$c['id_candidat'] ?>"
                 data-cand-numero="<?= htmlspecialchars((string)($c['numero'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                 data-cand-nom="<?= htmlspecialchars((string)$c['nom_candidat'], ENT_QUOTES, 'UTF-8') ?>"
                 data-cand-bio="<?= htmlspecialchars((string)($c['bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
              <div class="handle" title="Déplacer" style="padding:8px 10px;border-radius:12px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04)">≡</div>
              <div class="thumb lg js-zoom-img" data-full-src="<?= !empty($c['image_mime']) ? 'candidat_image.php?id=' . (int)$c['id_candidat'] : '' ?>">
                <?php if (!empty($c['image_mime'])): ?>
                  <img alt="" src="candidat_image.php?id=<?= (int)$c['id_candidat'] ?>" />
                <?php else: ?>
                  <span class="muted">IMG</span>
                <?php endif; ?>
              </div>
              <div style="flex:1">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                  <div style="font-weight:900">#<?= htmlspecialchars((string)($c['numero'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <div style="font-weight:900"><?= htmlspecialchars((string)$c['nom_candidat'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php if (!empty($c['bio'])): ?>
                  <div class="muted" style="font-size:13px;margin-top:6px;line-height:1.35">
                    <?= htmlspecialchars((string)$c['bio'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php endif; ?>
                <div class="cand-actions">
                  <button type="button" class="btn ghost xs js-edit-cand">Modifier</button>
                  <form method="post" style="margin:0" onsubmit="return confirm('Supprimer ce candidat ?');">
                    <input type="hidden" name="action" value="delete_candidat" />
                    <input type="hidden" name="id_election" value="<?= (int)$selectedElectionId ?>" />
                    <input type="hidden" name="id_candidat" value="<?= (int)$c['id_candidat'] ?>" />
                    <button class="btn ghost xs" type="submit">Supprimer</button>
                  </form>
                </div>
              </div>
              <span class="badge">Candidat</span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

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
</script>

<div class="modal" id="editModal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
    <div class="modal-hd">
      <div class="modal-title" id="editModalTitle">Modifier le candidat</div>
      <div class="spacer"></div>
      <button type="button" class="btn ghost xs js-close-modal">Fermer</button>
    </div>
    <div class="modal-bd">
      <form method="post" enctype="multipart/form-data" id="editForm" style="margin:0">
        <input type="hidden" name="action" value="update_candidat" />
        <input type="hidden" name="id_election" value="<?= (int)$selectedElectionId ?>" />
        <input type="hidden" name="id_candidat" id="edit_id_candidat" value="" />

        <div class="modal-grid">
          <div>
            <label>Photo actuelle</label>
            <div class="thumb lg js-zoom-img" id="editThumb" data-full-src=""><span class="muted">IMG</span></div>
          </div>
          <div>
            <label for="edit_numero">Numéro (manuel)</label>
            <input id="edit_numero" name="numero" type="number" inputmode="numeric" min="1" placeholder="Ex: 7" />

            <div style="margin-top:10px">
              <label for="edit_nom">Nom</label>
              <input id="edit_nom" name="nom_candidat" type="text" required />
            </div>

            <div style="margin-top:10px">
              <label for="edit_image">Changer la photo</label>
              <label class="file-pill">
                <span>Parcourir…</span>
                <input id="edit_image" name="image" type="file" accept="image/png,image/jpeg,image/webp" />
              </label>
              <label class="muted" style="display:flex;gap:8px;align-items:center;margin-top:8px">
                <input type="checkbox" name="remove_image" value="1" style="width:auto" />
                Supprimer la photo actuelle
              </label>
              <div class="hint">La nouvelle photo remplacera l’ancienne.</div>
            </div>

            <div style="margin-top:10px">
              <label for="edit_bio">Bio</label>
              <textarea id="edit_bio" name="bio" placeholder="(optionnel)"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-actions">
          <button class="btn primary" type="submit" data-loading="Sauvegarde...">Enregistrer</button>
          <span class="pill muted" id="editMeta"></span>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const list = document.getElementById('candList');
    const orderInput = document.getElementById('orderInput');
    const form = document.getElementById('reorderForm');
    if (!list || !orderInput || !form) return;

    let dragEl = null;
    function updateOrder(){
      const ids = Array.from(list.querySelectorAll('[data-cid]')).map(el => el.getAttribute('data-cid'));
      orderInput.value = ids.join(',');
    }
    updateOrder();

    list.addEventListener('dragstart', (e) => {
      const item = e.target && e.target.closest ? e.target.closest('.candidate') : null;
      if (!item) return;
      dragEl = item;
      item.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragend', () => {
      if (dragEl) dragEl.classList.remove('dragging');
      dragEl = null;
      updateOrder();
    });
    list.addEventListener('dragover', (e) => {
      e.preventDefault();
      const over = e.target && e.target.closest ? e.target.closest('.candidate') : null;
      if (!dragEl || !over || over === dragEl) return;
      const rect = over.getBoundingClientRect();
      const next = (e.clientY - rect.top) > rect.height / 2;
      list.insertBefore(dragEl, next ? over.nextSibling : over);
    });

    form.addEventListener('submit', () => updateOrder());
  })();
</script>

<script>
  (function(){
    const modal = document.getElementById('editModal');
    const closeBtns = document.querySelectorAll('.js-close-modal');
    const editBtns = document.querySelectorAll('.js-edit-cand');
    const idInput = document.getElementById('edit_id_candidat');
    const numInput = document.getElementById('edit_numero');
    const nomInput = document.getElementById('edit_nom');
    const bioInput = document.getElementById('edit_bio');
    const meta = document.getElementById('editMeta');
    const thumb = document.getElementById('editThumb');

    if (!modal || !idInput || !numInput || !nomInput || !bioInput || !meta) return;

    function open(){
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      setTimeout(() => nomInput.focus(), 0);
    }
    function close(){
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }

    closeBtns.forEach(b => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => {
      if (e.target === modal) close();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.classList.contains('open')) close();
    });

    editBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const card = btn.closest('.candidate');
        if (!card) return;
        const id = card.getAttribute('data-cand-id') || '';
        idInput.value = id;
        numInput.value = card.getAttribute('data-cand-numero') || '';
        nomInput.value = card.getAttribute('data-cand-nom') || '';
        bioInput.value = card.getAttribute('data-cand-bio') || '';
        meta.textContent = `ID #${idInput.value}`;
        if (thumb) {
          const hasImg = !!card.querySelector('.thumb.lg img');
          const src = hasImg && id ? 'candidat_image.php?id=' + id : '';
          thumb.setAttribute('data-full-src', src);
          if (src) {
            thumb.innerHTML = '<img alt="" src="' + src + '">';
          } else {
            thumb.innerHTML = '<span class="muted">IMG</span>';
          }
        }
        open();
      });
    });
  })();
</script>

<div class="img-lightbox" id="imgLightbox" aria-hidden="true">
  <div class="img-lightbox-card">
    <div class="img-lightbox-hd">
      <div class="title">Photo du candidat</div>
      <div class="spacer"></div>
      <button type="button" class="btn ghost xs js-close-lightbox">Fermer</button>
    </div>
    <div class="img-lightbox-body">
      <img id="lbImg" alt="Photo du candidat" />
    </div>
  </div>
</div>

<script>
  (function(){
    const lb = document.getElementById('imgLightbox');
    const lbImg = document.getElementById('lbImg');
    const closeBtns = document.querySelectorAll('.js-close-lightbox');

    if (!lb || !lbImg) return;

    function openLightbox(src){
      if (!src) return;
      lbImg.src = src;
      lb.classList.add('open');
      lb.setAttribute('aria-hidden','false');
    }
    function closeLightbox(){
      lb.classList.remove('open');
      lb.setAttribute('aria-hidden','true');
      lbImg.src = '';
    }

    closeBtns.forEach(b => b.addEventListener('click', closeLightbox));
    lb.addEventListener('click', (e) => {
      if (e.target === lb) closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && lb.classList.contains('open')) closeLightbox();
    });

    function wireZoom(){
      document.querySelectorAll('#candidatsPage .js-zoom-img').forEach((el) => {
        el.addEventListener('click', () => {
          const src = el.getAttribute('data-full-src') || '';
          if (src) openLightbox(src);
        });
      });
    }

    wireZoom();
  })();
</script>

</div>

<?php votigo_layout_end(); ?>

