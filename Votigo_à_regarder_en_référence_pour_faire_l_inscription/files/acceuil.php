<?php
session_start();
include '../config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>VOTIGO — Accueil</title>
    <link rel="stylesheet" href="../CSS/aceil.css">
     
</head>
<body>
    <div class="wrap">
        <main class="hero" role="main">
            <section class="content">
                <div class="brand">
                    <div class="logo">
                        <div class="brand">
               <img src="../images/logo_ispm.png" alt="logo logo_ispm" style="height: 90px; width:auto; object-fit:contain; border-radius: 20px;"/>
                <div class="theme-toggle" title="Changer le thème">
                    <button id="themeBtn" aria-pressed="false">🌙</button>
                </div>
            </div>
                    </div>
                </div>

                <h1>LE VOTE ÉLECTRONIQUE,
                    <br>RÉINVENTÉ. SÉCURISÉ.
                    <br>ANONYME.
                </h1>

                <p class="lead">Modernisez vos élections. Confidentialité et intégrité garanties, de n'importe quel appareil.</p>

                <div class="actions">
                    <a class="btn primary" href="inscription/etape1.php">S'inscrire</a>
                    <a class="btn ghost" href="se connecter/login.php">Se connecter</a>
                </div>
            </section>

            <aside class="visual" aria-hidden="true">
                <!-- Replace the path if a different image from the workspace should be used -->
                <img src="../images/votigo.jpg" alt="Illustration de sécurité et urne" />
            
            </aside>
        </main>
    </div>

    <script>
         // Theme toggle: remember in localStorage and apply on load
        (function(){
            var root = document.documentElement;
            var btn = document.getElementById('themeBtn');
            function applyTheme(isDark){
                if(isDark){ root.classList.add('dark-theme'); btn.textContent='☀️'; btn.setAttribute('aria-pressed','true') }
                else{ root.classList.remove('dark-theme'); btn.textContent='🌙'; btn.setAttribute('aria-pressed','false') }
            }
            try{
                var saved = localStorage.getItem('votigo_theme');
                var isDark = saved === 'dark' || (saved === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                applyTheme(isDark);
            }catch(e){console.warn(e)}

            btn.addEventListener('click', function(){
                var nowDark = !document.documentElement.classList.contains('dark-theme');
                applyTheme(nowDark);
                try{ localStorage.setItem('votigo_theme', nowDark ? 'dark':'light') }catch(e){}
            });
        })();

    </script>
</body>
</html>
