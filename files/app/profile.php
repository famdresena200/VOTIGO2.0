<?php
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_electeur();
$idUser = auth_user_id();
$userName = auth_user_name();

$pdo = db();

// Get user info
$stmt = $pdo->prepare('SELECT id_user, nom, email, nen, cin, telephone, date_naissance FROM USERS WHERE id_user = :id LIMIT 1');
$stmt->execute([':id' => $idUser]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: dashboard.php');
    exit;
}

// Get voting stats
$stmt = $pdo->prepare('
    SELECT COUNT(DISTINCT id_election) as total_votes
    FROM VOTES 
    WHERE token_anonyme IN (
        SELECT token_anonyme FROM VOTES 
        WHERE id_election IN (
            SELECT id_election FROM ELECTIONS
        )
    )
');

votigo_layout_start('Profil utilisateur — VOTIGO', 'profile', [
    'userName' => $userName,
    'verified' => $idUser > 0,
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
]);
?>

<div class="h1">Votre profil</div>
<div class="muted">Consultez et gérez vos informations personnelles.</div>

<div style="margin-bottom:24px">
    <a href="dashboard.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <span>←</span>
        <span>Retour au tableau de bord</span>
    </a>
</div>

<!-- Profile Info Card -->
<section class="card" style="margin-top:18px">
  <div class="hd">
    <h3>Informations personnelles</h3>
    <div class="spacer"></div>
    <span class="badge">Vérifié</span>
  </div>
  <div class="bd">
    <div class="profile-grid">
      <!-- Full Name -->
      <div class="profile-item">
        <div class="muted profile-label">Nom complet</div>
        <div class="profile-value"><?= htmlspecialchars((string)$user['nom'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      
      <!-- Email -->
      <div class="profile-item">
        <div class="muted profile-label">Adresse email</div>
        <div class="profile-value profile-break"><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>

      <!-- National Electoral Number -->
      <div class="profile-item">
        <div class="muted profile-label">Numéro d'électeur (NEN)</div>
        <div class="profile-value profile-mono"><?= htmlspecialchars((string)$user['nen'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>

      <!-- National ID (CIN) -->
      <div class="profile-item">
        <div class="muted profile-label">CIN / Pièce d'identité</div>
        <div class="profile-value profile-mono"><?= htmlspecialchars((string)$user['cin'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>

      <!-- Phone -->
      <div class="profile-item">
        <div class="muted profile-label">Numéro de téléphone</div>
        <div class="profile-value"><?= htmlspecialchars((string)$user['telephone'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>

      <!-- Date of Birth -->
      <div class="profile-item">
        <div class="muted profile-label">Date de naissance</div>
        <div class="profile-value"><?= htmlspecialchars(format_date_fr((string)$user['date_naissance']), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>

    <!-- Privacy Notice -->
    <div style="margin-top:18px;padding:12px;background:rgba(26,169,184,.08);border:1px solid rgba(26,169,184,.2);border-radius:12px">
      <div style="font-size:12px;color:var(--muted);line-height:1.4">
        <strong style="color:var(--text)">🔐 Sécurité des données</strong><br/>
        Vos informations personnelles sont traitées de manière confidentielle et sécurisée. Elles ne sont jamais associées à vos votes, qui restent complètement anonymes.
      </div>
    </div>
  </div>
</section>

<!-- Account Statistics -->
<div class="profile-stats">
  <section class="card profile-stat-card">
    <div class="bd profile-stat-body">
      <div class="profile-stat-icon profile-primary">
        👤
      </div>
      <div class="muted profile-stat-label">Compte</div>
      <div class="profile-stat-text">Électeur actif</div>
    </div>
  </section>

  <section class="card profile-stat-card">
    <div class="bd profile-stat-body">
      <div class="profile-stat-icon profile-success">
        ✓
      </div>
      <div class="muted profile-stat-label">Vérification</div>
      <div class="profile-stat-text">Identité vérifiée</div>
    </div>
  </section>

  <section class="card profile-stat-card">
    <div class="bd profile-stat-body">
      <div class="profile-stat-icon profile-info">
        🔐
      </div>
      <div class="muted profile-stat-label">Confidentialité</div>
      <div class="profile-stat-text">Votes anonymes</div>
    </div>
  </section>
</div>

<!-- Security Section -->
<section class="card" style="margin-top:16px">
  <div class="hd">
    <h3>Sécurité du compte</h3>
  </div>
  <div class="bd">
    <div class="profile-security-list">
      <!-- Password Change -->
      <div class="profile-security-row">
        <div>
          <div style="font-weight:600;margin-bottom:3px">Mot de passe</div>
          <div class="muted" style="font-size:12px">Modifiez votre mot de passe régulièrement</div>
        </div>
        <a href="change_password.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;font-size:12px">Modifier</a>
      </div>

      <!-- 2FA Status -->
      <div class="profile-security-row">
        <div>
          <div style="font-weight:600;margin-bottom:3px">Authentification 2FA</div>
          <div class="muted" style="font-size:12px">Authentification à deux facteurs activée</div>
        </div>
        <span class="badge" style="background:rgba(99,185,111,.15);border-color:rgba(99,185,111,.3);color:#63b96f">Actif</span>
      </div>

      <!-- Session Management -->
      <div class="profile-security-row">
        <div>
          <div style="font-weight:600;margin-bottom:3px">Déconnexion</div>
          <div class="muted" style="font-size:12px">Terminez votre session en toute sécurité</div>
        </div>
        <a href="../inscription/logout.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;font-size:12px">Se déconnecter</a>
      </div>
    </div>
  </div>
</section>

<!-- Support -->
<section class="card" style="margin-top:16px">
  <div class="hd">
    <h3>Aide & Support</h3>
  </div>
  <div class="bd">
    <div class="list" style="display:flex;flex-direction:column;gap:8px">
      <a href="../app/guide.php" style="text-decoration:none" class="btn ghost" style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:13px">📖 Guide d'utilisation VOTIGO</span>
        <span class="muted">→</span>
      </a>
      <a href="../app/a_propos.php" style="text-decoration:none" class="btn ghost" style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:13px">ℹ️ À propos de VOTIGO</span>
        <span class="muted">→</span>
      </a>
      <a href="../app/audit.php" style="text-decoration:none" class="btn ghost" style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:13px">🔍 Transparence & Audit</span>
        <span class="muted">→</span>
      </a>
    </div>
  </div>
</section>

<!-- Back Button -->
<div style="margin-top:20px;text-align:center">
  <a class="btn ghost" href="../app/dashboard.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px">
    <span>←</span> Retour au tableau de bord
  </a>
</div>

<style>
  .profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-top: 12px;
  }
  .profile-item {
    padding: 14px 16px;
    border-radius: 16px;
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.06);
    min-height: 94px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .profile-label {
    font-size: 12px;
    margin-bottom: 8px;
  }
  .profile-value {
    font-weight: 700;
    font-size: 14px;
    line-height: 1.5;
  }
  .profile-break {
    word-break: break-all;
  }
  .profile-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    letter-spacing: .02em;
  }
  .profile-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-top: 16px;
  }
  .profile-stat-card {
    min-height: 164px;
  }
  .profile-stat-body {
    text-align: center;
    padding: 20px;
    display: grid;
    place-items: center;
  }
  .profile-stat-icon {
    font-size: 32px;
    font-weight: 900;
    margin-bottom: 6px;
    line-height: 1;
  }
  .profile-primary { color: var(--primary); }
  .profile-success { color: #63b96f; }
  .profile-info { color: #1aa9b8; }
  .profile-stat-label {
    font-size: 12px;
    margin-bottom: 4px;
  }
  .profile-stat-text {
    font-weight: 700;
  }
  .profile-security-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .profile-security-row {
    padding: 12px;
    background: rgba(255,255,255,.02);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }
  @media (max-width: 640px) {
    .profile-grid { grid-template-columns: 1fr; }
    .profile-security-row { flex-direction: column; align-items: flex-start; }
  }
</style>

<?php votigo_layout_end(); ?>
