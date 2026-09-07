<?php
session_start();
header('Location: forgot_password.php');
exit;

require __DIR__ . '/_inc/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id_user, nom, prenom FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['reset_user_id'] = $user['id_user'];
            $_SESSION['reset_user_name'] = $user['nom'] . ' ' . $user['prenom'];
            $_SESSION['reset_code'] = rand(100000, 999999);
            $_SESSION['reset_mode'] = true;
            $_SESSION['show_code'] = true;
            header("Location: etape2seconnecter.php");
            exit;
        } else {
            $error = "Cet email n'existe pas dans notre base de données.";
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <link rel="icon" type="image/jpeg" href="../../images/IMG-20260127-WA0054.jpg" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VOTIGO - Mot de passe oublié</title>
    <style>
        :root {
            --primary: #1e73b8;
            --card-width: 420px;
            --muted: #9aa6ae;
            --bg-overlay: rgba(6, 20, 32, 0.45);
            --bg-body: #08161b;
            --bg-card: #fff;
            --text: #0b2630;
            --field-bg: #fff;
            --field-border: #e0e6ea;
            --btn-shadow: rgba(30, 115, 184, 0.18);
        }

        [data-theme="dark"] {
            --bg-body: #f5f5f5;
            --bg-card: #1a1a1a;
            --text: #ffffff;
            --muted: #cccccc;
            --field-bg: #2a2a2a;
            --field-border: #555;
            --btn-shadow: rgba(30, 115, 184, 0.3);
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            color: var(--text);
            background: var(--bg-body);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bg {
            position: fixed;
            inset: 0;
            background-image: url("../../images/cybersecurity.jpg");
            background-size: cover;
            background-position: center center;
            filter: blur(2px) brightness(0.7) contrast(1.05);
            z-index: 0;
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: linear-gradient(180deg, rgba(5, 25, 35, 0.38), rgba(5, 25, 35, 0.55));
            z-index: 1;
        }

        .container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1200px;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }

        .card {
            width: 100%;
            max-width: var(--card-width);
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(2, 17, 27, 0.45);
            padding: 34px 34px 22px;
            text-align: center;
        }

        .logo-container{
            display: flex;
            justify-content: center;
            gap: 50%;
            margin-bottom: 20px;
        }

        .logo {
            height: 68px;
            width: auto;
            max-width: 160px;
            object-fit: contain;
            margin-bottom: 20px;
            
        }

        h1 {
            margin: 6px 0 18px;
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
        }

        .field {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--field-border);
            padding: 12px 14px;
            border-radius: 8px;
            background: var(--field-bg);
        }

        .field:focus-within {
            box-shadow: 0 0 0 3px rgba(30, 115, 184, 0.12);
            border-color: var(--primary)
        }

        .field svg {
            width: 20px;
            height: 20px;
            flex: 0 0 20px;
            color: var(--muted)
        }

        .field input {
            border: 0;
            outline: none;
            font-size: 15px;
            color: var(--text);
            flex: 1;
            background: transparent
        }

        .btn {
            margin-top: 6px;
            border: 0;
            padding: 12px 14px;
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            box-shadow: 0 6px 12px var(--btn-shadow);
        }

        .btn:active {
            transform: translateY(1px)
        }

        .small {
            font-size: 13px;
            color: var(--muted);
            margin-top: 10px;
        }

        .footer {
            margin-top: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--muted);
        }

        .anchor {
            color: #0b6aa6;
            text-decoration: none;
            font-weight: 600
        }

        .error {
            color: #dc3545;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .success {
            color: #28a745;
            margin-bottom: 15px;
            font-size: 13px;
        }

        /* Responsive */
        @media (max-width:520px) {
            .card {
                border-radius: 10px;
                padding: 20px
            }

            :root {
                --card-width: 92vw
            }
        }
    </style>
</head>

<body>
    <div class="bg" aria-hidden="true"></div>
    <div class="overlay" aria-hidden="true"></div>

    <main class="container">
        <section class="card" role="main" aria-labelledby="reset-title">
            <div class="logo-container">
                <img class="logo" src="../../images/logo_ispm.png" alt="VOTIGO logo" style="border-radius: 20px;">
                <img class="logo" src="../../images/IMG-20260127-WA0054.jpg" alt="VOTIGO logo" style="border-radius: 20px;">
            </div>
            <h1 id="reset-title">Réinitialiser votre mot de passe</h1>

            <p style="color:var(--muted); font-size:14px; margin-bottom:20px;">Entrez votre adresse email pour commencer le processus de réinitialisation.</p>

            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

            <form method="post">
                <div class="field" title="Adresse email">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                    </svg>
                    <input type="email" name="email" placeholder="Votre adresse email" required>
                </div>

                <button class="btn" type="submit">Continuer</button>

                <div class="footer">
                    <a class="anchor" href="login.php">Retour à la connexion</a>
                    <button id="theme-toggle" class="btn" style="font-size:12px; padding:6px 10px; margin-right:10px;">🌙</button>
                </div>
            </form>
        </section>
    </main>

    <script>
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
    </script>

</body>

</html>