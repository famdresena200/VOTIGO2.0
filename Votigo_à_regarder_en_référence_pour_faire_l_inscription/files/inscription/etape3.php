<?php
session_start();
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: etape1.php");
    exit;
}

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
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE USERS SET password_hash = ? WHERE id_user = ?");
            $stmt->execute([$hash, $_SESSION['user_id']]);
            // Garder les variables de session pour maintenir l'utilisateur connecté
            unset($_SESSION['validation_code']);
            $_SESSION['success'] = "Inscription terminée avec succès !";
            // Rediriger directement vers la page principale
            header("Location: ../se connecter/login.php");
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
    <title>Sécurisation de Votre Compte — VOTIGO</title>
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
        .brand svg{width:34px;height:34px}
        .progressbar{display:flex;gap:14px;align-items:center;margin-left:auto}
        .step{display:flex;flex-direction:column;align-items:center;font-size:13px;color:var(--muted)}
        .step .dot{width:30px;height:30px;border-radius:999px;background:#fff;border:2px solid #dbe7ff;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--blue)}
        .step.done .dot{background:#18b78a;color:white;border-color:transparent}
        .step.active .dot{background:linear-gradient(180deg,var(--blue) 0%,var(--blue-dark) 100%);color:white;border-color:transparent}

        .password-conditions ul {
            list-style: none;
            padding: 0;
            margin: 6px 0 12px;
            font-size: 13px;
            color: var(--muted);
        }
        .password-conditions li {
            margin-bottom: 4px;
        }
        .field {
            margin-bottom: 12px;
        }
        .field input[type="password"], .field input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--text);
        }
        .field label {
            display: flex;
            align-items: center;
            font-size: 13px;
            color: var(--muted);
            margin-top: 6px;
        }

        .password-strength{
            margin:20px 0;
            padding:12px;
            border-radius:8px;
            background:rgba(0,212,255,0.1);
            border:1px solid var(--input-border);
        }

        .strength-bar{
            width:100%;
            height:6px;
            background:var(--input-border);
            border-radius:3px;
            overflow:hidden;
            margin-bottom:8px;
        }

        .strength-fill{
            height:100%;
            background:linear-gradient(90deg, #ff4d4d, #ffa500, #ffff00, #9acd32, #00ff00);
            transition:width 0.3s;
        }

        .card{display:grid;grid-template-columns:1fr 420px;gap:24px;background:var(--card-bg);padding:28px;border-radius:12px;margin-top:18px;align-items:start}
        .form h2{margin:6px 0 6px 0}
        .muted{color:var(--muted);font-size:14px;margin-bottom:18px}

        form .row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
        label{font-size:13px;color:var(--muted);margin-bottom:6px;display:block}
        input[type="password"]{width:100%;padding:12px 14px;border-radius:8px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text)}

        .actions{display:flex;gap:12px;align-items:center;margin-top:18px}
        .btn{padding:10px 18px;border-radius:999px;border:0;cursor:pointer;font-weight:600}
        .btn.primary{background:linear-gradient(180deg,var(--blue),var(--blue-dark));color:#fff}
        .btn.ghost{background:#fff;border:1px solid #cfe0ff;color:var(--blue-dark)}

        .visual{display:flex;align-items:center;justify-content:center}
        .visual img{width:100%;height:auto;border-radius:12px;filter:drop-shadow(var(--shadow))}

        .theme-toggle{margin-left:14px}
        .theme-toggle button{background:transparent;border:1px solid rgba(0,0,0,0.06);padding:8px;border-radius:999px;cursor:pointer;font-size:16px}
        .dark-theme .theme-toggle button{border-color:rgba(255,255,255,0.08)}

        @media (max-width:960px){
            .card{grid-template-columns:1fr;}
            .visual{order:1}
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div class="brand">
               <img src="../../images/logo_ispm.png" alt="logo logo_ispm" style="height: 90px; width:auto; object-fit:contain; border-radius: 20px;"/>
            </div>

            <div class="progressbar" role="navigation" aria-label="Progression">
                <div class="step done"><div class="dot">1</div><div>Info</div></div>
                <div class="step done"><div class="dot">2</div><div>Validation</div></div>
                <div class="step active"><div class="dot">3</div><div>2FA</div></div>
            </div>

            <div class="brand">
               <img src="../../images/votigo.jpg" alt="logo VOTIGO" style="height: 90px; width:auto; object-fit:contain; border-radius: 20px;"/>
            </div>
            <div class="theme-toggle" title="Changer le thème">
                <button id="themeBtn" aria-pressed="false">🌙</button>
            </div>
        </div>

        <section class="card">
            <div class="form">
                <h2>Sécurisation de Votre Compte</h2>
                <div class="muted">Étape 3 sur 3 : Créez un mot de passe sécurisé</div>
                <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
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
                        <a href="etape2.php"><button type="button" class="btn ghost">Retour</button></a>
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

        // Password strength
        document.getElementById('password').addEventListener('input', function(){
            var pass = this.value;
            var strength = 0;
            if (pass.length >= 8) strength++;
            if (/[a-z]/.test(pass)) strength++;
            if (/[A-Z]/.test(pass)) strength++;
            if (/[0-9]/.test(pass)) strength++;
            if (/[!@#$%]/.test(pass)) strength++;

            var fill = document.getElementById('fill');
            var text = document.getElementById('strengthText');
            var container = document.getElementById('strength');
            container.style.display = pass ? 'block' : 'none';
            fill.style.width = (strength / 5 * 100) + '%';
            var texts = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
            text.textContent = texts[strength - 1] || 'Très faible';
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