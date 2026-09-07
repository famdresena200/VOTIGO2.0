<?php
session_start();
require __DIR__ . '/_inc/db.php';
require __DIR__ . '/../_inc/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $numero_electeur = $_POST['electeur'] ?? '';
    $code_secret = $_POST['secret'] ?? '';

    // Vérifier que les champs sont remplis
    if (empty($numero_electeur) || empty($code_secret)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT id_user, nom, prenom, password_hash FROM users WHERE nen = ?");
            $stmt->execute([$numero_electeur]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($code_secret, $user['password_hash'])) {
                $userName = $user['nom'] . ' ' . $user['prenom'];
                login_electeur($user['id_user'], $userName);
                header("Location: ../app/dashboard.php");
                exit;
            } else {
                $error = "Numéro d’électeur ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion à la base de données.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VOTIGO - Connexion</title>
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

        [data-theme="dark"] {
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
        html,body{height:100%;}
        body{
            margin:0;
            font-family:Inter,Segoe UI,Arial,sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at top left, rgba(66, 132, 255, 0.18), transparent 28%),
                radial-gradient(circle at bottom right, rgba(18, 176, 163, 0.18), transparent 24%),
                linear-gradient(135deg, var(--bg), var(--bg-strong));
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
            display:flex;
            align-items:center;
            justify-content:center;
            transition:background .25s ease,color .25s ease;
        }

        .container{
            position:relative; z-index:2; width:100%; max-width:1200px; padding:40px 20px; display:flex; justify-content:center;
        }

        .card{
            width:100%; max-width:460px;
            background:var(--surface); backdrop-filter:blur(14px);
            border:1px solid var(--border); border-radius:28px; box-shadow:var(--shadow);
            padding:30px 28px 24px; text-align:left;
        }

        .logo-row{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
            margin-bottom:18px;
        }

        .logo{
            height:72px;
            width:auto;
            max-width:160px;
            object-fit:contain;
            border-radius:18px;
            box-shadow:0 16px 30px rgba(12,25,39,0.12);
        }

        .top-links{
            display:flex;
            justify-content:center;
            gap:8px;
            flex-wrap:wrap;
            margin-bottom:14px;
        }
        .link-btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:9px 14px;
            border-radius:999px;
            border:1px solid rgba(29,110,242,.18);
            text-decoration:none;
            color:var(--text);
            font-size:12px;
            font-weight:700;
            background:rgba(255,255,255,.12);
        }

        .security-banner{
            display:flex;
            flex-direction:column;
            gap:6px;
            padding:14px 16px;
            margin:0 0 18px;
            border-radius:16px;
            border:1px solid rgba(29,110,242,.12);
            background:linear-gradient(135deg, rgba(29,110,242,.08), rgba(18,176,163,.05));
            text-align:left;
        }
        .security-banner strong{font-size:14px; color:var(--text)}
        .security-banner span{font-size:13px; color:var(--muted)}

        h1{
            margin:8px 0 20px; font-size:clamp(1.8rem,2vw,2.2rem); font-weight:800; color:var(--text); line-height:1.2;
        }

        form{display:flex; flex-direction:column; gap:12px; align-items:stretch;}

        .field{
            display:flex; align-items:center; gap:10px; border:1px solid var(--border); padding:12px 14px; border-radius:14px; background:rgba(255,255,255,.62); box-shadow:inset 0 1px 2px rgba(15,29,50,.02);
        }
        .field:focus-within{box-shadow:0 0 0 4px rgba(29,110,242,.08); border-color:rgba(29,110,242,.42)}

        .field svg{width:20px; height:20px; flex:0 0 20px; color:var(--muted)}
        .field input{border:0; outline:none; font-size:15px; color:var(--text); flex:1; background:transparent}

        .btn{
            margin-top:6px;
            border:0;
            padding:13px 16px;
            background:linear-gradient(135deg, #1d6ef2, #12b0a3);
            color:#fff;
            font-weight:800;
            border-radius:999px;
            cursor:pointer;
            font-size:15px;
            box-shadow:0 18px 42px rgba(29,110,242,.2);
            transition:all .2s cubic-bezier(.4,0,.2,1);
            border:1px solid rgba(29,110,242,.2);
        }
        .btn:hover{
            transform:translateY(-2px);
            filter:brightness(1.08);
            box-shadow:0 22px 52px rgba(29,110,242,.28);
        }
        .btn:active{transform:translateY(1px)}

        .small{
            font-size:13px; color:var(--muted);
            margin-top:10px;
            line-height:1.6;
        }

        .footer{
            margin-top:14px; display:flex; justify-content:space-between; align-items:center; font-size:13px; color:var(--muted); gap:10px;
        }

        .anchor{color:var(--primary); text-decoration:none; font-weight:700}

        .error-message{color:var(--danger); font-weight:700; margin-bottom:12px; padding:12px 14px; background:rgba(239,83,80,.08); border:1px solid rgba(239,83,80,.12); border-radius:12px;}
        .success-message{color:var(--success); font-weight:700; margin-bottom:12px; padding:12px 14px; background:rgba(27,191,141,.08); border:1px solid rgba(27,191,141,.12); border-radius:12px;}

        #theme-toggle{margin-right:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:700; background:rgba(255,255,255,.18); border:1px solid rgba(29,110,242,.16); box-shadow:none; }

        @media (max-width:520px){
            .card{border-radius:22px; padding:22px 18px 18px}
            .logo-row{gap:10px}
            .logo{height:58px}
            .container{padding:20px 12px}
        }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true"></div>
    <div class="overlay" aria-hidden="true"></div>

    <main class="container">
        <section class="card" role="main" aria-labelledby="signin-title">
            <div class="logo-row">
                <img class="logo" src="../../images/logo_ispm.png" alt="ISPM logo" style="border-radius: 20px;">
                <img class="logo" src="../../images/IMG-20260127-WA0054.jpg" alt="VOTIGO logo" style="border-radius: 20px;">
             </div>

            <div class="top-links">
                <a href="../index.php" class="link-btn">Accueil</a>
                <a href="etape1.php" class="link-btn">Créer un compte</a>
            </div>

            <h1 id="signin-title">Connexion Électeur</h1>
            <div class="security-banner">
                <strong>Accès sécurisé</strong>
                <span>Connectez-vous à votre espace voteur pour accéder à votre tableau de bord et vos élections.</span>
            </div>
            <?php if (isset($_SESSION['success'])) { echo "<p class='success-message'>".$_SESSION['success']."</p>"; unset($_SESSION['success']); } ?>
            <?php if (isset($error)) echo "<p class='error-message'>$error</p>"; ?>
            <form method="post" id="login-form" novalidate>
                <div class="field" title="Numéro d'Électeur">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <input id="electeur" type="text" name="electeur" placeholder="Numéro d'Électeur (10 chiffres)" pattern="[0-9]{10}" maxlength="10" inputmode="numeric" required>
                </div>
                <div id="electeur-error" style="color:#d32f2f;font-size:13px;min-height:16px;"></div>

                <div class="field" title="Mot de passe">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <input id="secret" type="password" name="secret" placeholder="Votre mot de passe" required>
                </div>

                <button id="login-btn" class="btn" type="submit">Se Connecter</button>

                <div class="small">Vos données restent protégées et utilisées uniquement pour votre accès au vote.</div>

                <div class="footer">
                    <a class="anchor" href="forgot_password.php">Mot de passe oublié ?</a>
                    <button id="theme-toggle" type="button" class="btn" style="font-size:12px; padding:6px 10px; margin-right:10px;">🌙</button>
                </div>
            </form>
        </section>
    </main>

    <script>
        (function() {
            const themeToggle = document.getElementById('theme-toggle');
            const html = document.documentElement;

            // Load saved theme
            const savedTheme = localStorage.getItem('theme') || 'light';
            html.setAttribute('data-theme', savedTheme);
            themeToggle.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

            themeToggle.addEventListener('click', (e) => {
                e.preventDefault();
                const currentTheme = html.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                themeToggle.textContent = newTheme === 'dark' ? '☀️' : '🌙';
            });

            // Validation en temps réel
            const form = document.getElementById('login-form');
            const electeurInput = document.getElementById('electeur');
            const secretInput = document.getElementById('secret');
            const loginBtn = document.getElementById('login-btn');
            const electeurError = document.getElementById('electeur-error');
            let isSubmitting = false;

            function validateElecteur(value) {
                return /^\d{10}$/.test(value);
            }

            function updateElecteurError() {
                const value = electeurInput.value.trim();
                
                if (!value) {
                    electeurError.textContent = '';
                    electeurInput.parentElement.style.borderColor = '';
                    return true;
                }

                if (!validateElecteur(value)) {
                    electeurError.textContent = 'Le numéro d\'électeur doit contenir exactement 10 chiffres.';
                    electeurInput.parentElement.style.borderColor = '#d32f2f';
                    return false;
                }

                electeurError.textContent = '';
                electeurInput.parentElement.style.borderColor = '';
                return true;
            }

            electeurInput.addEventListener('input', () => {
                // Allow only digits
                electeurInput.value = electeurInput.value.replace(/[^\d]/g, '');
                updateElecteurError();
            });

            electeurInput.addEventListener('blur', updateElecteurError);

            // Prevent double-submit
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }

                if (!validateElecteur(electeurInput.value.trim())) {
                    e.preventDefault();
                    electeurError.textContent = 'Le numéro d\'électeur doit contenir exactement 10 chiffres.';
                    electeurInput.parentElement.style.borderColor = '#d32f2f';
                    return false;
                }

                if (!secretInput.value.trim()) {
                    e.preventDefault();
                    secretInput.parentElement.style.borderColor = '#d32f2f';
                    return false;
                }

                isSubmitting = true;
                loginBtn.disabled = true;
                loginBtn.textContent = '⏳ Connexion en cours...';
                loginBtn.style.opacity = '0.6';
                loginBtn.style.cursor = 'not-allowed';
            });

            // Clear error state on focus
            electeurInput.addEventListener('focus', () => {
                electeurInput.parentElement.style.borderColor = '';
                electeurError.textContent = '';
            });
            
            secretInput.addEventListener('focus', () => {
                secretInput.parentElement.style.borderColor = '';
            });
        })();
    </script>

</body>
</html>
