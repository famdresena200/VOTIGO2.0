<?php
session_start();
include '../../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $numero_electeur = $_POST['electeur'] ?? '';
    $code_secret = $_POST['secret'] ?? '';

    // Vérifier que les champs sont remplis
    if (empty($numero_electeur) || empty($code_secret)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id_user, nom, prenom, password_hash FROM USERS WHERE nen = ?");
            $stmt->execute([$numero_electeur]);
            $user = $stmt->fetch();

            if ($user && password_verify($code_secret, $user['password_hash'])) {
                // Connexion réussie - définir les variables de session
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['user_name'] = $user['nom'] . ' ' . $user['prenom'];
                $_SESSION['nen'] = $numero_electeur;

                // Rediriger directement vers la page principale
                header("Location: ../app/dashboard.php");
                exit;
            } else {
                $error = "Numéro d'électeur ou code secret incorrect.";
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
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary:#1e73b8; /* blue */
            --card-width:420px;
            --muted:#9aa6ae;
            --bg-overlay: rgba(6,20,32,0.45);
            --bg-body: #08161b;
            --bg-card: #fff;
            --text: #0b2630;
            --field-bg: #fff;
            --field-border: #e0e6ea;
            --btn-shadow: rgba(30,115,184,0.18);
        }

        [data-theme="dark"] {
            --bg-body: #f5f5f5;
            --bg-card: #1a1a1a;
            --text: #ffffff;
            --muted: #cccccc;
            --field-bg: #2a2a2a;
            --field-border: #555;
            --btn-shadow: rgba(30,115,184,0.3);
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

        /* Background image + subtle gradient overlay */
        .bg{
            position:fixed;
            inset:0;
            background-image: url("../../images/cybersecurity.jpg");
            background-size:cover;
            background-position:center center;
            filter:blur(2px) brightness(0.7) contrast(1.05);
            z-index:0;
        }
        .overlay{
            position:fixed; inset:0; background:linear-gradient(180deg, rgba(5,25,35,0.38), rgba(5,25,35,0.55)); z-index:1;
        }

        .container{
            position:relative; z-index:2; width:100%; max-width:1200px; padding:40px 20px; display:flex; justify-content:center;
        }

        .card{
            width:100%; max-width:var(--card-width);
            background:var(--bg-card); border-radius:12px; box-shadow:0 20px 40px rgba(2,17,27,0.45);
            padding:34px 34px 22px; text-align:center;
        }

        .logo{
            height:68px;
            width:auto;
            max-width:160px;
            object-fit:contain;
        }

        .from{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:6px;
        }

        h1{
            margin:6px 0 18px; font-size:20px; font-weight:700; color:var(--text);
        }

        form{display:flex; flex-direction:column; gap:12px; align-items:stretch;}

        .field{
            display:flex; align-items:center; gap:10px; border:1px solid var(--field-border); padding:12px 14px; border-radius:8px; background:var(--field-bg);
        }
        .field:focus-within{box-shadow:0 0 0 3px rgba(30,115,184,0.12); border-color:var(--primary)}

        .field svg{width:20px; height:20px; flex:0 0 20px; color:var(--muted)}
        .field input{border:0; outline:none; font-size:15px; color:var(--text); flex:1; background:transparent}

        .btn{
            margin-top:6px; border:0; padding:12px 14px; background:var(--primary); color:#fff; font-weight:600; border-radius:8px; cursor:pointer; font-size:15px;
            box-shadow:0 6px 12px var(--btn-shadow);
        }
        .btn:active{transform:translateY(1px)}

        .small{
            font-size:13px; color:var(--muted);
            margin-top:10px;
        }

        .twofa{margin-top:6px}

        .footer{
            margin-top:14px; display:flex; justify-content:space-between; align-items:center; font-size:13px; color:var(--muted);
        }

        .anchor{color:#0b6aa6; text-decoration:none; font-weight:600}

        /* Responsive */
        @media (max-width:520px){
            .card{border-radius:10px; padding:20px}
            :root{--card-width:92vw}
        }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true"></div>
    <div class="overlay" aria-hidden="true"></div>

    <main class="container">
        <section class="card" role="main" aria-labelledby="signin-title">
            <div class="from">
                <img class="logo" src="../../images/logo_ispm.png" alt="ISPM logo" style="border-radius: 20px;">
                <img class="logo" src="../../images/votigo.jpg" alt="VOTIGO logo" style="border-radius: 20px;">
             </div>
            

            <h1 id="signin-title">Connexion Électeur Sécurisée</h1>
            <?php if (isset($_SESSION['success'])) { echo "<p style='color:green;'>".$_SESSION['success']."</p>"; unset($_SESSION['success']); } ?>
            <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
            <form method="post">
                <div class="field" title="Numéro d'Électeur">
                    <!-- user icon -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <input type="text" name="electeur" placeholder="Numéro d'Électeur" required>
                </div>

                <div class="field" title="Code secret">
                    <!-- lock icon -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <input type="password" name="secret" placeholder="Code Secret unique" required>
                </div>

                <button class="btn" type="submit">Se Connecter</button>

                <div class="small">Connexion sécurisée avec chiffrement AES-256</div>

                <div class="footer">
                    <a class="anchor" href="mote_de_passe_oublie.php">Mot de passe oublié ?</a>
                    <button id="theme-toggle" class="btn" style="font-size:12px; padding:6px 10px; margin-right:10px;">Mode Clair</button>
                    <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7f86" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17a2 2 0 0 0 2-2v-3"></path><rect x="3" y="11" width="18" height="8" rx="2"></rect></svg>
                        Connexion Chiffrée (AES-256)
                    </div>
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