<?php
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

$pdo = db();
$userName = auth_user_name();

votigo_layout_start('Guide d\'utilisation — VOTIGO', 'guide', [
    'userName' => $userName,
    'verified' => auth_is_electeur() || auth_is_admin(),
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
]);
?>

<div class="h1">Guide d'utilisation</div>
<div class="muted">Découvrez comment utiliser VOTIGO pour participer aux élections en toute confiance et facilité.</div>

<div style="margin-bottom:24px">
    <a href="dashboard.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <span>←</span>
        <span>Retour au tableau de bord</span>
    </a>
</div>

<section class="card" style="margin-top:16px">
  <div class="hd"><h3>Les principes de VOTIGO</h3></div>
  <div class="bd">
    <div style="display:grid;gap:16px">
      <div>
        <div style="font-weight:500;color:var(--fg)">🔒 Confidentialité absolue</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Votre vote est Votre vote est protégé par un système de tokenisation anonyme. Personne, pas même les administrateurs, ne peut savoir pour qui vous avez voté. Même s'ils accèdent à la base de données, le lien entre votre identité et votre vote reste invisible.
        </div>
      </div>
      <div>
        <div style="font-weight:500;color:var(--fg)">✓ Vote unique</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Un électeur = Un vote. Le système empêche automatiquement les votes multiples en utilisant des tokens uniques liés à l'élection. Les tentatives de revote sont refusées en silence.
        </div>
      </div>
      <div>
        <div style="font-weight:500;color:var(--fg)">📊 Transparence</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Consultez les résultats et les statistiques de vote en temps réel. La page "Transparence &amp; Audit" vous montre les chiffres bruts et les pourcentages pour chaque élection.
        </div>
      </div>
    </div>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="hd"><h3>Comment voter</h3></div>
  <div class="bd">
    <div style="display:grid;gap:14px">
      <div style="padding:12px;background:var(--bg-alt);border-radius:8px">
        <div style="font-weight:500;color:var(--fg)">Étape 1 : Accédez à votre tableau de bord</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Après connexion, vous arrivez sur votre <strong>Tableau de bord</strong>. Vous y verrez toutes les élections disponibles et en cours.
        </div>
      </div>
      <div style="padding:12px;background:var(--bg-alt);border-radius:8px">
        <div style="font-weight:500;color:var(--fg)">Étape 2 : Sélectionnez une élection</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Cliquez sur une élection pour voir la liste des candidats. Chaque candidat est présenté avec une photo, une biographie et un numéro d'ordre.
        </div>
      </div>
      <div style="padding:12px;background:var(--bg-alt);border-radius:8px">
        <div style="font-weight:500;color:var(--fg)">Étape 3 : Examinez les candidats</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Explorez les profils des candidats. Cliquez sur une photo pour l'agrandir dans une fenêtre lightbox. Prenez votre temps pour bien connaître les options.
        </div>
      </div>
      <div style="padding:12px;background:var(--bg-alt);border-radius:8px">
        <div style="font-weight:500;color:var(--fg)">Étape 4 : Sélectionnez votre candidat</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Cliquez sur la carte du candidat de votre choix pour le sélectionner. Une coche (✓) apparaîtra pour confirmer votre sélection.
        </div>
      </div>
      <div style="padding:12px;background:var(--bg-alt);border-radius:8px">
        <div style="font-weight:500;color:var(--fg)">Étape 5 : Confirmez votre vote</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Cliquez sur le bouton <strong>CONFIRMER MON VOTE</strong>. Une fois confirmé, votre vote <strong>ne peut plus être modifié</strong>. Soyez certain de votre choix avant de confirmer.
        </div>
      </div>
      <div style="padding:12px;background:var(--bg-alt);border-radius:8px">
        <div style="font-weight:500;color:var(--fg)">Étape 6 : Fin du vote</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Vous recevrez une confirmation de votre vote. Vous pouvez retourner au tableau de bord, consulter les résultats (anonymes), ou vérifier l'audit de transparence.
        </div>
      </div>
    </div>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="hd"><h3>Après votre vote</h3></div>
  <div class="bd">
    <div style="display:grid;gap:14px">
      <div>
        <div style="font-weight:500;color:var(--fg)">Voir les candidats à nouveau</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Après avoir voté, vous verrez un bouton <strong>"Afficher les candidats"</strong> pour revoir la liste des candidats. Ce bouton vous permet de consulter les profils <strong>sans pouvoir revote</strong>r. Votre choix reste confidentiel même pour vous-même une fois enregistré.
        </div>
      </div>
      <div>
        <div style="font-weight:500;color:var(--fg)">Vérifier la transparence</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Allez sur <strong>"Transparence &amp; Audit"</strong> pour voir :
          <ul style="margin-top:8px;margin-left:20px;line-height:1.8">
            <li>Le nombre total de votes par candidat</li>
            <li>Les pourcentages de votes</li>
            <li>Les statistiques brutes par élection</li>
            <li>Aucune information personnelle n'est affichée</li>
          </ul>
        </div>
      </div>
      <div>
        <div style="font-weight:500;color:var(--fg)">Consulter votre historique</div>
        <div class="muted" style="margin-top:4px;line-height:1.6">
          Votre <strong>Historique de vote</strong> enregistre les élections auxquelles vous avez participé, mais ne montre jamais vos votes spécifiques. C'est une preuve de votre participation sans révéler vos choix.
        </div>
      </div>
    </div>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="hd"><h3>Questions fréquentes</h3></div>
  <div class="bd">
    <details style="cursor:pointer;user-select:none;margin-bottom:12px">
      <summary style="padding:10px;background:var(--bg-alt);border-radius:6px;font-weight:500">Qui peut voir pour qui j'ai voté ?</summary>
      <div class="muted" style="padding:12px;line-height:1.6">
        Personne. Votre vote est anonyme grâce au système de tokenisation HMAC-SHA256. Les administrateurs ne peuvent pas établir le lien entre votre identité et votre vote, même s'ils accèdent directement à la base de données.
      </div>
    </details>
    <details style="cursor:pointer;user-select:none;margin-bottom:12px">
      <summary style="padding:10px;background:var(--bg-alt);border-radius:6px;font-weight:500">Que se passe-t-il si je me déconnecte avant de confirmer ?</summary>
      <div class="muted" style="padding:12px;line-height:1.6">
        Votre vote n'est enregistré que si vous cliquez sur "CONFIRMER MON VOTE". La simple sélection d'un candidat sans confirmation ne fait rien. Vous pouvez vous déconnecter sans risque jusqu'à la confirmation.
      </div>
    </details>
    <details style="cursor:pointer;user-select:none;margin-bottom:12px">
      <summary style="padding:10px;background:var(--bg-alt);border-radius:6px;font-weight:500">Puis-je changer mon vote après ?</summary>
      <div class="muted" style="padding:12px;line-height:1.6">
        Non, une fois confirmé, votre vote est final et irrévocable. Cela garantit l'intégrité du scrutin. Assurez-vous de votre choix avant de confirmer.
      </div>
    </details>
    <details style="cursor:pointer;user-select:none;margin-bottom:12px">
      <summary style="padding:10px;background:var(--bg-alt);border-radius:6px;font-weight:500">Comment dois-je m'inscrire pour voter ?</summary>
      <div class="muted" style="padding:12px;line-height:1.6">
        Vous devez déjà être inscrit(e) via la procédure d'inscription (Étape 1, 2FA, et Étape 3). Si vous ne l'êtes pas encore, allez sur la page d'inscription pour créer un compte électeur avec vérification 2FA.
      </div>
    </details>
    <details style="cursor:pointer;user-select:none;margin-bottom:12px">
      <summary style="padding:10px;background:var(--bg-alt);border-radius:6px;font-weight:500">Mes données personnelles sont-elles sécurisées ?</summary>
      <div class="muted" style="padding:12px;line-height:1.6">
        Oui. VOTIGO utilise des connexions sécurisées (HTTPS projeté), des mots de passe hachés, et des vérifications 2FA. Vos données ne sont jamais liées à vos votes grâce à l'anonymisation.
      </div>
    </details>
  </div>
</section>

<div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap">
  <a class="btn primary" href="../app/dashboard.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Retour au tableau de bord</a>
  <a class="btn ghost" href="../app/a_propos.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">À propos de VOTIGO</a>
  <a class="btn ghost" href="../app/audit.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Transparence &amp; Audit</a>
</div>

<?php votigo_layout_end(); ?>
