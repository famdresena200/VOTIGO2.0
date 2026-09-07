<?php
session_start();
require __DIR__ . '/_inc/bootstrap.php';
require __DIR__ . '/_inc/db.php';

if (empty($_SESSION['reset_data']) || $_SESSION['reset_data']['step'] !== 'reset') {
    header('Location: forgot_password.php');
    exit;
}

if (time() > $_SESSION['reset_data']['expires_at']) {
    unset($_SESSION['reset_data']);
    header('Location: forgot_password.php');
    exit;
}

$reset_data = $_SESSION['reset_data'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%]).{8,}$/', $password)) {
        $error = "Le mot de passe doit contenir au moins 8 caractères, une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial (!@#$%).";
    } else {
        try {
            $pdo = db();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id_user = ?');
            $stmt->execute([$hash, $reset_data['id_user']]);

            // Nettoyage et redirection vers login
            unset($_SESSION['reset_data']);
            $_SESSION['success'] = "Votre mot de passe a été réinitialisé avec succès !";
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            $error = "Erreur serveur.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/jpeg" href="../../images/IMG-20260127-WA0054.jpg" />
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Réinitialisation du mot de passe — VOTIGO</title>
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

        .card{display:grid;grid-template-columns:1fr 420px;gap:24px;background:var(--card-bg);padding:28px;border-radius:12px;margin-top:18px;align-items:start}
        .form h2{margin:6px 0 6px 0}
        .muted{color:var(--muted);font-size:14px;margin-bottom:18px}
        .info{color:var(--blue);font-size:13px;margin-bottom:16px;padding:10px;background:rgba(43,132,255,0.1);border-radius:8px}

        label{font-size:13px;color:var(--muted);margin-bottom:6px;display:block}
        input[type="password"]{width:100%;padding:12px 14px;border-radius:8px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text)}
        
        .field{margin-bottom:16px}
        .field-label{display:flex;align-items:center;justify-content:space-between}
        .field-label label{margin:0}
        .checkbox-label{font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;cursor:pointer;margin-top:6px}

        .requirements{font-size:13px;color:var(--muted);margin:12px 0;list-style:none;padding:0}
        .requirements li{margin-bottom:4px}
        .requirements li:before{content:'✓ ';color:var(--blue);font-weight:600;margin-right:4px}

        .strength-bar{margin:12px 0;height:6px;background:var(--input-border);border-radius:3px;overflow:hidden}
        .strength-fill{height:100%;background:linear-gradient(90deg,#ff4d4d,#ffa500,#ffff00,#9acd32,#00ff00);transition:width .3s;width:0%}
        .strength-text{font-size:13px;color:var(--muted);margin-bottom:12px}

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
            <div class="theme-toggle" title="Changer le thème">
                <button id="themeBtn" aria-pressed="false">🌙</button>
            </div>
        </header>

        <main class="card">
            <section class="form">
                <h2>Créer un Nouveau Mot de Passe</h2>
                <div class="muted">Choisissez un mot de passe sécurisé</div>
                <div class="info"><?php echo htmlspecialchars($reset_data['nom'], ENT_QUOTES, 'UTF-8'); ?></div>

                <?php if ($error !== ''): ?>
                    <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" id="resetForm" novalidate>
                    <div class="field">
                        <label for="password">Nouveau mot de passe</label>
                        <input id="password" name="password" type="password" placeholder="••••••••" required />
                        <label class="checkbox-label">
                            <input type="checkbox" id="showPassword"> Afficher
                        </label>
                    </div>

                    <div class="field">
                        <label for="confirm">Confirmer le mot de passe</label>
                        <input id="confirm" name="confirm" type="password" placeholder="••••••••" required />
                        <label class="checkbox-label">
                            <input type="checkbox" id="showConfirm"> Afficher
                        </label>
                    </div>

                    <ul class="requirements">
                        <li>Au moins 8 caractères</li>
                        <li>Au moins une lettre majuscule (A-Z)</li>
                        <li>Au moins une lettre minuscule (a-z)</li>
                        <li>Au moins un chiffre (0-9)</li>
                        <li>Au moins un caractère spécial (!@#$%)</li>
                    </ul>

                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-text" id="strengthText"></div>

                    <div class="actions">
                        <button type="submit" class="btn primary">Réinitialiser le mot de passe</button>
                        <a href="login.php" class="btn ghost">Retour à la connexion</a>
                    </div>
                </form>
            </section>

            <aside class="visual" aria-hidden="true">
                <img src="../../images/cybersecurity.jpg" alt="Illustration de sécurité" />
            </aside>
        </main>
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

        // Password strength
        var passInput = document.getElementById('password');
        var strengthFill = document.getElementById('strengthFill');
        var strengthText = document.getElementById('strengthText');

        passInput.addEventListener('input', function(){
            var pass = this.value;
            var strength = 0;
            if (pass.length >= 8) strength++;
            if (/[a-z]/.test(pass)) strength++;
            if (/[A-Z]/.test(pass)) strength++;
            if (/[0-9]/.test(pass)) strength++;
            if (/[!@#$%]/.test(pass)) strength++;

            var percent = (strength / 5) * 100;
            strengthFill.style.width = percent + '%';
            
            var texts = ['', 'Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
            strengthText.textContent = texts[strength] || '';
        });

        // Show/hide password
        document.getElementById('showPassword').addEventListener('change', function(){
            document.getElementById('password').type = this.checked ? 'text' : 'password';
        });
        document.getElementById('showConfirm').addEventListener('change', function(){
            document.getElementById('confirm').type = this.checked ? 'text' : 'password';
        });
    </script>
</body>
</html>
