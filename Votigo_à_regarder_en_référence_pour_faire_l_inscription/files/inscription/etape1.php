<?php
session_start();
include '../../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $nen = $_POST['nni'] ?? ''; // assuming first nni is nen
    $cin = $_POST['cin'] ?? ''; // need to adjust, in form it's nni for cin?
    $telephone = $_POST['numtel'] ?? '';
    $date_naissance = $_POST['dob'] ?? '';
    $consent = isset($_POST['consent']) ? true : false;

    // Validation complète - tous les champs requis
    if (empty($nom) || empty($prenom) || empty($email) || empty($nen) || empty($cin)) {
        $error = "Veuillez remplir tous les champs requis.";
    } elseif (!preg_match('/^\d{10}$/', $nen)) {
        $error = "Le numéro d'électeur doit comporter exactement 10 chiffres.";
    } elseif (!preg_match('/^\d{12}$/', $cin)) {
        $error = "Le numéro CIN doit comporter exactement 12 chiffres.";
    } elseif (!empty($telephone) && strlen(preg_replace('/\D/', '', $telephone)) !== 10) {
        $error = "Le numéro de téléphone doit comporter exactement 10 chiffres.";
    } else {
        try {
            $pdo = getDBConnection();
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Cet email est déjà utilisé.";
            } else {
                // Check if nen already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE nen = ?");
                $stmt->execute([$nen]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Ce numéro d'électeur est déjà utilisé.";
                } else {
                    // Check if cin already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE cin = ?");
                    $stmt->execute([$cin]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Ce numéro CIN est déjà utilisé.";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO USERS (nom, prenom, email, nen, cin, telephone, date_naissance) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nom, $prenom, $email, $nen, $cin, $telephone, $date_naissance]);
                        $user_id = $pdo->lastInsertId();
                        $_SESSION['user_id'] = $user_id;
                        header("Location: etape2.php");
                        exit;
                    }
                }
            }
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
    <title>Inscription Électeur — VOTIGO</title>

    <style>
        /* Theme variables (light default) */
        :root{
            --blue:#2b84ff; --blue-dark:#1760d6; --muted:#6b7280;
            --bg:#f4f8fb; --text:#0b2540; --card-bg: linear-gradient(180deg,#ffffffcc,#ffffffcc);
            --input-bg:#fbfdff; --input-border:#d8e1ef; --shadow: 0 8px 30px rgba(11,37,64,0.08);
        }

        /* Dark theme overrides */
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
        /* progress */
        .progressbar{display:flex;gap:14px;align-items:center;margin-left:auto}
        .step{display:flex;flex-direction:column;align-items:center;font-size:13px;color:var(--muted)}
        .step .dot{width:30px;height:30px;border-radius:999px;background:#fff;border:2px solid #dbe7ff;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--blue)}
        .step.active .dot{background:linear-gradient(180deg,var(--blue) 0%,var(--blue-dark) 100%);color:white;border-color:transparent}

        .card{display:grid;grid-template-columns:1fr 420px;gap:24px;background:var(--card-bg);padding:28px;border-radius:12px;margin-top:18px;align-items:start}
        .form h2{margin:6px 0 6px 0}
        .muted{color:var(--muted);font-size:14px;margin-bottom:18px}

        form .row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
        label{font-size:13px;color:var(--muted);margin-bottom:6px;display:block}
        input[type="text"],input[type="email"],input[type="date"],input[type="password"],input[type="tel"]{width:100%;padding:12px 14px;border-radius:8px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text)}
        .full{grid-column:1 / -1}

        .actions{display:flex;gap:12px;align-items:center;margin-top:10px}
        .btn{padding:10px 18px;border-radius:999px;border:0;cursor:pointer;font-weight:600}
        .btn.primary{background:linear-gradient(180deg,var(--blue),var(--blue-dark));color:#fff}
        .btn.ghost{background:#fff;border:1px solid #cfe0ff;color:var(--blue-dark)}

        .visual{display:flex;align-items:center;justify-content:center}
        .visual img{width:100%;height:auto;border-radius:12px;filter:drop-shadow(var(--shadow))}

        .small{font-size:13px;color:var(--muted)}
        .consent{display:flex;gap:8px;align-items:flex-start;margin-top:12px}

        /* Theme toggle styles */
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
                <div class="step active"><div class="dot">1</div><div>Info</div></div>
                <div class="step"><div class="dot">2</div><div>Validation</div></div>
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
                <h2>Inscription Électeur - Créez votre compte VOTIGO</h2>
                <div class="muted">Étape 1 sur 3 : Renseignez vos informations d'identité</div>
                <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
                <form id="signup" method="post" novalidate>
                    <div class="row">
                        <div>
                            <label for="nom">Nom de famille:</label>
                            <input id="nom" name="nom" type="text" required/>
                        </div>
                        <div>
                            <label for="nen">Numéro d'Électeur National (10 chiffres):</label>
                            <input id="nen" name="nni" type="text" required maxlength="10" pattern="[0-9]{10}" placeholder="0123456789" title="Veuillez entrer exactement 10 chiffres"/>
                        </div>
                        
                        
                    </div>

                    <div class="row">
                        <div>
                            <label for="prenom">Prénom(s):</label>
                            <input id="prenom" name="prenom" type="text" required />
                        </div>
                        <div>
                            <label for="email">Adresse Email</label>
                            <input id="email" name="email" type="email" required />
                        </div>
                        <div>
                            <label for="cin">Numéro CIN (12 chiffres)</label>
                            <input id="cin" name="cin" type="text" required maxlength="12" pattern="[0-9]{12}" placeholder="012345678901" title="Veuillez entrer exactement 12 chiffres"/>
                        </div>
                         <div>
                            <label for="telephone">Numéro de téléphone (10 chiffres)</label>
                            <input id="telephone" name="numtel" type="tel" maxlength="10" pattern="[0-9]{13}" placeholder="0345678912" title="Veuillez entrer exactement 13 chiffres"/>
                        </div>

                    </div>

                    <div class="row">
                        <div>
                            <label for="dob">Date de Naissance</label>
                            <input id="dob" name="dob" type="date" />
                        </div>
                       
                    </div>

                    <div class="consent">
                        <input id="consent" type="checkbox" />
                        <label for="consent" class="small">Je consens au traitement sécurisé de mes données selon la <a href="#">politique de confidentialité</a>.</label>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn primary">S'inscrire</button>
                        <a href="../se connecter/login.php"><button type="button" class="btn ghost" >Se connecter</button></a>
                    </div>
                </form>
            </div>

            <aside class="visual" aria-hidden="true">
                <img src="../../images/cybersecurity.jpg" alt="Illustration de sécurité et urne" />
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