<?php
declare(strict_types=1);

function votigo_icon(string $name): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12a8 8 0 1116 0v7a1 1 0 01-1 1H5a1 1 0 01-1-1v-7z" stroke="currentColor" stroke-width="1.6"/><path d="M8 20v-8m4 8V8m4 12v-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'elections' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3v4m10-4v4M4.5 8.5h15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M6 6h12a2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2z" stroke="currentColor" stroke-width="1.6"/></svg>',
        'history' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12a8 8 0 101.6-4.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M4 4v5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'audit' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 11l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 2l7 3v6c0 6-4.2 9.8-7 11-2.8-1.2-7-5-7-11V5l7-3z" stroke="currentColor" stroke-width="1.6"/></svg>',
        'profile' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 10-16 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M12 12a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="1.6"/></svg>',
        'admin' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2l7 4v6c0 5-3.6 9-7 10-3.4-1-7-5-7-10V6l7-4z" stroke="currentColor" stroke-width="1.6"/><path d="M12 7v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M9.5 10h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'vote' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10h16v10H4V10z" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V6a4 4 0 118 0v4" stroke="currentColor" stroke-width="1.6"/><path d="M9 15l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    ];

    return $icons[$name] ?? '';
}

function format_date_fr(string $date): string
{
    if (empty($date)) {
        return '';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date; // fallback
    }
    $day = (int)date('d', $timestamp);
    $month = (int)date('n', $timestamp);
    $year = (int)date('Y', $timestamp);
    $months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    return $day . ' ' . $months[$month - 1] . ' ' . $year;
}

function votigo_layout_start(string $title, string $active, array $opts = []): void
{
    $userName = (string)($opts['userName'] ?? 'Électeur');
    $verified = (bool)($opts['verified'] ?? false);
    $showSidebar = (bool)($opts['showSidebar'] ?? true);
    $navMode = (string)($opts['navMode'] ?? 'electeur'); // 'electeur' | 'admin'
    $isAdmin = (bool)($opts['isAdmin'] ?? false);

    if ($navMode === 'admin' || $isAdmin) {
        $badge = $verified ? 'Administrateur vérifié' : 'Session admin';
        $badgeIco = $verified ? votigo_icon('admin') : votigo_icon('profile');
    } else {
        $badge = $verified ? 'Électeur vérifié' : 'Session active';
        $badgeIco = $verified ? votigo_icon('audit') : votigo_icon('profile');
    }

    $logo = '../../images/IMG-20260127-WA0054.jpg';
    $logoAlt = 'Logo Votigo';
    $secondaryLogo = '../../images/logo_ispm.png';
    $secondaryLogoAlt = 'Logo ISPM';
    $secondaryLogoText = 'INSTITUT SUPERIEUR POLYTECHNIQUE DE MADAGASCAR';

    if (!empty($opts['logo'])) {
        $logo = (string)$opts['logo'];
    }
    if (!empty($opts['logoAlt'])) {
        $logoAlt = (string)$opts['logoAlt'];
    }
    if (!empty($opts['secondaryLogo'])) {
        $secondaryLogo = (string)$opts['secondaryLogo'];
    }
    if (!empty($opts['secondaryLogoAlt'])) {
        $secondaryLogoAlt = (string)$opts['secondaryLogoAlt'];
    }
    if (!empty($opts['secondaryLogoText'])) {
        $secondaryLogoText = (string)$opts['secondaryLogoText'];
    }
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="../../CSS/votigo.css" />
  <script defer src="../../JS/votigo.js"></script>
</head>
<body>
  <div class="app" style="<?= $showSidebar ? '' : 'grid-template-columns:1fr' ?>">
    <?php if ($showSidebar): ?>
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-block brand-blue">
          <img class="brand-logo" src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($logoAlt, ENT_QUOTES, 'UTF-8') ?>" />
          <div class="brand-text">
            <div class="name">VOTIGO</div>
            <div class="muted" style="font-size:12px;margin-top:2px">Votre choix, votre pouvoir</div>
          </div>
        </div>
      </div>

      <nav class="nav" aria-label="Navigation">
        <?php if ($navMode === 'admin'): ?>
          <a class="<?= $active === 'admin' ? 'active' : '' ?>" href="../admin/admin.php">
            <span class="ico"><?= votigo_icon('admin') ?></span>
            <span>Administration</span>
            <span class="badge">Admin</span>
          </a>
          <a class="<?= $active === 'admin-results' ? 'active' : '' ?>" href="../admin/results.php">
            <span class="ico"><?= votigo_icon('audit') ?></span>
            <span>Résultats</span>
          </a>
        <?php else: ?>
          <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="../app/dashboard.php">
            <span class="ico"><?= votigo_icon('dashboard') ?></span>
            <span>Tableau de bord</span>
            <span class="badge">Active</span>
          </a>
          <a class="<?= $active === 'elections' ? 'active' : '' ?>" href="../app/results.php">
            <span class="ico"><?= votigo_icon('audit') ?></span>
            <span>Résultats des élections</span>
          </a>
          <a class="<?= $active === 'history' ? 'active' : '' ?>" href="../app/historique.php">
            <span class="ico"><?= votigo_icon('history') ?></span>
            <span>Historique des votes</span>
          </a>
          <a class="<?= $active === 'audit' ? 'active' : '' ?>" href="../app/audit.php">
            <span class="ico"><?= votigo_icon('audit') ?></span>
            <span>Transparence & Audit</span>
          </a>
          <a class="<?= $active === 'guide' ? 'active' : '' ?>" href="../app/guide.php">
            <span class="ico"><?= votigo_icon('elections') ?></span>
            <span>Guide d'utilisation</span>
          </a>
          <a class="<?= $active === 'about' ? 'active' : '' ?>" href="../app/a_propos.php">
            <span class="ico"><?= votigo_icon('profile') ?></span>
            <span>À propos</span>
          </a>
          <?php if ($isAdmin): ?>
            <a class="<?= $active === 'admin' ? 'active' : '' ?>" href="../admin/admin.php">
              <span class="ico"><?= votigo_icon('admin') ?></span>
              <span>Administration</span>
            </a>
          <?php endif; ?>
        <?php endif; ?>
      </nav>

      <div class="sidebar-footer">
        <div class="brand-block brand-green">
          <img class="brand-logo" src="<?= htmlspecialchars($secondaryLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($secondaryLogoAlt, ENT_QUOTES, 'UTF-8') ?>" />
          <div class="brand-text">
            <div class="brand-secondary-text"><?= htmlspecialchars($secondaryLogoText, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <div class="small">
          <div style="margin-bottom:8px">
            <?php
              $logoutHref = ($navMode === 'admin' || $isAdmin) ? '../admin/logout.php' : '../inscription/logout.php';
            ?>
            <a href="<?= htmlspecialchars($logoutHref, ENT_QUOTES, 'UTF-8') ?>" class="btn ghost" style="width:100%;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;font-size:12px">Se déconnecter</a>
          </div>
          <!-- Votigo modernise le vote avec une expérience rapide, fiable et orientée confidentialité. -->
        </div>
      </div>
    </aside>
    <?php endif; ?>

    <main class="content">
      <header class="topbar">
        <div class="chip">
          <span class="ico" style="width:18px;height:18px"><?= $badgeIco ?></span>
          <span><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="spacer"></div>
        <div class="chip">
          <div class="avatar" aria-hidden="true"></div>
          <div style="font-weight:800"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </header>

      <div class="toast" data-toast>
        <div class="k" data-toast-title></div>
        <div class="m" data-toast-message></div>
      </div>

      <div id="votigo-lightbox" class="votigo-lightbox" aria-hidden="true" style="display:none!important">
        <div class="votigo-lightbox-backdrop" data-lightbox-close></div>
        <button class="votigo-lightbox-close" type="button" aria-label="Fermer l'image">×</button>
        <div class="votigo-lightbox-inner">
          <img id="votigo-lightbox-img" src="" alt="" />
        </div>
      </div>
<?php
}

function votigo_layout_end(): void
{
    ?>
    </main>
  </div>
</body>
</html>
<?php
}

