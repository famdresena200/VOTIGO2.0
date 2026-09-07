<?php
session_start();
require __DIR__ . '/_inc/bootstrap.php';
require __DIR__ . '/_inc/db.php';
require __DIR__ . '/../_inc/auth.php';

$role = auth_role();
if ($role === 'electeur') {
    header('Location: ../app/dashboard.php');
    exit;
}
if ($role === 'admin') {
    header('Location: ../admin/admin.php');
    exit;
}

$error = '';

function normalize_identifier(string $value): string
{
    return preg_replace('/\s+/', '', trim($value)) ?? '';
}

function is_valid_nen(string $value): bool
{
    $value = normalize_identifier($value);
    return preg_match('/^\d{10}$/', $value) === 1 && (int)$value > 0;
}

function is_valid_cin(string $value): bool
{
    $value = normalize_identifier($value);
    return preg_match('/^\d{12}$/', $value) === 1 && (int)$value > 0;
}

function is_elector_authorized(PDO $pdo, string $cin, string $nen, string $nom, string $prenom): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM ELECTEURS_AUTORISES
         WHERE cin = :cin
           AND nen = :nen
           AND LOWER(TRIM(nom)) = LOWER(TRIM(:nom))
           AND LOWER(TRIM(prenom)) = LOWER(TRIM(:prenom))
         LIMIT 1'
    );

    $stmt->execute([
        ':cin' => normalize_identifier($cin),
        ':nen' => normalize_identifier($nen),
        ':nom' => trim($nom),
        ':prenom' => trim($prenom),
    ]);

    return (bool)$stmt->fetchColumn();
}

function find_authorized_match(PDO $pdo, string $cin, string $nen): ?array
{
    $stmt = $pdo->prepare(
        'SELECT nom, prenom, cin, nen FROM ELECTEURS_AUTORISES
         WHERE cin = :cin OR nen = :nen
         LIMIT 1'
    );

    $stmt->execute([
        ':cin' => normalize_identifier($cin),
        ':nen' => normalize_identifier($nen),
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// Vérification AJAX d'unicité
if (($_GET['action'] ?? '') === 'check_unique') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? '';
    $value = trim($_GET['value'] ?? '');
    $exists = false;
    
    if ($type === 'email' && $value !== '') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM USERS WHERE email = ?');
        $stmt->execute([$value]);
        $exists = $stmt->fetchColumn() > 0;
    } elseif ($type === 'nen' && $value !== '') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM USERS WHERE nen = ?');
        $stmt->execute([$value]);
        $exists = $stmt->fetchColumn() > 0;
    } elseif ($type === 'cin' && $value !== '') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM USERS WHERE cin = ?');
        $stmt->execute([$value]);
        $exists = $stmt->fetchColumn() > 0;
    }
    
    echo json_encode(['exists' => $exists]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim((string)($_POST['nom'] ?? ''));
    $prenom  = trim((string)($_POST['prenom'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $nen     = normalize_identifier((string)($_POST['nni'] ?? ''));
    $cin     = normalize_identifier((string)($_POST['cin'] ?? ''));
    $telephone = trim((string)($_POST['numtel'] ?? ''));
    $date_naissance = trim((string)($_POST['dob'] ?? ''));
    $consent = isset($_POST['consent']) ? 1 : 0;

    // Validation complète - tous les champs requis
    if (empty($nom) || empty($prenom) || empty($email) || empty($nen) || empty($cin)) {
        $error = "Veuillez remplir tous les champs requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif (!is_valid_nen($nen)) {
        $error = "Le numéro d'électeur doit comporter exactement 10 chiffres et être valide.";
    } elseif (!is_valid_cin($cin)) {
        $error = "Le numéro CIN doit comporter exactement 12 chiffres et être valide.";
    } elseif (!empty($telephone) && strlen(preg_replace('/\D/', '', $telephone)) !== 10) {
        $error = "Le numéro de téléphone doit comporter exactement 10 chiffres.";
    } elseif ($consent !== 1) {
        $error = "Vous devez accepter la politique de confidentialité.";
    } else {
        try {
            $pdo = db();

            if (!is_elector_authorized($pdo, $cin, $nen, $nom, $prenom)) {
                $authorizedMatch = find_authorized_match($pdo, $cin, $nen);

                if ($authorizedMatch !== null) {
                    $error = "Les informations saisies ne correspondent pas à l’électeur autorisé enregistré. Vérifiez le nom, le prénom, le CIN et le NEN.";
                } else {
                    $error = "Ce couple CIN/NEN n’est pas présent dans la liste des électeurs autorisés.";
                }
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Cet email est déjà utilisé.";
                } else {
                    // Check if nen already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE nen = ?");
                    $stmt->execute([$nen]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Ce numéro d’électeur est déjà utilisé.";
                    } else {
                        // Check if cin already exists
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE cin = ?");
                        $stmt->execute([$cin]);
                        if ($stmt->fetchColumn() > 0) {
                            $error = "Ce numéro CIN est déjà utilisé.";
                        } else {
                            // Insert user without password
                            $stmt = $pdo->prepare("INSERT INTO USERS (nom, prenom, email, nen, cin, telephone, date_naissance) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$nom, $prenom, $email, $nen, $cin, $telephone, $date_naissance]);
                            $user_id = $pdo->lastInsertId();
                            $_SESSION['user_id'] = $user_id;
                            header("Location: etape2.php");
                            exit;
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Inscription Électeur — VOTIGO</title>

    <style>
        :root{
            --bg: #eef5ff;
            --bg-strong: #dfeeff;
            --surface: rgba(255, 255, 255, 0.72);
            --surface-strong: #ffffff;
            --text: #0f1d32;
            --muted: #5f7185;
            --primary: #1d6ef2;
            --primary-strong: #1349a3;
            --secondary: #12b0a3;
            --success: #1bbf8d;
            --danger: #ef5350;
            --border: rgba(141, 164, 194, 0.24);
            --shadow: 0 24px 80px rgba(16, 38, 60, 0.12);
        }

        .dark-theme{
            --bg: #091722;
            --bg-strong: #112334;
            --surface: rgba(13, 24, 35, 0.76);
            --surface-strong: rgba(15, 27, 39, 0.9);
            --text: #edf7ff;
            --muted: #a8bbd1;
            --primary: #72a8ff;
            --primary-strong: #9cc3ff;
            --secondary: #43d4c0;
            --success: #62e4b0;
            --danger: #ff7f7a;
            --border: rgba(177, 202, 228, 0.12);
            --shadow: 0 28px 90px rgba(0, 0, 0, 0.4);
        }

        *{box-sizing:border-box}
        body{
            margin:0;
            min-height:100vh;
            font-family:Inter,Segoe UI,Arial,sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at top left, rgba(66, 132, 255, 0.18), transparent 28%),
                radial-gradient(circle at bottom right, rgba(18, 176, 163, 0.18), transparent 24%),
                linear-gradient(135deg, var(--bg), var(--bg-strong));
            transition:background .25s ease,color .25s ease;
        }
        .container{max-width:1240px;margin:28px auto;padding:18px 22px 40px}
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;background:var(--surface);backdrop-filter:blur(12px);border:1px solid var(--border);border-radius:24px;padding:14px 18px;box-shadow:var(--shadow)}
        .top-actions{display:flex;align-items:center;gap:10px}
        .brand{display:flex;align-items:center;gap:12px;font-weight:700}
        .brand img{height:72px;width:auto;object-fit:contain;border-radius:18px;box-shadow:0 16px 30px rgba(12,25,39,0.12)}
        .progressbar{display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap}
        .btn-sm{padding:10px 16px;font-size:13px;border-radius:999px;border:1px solid rgba(29,110,242,.2);background:rgba(255,255,255,.45);text-decoration:none;color:var(--text);display:inline-flex;align-items:center;justify-content:center;font-weight:600;transition:all .2s ease}
        .btn-sm:hover{transform:translateY(-1px)}
        .step{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;font-size:12px;color:var(--muted);padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.18);min-width:90px}
        .step .dot{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.55);border:2px solid rgba(29,110,242,.12);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--primary);box-shadow:inset 0 0 0 1px rgba(255,255,255,.45)}
        .step.active{background:linear-gradient(135deg, rgba(29,110,242,.12), rgba(18,176,163,.08));border:1px solid rgba(29,110,242,.12)}
        .step.active .dot{background:linear-gradient(180deg,var(--primary),var(--primary-strong));color:#fff;border-color:transparent;box-shadow:0 14px 28px rgba(29,110,242,.22)}

        .card{display:grid;grid-template-columns:1.25fr 0.95fr;gap:26px;background:var(--surface);backdrop-filter:blur(14px);padding:28px;border-radius:28px;margin-top:20px;align-items:stretch;border:1px solid var(--border);box-shadow:var(--shadow)}
        .form{padding:4px 4px 0}
        .form h2{margin:8px 0 6px;font-size:clamp(1.8rem,2vw,2.3rem);line-height:1.2}
        .muted{color:var(--muted);font-size:15px;margin-bottom:18px}

        form .row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
        label{font-size:13px;color:var(--muted);margin-bottom:7px;display:block;font-weight:600}
        input[type="text"],input[type="email"],input[type="date"],input[type="password"],input[type="tel"]{
            width:100%;padding:13px 14px;border-radius:14px;border:1px solid var(--border);background:rgba(255,255,255,.62);color:var(--text);box-shadow:inset 0 1px 2px rgba(15,29,50,.02);transition:border-color .2s ease, box-shadow .2s ease, transform .2s ease
        }
        input:focus{outline:none;border-color:rgba(29,110,242,.42);box-shadow:0 0 0 4px rgba(29,110,242,.08)}
        .full{grid-column:1 / -1}
        .input-msg{font-size:12px;margin-top:5px;min-height:18px;display:block}
        .input-msg.error{color:var(--danger)}
        .input-msg.success{color:var(--success)}
        .input-msg.info{color:var(--muted)}

        .actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:14px}
        .btn{
            border:0;cursor:pointer;font-weight:700;padding:12px 18px;border-radius:999px;color:var(--text);background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.2);transition:all .2s cubic-bezier(.4,0,.2,1);text-decoration:none;display:inline-flex;align-items:center;justify-content:center;
        }
        .btn:hover{transform:translateY(-2px);box-shadow:0 12px 22px rgba(16,38,60,0.12)}
        .btn:active{transform:translateY(0)}
        .btn.primary{border:1px solid rgba(29,110,242,.2);background:linear-gradient(135deg, #1d6ef2, #12b0a3);box-shadow:0 18px 42px rgba(29,110,242,.2);color:#fff}
        .btn.primary:hover{box-shadow:0 20px 46px rgba(29,110,242,.27)}
        .btn.ghost{background:rgba(255,255,255,.08);backdrop-filter:blur(4px)}

        .visual{display:flex;align-items:center;justify-content:center;padding:8px}
        .visual img{width:100%;height:100%;max-height:620px;object-fit:cover;border-radius:24px;box-shadow:var(--shadow);border:1px solid rgba(255,255,255,.18)}
        .small{font-size:13px;color:var(--muted)}
        .consent{display:flex;gap:10px;align-items:flex-start;margin-top:12px;padding:10px 12px;border-radius:14px;background:rgba(18,176,163,.05);border:1px solid rgba(18,176,163,.08)}
        .consent input{margin-top:4px}
        .consent label{margin:0;line-height:1.5}
        .consent a{color:var(--primary);text-decoration:none}

        .theme-toggle{margin-left:8px}
        .theme-toggle button{background:rgba(255,255,255,.45);border:1px solid rgba(15,29,50,.08);padding:10px 12px;border-radius:999px;cursor:pointer;font-size:16px;box-shadow:0 8px 18px rgba(15,29,50,.08)}
        .dark-theme .theme-toggle button{border-color:rgba(255,255,255,.08);background:rgba(17,35,52,.6)}

        .error{color:var(--danger);font-size:14px;margin-bottom:16px;padding:12px 14px;background:rgba(239,83,80,.08);border:1px solid rgba(239,83,80,.12);border-radius:12px}
        .security-banner{display:flex;flex-direction:column;gap:6px;margin:0 0 18px;padding:14px 16px;border-radius:16px;border:1px solid rgba(29,110,242,.12);background:linear-gradient(135deg, rgba(29,110,242,.08), rgba(18,176,163,.05));color:var(--text)}
        .security-banner strong{font-size:14px}
        .security-banner span{font-size:13px;color:var(--muted)}

        @media (max-width:960px){
            .card{grid-template-columns:1fr;}
            .visual{order:1}
            .topbar{flex-wrap:wrap}
            .top-actions{margin-left:auto}
            .brand img{height:58px}
        }
        @media (max-width:620px){
            form .row{grid-template-columns:1fr}
            .container{padding:12px 14px 28px}
            .topbar{padding:12px 14px;border-radius:18px}
            .progressbar{width:100%;justify-content:space-between}
            .step{min-width:70px;padding:4px 8px}
            .card{padding:18px;border-radius:20px}
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div class="brand">
               <img src="../../images/logo_ispm.png" alt="logo logo_ispm" style="height: 90px; width:auto; object-fit:contain; border-radius: 20px;"/>
            </div>

            <div class="top-actions">
                <a href="../index.php" class="btn-sm">Accueil</a>
            </div>

            <div class="progressbar" role="navigation" aria-label="Progression">
                <div class="step active"><div class="dot">1</div><div>Info</div></div>
                <div class="step"><div class="dot">2</div><div>Validation</div></div>
                <div class="step"><div class="dot">3</div><div>2FA</div></div>
            </div>

            <div class="brand">
               <img src="../../images/IMG-20260127-WA0054.jpg" alt="logo VOTIGO" style="height: 90px; width:auto; object-fit:contain; border-radius: 20px;"/>
            </div>
            <div class="theme-toggle" title="Changer le thème">
                <button id="themeBtn" aria-pressed="false">🌙</button>
            </div>
        </div>

        <section class="card">
            <div class="form">
                <h2>Inscription Électeur - Créez votre compte VOTIGO</h2>
                <div class="muted">Étape 1 sur 3 : Renseignez vos informations d'identité</div>
                <div class="security-banner">
                    <strong>Vérification d’éligibilité</strong>
                    <span>Nous vérifions votre identité à partir du couple nom, prénom, CIN et NEN avant l’ouverture du compte.</span>
                </div>
                <?php if ($error !== ''): ?>
                    <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form id="signup" method="post" novalidate>
                    <div class="row">
                        <div>
                            <label for="nom">Nom de famille *</label>
                            <input id="nom" name="nom" type="text" required maxlength="100" placeholder="Ex: Dupont"/>
                        </div>
                        <div>
                            <label for="nen">Numéro d'Électeur National *</label>
                            <input id="nen" name="nni" type="text" required maxlength="10" pattern="[0-9]{10}" placeholder="0123456789" title="Exactement 10 chiffres"/>
                            <span id="nen-msg" class="input-msg info">10 chiffres requis</span>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label for="prenom">Prénom(s) *</label>
                            <input id="prenom" name="prenom" type="text" required maxlength="100" placeholder="Ex: Jean"/>
                        </div>
                        <div>
                            <label for="email">Adresse Email *</label>
                            <input id="email" name="email" type="email" required maxlength="150" placeholder="nom@exemple.fr" />
                            <span id="email-msg" class="input-msg info"></span>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label for="cin">Numéro CIN *</label>
                            <input id="cin" name="cin" type="text" required maxlength="12" pattern="[0-9]{12}" placeholder="012345678901" title="Exactement 12 chiffres"/>
                            <span id="cin-msg" class="input-msg info">12 chiffres requis</span>
                        </div>
                        <div>
                            <label for="telephone">Numéro de téléphone</label>
                            <input id="telephone" name="numtel" type="tel" maxlength="10" pattern="[0-9]{10}" placeholder="0612345678" title="Exactement 10 chiffres"/>
                            <span id="tel-msg" class="input-msg info">10 chiffres (optionnel)</span>
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label for="dob">Date de Naissance</label>
                            <input id="dob" name="dob" type="date" />
                        </div>
                    </div>

                    <div class="consent">
                        <input id="consent" name="consent" type="checkbox" />
                        <label for="consent" class="small">Je consens au traitement sécurisé de mes données selon la <a href="#">politique de confidentialité</a>.</label>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn primary">S'inscrire</button>
                        <a href="../index.php" class="btn ghost">Retour à l'accueil</a>
                        <a href="login.php" class="btn ghost">Se connecter</a>
                    </div>
                </form>
            </div>

            <aside class="visual" aria-hidden="true">
                <img src="../../images/cybersecurity.jpg" alt="Illustration de sécurité et urne" />
            </aside>
        </section>
    </div>

    <script>
        // Theme toggle
        (function(){
            var root = document.documentElement;
            var btn = document.getElementById('themeBtn');
            var theme = localStorage.getItem('theme') || 'light';
            if (theme === 'dark') {
                root.classList.add('dark-theme');
                btn.textContent = '☀️';
                btn.setAttribute('aria-pressed', 'true');
            }
            btn.addEventListener('click', function(){
                var isDark = root.classList.toggle('dark-theme');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                btn.textContent = isDark ? '☀️' : '🌙';
                btn.setAttribute('aria-pressed', isDark);
            });
        })();

        // Form validation and interactions
        (function(){
            var form = document.getElementById('signup');
            var submitBtn = form.querySelector('button[type="submit"]');
            var isSubmitting = false;

            // Real-time validation and AJAX checks
            var emailInput = document.getElementById('email');
            var nenInput = document.getElementById('nen');
            var cinInput = document.getElementById('cin');
            var telInput = document.getElementById('telephone');
            var consentCheckbox = document.getElementById('consent');

            function checkUnique(field, type) {
                var msg = document.getElementById(field + '-msg');
                var value = document.getElementById(field).value.trim();
                
                // Validation de format d'abord
                if (type === 'email') {
                    if (!value) {
                        msg.textContent = '';
                        msg.className = 'input-msg info';
                        return;
                    }
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        msg.textContent = 'Format email invalide';
                        msg.className = 'input-msg error';
                        return;
                    }
                } else if (type === 'nen') {
                    if (!value) {
                        msg.textContent = '10 chiffres requis';
                        msg.className = 'input-msg info';
                        return;
                    }
                    if (!/^\d{10}$/.test(value)) {
                        msg.textContent = 'Doit être exactement 10 chiffres';
                        msg.className = 'input-msg error';
                        return;
                    }
                } else if (type === 'cin') {
                    if (!value) {
                        msg.textContent = '12 chiffres requis';
                        msg.className = 'input-msg info';
                        return;
                    }
                    if (!/^\d{12}$/.test(value)) {
                        msg.textContent = 'Doit être exactement 12 chiffres';
                        msg.className = 'input-msg error';
                        return;
                    }
                } else if (type === 'tel') {
                    if (!value) {
                        msg.textContent = '10 chiffres (optionnel)';
                        msg.className = 'input-msg info';
                        return;
                    }
                    if (!/^\d{10}$/.test(value)) {
                        msg.textContent = 'Doit être exactement 10 chiffres';
                        msg.className = 'input-msg error';
                        return;
                    }
                    msg.textContent = '✓ Valide';
                    msg.className = 'input-msg success';
                    return;
                }

                // AJAX check for uniqueness
                fetch('etape1.php?action=check_unique&type=' + type + '&value=' + encodeURIComponent(value))
                    .then(r => r.json())
                    .then(data => {
                        if (data.exists) {
                            msg.textContent = 'Déjà utilisé';
                            msg.className = 'input-msg error';
                            document.getElementById(field).style.borderColor = '#ff4444';
                        } else {
                            msg.textContent = '✓ Disponible';
                            msg.className = 'input-msg success';
                            document.getElementById(field).style.borderColor = '';
                        }
                    })
                    .catch(() => {
                        msg.textContent = '';
                        msg.className = 'input-msg';
                    });
            }

            // Debounce function
            function debounce(func, wait) {
                var timeout;
                return function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(func, wait);
                };
            }

            emailInput.addEventListener('input', debounce(() => checkUnique('email', 'email'), 500));
            nenInput.addEventListener('input', debounce(() => checkUnique('nen', 'nen'), 500));
            cinInput.addEventListener('input', debounce(() => checkUnique('cin', 'cin'), 500));
            telInput.addEventListener('input', debounce(() => checkUnique('tel', 'tel'), 500));

            // Focus clear
            form.querySelectorAll('input').forEach(function(input){
                input.addEventListener('focus', function(){
                    input.style.borderColor = '';
                });
            });

            // Prevent double-submit
            form.addEventListener('submit', function(e){
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }

                // Check consent before submission
                if (!consentCheckbox.checked) {
                    e.preventDefault();
                    var errorDiv = form.querySelector('.error');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'error';
                        form.insertBefore(errorDiv, form.firstChild);
                    }
                    errorDiv.textContent = 'Vous devez accepter la politique de confidentialité.';
                    return false;
                }

                isSubmitting = true;
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Inscription en cours...';
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
            });

            // Restore button if nothing happens (failsafe)
            window.addEventListener('beforeunload', function(){
                if (isSubmitting) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'S\'inscrire';
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                    isSubmitting = false;
                }
            });
        })();
    </script>
</body>
</html>

