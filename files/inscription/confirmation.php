<?php
session_start();
require __DIR__ . '/_inc/bootstrap.php';
require __DIR__ . '/_inc/db.php';
require __DIR__ . '/../_inc/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: etape1.php');
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id_user, nom, prenom, email FROM USERS WHERE id_user = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user = null;
}

$userName = $user ? htmlspecialchars($user['prenom'] . ' ' . $user['nom'], ENT_QUOTES, 'UTF-8') : 'électeur';
$email = $user ? htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Inscription confirmée — VOTIGO</title>
    <style>
        :root{
            --bg: #eef5ff;
            --bg-strong: #dfeeff;
            --surface: rgba(255, 255, 255, 0.72);
            --text: #0f1d32;
            --muted: #5f7185;
            --primary: #1d6ef2;
            --primary-strong: #1349a3;
            --success: #1bbf8d;
            --border: rgba(141, 164, 194, 0.24);
            --shadow: 0 24px 80px rgba(16, 38, 60, 0.12);
        }
        .dark-theme{
            --bg: #091722;
            --bg-strong: #112334;
            --surface: rgba(13, 24, 35, 0.76);
            --text: #edf7ff;
            --muted: #a8bbd1;
            --primary: #72a8ff;
            --primary-strong: #9cc3ff;
            --success: #62e4b0;
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
        .container{max-width:980px;margin:36px auto;padding:20px}
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:26px;background:var(--surface);backdrop-filter:blur(12px);border:1px solid var(--border);border-radius:24px;padding:14px 18px;box-shadow:var(--shadow)}
        .brand{display:flex;align-items:center;gap:12px;font-weight:700}
        .brand img{height:72px;width:auto;object-fit:contain;border-radius:18px;box-shadow:0 16px 30px rgba(12,25,39,0.12)}
        .theme-toggle button{background:rgba(255,255,255,.45);border:1px solid rgba(15,29,50,.08);padding:10px 12px;border-radius:999px;cursor:pointer;font-size:16px;box-shadow:0 8px 18px rgba(15,29,50,.08)}
        .dark-theme .theme-toggle button{border-color:rgba(255,255,255,.08);background:rgba(17,35,52,.6)}
        .card{background:var(--surface);padding:34px;border-radius:28px;box-shadow:var(--shadow);border:1px solid var(--border);backdrop-filter:blur(14px)}
        .success-badge{display:inline-flex;align-items:center;gap:10px;padding:10px 16px;border-radius:999px;background:rgba(27,191,141,.12);color:#0e8f68;font-weight:800;border:1px solid rgba(27,191,141,.18)}
        h1{margin:20px 0 10px;font-size:clamp(2rem,3vw,3rem);line-height:1.1}
        .muted{color:var(--muted);font-size:16px;line-height:1.7}
        .info-box{margin-top:24px;padding:18px;border-radius:16px;background:linear-gradient(135deg, rgba(29,110,242,.08), rgba(18,176,163,.05));border:1px solid rgba(29,110,242,.12)}
        .info-box strong{display:block;margin-bottom:8px}
        .actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}
        .btn{padding:12px 20px;border-radius:999px;border:0;cursor:pointer;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;transition:all .2s ease}
        .btn.primary{background:linear-gradient(135deg, #1d6ef2, #12b0a3);color:#fff;box-shadow:0 18px 42px rgba(29,110,242,.2)}
        .btn.ghost{background:rgba(255,255,255,.08);border:1px solid rgba(29,110,242,.2);color:var(--text)}
        .list{margin:18px 0 0;padding-left:20px;color:var(--muted);line-height:1.8}
        @media (max-width:768px){
            .topbar{flex-wrap:wrap}
            .brand img{height:58px}
            .card{padding:22px;border-radius:20px}
            h1{font-size:2rem}
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="topbar">
            <div class="brand">
                <img src="../../images/logo_ispm.png" alt="Logo ISPM" />
                <img src="../../images/IMG-20260127-WA0054.jpg" alt="Logo VOTIGO" />
            </div>
            <div class="theme-toggle" title="Changer le thème">
                <button id="themeBtn" aria-pressed="false">🌙</button>
            </div>
        </header>

        <main class="card">
            <div class="success-badge">✓ Inscription confirmée</div>
            <h1>Votre compte a bien été créé</h1>
            <p class="muted">Bienvenue <?php echo $userName; ?>. Votre inscription a été enregistrée avec succès et votre identité a bien été vérifiée.</p>

            <div class="info-box">
                <strong>Informations enregistrées</strong>
                <div><?php echo $email ?: 'Email enregistré'; ?></div>
            </div>

            <ul class="list">
                <li>Votre dossier est désormais actif et prêt pour la connexion.</li>
                <li>Les données sont sécurisées et utilisées uniquement dans le cadre du vote.</li>
                <li>Vous pouvez maintenant accéder à votre espace personnel.</li>
            </ul>

            <div class="actions">
                <a href="login.php" class="btn primary">Se connecter</a>
                <a href="../index.php" class="btn ghost">Retour à l'accueil</a>
            </div>
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
        })();
    </script>
</body>
</html>
