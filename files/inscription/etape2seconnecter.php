<?php
session_start();
require __DIR__ . '/_inc/db.php';

// Check if this is reset mode
$reset_mode = isset($_SESSION['reset_mode']) && $_SESSION['reset_mode'];

if (!$reset_mode) {
    header("Location: login.php");
    exit;
}

// Vérifier que le code de reset existe
if (!isset($_SESSION['reset_code'])) {
    $error = "Code de vérification manquant. Veuillez recommencer le processus de réinitialisation.";
}

// Handle resend code (uniquement en GET, pas pendant POST)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['resend']) && $_GET['resend'] == '1') {
    $old_code = $_SESSION['reset_code'] ?? 'none';
    $_SESSION['reset_code'] = rand(100000, 999999);
    $_SESSION['show_code'] = true;
    $resend_message = "Code renvoyé avec succès !";
    error_log("RESEND: Ancien code: $old_code, Nouveau code: {$_SESSION['reset_code']}");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = preg_replace('/\D/', '', $_POST['code'] ?? ''); // supprimer tous les non-chiffres
    $code = substr($code, 0, 6); // max 6 chiffres
    $check_code = isset($_SESSION['reset_code']) ? (string)$_SESSION['reset_code'] : '';

    // Vérifier que le code a le bon format
    if ($code === '') {
        $error = "Veuillez entrer le code.";
    } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
        $error = "Code invalide (6 chiffres requis).";
    } elseif ($code === $check_code) {
        unset($_SESSION['reset_code']);
        unset($_SESSION['show_code']); // Masquer le code après succès
        $_SESSION['success'] = "Code vérifié avec succès !";
        header("Location: etape3seconnecter.php");
        exit;
    } else {
        $error = "Code incorrect. Veuillez réessayer.";
        $_SESSION['show_code'] = true; // Réafficher le code en cas d'erreur
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/jpeg" href="../../images/IMG-20260127-WA0054.jpg" />
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Vérification du Code — VOTIGO</title>
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
        .step{width:24px;height:24px;border-radius:50%;background:var(--input-border);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:var(--muted);transition:all .2s}
        .step.done{background:var(--blue);color:#fff}
        .step.active{background:var(--blue);color:#fff;box-shadow:0 0 0 4px rgba(43,132,255,0.2)}

        .card{background:var(--card-bg);padding:28px;border-radius:12px;max-width:420px;margin:0 auto;box-shadow:var(--shadow)}
        .card h2{margin:0 0 6px;font-size:18px}
        .muted{color:var(--muted);font-size:14px;margin-bottom:18px}

        form .row{display:grid;grid-template-columns:1fr;gap:12px;margin-bottom:12px}
        label{font-size:13px;color:var(--muted);margin-bottom:6px;display:block;font-weight:500}
        input[type="text"],input[type="email"],input[type="password"],input[type="number"]{width:100%;padding:12px 14px;border-radius:8px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text);font-size:14px;transition:all 0.2s ease}
        input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(43,132,255,0.1)}

        .btn{padding:11px 20px;border-radius:8px;border:0;cursor:pointer;font-weight:600;font-size:15px;transition:all 0.2s ease;margin-top:12px}
        .btn.primary{background:linear-gradient(180deg,var(--blue),var(--blue-dark));color:#fff;box-shadow:0 4px 12px rgba(43,132,255,0.25);width:100%}
        .btn.primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(43,132,255,0.35)}
        .btn.primary:disabled{opacity:.6;cursor:not-allowed;transform:none}
        .btn.ghost{background:transparent;border:1.5px solid #cfe0ff;color:var(--blue-dark);text-decoration:none;width: auto;margin: 0}
        .btn.ghost:hover{background:rgba(43,132,255,0.05)}

        .input-msg{font-size:12px;margin-top:4px;min-height:18px;display:block;line-height:1.4}
        .input-msg.error{color:#ff4444;font-weight:500}
        .input-msg.success{color:#18b78a;font-weight:500}
        .input-msg.info{color:#999}

        .error{color:#ff4444;font-size:14px;margin-bottom:16px;padding:12px 14px;background:rgba(255,68,68,0.12);border-radius:8px;border-left:3px solid #ff4444;font-weight:500}
        .success{color:#18b78a;font-size:14px;margin-bottom:16px;padding:12px 14px;background:rgba(24,183,138,0.12);border-radius:8px;border-left:3px solid #18b78a;font-weight:500}

        .actions{display:flex;gap:12px;align-items:center;margin-top:18px;flex-wrap:wrap;justify-content:space-between}
        .theme-toggle{margin-left:auto}
        .theme-toggle button{background:rgba(43,132,255,0.1);border:1px solid rgba(43,132,255,0.3);padding:8px 12px;border-radius:8px;cursor:pointer;font-size:16px;transition:all 0.2s ease}
        .theme-toggle button:hover{background:rgba(43,132,255,0.15);border-color:rgba(43,132,255,0.5)}
        .dark-theme .theme-toggle button{background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.12)}
        .dark-theme .theme-toggle button:hover{background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.2)}

        .footer{margin-top:16px;padding-top:16px;border-top:1px solid var(--input-border);font-size:13px;color:var(--muted);text-align:center}
        .footer a{color:var(--blue);text-decoration:none;font-weight:600}

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
                <div class="step active">2</div>
                <div class="step">3</div>
            </div>
        </div>

        <main class="card">
            <h2>Vérification du Code</h2>
            <div class="muted">Entrez le code de vérification affiché ci-dessous</div>
            

            
            <?php if (isset($_SESSION['show_code']) && $_SESSION['show_code']): ?>
                <div style="background:linear-gradient(135deg, #2b84ff 0%, #1760d6 100%);color:#fff;padding:20px;border-radius:12px;margin-bottom:20px;text-align:center">
                    <div style="font-size:13px;margin-bottom:8px;opacity:0.9">Votre code de vérification :</div>
                    <div style="font-size:48px;font-weight:700;letter-spacing:2px;font-family:monospace">
                        <?php 
                        $display_code = $_SESSION['reset_code'];
                        echo htmlspecialchars($display_code, ENT_QUOTES, 'UTF-8'); 
                        ?>
                    </div>
                    <div style="font-size:12px;margin-top:8px;opacity:0.8">Valable 10 minutes</div>
                </div>
                <?php // Ne pas unset show_code automatiquement, seulement après succès ou resend ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="success"><?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($error) && $error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            
            <!-- DEBUG TOUJOURS VISIBLE: Session code: '<?php echo $_SESSION['reset_code'] ?? 'NOT SET'; ?>', Show code: '<?php echo isset($_SESSION['show_code']) ? 'true' : 'false'; ?>' -->
            <?php if (isset($resend_message)): ?>
                <div class="success"><?php echo htmlspecialchars($resend_message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" id="verify-form" novalidate>
                <div class="row">
                    <label for="code">Code de Confirmation *</label>
                    <input id="code" type="text" name="code" placeholder="Entrez les 6 chiffres" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" required />
                    <span id="code-msg" class="input-msg info">6 chiffres requis</span>
                </div>

                <div class="actions">
                    <button id="verify-btn" type="submit" class="btn primary">Vérifier</button>
                </div>

                <div class="footer">
                    <a href="?resend=1">Renvoyer le code</a>
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

            // Validation
            const codeInput = document.getElementById('code');
            const codeMsg = document.getElementById('code-msg');
            const form = document.getElementById('verify-form');
            const verifyBtn = document.getElementById('verify-btn');
            let isSubmitting = false;

            function validateCode(value) {
                return /^\d{6}$/.test(value);
            }

            function updateCodeStatus() {
                const value = codeInput.value.trim();
                const isValid = value === '' || validateCode(value);
                
                if (value === '') {
                    codeMsg.textContent = '6 chiffres requis';
                    codeMsg.className = 'input-msg info';
                    codeInput.style.borderColor = '';
                    return true;
                }

                if (!isValid) {
                    codeMsg.textContent = 'Le code doit contenir 6 chiffres';
                    codeMsg.className = 'input-msg error';
                    codeInput.style.borderColor = '#ff4444';
                    return false;
                }

                codeMsg.textContent = '✓ Code valide';
                codeMsg.className = 'input-msg success';
                codeInput.style.borderColor = '';
                return true;
            }

            codeInput.addEventListener('input', () => {
                codeInput.value = codeInput.value.replace(/[^\d]/g, '');
                updateCodeStatus();
            });

            codeInput.addEventListener('blur', updateCodeStatus);

            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }

                if (!validateCode(codeInput.value.trim())) {
                    e.preventDefault();
                    codeMsg.textContent = 'Le code doit contenir 6 chiffres';
                    codeMsg.className = 'input-msg error';
                    codeInput.style.borderColor = '#ff4444';
                    return false;
                }

                isSubmitting = true;
                verifyBtn.disabled = true;
                verifyBtn.textContent = '⏳ Vérification...';
            });

            codeInput.addEventListener('focus', () => {
                codeInput.style.borderColor = '';
            });
        })();
    </script>
</body>
</html>