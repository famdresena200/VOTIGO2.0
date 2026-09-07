<?php
session_start();
require __DIR__ . '/_inc/bootstrap.php';

if (empty($_SESSION['reset_data']) || $_SESSION['reset_data']['step'] !== 'verify') {
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
    $code = preg_replace('/\s+/', '', (string)($_POST['code'] ?? ''));

    if (!preg_match('/^\d{6}$/', $code)) {
        $error = "Veuillez entrer un code valide (6 chiffres).";
    } elseif ($code !== $reset_data['code']) {
        $error = "Code incorrect.";
    } else {
        $_SESSION['reset_data']['step'] = 'reset';
        header('Location: forgot_password_reset.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Vérification du code — VOTIGO</title>
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
        .info{color:var(--blue);font-size:13px;margin-bottom:16px;padding:10px;background:rgba(43,132,255,0.1);border-radius:8px}

        label{font-size:13px;color:var(--muted);margin-bottom:6px;display:block}
        input[type="text"]{width:100%;padding:12px 14px;border-radius:8px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text)}
        
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
        .code-display{font-size:13px;color:var(--muted);margin-top:16px;padding-top:16px;border-top:1px solid var(--input-border)}
        .code-display code{background:var(--input-bg);padding:4px 8px;border-radius:4px;font-weight:600;color:var(--blue)}

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
                <h2>Vérification du Code</h2>
                <div class="muted">Entrez le code de vérification affiché ci-dessous</div>
                <div class="security-banner">
                    <strong>Protection du compte</strong>
                    <span>Ce code confirme votre identité afin de sécuriser la réinitialisation du mot de passe.</span>
                </div>
                <div class="info"><?php echo htmlspecialchars($reset_data['nom'] . ' (' . $reset_data['email'] . ')', ENT_QUOTES, 'UTF-8'); ?></div>

                <?php if (isset($_SESSION['show_code']) && $_SESSION['show_code']): ?>
                    <div style="background:linear-gradient(135deg, #2b84ff 0%, #1760d6 100%);color:#fff;padding:20px;border-radius:12px;margin-bottom:20px;text-align:center">
                        <div style="font-size:13px;margin-bottom:8px;opacity:0.9">Votre code de vérification :</div>
                        <div style="font-size:48px;font-weight:700;letter-spacing:8px;font-family:monospace"><?php echo htmlspecialchars($reset_data['code'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div style="font-size:12px;margin-top:8px;opacity:0.8">Valable 10 minutes</div>
                    </div>
                    <?php unset($_SESSION['show_code']); ?>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="field">
                        <label for="code">Code de vérification (6 chiffres)</label>
                        <input id="code" name="code" type="text" inputmode="numeric" maxlength="6" placeholder="123456" required />
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn primary">Vérifier</button>
                        <a href="forgot_password.php" class="btn ghost">Retour</a>
                        <a href="../index.php" class="btn ghost">Accueil</a>
                    </div>
                </form>
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
        })();
    </script>
</body>
</html>
