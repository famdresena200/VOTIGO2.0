<?php
session_start();
require __DIR__ . '/inscription/_inc/bootstrap.php';
require __DIR__ . '/inscription/_inc/db.php';
require __DIR__ . '/_inc/auth.php';

$role = auth_role();
if ($role === 'electeur') {
    header('Location: app/dashboard.php');
    exit;
}
if ($role === 'admin') {
    header('Location: admin/admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>VOTIGO — Accueil</title>
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
        .container{max-width:1240px;margin:0 auto;padding:22px 22px 40px;min-height:100vh;display:flex;flex-direction:column;justify-content:center}

        .topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:44px;padding:14px 18px;background:var(--surface);backdrop-filter:blur(12px);border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow)}
        .logo-group{display:flex;gap:12px;align-items:center}
        .logo-group img{height:72px;width:auto;object-fit:contain;border-radius:18px;box-shadow:0 16px 30px rgba(12,25,39,0.12)}

        .theme-toggle{margin-left:auto}
        .theme-toggle button{background:rgba(255,255,255,.45);border:1px solid rgba(15,29,50,.08);padding:10px 12px;border-radius:999px;cursor:pointer;font-size:16px;box-shadow:0 8px 18px rgba(15,29,50,.08)}
        .dark-theme .theme-toggle button{border-color:rgba(255,255,255,.08);background:rgba(17,35,52,.6)}

        .hero{display:grid;grid-template-columns:1.15fr 0.85fr;gap:36px;align-items:center;background:var(--surface);backdrop-filter:blur(14px);padding:28px;border:1px solid var(--border);border-radius:28px;box-shadow:var(--shadow)}
        .content{padding:10px 6px}
        .content h1{font-size:clamp(2.4rem,4vw,4rem);line-height:1.06;margin:0 0 20px;font-weight:800;letter-spacing:-0.06em}
        .lead{font-size:18px;color:var(--muted);margin:0 0 28px;line-height:1.7;max-width:620px}
        .badge{display:inline-flex;align-items:center;gap:10px;padding:10px 14px;border-radius:999px;border:1px solid rgba(29,110,242,.14);background:linear-gradient(135deg, rgba(29,110,242,.08), rgba(18,176,163,.04));color:var(--text);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;margin-bottom:18px}

        .actions{display:flex;gap:12px;flex-wrap:wrap}
        .btn{padding:14px 24px;border-radius:999px;border:0;cursor:pointer;font-weight:700;font-size:15px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;transition:all .25s ease}
        .btn.primary{background:linear-gradient(135deg, #1d6ef2, #12b0a3);color:#fff;box-shadow:0 18px 42px rgba(29,110,242,.2)}
        .btn.primary:hover{transform:translateY(-2px);box-shadow:0 22px 52px rgba(29,110,242,.28)}
        .btn.ghost{background:rgba(255,255,255,.18);border:1px solid rgba(29,110,242,.18);color:var(--text)}
        .btn.ghost:hover{transform:translateY(-2px)}

        .visual{display:flex;align-items:center;justify-content:center;padding:8px}
        .visual img{width:100%;height:100%;max-height:620px;object-fit:cover;border-radius:24px;box-shadow:var(--shadow);border:1px solid rgba(255,255,255,.18)}

        @media (max-width:960px){
            .hero{grid-template-columns:1fr;}
            .visual{order:1}
            .content h1{font-size:clamp(2.2rem,8vw,3.2rem)}
            .lead{font-size:16px}
        }
        @media (max-width:620px){
            .container{padding:12px 14px 28px}
            .topbar{padding:12px 14px;border-radius:18px}
            .logo-group img{height:56px}
            .hero{padding:18px;border-radius:22px}
            .badge{letter-spacing:.08em}
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="topbar">
            <div class="logo-group">
                <img src="../images/logo_ispm.png" alt="ISPM logo" />
                <img src="../images/IMG-20260127-WA0054.jpg" alt="VOTIGO logo" />
            </div>
            <div class="theme-toggle" title="Changer le thème">
                <button id="themeBtn" aria-pressed="false">🌙</button>
            </div>
        </header>

        <main class="hero">
            <section class="content">
                <h1>LE VOTE ÉLECTRONIQUE,<br>RÉINVENTÉ. SÉCURISÉ.<br>ANONYME.</h1>
                <p class="lead">Modernisez vos élections. Confidentialité et intégrité garanties, de n'importe quel appareil.</p>
                <div class="actions">
                    <a href="inscription/etape1.php" class="btn primary">S'inscrire</a>
                    <a href="inscription/login.php" class="btn ghost">Se connecter</a>
                </div>
            </section>

            <aside class="visual" aria-hidden="true">
                <img src="../images/cybersecurity.jpg" alt="Illustration de sécurité et urne" />
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
    </script>
</body>
</html>
