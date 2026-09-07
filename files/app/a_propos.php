<?php
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

$pdo = db();
$userName = auth_user_name();
$idUser = auth_user_id();
$userInfo = [];

// Get user details if logged in
if ($idUser > 0) {
    $stmt = $pdo->prepare('SELECT nom, email, prenom, nen, cin, telephone, date_naissance, date_inscription FROM users WHERE id_user = :id LIMIT 1');
    $stmt->execute([':id' => $idUser]);
    $userInfo = $stmt->fetch() ?? [];
}

$totalUsers = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
$totalVotes = (int)$pdo->query('SELECT COUNT(*) AS c FROM votes')->fetch()['c'];

votigo_layout_start('À propos — VOTIGO', 'about', [
    'userName' => $userName,
    'verified' => auth_is_electeur() || auth_is_admin(),
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
]);
?>

<div class="h1">À propos de VOTIGO</div>
<div class="muted">Une application de vote électronique conçue pour être rapide, accessible et orientée transparence.</div>

<div style="margin-bottom:24px">
    <a href="dashboard.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <span>←</span>
        <span>Retour au tableau de bord</span>
    </a>
</div>

<?php if ($idUser > 0 && !empty($userInfo)): ?>
<section class="card" style="margin-top:18px;border-color:rgba(26,169,184,.3)">
  <div class="hd" style="background:linear-gradient(135deg, rgba(26,169,184,.15), rgba(99,185,111,.08))">
    <h3><span style="color:var(--primary)">👤</span> Vos informations</h3>
    <div class="spacer"></div>
  </div>
  <div class="bd">
    <div class="cards-2">
      <div class="mini">
        <div class="t" style="font-size:14px;color:var(--muted)">Nom complet</div>
        <div class="s" style="font-weight:700;margin-top:4px;color:var(--text)"><?= htmlspecialchars((string)($userInfo['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="mini">
        <div class="t" style="font-size:14px;color:var(--muted)">Email</div>
        <div class="s" style="font-weight:700;margin-top:4px;color:var(--text)"><?= htmlspecialchars((string)($userInfo['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="mini">
        <div class="t" style="font-size:14px;color:var(--muted)">Numéro CIN</div>
        <div class="s" style="font-weight:700;margin-top:4px;color:var(--text)"><?= htmlspecialchars((string)($userInfo['cin'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="mini">
        <div class="t" style="font-size:14px;color:var(--muted)">Numéro Électeur</div>
        <div class="s" style="font-weight:700;margin-top:4px;color:var(--text)"><?= htmlspecialchars((string)($userInfo['nen'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="mini">
        <div class="t" style="font-size:14px;color:var(--muted)">Téléphone</div>
        <div class="s" style="font-weight:700;margin-top:4px;color:var(--text)"><?= htmlspecialchars((string)($userInfo['telephone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="mini">
        <div class="t" style="font-size:14px;color:var(--muted)">Date de naissance</div>
        <div class="s" style="font-weight:700;margin-top:4px;color:var(--text)"><?= htmlspecialchars(format_date_fr((string)($userInfo['date_naissance'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="mini">
        <div class="t" style="font-size:14px;color:var(--muted)">Inscrit depuis</div>
        <div class="s" style="font-weight:700;margin-top:4px;color:var(--text)"><?= htmlspecialchars(format_date_fr((string)($userInfo['date_inscription'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="mini">
        <div class="t" style="font-size:14px;color:var(--muted)">Prénom(s)</div>
        <div class="s" style="font-weight:700;margin-top:4px;color:var(--text)"><?= htmlspecialchars((string)($userInfo['prenom'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
    <div style="margin-top:16px">
      <a class="btn ghost" href="../app/profile.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;padding:10px 16px">Modifier profil</a>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="grid" style="grid-template-columns:1fr 1fr;margin-top:16px">
  <section class="card">
    <div class="hd"><h3>Notre mission</h3></div>
    <div class="bd">
      <div class="muted" style="line-height:1.6">
        Votigo vise à moderniser le processus de vote en garantissant la confidentialité et l’intégrité des données.
        Notre objectif est de faciliter l’accès au vote pour tous, partout et à tout moment, tout en renforçant la participation citoyenne.
      </div>
      <div style="margin-top:14px;display:flex;gap:10px">
        <a class="btn primary" href="../app/guide.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Guide d'utilisation</a>
        <a class="btn ghost" href="../app/audit.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Transparence & Audit</a>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="hd"><h3>Chiffres clés</h3></div>
    <div class="bd">
      <div class="cards-2">
        <div class="mini">
          <div class="t"><?= (int)$totalUsers ?></div>
          <div class="s">Électeur(s) inscrit(s)</div>
        </div>
        <div class="mini">
          <div class="t"><?= (int)$totalVotes ?></div>
          <div class="s">Vote(s) enregistré(s)</div>
        </div>
      </div>
      <div class="muted" style="margin-top:12px;line-height:1.6">
        Le vote électronique peut améliorer la transparence et accélérer la disponibilité des résultats, tout en réduisant les coûts et le temps d’organisation.
      </div>
    </div>
  </section>
</div>

<section class="card" style="margin-top:16px">
  <div class="hd"><h3>Technologies</h3></div>
  <div class="bd">
    <div class="cards-2">
      <div class="mini">
        <div class="t">Interface</div>
        <div class="s">HTML / CSS / JavaScript — responsive et moderne</div>
      </div>
      <div class="mini">
        <div class="t">Back-end</div>
        <div class="s">PHP — logique, gestion des votes, sécurité</div>
      </div>
      <div class="mini">
        <div class="t">Base de données</div>
        <div class="s">MySQL — élections, candidats, votes et résultats</div>
      </div>
      <div class="mini">
        <div class="t">Sécurité (prochaine étape)</div>
        <div class="s">HTTPS, hashing, authentification et 2FA</div>
      </div>
    </div>
  </div>
</section>

<?php votigo_layout_end(); ?>

