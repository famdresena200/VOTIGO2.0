<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../_inc/auth.php';

// Si déjà admin, aller au panneau.
if (auth_is_admin()) {
    header('Location: admin.php');
    exit;
}

$expectedUser = admin_expected_user();
$expectedPass = admin_expected_pass();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = trim((string)($_POST['password'] ?? ''));

    if ($user === '' || $pass === '') {
        $error = "Veuillez saisir l'identifiant et le mot de passe.";
    } else {
        $okUser = hash_equals(strtolower($expectedUser), strtolower($user));
        $okPass = hash_equals($expectedPass, $pass);
        if (!$okUser || !$okPass) {
            $error = "Identifiants incorrects.";
        } else {
            login_admin($expectedUser);
            header('Location: admin.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/jpeg" href="../../images/IMG-20260127-WA0054.jpg" />
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Connexion Admin — VOTIGO</title>
  <link rel="stylesheet" href="../../CSS/votigo.css" />
  <link rel="stylesheet" href="../../CSS/login_admin.css" />
 
  <script defer src="../../JS/votigo.js"></script>
</head>
<body>
  <!-- ISPM corner logo (top-left) -->
  <div class="admin-login-corner-logo">
    <img src="../../images/logo_ispm.png" alt="ISPM" />
  </div>
  
  <!-- Votigo animated watermark background -->
  <div class="admin-login-votigo-watermark"></div>
  
  <div class="admin-login">
    <div class="admin-login-card">
      <div class="admin-login-header">
        <!-- Large Votigo logo in center -->
        <div class="admin-login-main-logo">
          <img src="../../images/IMG-20260127-WA0054.jpg" alt="VOTIGO" />
        </div>
        <h1 class="admin-login-title">Administration</h1>
        <p class="admin-login-subtitle">Plateforme de vote sécurisée</p>
      </div>

      <div class="admin-login-form-card">
        <?php if ($error !== ''): ?>
          <div class="admin-login-error">
            <span style="opacity:0.7">⚠</span>
            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <div class="admin-login-form-group">
            <label for="username">Identifiant</label>
            <input id="username" name="username" type="text" autocomplete="username" required placeholder="Saisir l'identifiant" />
          </div>

          <div class="admin-login-form-group">
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Saisir le mot de passe" />
          </div>

          <div class="admin-login-actions">
            <button type="submit" class="admin-login-btn primary">Connexion</button>
            <a href="../app/a_propos.php" class="admin-login-btn ghost" style="text-decoration:none;display:flex;align-items:center;justify-content:center">Retour</a>
          </div>
        </form>
      </div>

      <div class="admin-login-footer">
        Accès sécurisé pour les administrateurs.<br />
        Pour toute question, <a href="#">consultez la documentation</a>.
      </div>
    </div>
  </div>
</body>
</html>

