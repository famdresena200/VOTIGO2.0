<?php
session_start();
require __DIR__ . '/_inc/bootstrap.php';
require __DIR__ . '/_inc/db.php';

$error = '';

// Vérification AJAX de l'email
if (($_GET['action'] ?? '') === 'check_email') {
    header('Content-Type: application/json');
    $email = trim($_GET['email'] ?? '');
    $exists = false;
    
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM USERS WHERE email = ?');
            $stmt->execute([$email]);
            $exists = $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            // Ignore
        }
    }
    
    echo json_encode(['exists' => $exists]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '') {
        $error = "Veuillez entrer votre adresse email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT id_user, nom, prenom FROM USERS WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "Aucun compte n'existe avec cette adresse email.";
            } else {
                // Générer code et stocker en session
                $code = (string)random_int(100000, 999999);
                $_SESSION['reset_data'] = [
                    'id_user' => (int)$user['id_user'],
                    'nom' => (string)($user['nom'] . ' ' . $user['prenom']),
                    'email' => $email,
                    'code' => $code,
                    'step' => 'verify',
                    'expires_at' => time() + 600,
                ];
                $_SESSION['show_code'] = true;
                header('Location: forgot_password_verify.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = "Erreur serveur.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mot de passe oublié — VOTIGO</title>
    <style>
        :root{
            --blue:#2b84ff; --blue-dark:#1760d6; --muted:#6b7280;
            --bg:#f4f8fb; --text:#0b2540; --card-bg: linear-gradient(180deg,#ffffffcc,#ffffffcc);
            --input-bg:#fbfdff; --input-border:#d8e1ef; --shadow: 0 8px 30px rgba(11,37,64,0.08);
        }

        .dark-theme{
            --bg:#071216; --text:#d7eef6; --card-bg: linear-gradient(180deg, rgba(7,18,22,0.6), rgba(7,18,22,0.5));
            --input-bg:#08161b; --input-border: rgba(255,255,255,0.06); --muted:#9fb9c8; --shadow: 0 8px 30px rgba(0,0,0,0.6);
        }

        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,Segoe UI,Arial;color:var(--text);background:var(--bg);transition:background .25s ease,color .25s ease}
        .container{max-width:1100px;margin:36px auto;padding:20px}
        .topbar{display:flex;align-items:center;gap:18px}
        .brand{display:flex;align-items:center;gap:10px;font-weight:700}
        .brand img{height:60px;width:auto;object-fit:contain;border-radius:12px}

        .top-links{display:flex;align-items:center;gap:10px;margin-left:auto;flex-wrap:wrap}
        .link-btn{padding:8px 12px;border-radius:999px;border:1px solid rgba(43,132,255,.25);background:rgba(255,255,255,.35);text-decoration:none;color:var(--text);font-size:12px;font-weight:600;display:inline-flex;align-items:center;justify-content:center}

        .card{display:grid;grid-template-columns:1fr 420px;gap:24px;background:var(--card-bg);padding:28px;border-radius:12px;margin-top:18px;align-items:start}
        .form h2{margin:6px 0 6px 0}
        .muted{color:var(--muted);font-size:14px;margin-bottom:18px}

        label{font-size:13px;color:var(--muted);margin-bottom:6px;display:block}
        input[type="email"]{width:100%;padding:12px 14px;border-radius:8px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text)}
        
        .field{margin-bottom:16px}

        .actions{display:flex;gap:12px;align-items:center;margin-top:18px}
        .btn{padding:10px 18px;border-radius:999px;border:0;cursor:pointer;font-weight:600}
        .btn.primary{background:linear-gradient(180deg,var(--blue),var(--blue-dark));color:#fff}
        .btn.ghost{background:#fff;border:1px solid #cfe0ff;color:var(--blue-dark);text-decoration:none}

        .visual{display:flex;align-items:center;justify-content:center}
        .visual img{width:100%;height:auto;border-radius:12px;filter:drop-shadow(var(--shadow))}

        .theme-toggle{margin-left:auto}
        .theme-toggle button{background:transparent;border:1px solid rgba(0,0,0,0.06);padding:8px;border-radius:999px;cursor:pointer;font-size:16px}
        .dark-theme .theme-toggle button{border-color:rgba(255,255,255,0.08)}

        .error{color:#ff4444;font-size:14px;margin-bottom:16px;padding:10px;background:rgba(255,68,68,0.1);border-radius:8px}
        .security-banner{
            display:flex;flex-direction:column;gap:6px;
            margin:0 0 18px;
            padding:12px 14px;
            border-radius:10px;
            border:1px solid rgba(43,132,255,0.15);
            background:rgba(43,132,255,0.08);
            color:var(--text);
        }
        .security-banner strong{font-size:14px}
        .security-banner span{font-size:13px;color:var(--muted)}
        .footer{margin-top:16px;padding-top:16px;border-top:1px solid var(--input-border);font-size:13px}
        .footer a{color:var(--blue);text-decoration:none;font-weight:600}

        .email-msg{font-size:12px;margin-top:4px;min-height:18px;display:block}
        .email-msg.error{color:#ff4444}
        .email-msg.success{color:#18b78a}
        .email-msg.info{color:#999}

        @media (max-width:960px){
            .card{grid-template-columns:1fr;}
            .visual{order:1}
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="topbar">
            <div class="brand">
                <img src="../../images/logo_ispm.png" alt="ISPM logo" />
                <img src="../../images/IMG-20260127-WA0054.jpg" alt="VOTIGO logo" />
            </div>
            <div class="top-links">
                <a href="../index.php" class="link-btn">Accueil</a>
                <a href="login.php" class="link-btn">Se connecter</a>
            </div>
            <div class="theme-toggle" title="Changer le thème">
                <button id="themeBtn" aria-pressed="false">🌙</button>
            </div>
        </header>

        <main class="card">
            <section class="form">
                <h2>Récupération du Mot de Passe</h2>
                <div class="muted">Entrez votre adresse email pour réinitialiser votre mot de passe</div>
                <div class="security-banner">
                    <strong>Vérification de sécurité</strong>
                    <span>Nous vérifions votre adresse email pour sécuriser l’accès à votre compte et protéger vos données.</span>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" id="forgot-form" novalidate>
                    <div class="field">
                        <label for="email">Adresse Email *</label>
                        <input id="email" name="email" type="email" placeholder="voteur@example.com" maxlength="150" required />
                        <span id="email-msg" class="email-msg info"></span>
                    </div>

                    <div class="actions">
                        <button id="submit-btn" type="submit" class="btn primary">Continuer</button>
                        <a href="login.php" class="btn ghost">Retour</a>
                        <a href="../index.php" class="btn ghost">Accueil</a>
                    </div>
                </form>

                <div class="footer">
                    Pas encore de compte ? <a href="etape1.php">S'inscrire</a>
                </div>
            </section>

            <aside class="visual" aria-hidden="true">
                <img src="../../images/cybersecurity.jpg" alt="Illustration de sécurité" />
            </aside>
        </main>
    </div>

    <script>
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

            // Email validation and AJAX check
            var form = document.getElementById('forgot-form');
            var emailInput = document.getElementById('email');
            var emailMsg = document.getElementById('email-msg');
            var submitBtn = document.getElementById('submit-btn');
            var isSubmitting = false;

            function validateEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            function debounce(func, wait) {
                var timeout;
                return function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(func, wait);
                };
            }

            function checkEmail() {
                var value = emailInput.value.trim();

                if (!value) {
                    emailMsg.textContent = '';
                    emailMsg.className = 'email-msg';
                    emailInput.style.borderColor = '';
                    return;
                }

                if (!validateEmail(value)) {
                    emailMsg.textContent = 'Format email invalide';
                    emailMsg.className = 'email-msg error';
                    emailInput.style.borderColor = '#ff4444';
                    return;
                }

                // AJAX check
                fetch('forgot_password.php?action=check_email&email=' + encodeURIComponent(value))
                    .then(r => r.json())
                    .then(data => {
                        if (data.exists) {
                            emailMsg.textContent = '✓ Email trouvé';
                            emailMsg.className = 'email-msg success';
                            emailInput.style.borderColor = '';
                        } else {
                            emailMsg.textContent = 'Email non trouvé dans le système';
                            emailMsg.className = 'email-msg error';
                            emailInput.style.borderColor = '#ff4444';
                        }
                    })
                    .catch(() => {
                        emailMsg.textContent = '';
                        emailMsg.className = 'email-msg';
                    });
            }

            emailInput.addEventListener('input', debounce(checkEmail, 500));
            emailInput.addEventListener('focus', () => {
                emailInput.style.borderColor = '';
            });

            // Prevent double-submit
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }

                var email = emailInput.value.trim();
                if (!email || !validateEmail(email)) {
                    e.preventDefault();
                    emailMsg.textContent = 'Veuillez entrer une adresse email valide.';
                    emailMsg.className = 'email-msg error';
                    emailInput.style.borderColor = '#ff4444';
                    return false;
                }

                isSubmitting = true;
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Traitement...';
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
            });
        })();
    </script>
</body>
</html>
