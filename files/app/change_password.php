<?php
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_electeur();
$idUser = auth_user_id();
$userName = auth_user_name();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = trim((string)($_POST['current_password'] ?? ''));
    $newPassword = trim((string)($_POST['new_password'] ?? ''));
    $confirmPassword = trim((string)($_POST['confirm_password'] ?? ''));

    // Validation
    if ($currentPassword === '') {
        $error = "Veuillez entrer votre mot de passe actuel.";
    } elseif ($newPassword === '') {
        $error = "Veuillez entrer un nouveau mot de passe.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif ($newPassword === $currentPassword) {
        $error = "Le nouveau mot de passe doit être différent de l'ancien.";
    } else {
        // Verify current password
        $pdo = db();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id_user = :id LIMIT 1');
        $stmt->execute([':id' => $idUser]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            $error = "Votre mot de passe actuel est incorrect.";
        } else {
            // Update password
            try {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id_user = :id');
                $stmt->execute([':hash' => $newHash, ':id' => $idUser]);
                $success = true;
            } catch (PDOException $e) {
                $error = "Une erreur est survenue lors de la mise à jour du mot de passe.";
                error_log('VOTIGO: password change error: ' . $e->getMessage());
            }
        }
    }
}

votigo_layout_start('Modifier le mot de passe — VOTIGO', 'profile', [
    'userName' => $userName,
    'verified' => $idUser > 0,
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
]);
?>

<div class="h1">Modifier votre mot de passe</div>
<div class="muted">Choisissez un mot de passe fort et unique pour sécuriser votre compte.</div>

<div style="margin-bottom:24px">
    <a href="profile.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <span>←</span>
        <span>Retour au profil</span>
    </a>
</div>

<?php if ($success): ?>
  <div data-flash-type="ok" data-flash-title="Succès" data-flash-message="Votre mot de passe a été modifié avec succès."></div>
  <div style="margin-top:20px">
    <a class="btn primary" href="profile.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Retour au profil</a>
  </div>
<?php else: ?>

  <section class="card" style="margin-top:18px;max-width:600px">
    <div class="hd">
      <h3>Sécurité du compte</h3>
    </div>
    <div class="bd">
      <?php if ($error !== ''): ?>
        <div data-flash-type="err" data-flash-title="Erreur" data-flash-message="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"></div>
      <?php endif; ?>

      <form method="post" novalidate style="margin-top:18px">
        <!-- Current Password -->
        <div style="margin-bottom:16px">
          <label for="current_password" style="display:block;color:var(--muted);font-size:13px;margin-bottom:6px">Mot de passe actuel</label>
          <input
            id="current_password"
            name="current_password"
            type="password"
            autocomplete="current-password"
            required
            placeholder="Entrez votre mot de passe actuel"
            style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.10);background:rgba(5,18,28,.35);color:var(--text);outline:none"
          />
        </div>

        <!-- New Password -->
        <div style="margin-bottom:16px">
          <label for="new_password" style="display:block;color:var(--muted);font-size:13px;margin-bottom:6px">Nouveau mot de passe</label>
          <input
            id="new_password"
            name="new_password"
            type="password"
            autocomplete="new-password"
            required
            placeholder="Minimum 8 caractères"
            style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.10);background:rgba(5,18,28,.35);color:var(--text);outline:none"
            minlength="8"
          />
          <div style="margin-top:6px;font-size:12px;color:var(--muted);line-height:1.4">
            Utilisez un mot de passe fort combinant majuscules, minuscules, chiffres et caractères spéciaux.
          </div>
        </div>

        <!-- Confirm Password -->
        <div style="margin-bottom:20px">
          <label for="confirm_password" style="display:block;color:var(--muted);font-size:13px;margin-bottom:6px">Confirmer le nouveau mot de passe</label>
          <input
            id="confirm_password"
            name="confirm_password"
            type="password"
            autocomplete="new-password"
            required
            placeholder="Confirmez votre nouveau mot de passe"
            style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.10);background:rgba(5,18,28,.35);color:var(--text);outline:none"
            minlength="8"
          />
        </div>

        <!-- Password Strength Indicator -->
        <div style="padding:12px;background:rgba(26,169,184,.08);border:1px solid rgba(26,169,184,.2);border-radius:12px;margin-bottom:20px">
          <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:8px">Recommandations de sécurité :</div>
          <ul style="margin:0;padding-left:16px;font-size:12px;color:var(--muted);line-height:1.6">
            <li>✓ Au moins 8 caractères</li>
            <li>✓ Mélangez majuscules et minuscules</li>
            <li>✓ Incluez des chiffres (0-9)</li>
            <li>✓ Incluez des caractères spéciaux (!@#$%)</li>
            <li>✓ N'utilisez pas d'informations personnelles</li>
          </ul>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:flex-end">
          <a href="profile.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;padding:10px 16px">Annuler</a>
          <button type="submit" class="btn primary" style="padding:10px 16px">Modifier le mot de passe</button>
        </div>
      </form>
    </div>
  </section>

  <!-- Security Tips -->
  <section class="card" style="margin-top:16px">
    <div class="hd">
      <h3>Conseils de sécurité</h3>
    </div>
    <div class="bd">
      <div style="display:flex;flex-direction:column;gap:12px">
        <div style="padding:12px;background:rgba(255,255,255,.02);border-left:3px solid var(--primary);border-radius:8px">
          <div style="font-weight:600;font-size:13px;margin-bottom:3px">🔐 Mots de passe uniques</div>
          <div style="font-size:12px;color:var(--muted)">N'utilisez jamais le même mot de passe pour plusieurs services.</div>
        </div>
        <div style="padding:12px;background:rgba(255,255,255,.02);border-left:3px solid #63b96f;border-radius:8px">
          <div style="font-weight:600;font-size:13px;margin-bottom:3px">🛡️ Authentification 2FA</div>
          <div style="font-size:12px;color:var(--muted)">Vous utilisez déjà l'authentification à deux facteurs (2FA) pour une protection supplémentaire.</div>
        </div>
        <div style="padding:12px;background:rgba(255,255,255,.02);border-left:3px solid #ff9800;border-radius:8px">
          <div style="font-weight:600;font-size:13px;margin-bottom:3px">💻 Changement régulier</div>
          <div style="font-size:12px;color:var(--muted)">Modifiez votre mot de passe tous les 3 mois pour une meilleure sécurité.</div>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

<div style="margin-top:24px;text-align:center">
  <a class="btn ghost" href="profile.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px">
    <span>←</span> Retour au profil
  </a>
</div>

<?php votigo_layout_end(); ?>
