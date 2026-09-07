<?php
session_start();
require __DIR__ . '/_inc/bootstrap.php';
require __DIR__ . '/_inc/db.php';
require __DIR__ . '/../_inc/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: etape1.php");
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%]).{8,}$/', $password)) {
        $error = "Le mot de passe doit contenir au moins 8 caractères, une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial (!@#$%).";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $pdo = db();
            $stmt = $pdo->prepare("UPDATE USERS SET password_hash = ? WHERE id_user = ?");
            $stmt->execute([$hash, $_SESSION['user_id']]);
            
            // Get user info to login
            $stmt = $pdo->prepare("SELECT id_user, nom FROM USERS WHERE id_user = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                login_electeur($user['id_user'], $user['nom']);
            }
            
            // Clean up validation code
            unset($_SESSION['validation_code']);
            
            $success = true;
            header("Location: confirmation.php");
            exit;
        } catch (PDOException $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
}

// Get user info from database
try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id_user, nom, prenom, email FROM USERS WHERE id_user = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: etape1.php");
        exit;
    }
    
    $nomComplet = htmlspecialchars($user['nom'] . ' ' . $user['prenom'], ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
} catch (PDOException $e) {
    header("Location: etape1.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sécurisation de Votre Compte — VOTIGO</title>
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
        .step.done .dot{background:linear-gradient(180deg,var(--success),#12b0a3);color:#fff;border-color:transparent;box-shadow:0 14px 28px rgba(27,191,141,.22)}

        .password-conditions ul{list-style:none;padding:0;margin:8px 0 16px;font-size:13px;color:var(--muted)}
        .password-conditions li{margin-bottom:5px}
        .field{margin-bottom:14px}
        .field input[type="password"], .field input[type="text"]{width:100%;padding:13px 14px;border-radius:14px;border:1px solid var(--border);background:rgba(255,255,255,.62);color:var(--text);transition:border-color .2s ease, box-shadow .2s ease}
        .field input:focus{outline:none;border-color:rgba(29,110,242,.42);box-shadow:0 0 0 4px rgba(29,110,242,.08)}
        .field label{display:flex;align-items:center;font-size:13px;color:var(--muted);margin-bottom:7px;font-weight:600}

        .password-strength{margin:18px 0;padding:14px 16px;border-radius:16px;background:linear-gradient(135deg, rgba(18,176,163,.08), rgba(29,110,242,.04));border:1px solid rgba(29,110,242,.12)}
        .strength-bar{width:100%;height:8px;background:rgba(141,164,194,.25);border-radius:999px;overflow:hidden;margin-bottom:8px}
        .strength-fill{height:100%;background:linear-gradient(90deg, #ff6058, #ffaf38, #ffe05d, #7adf8d, #1bbf8d);transition:width .3s ease}

        .card{display:grid;grid-template-columns:1.25fr 0.95fr;gap:26px;background:var(--surface);backdrop-filter:blur(14px);padding:28px;border-radius:28px;margin-top:20px;align-items:stretch;border:1px solid var(--border);box-shadow:var(--shadow)}
        .form{padding:4px 4px 0}
        .form h2{margin:8px 0 6px;font-size:clamp(1.8rem,2vw,2.3rem);line-height:1.2}
        .muted{color:var(--muted);font-size:15px;margin-bottom:18px}

        .actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:18px}
        .btn{border:0;cursor:pointer;font-weight:700;padding:12px 18px;border-radius:999px;color:var(--text);background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.2);transition:all .2s cubic-bezier(.4,0,.2,1);text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
        .btn:hover{transform:translateY(-2px);box-shadow:0 12px 22px rgba(16,38,60,0.12)}
        .btn:active{transform:translateY(0)}
        .btn.primary{border:1px solid rgba(29,110,242,.2);background:linear-gradient(135deg, #1d6ef2, #12b0a3);box-shadow:0 18px 42px rgba(29,110,242,.2);color:#fff}
        .btn.primary:hover{box-shadow:0 20px 46px rgba(29,110,242,.27)}
        .btn.ghost{background:rgba(255,255,255,.08);backdrop-filter:blur(4px)}

        .visual{display:flex;align-items:center;justify-content:center;padding:8px}
        .visual img{width:100%;height:100%;max-height:620px;object-fit:cover;border-radius:24px;box-shadow:var(--shadow);border:1px solid rgba(255,255,255,.18)}

        .theme-toggle{margin-left:8px}
        .theme-toggle button{background:rgba(255,255,255,.45);border:1px solid rgba(15,29,50,.08);padding:10px 12px;border-radius:999px;cursor:pointer;font-size:16px;box-shadow:0 8px 18px rgba(15,29,50,.08)}
        .dark-theme .theme-toggle button{border-color:rgba(255,255,255,.08);background:rgba(17,35,52,.6)}

        .error{color:var(--danger);font-size:14px;margin-bottom:16px;padding:12px 14px;background:rgba(239,83,80,.08);border:1px solid rgba(239,83,80,.12);border-radius:12px}
        .info{color:var(--primary);font-size:14px;margin-bottom:16px;padding:12px 14px;background:rgba(29,110,242,.08);border:1px solid rgba(29,110,242,.12);border-radius:12px}
        .security-banner{display:flex;flex-direction:column;gap:6px;margin:0 0 18px;padding:14px 16px;border-radius:16px;border:1px solid rgba(29,110,242,.12);background:linear-gradient(135deg, rgba(29,110,242,.08), rgba(18,176,163,.05));color:var(--text)}
        .security-banner strong{font-size:14px}
        .security-banner span{font-size:13px;color:var(--muted)}

        @media (max-width:960px){
            .card{grid-template-columns:1fr;}
            .visual{order:1}
            .topbar{flex-wrap:wrap}
            .progressbar{width:100%;justify-content:space-between}
            .brand img{height:58px}
        }
        @media (max-width:620px){
            .container{padding:12px 14px 28px}
            .topbar{padding:12px 14px;border-radius:18px}
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
                <div class="step done"><div class="dot">1</div><div>Info</div></div>
                <div class="step done"><div class="dot">2</div><div>Validation</div></div>
                <div class="step active"><div class="dot">3</div><div>2FA</div></div>
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
                <h2>Sécurisation de Votre Compte</h2>
                <div class="muted">Étape 3 sur 3 : Créez un mot de passe sécurisé</div>
                <div class="security-banner">
                    <strong>Protection du compte</strong>
                    <span>Votre mot de passe protège vos accès futurs. Nous ne demandons jamais de partage de votre mot de passe.</span>
                </div>
                <div class="info"><?php echo $nomComplet . ' — ' . $email; ?></div>
                <?php if ($error !== ''): ?>
                    <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <form method="post" id="passwordForm">
                    <div>
                        <label for="password">Mot de passe</label>
                        <div class="password-conditions">
                            <ul>
                                <li>Au moins 8 caractères</li>
                                <li>Au moins une lettre majuscule</li>
                                <li>Au moins une lettre minuscule</li>
                                <li>Au moins un chiffre</li>
                                <li>Au moins un caractère spécial parmi (!@#$%)</li>
                            </ul>
                        </div>
                        <div class="field">
                            <input id="password" name="password" type="password" required />
                            <label><input type="checkbox" id="showPassword"> Afficher le mot de passe</label>
                        </div>
                    </div>

                    <div>
                        <label for="confirm">Confirmer le mot de passe</label>
                        <div class="field">
                            <input id="confirm" name="confirm" type="password" required />
                            <label><input type="checkbox" id="showConfirm"> Afficher le mot de passe</label>
                        </div>
                    </div>

                    <div class="password-strength" id="strength" style="display:none;">
                        <div class="strength-bar">
                            <div class="strength-fill" id="fill" style="width:0%"></div>
                        </div>
                        <span id="strengthText">Faible</span>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn primary">Terminer l'inscription</button>
                        <a href="etape2.php" class="btn ghost">Retour</a>
                        <a href="../index.php" class="btn ghost">Accueil</a>
                    </div>
                </form>
            </div>

            <aside class="visual" aria-hidden="true">
                <img src="../../images/cybersecurity.jpg" alt="Illustration de sécurité" />
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

        // Password strength and validation
        (function(){
            var form = document.getElementById('passwordForm');
            var passwordInput = document.getElementById('password');
            var confirmInput = document.getElementById('confirm');
            var submitBtn = form.querySelector('button[type="submit"]');
            var fill = document.getElementById('fill');
            var strengthText = document.getElementById('strengthText');
            var strengthContainer = document.getElementById('strength');
            var isSubmitting = false;

            // Validate password strength
            var validatePassword = function(pass) {
                var strength = 0;
                var checks = {
                    length: pass.length >= 8,
                    lowercase: /[a-z]/.test(pass),
                    uppercase: /[A-Z]/.test(pass),
                    number: /[0-9]/.test(pass),
                    special: /[!@#$%]/.test(pass)
                };
                
                for (var key in checks) {
                    if (checks[key]) strength++;
                }
                return { strength: strength, checks: checks };
            };

            // Update password strength display
            var updateStrength = function(pass) {
                var result = validatePassword(pass);
                var strength = result.strength;
                
                strengthContainer.style.display = pass ? 'block' : 'none';
                fill.style.width = (strength / 5 * 100) + '%';
                
                var colors = ['#ff4444', '#ff8844', '#ffaa44', '#9acd32', '#00ff00'];
                fill.style.background = colors[strength - 1] || '#ff4444';
                
                var texts = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
                strengthText.textContent = texts[strength - 1] || 'Très faible';
                strengthText.style.color = colors[strength - 1] || '#ff4444';
                
                return strength === 5;
            };

            // Real-time validation
            passwordInput.addEventListener('input', function(){
                updateStrength(this.value);
                validateMatch();
            });

            // Validate password match
            var validateMatch = function() {
                var pass = passwordInput.value;
                var confirm = confirmInput.value;
                
                if (confirm && pass !== confirm) {
                    confirmInput.style.borderColor = '#ff4444';
                } else if (confirm) {
                    confirmInput.style.borderColor = '#18b78a';
                } else {
                    confirmInput.style.borderColor = '';
                }
            };

            confirmInput.addEventListener('input', validateMatch);
            confirmInput.addEventListener('blur', function(){
                if (this.value && passwordInput.value !== this.value) {
                    this.style.borderColor = '#ff4444';
                }
            });

            // Show/hide password
            document.getElementById('showPassword').addEventListener('change', function(){
                passwordInput.type = this.checked ? 'text' : 'password';
            });
            document.getElementById('showConfirm').addEventListener('change', function(){
                confirmInput.type = this.checked ? 'text' : 'password';
            });

            // Prevent double-submit
            form.addEventListener('submit', function(e){
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }

                var pass = passwordInput.value;
                var confirm = confirmInput.value;
                var result = validatePassword(pass);

                // Validate requirements
                if (!result.checks.length || !result.checks.lowercase || !result.checks.uppercase || 
                    !result.checks.number || !result.checks.special) {
                    e.preventDefault();
                    var errorDiv = form.querySelector('.error');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'error';
                        form.insertBefore(errorDiv, form.firstChild);
                    }
                    errorDiv.textContent = 'Le mot de passe ne respecte pas tous les critères.';
                    return false;
                }

                if (pass !== confirm) {
                    e.preventDefault();
                    var errorDiv = form.querySelector('.error');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'error';
                        form.insertBefore(errorDiv, form.firstChild);
                    }
                    errorDiv.textContent = 'Les mots de passe ne correspondent pas.';
                    return false;
                }

                isSubmitting = true;
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Inscription en cours...';
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
            });
        })();
    </script>
</body>
</html>
