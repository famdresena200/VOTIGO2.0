<?php
session_start();
include '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: etape1.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = $_POST['code'] ?? '';
    if ($code == $_SESSION['validation_code']) {
        header("Location: etape3.php");
        exit;
    } else {
        $error = "Code incorrect.";
    }
} else {
    // Generate random code
    $_SESSION['validation_code'] = rand(100000, 999999);
    // In real app, send email here
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Validation d'identité — VOTIGO</title>
    <style>
        /* Same styles as etape1 */
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
        .step.active .dot{background:linear-gradient(180deg,var(--blue) 0%,var(--blue-dark) 100%);color:white;border-color:transparent}
        .step.done .dot{background:#18b78a;color:white;border-color:transparent}

        .card{display:grid;grid-template-columns:1fr 420px;gap:24px;background:var(--card-bg);padding:28px;border-radius:12px;margin-top:18px;align-items:start}
        .form h2{margin:6px 0 6px 0}
        .muted{color:var(--muted);font-size:14px;margin-bottom:18px}

        .code-row{display:flex;flex-direction:column;gap:10px}
        .label{font-size:14px;color:var(--muted)}
        .code-input{display:flex;align-items:center;gap:12px}
        .code-box{flex:1;padding:14px 16px;border-radius:10px;border:1px solid var(--input-border);background:var(--input-bg);font-size:20px;letter-spacing:4px;color:var(--text)}
        .timer{color:var(--muted);font-size:14px}
        .resend{color:var(--blue-dark);cursor:pointer;text-decoration:underline;font-size:13px;margin-left:8px}

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
                <div class="step active"><div class="dot">2</div><div>Validation</div></div>
                <div class="step"><div class="dot">3</div><div>2FA</div></div>
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
                <h2>Validation d'identité</h2>
                <div class="muted">Étape 2 sur 3 : Entrez le code de validation envoyé à votre email</div>
                <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
                <form method="post">
                    <div class="code-row">
                        <label class="label">Code de validation</label>
                        <div class="code-input">
                            <input type="text" name="code" class="code-box" maxlength="6" required />
                        </div>
                        <div class="timer">Code valide pour 10 minutes</div>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn primary">Valider</button>
                        <a href="etape1.php"><button type="button" class="btn ghost">Retour</button></a>
                    </div>
                </form>
                <p>Code de test: <?php echo $_SESSION['validation_code']; ?></p> <!-- Remove in production -->
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
    </script>
</body>
</html>