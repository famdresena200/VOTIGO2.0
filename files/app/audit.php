<?php
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

$pdo = db();
$userName = auth_user_name();
$totalVotes = (int)$pdo->query('SELECT COUNT(*) AS c FROM votes')->fetch()['c'];
$totalElections = (int)$pdo->query('SELECT COUNT(*) AS c FROM elections')->fetch()['c'];

votigo_layout_start('Transparence & Audit — VOTIGO', 'audit', [
    'userName' => $userName,
    'verified' => auth_is_electeur() || auth_is_admin(),
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
]);
?>

<div class="h1">Transparence & Audit</div>
<div class="muted">Objectif : des résultats vérifiables, une traçabilité claire et une confiance renforcée.</div>

<div style="margin-bottom:24px">
    <a href="dashboard.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <span>←</span>
        <span>Retour au tableau de bord</span>
    </a>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr;margin-top:16px">
  <section class="card">
    <div class="hd"><h3>État actuel</h3></div>
    <div class="bd">
      <div class="cards-2">
        <div class="mini">
          <div class="t"><?= (int)$totalElections ?></div>
          <div class="s">Élection(s) en base</div>
        </div>
        <div class="mini">
          <div class="t"><?= (int)$totalVotes ?></div>
          <div class="s">Vote(s) enregistrés</div>
        </div>
      </div>
      <div class="muted" style="margin-top:12px;line-height:1.6">
        Dans cette version, un vote est unique par élection (contrainte base) et les résultats sont comptabilisés côté serveur.
        Les fonctionnalités avancées (chiffrement, preuve, 2FA) seront ajoutées ensuite.
      </div>
    </div>
  </section>

  <section class="card" id="crypto">
    <div class="hd"><h3>Vérification cryptographique</h3></div>
    <div class="bd">
      <div class="muted" style="line-height:1.6">
        - Hashing et signatures pour l’intégrité des enregistrements<br/>
        - Jetons anonymes renforcés (non traçables vers l’électeur)<br/>
        - Vérification publique des résultats sans exposer l’identité
      </div>
      <div style="margin-top:14px;display:flex;gap:10px">
        <a class="btn primary" href="../app/dashboard.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Retour dashboard</a>
        <a class="btn ghost" href="../app/a_propos.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">À propos</a>
      </div>
    </div>
  </section>
</div>

<section class="card" style="margin-top:16px" id="public">
  <div class="hd"><h3>Livre d’audit public</h3></div>
  <div class="bd">
    <div class="muted" style="line-height:1.6">
      Architecture de transparence en place : règles, traçabilité interne et visibilité claire des résultats sans exposition des données personnelles.
    </div>
    <div style="margin-top:12px;display:grid;gap:10px">
      <div class="mini">
        <div class="t">Règles</div>
        <div class="s">1 vote par électeur et par élection • dates contrôlées • résultats calculés côté serveur</div>
      </div>
      <div class="mini">
        <div class="t">Traçabilité</div>
        <div class="s">Token anonyme stocké avec le vote pour préserver la confidentialité tout en garantissant une seule participation</div>
      </div>
      <div class="mini">
        <div class="t">Transparence</div>
        <div class="s">Résultats agrégés par candidat (table resultats) pour accélérer l’affichage et simplifier la vérification</div>
      </div>
    </div>
  </div>
</section>

<?php votigo_layout_end(); ?>

