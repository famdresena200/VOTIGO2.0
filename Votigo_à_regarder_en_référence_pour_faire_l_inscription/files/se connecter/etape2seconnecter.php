<?php
session_start();
include '../../config.php';

// Check if this is reset mode or login mode
$reset_mode = isset($_SESSION['reset_mode']) && $_SESSION['reset_mode'];
$login_mode = isset($_SESSION['user_id']) && isset($_SESSION['login_code']);

if (!$reset_mode && !$login_mode) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = $_POST['code'] ?? '';
    
    if ($reset_mode) {
        $check_code = $_SESSION['reset_code'] ?? '';
    } else {
        $check_code = $_SESSION['login_code'] ?? '';
    }
    
    if ($code == $check_code) {
        if ($reset_mode) {
            unset($_SESSION['reset_code']);
        } else {
            unset($_SESSION['login_code']);
        }
        header("Location: etape3seconnecter.php");
        exit;
    } else {
        $error = "Code incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VOTIGO - Vérifier Email</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary:#00d4ff;
            --primary-dark:#0099cc;
            --card-width:420px;
            --muted:#9aa6ae;
            --bg-body: #08161b;
            --bg-card: #fff;
            --text: #0b2630;
            --field-bg: #fff;
            --field-border: #e0e6ea;
            --btn-shadow: rgba(0,212,255,0.3);
        }

        [data-theme="dark"] {
            --bg-body: #f5f5f5;
            --bg-card: #1a1a1a;
            --text: #ffffff;
            --muted: #cccccc;
            --field-bg: #2a2a2a;
            --field-border: #555;
            --btn-shadow: rgba(0,212,255,0.4);
        }

        *{box-sizing:border-box}
        html,body{height:100%;}
        
        body{
            margin:0;
            font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            color:var(--text);
            background:var(--bg-body);
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .bg{
            position:fixed;
            inset:0;
            background:url('../../images/fond1.jpg');
            z-index:0;
        }

        .container{
            position:relative; z-index:2; width:100%; max-width:1200px; padding:40px 20px; display:flex; justify-content:center;
        }

        .card{
            width:100%; max-width:var(--card-width);
            background:var(--bg-card); border-radius:12px; box-shadow:0 20px 40px rgba(0,212,255,0.1);
            padding:34px 34px 22px; text-align:center;
            position:relative;
        }

        h1{
            margin:6px 0 18px; font-size:20px; font-weight:700; color:var(--text);
        }

        .step-indicator{
            display:flex;
            justify-content:center;
            gap:20px;
            margin-bottom:30px;
            font-size:12px;
        }

        .step{
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:8px;
        }

        .step-circle{
            width:40px;
            height:40px;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:600;
            color:#000;
            transition:all 0.3s ease;
        }

        .step.active .step-circle{
            background:var(--primary);
            box-shadow:0 0 15px rgba(0,212,255,0.5);
        }

        .step.completed .step-circle{
            background:#28a745;
            color:#fff;
        }

        .code-input{
            display:flex;
            justify-content:center;
            gap:10px;
            margin:20px 0;
        }

        .code-box{
            width:240px;
            height:48px;
            border:2px solid var(--field-border);
            border-radius:8px;
            background:var(--field-bg);
            color:var(--text);
            font-size:18px;
            padding:0 12px;
            text-align:center;
            transition:border-color 0.3s;
        }

        .brand-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:10px;
            margin:0 0 20px;
        }

        .brand-logo{
            max-height:56px;
            max-width:45%;
            object-fit:contain;
        }

        .code-box:focus{
            border-color:var(--primary);
            outline:none;
        }

        .btn{
            margin-top:20px;
            border:0;
            padding:12px 24px;
            background:var(--primary);
            color:#fff;
            font-weight:600;
            border-radius:8px;
            cursor:pointer;
            font-size:15px;
            box-shadow:0 6px 12px var(--btn-shadow);
        }

        .btn:active{
            transform:translateY(1px);
        }

        .resend{
            margin-top:15px;
            color:var(--muted);
            font-size:14px;
        }

        .resend a{
            color:var(--primary);
            text-decoration:none;
        }

        .theme-toggle{
            position:absolute;
            top:20px;
            right:20px;
            background:transparent;
            border:1px solid rgba(0,0,0,0.1);
            padding:8px;
            border-radius:50%;
            cursor:pointer;
            font-size:16px;
        }

        [data-theme="light"] .theme-toggle{
            border-color:rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true"></div>

    <main class="container">
        <section class="card" role="main" aria-labelledby="verify-title">
            <button class="theme-toggle" id="themeBtn" aria-pressed="false">🌙</button>

            <div class="step-indicator" role="navigation" aria-label="Étapes de connexion">
                <div class="step completed">
                    <div class="step-circle">1</div>
                    <span>Connexion</span>
                </div>
                <div class="step active">
                    <div class="step-circle">2</div>
                    <span>Vérification</span>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <span>Accès</span>
                </div>
            </div>

            <div class="brand-header" aria-hidden="true">
                <img src="../../images/logo_ispm.png" alt="ISPM" class="brand-logo">
                <img src="../../images/votigo.jpg" alt="VOTIGO" class="brand-logo">
            </div>

            <h1 id="verify-title">Vérifiez votre identité</h1>

            <p>Un code de vérification a été envoyé à votre adresse email. Entrez-le ci-dessous pour continuer.</p>
            <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

            <form method="post">
                <div class="code-input">
                    <input type="text" name="code" class="code-box" maxlength="6" required />
                </div>
                <button class="btn" type="submit">Vérifier</button>
            </form>

            <div class="resend">
                Vous n'avez pas reçu le code ? <a href="#">Renvoyer</a>
            </div>
            <div style="margin-top:15px;">
                <a href="login.php">Retour à la connexion</a>
            </div>
            <p>Code de test: <?php echo $reset_mode ? $_SESSION['reset_code'] : $_SESSION['login_code']; ?></p> <!-- Remove in production -->
        </section>
    </main>

    <script>
        const themeToggle = document.getElementById('themeBtn');
        const html = document.documentElement;

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        themeToggle.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            themeToggle.textContent = newTheme === 'dark' ? '☀️' : '🌙';
        });
    </script>
</body>
</html>