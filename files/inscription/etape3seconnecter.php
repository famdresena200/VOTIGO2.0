<?php
session_start();
require __DIR__ . '/_inc/db.php';

$reset_mode = isset($_SESSION['reset_mode']) && $_SESSION['reset_mode'];

if (!$reset_mode) {
    header("Location: login.php");
    exit;
}

// Reset mode - handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%]).{8,}$/', $password)) {
        $error = "Le mot de passe doit contenir au moins 8 caractères, une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial (!@#$%).";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $pdo = db();
            $stmt = $pdo->prepare("UPDATE USERS SET password_hash = ? WHERE id_user = ?");
            $stmt->execute([$hash, $_SESSION['reset_user_id']]);
            
            // Clear session variables
            unset($_SESSION['reset_user_id'], $_SESSION['reset_user_name'], $_SESSION['reset_mode']);
            $_SESSION['success'] = "Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
            header("Location: login.php");
            exit;
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
    <title>Nouveau Mot de Passe — VOTIGO</title>
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
        .topbar{display:flex;align-items:center;gap:20px;justify-content:space-between}
        .brand{display:flex;align-items:center;gap:14px;font-weight:700}
        .brand img{height:90px;width:auto;object-fit:contain;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,0.12);transition:transform .2s ease}
        .brand img:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,0,0,0.16)}

        .header{text-align:center;margin-bottom:32px}
        .progress-bar{display:flex;gap:6px;margin-bottom:24px;justify-content:center}
        .step{width:28px;height:28px;border-radius:50%;background:var(--input-border);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:var(--muted);transition:all .2s}
        .step.done{background:#18b78a;color:#fff}
        .step.active{background:var(--blue);color:#fff;box-shadow:0 0 0 4px rgba(43,132,255,0.2)}

        .card{background:var(--card-bg);padding:28px;border-radius:12px;max-width:520px;margin:0 auto;box-shadow:var(--shadow)}
        .card h2{margin:0 0 6px;font-size:18px}
        .muted{color:var(--muted);font-size:14px;margin-bottom:18px}

        form .row{display:grid;grid-template-columns:1fr;gap:12px;margin-bottom:12px}
        label{font-size:13px;color:var(--muted);margin-bottom:6px;display:block;font-weight:500}
        input[type="password"],input[type="text"]{width:100%;padding:12px 14px;border-radius:8px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text);font-size:14px;transition:all 0.2s ease}
        input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(43,132,255,0.1)}

        .password-feedback{display:grid;grid-template-columns:8px 1fr;gap:8px;font-size:13px;margin-bottom:12px;align-items:center;color:var(--muted)}
        .password-feedback.done{color:#18b78a}
        .password-feedback.done circle{fill:#18b78a}
        .password-feedback circle{fill:var(--input-border)}

        .strength-meter{margin:12px 0;padding:12px;background:rgba(43,132,255,0.08);border-radius:8px;border-left:3px solid var(--blue)}
        .strength-bar{width:100%;height:4px;background:var(--input-border);border-radius:2px;overflow:hidden;margin-bottom:6px}
        .strength-fill{height:100%;background:linear-gradient(90deg,#ff4d4d 0%,#ffb84d 25%,#ffd700 50%,#b3d700 75%,#2bdc59 100%);transition:width .2s}
        .strength-text{font-size:12px;font-weight:600}

        .checkbox-row{display:flex;align-items:center;gap:8px;font-size:13px;margin:6px 0}
        .checkbox-row input{margin:0}

        .btn{padding:11px 20px;border-radius:8px;border:0;cursor:pointer;font-weight:600;font-size:15px;transition:all 0.2s ease;width:100%;margin-top:12px}
        .btn.primary{background:linear-gradient(180deg,var(--blue),var(--blue-dark));color:#fff;box-shadow:0 4px 12px rgba(43,132,255,0.25)}
        .btn.primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(43,132,255,0.35)}
        .btn.primary:disabled{opacity:.6;cursor:not-allowed;transform:none}
        .btn.ghost{background:transparent;border:1.5px solid #cfe0ff;color:var(--blue-dark);text-decoration:none;width:auto}
        .btn.ghost:hover{background:rgba(43,132,255,0.05)}

        .error{color:#ff4444;font-size:14px;margin-bottom:16px;padding:12px 14px;background:rgba(255,68,68,0.12);border-radius:8px;border-left:3px solid #ff4444;font-weight:500}
        .success{color:#18b78a;font-size:14px;margin-bottom:16px;padding:12px 14px;background:rgba(24,183,138,0.12);border-radius:8px;border-left:3px solid #18b78a;font-weight:500}

        .theme-toggle{margin-left:auto}
        .theme-toggle button{background:rgba(43,132,255,0.1);border:1px solid rgba(43,132,255,0.3);padding:8px 12px;border-radius:8px;cursor:pointer;font-size:16px;transition:all 0.2s ease}
        .theme-toggle button:hover{background:rgba(43,132,255,0.15);border-color:rgba(43,132,255,0.5)}
        .dark-theme .theme-toggle button{background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.12)}
        .dark-theme .theme-toggle button:hover{background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.2)}

        .actions{display:flex;gap:12px;flex-direction:column}

        @media (max-width:960px){
            .card{max-width:100%}
            .topbar{flex-wrap:wrap}
            .brand img{height:75px}
        }

        @media (max-width:768px){
            .container{padding:12px}
            .card{padding:20px}
            .topbar{gap:12px}
            .brand img{height:65px}
        }

        @media (max-width:480px){
            .container{padding:8px}
            .card{padding:16px}
            .topbar{gap:8px}
            .brand{gap:10px}
            .brand img{height:55px}
            .card h2{font-size:16px}
            input{font-size:16px}
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
            <div class="theme-toggle" title="Changer le thème">
                <button id="themeBtn" aria-pressed="false">🌙</button>
            </div>
        </header>

        <div class="header">
            <div class="progress-bar">
                <div class="step done">1</div>
                <div class="step done">2</div>
                <div class="step active">3</div>
            </div>
        </div>

        <main class="card">
            <h2>Créer un Nouveau Mot de Passe</h2>
            <div class="muted">Étape 3 sur 3 : Sécurisez votre compte avec un mot de passe robuste</div>
            
            <?php if (isset($error) && $error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" id="password-form" novalidate>
                <div class="row">
                    <label for="password">Nouveau Mot de Passe *</label>
                    <input id="password" type="password" name="password" placeholder="Minimum 8 caractères" required />
                </div>

                <div id="strength-feedback" style="display:none;margin-bottom:12px">
                    <div class="strength-meter">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strength-fill" style="width:0%"></div>
                        </div>
                        <div class="strength-text" id="strength-text">Très faible</div>
                    </div>
                    <div class="password-feedback" id="check-length">
                        <svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                        <span>Au moins 8 caractères</span>
                    </div>
                    <div class="password-feedback" id="check-lowercase">
                        <svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                        <span>Une lettre minuscule (a-z)</span>
                    </div>
                    <div class="password-feedback" id="check-uppercase">
                        <svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                        <span>Une lettre majuscule (A-Z)</span>
                    </div>
                    <div class="password-feedback" id="check-number">
                        <svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                        <span>Au moins un chiffre (0-9)</span>
                    </div>
                    <div class="password-feedback" id="check-special">
                        <svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                        <span>Un caractère spécial (!@#$%)</span>
                    </div>
                </div>

                <div class="checkbox-row">
                    <input type="checkbox" id="show-password" />
                    <label for="show-password" style="margin:0">Afficher le mot de passe</label>
                </div>

                <div class="row">
                    <label for="confirm">Confirmer le Mot de Passe *</label>
                    <input id="confirm" type="password" name="confirm" placeholder="Répétez le mot de passe" required />
                </div>

                <div class="checkbox-row">
                    <input type="checkbox" id="show-confirm" />
                    <label for="show-confirm" style="margin:0">Afficher le mot de passe</label>
                </div>

                <div class="actions">
                    <button id="submit-btn" type="submit" class="btn primary">Réinitialiser le Mot de Passe</button>
                    <a href="login.php"><button type="button" class="btn ghost">Retour à la Connexion</button></a>
                </div>
            </form>
        </main>
    </div>

    <script>
        (function() {
            // Theme toggle
            const themeBtn = document.getElementById('themeBtn');
            const html = document.documentElement;
            const savedTheme = localStorage.getItem('theme') || 'light';
            
            if (savedTheme === 'dark') html.classList.add('dark-theme');
            themeBtn.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
            themeBtn.setAttribute('aria-pressed', savedTheme === 'dark' ? 'true' : 'false');
            
            themeBtn.addEventListener('click', () => {
                html.classList.toggle('dark-theme');
                const isDark = html.classList.contains('dark-theme');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                themeBtn.textContent = isDark ? '☀️' : '🌙';
                themeBtn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            });

            // Password strength checker
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm');
            const strengthFeedback = document.getElementById('strength-feedback');
            const strengthFill = document.getElementById('strength-fill');
            const strengthText = document.getElementById('strength-text');
            const form = document.getElementById('password-form');
            const submitBtn = document.getElementById('submit-btn');

            const checks = {
                length: { elem: document.getElementById('check-length'), regex: /.{8,}/ },
                lowercase: { elem: document.getElementById('check-lowercase'), regex: /[a-z]/ },
                uppercase: { elem: document.getElementById('check-uppercase'), regex: /[A-Z]/ },
                number: { elem: document.getElementById('check-number'), regex: /[0-9]/ },
                special: { elem: document.getElementById('check-special'), regex: /[!@#$%]/ }
            };

            function updatePasswordStrength() {
                const password = passwordInput.value;
                strengthFeedback.style.display = password ? 'block' : 'none';

                let score = 0;
                for (const [key, check] of Object.entries(checks)) {
                    const isValid = check.regex.test(password);
                    check.elem.classList.toggle('done', isValid);
                    if (isValid) score++;
                }

                strengthFill.style.width = (score / 5 * 100) + '%';
                const levels = ['Très faible', 'Faible', 'Acceptable', 'Bon', 'Excellent'];
                strengthText.textContent = score > 0 ? levels[score - 1] : 'Très faible';
            }

            passwordInput.addEventListener('input', updatePasswordStrength);

            document.getElementById('show-password').addEventListener('change', function() {
                passwordInput.type = this.checked ? 'text' : 'password';
            });

            document.getElementById('show-confirm').addEventListener('change', function() {
                confirmInput.type = this.checked ? 'text' : 'password';
            });

            form.addEventListener('submit', function(e) {
                const password = passwordInput.value.trim();
                const confirm = confirmInput.value.trim();

                if (password !== confirm) {
                    // Let server handle the mismatch
                }

                if (!new RegExp('^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[!@#$%]).{8,}$').test(password)) {
                    e.preventDefault();
                    // Error will be shown by server or client-side
                }

                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Traitement...';
            });
        })();
    </script>
</body>
</html>