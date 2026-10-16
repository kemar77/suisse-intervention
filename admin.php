<?php
/**
 * admin.php — Mini-admin "Assistance client" (Suisse Intervention).
 * Focus UNIQUEMENT : téléphone · email · WhatsApp Business (pour le hub assistance).
 * Architecture : site statique + PHP qui écrit config.json. Le site lit config.json au chargement.
 * Sécurité : mot de passe hashé (password_hash), session PHP, CSRF token, écriture JSON validée.
 * Déploiement : O2SWITCH (PHP 7.4+/8). Aucune base de données — fichier JSON.
 */
session_start();
$CFG    = __DIR__ . '/config.json';
$PASSF  = __DIR__ . '/.admin_pass.json';
$CSRF   = $_SESSION['csrf'] ?? ($_SESSION['csrf'] = bin2hex(random_bytes(16)));

/* ---------- helpers ---------- */
function load_cfg($file){ $s = @file_get_contents($file); if(!$s) return null; $j = json_decode($s, true); return is_array($j) ? $j : null; }
function write_cfg($file,$arr){
  $arr['updated_at'] = date('Y-m-d H:i:s');
  return file_put_contents($file, json_encode($arr, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) !== false;
}
function clean_tel($t){ return preg_replace('/[^0-9+]/','', trim($t)); }
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect($to){ header('Location: '.$to); exit; }

$cfg = load_cfg($CFG) ?: [
  'telephone'=>'+41 79 856 78 90','telephone_href'=>'+41798567890',
  'email'=>'info@suisseintervention.ch','whatsapp_token'=>'','whatsapp_connected'=>false,
];
$hasPass = file_exists($PASSF) && (json_decode(@file_get_contents($PASSF), true)['hash'] ?? null);

/* ---------- Actions (avant tout output) ---------- */
$action = $_POST['action'] ?? '';
if ($action === 'setup_pass' && !$hasPass){
  $p = $_POST['pw'] ?? ''; $p2 = $_POST['pw2'] ?? '';
  if (strlen($p) < 6 || $p !== $p2){ $err = 'Mot de passe trop court ou non confirmé (min. 6).'; }
  else { file_put_contents($PASSF, json_encode(['hash'=>password_hash($p, PASSWORD_DEFAULT)])); $_SESSION['auth']=true; redirect('admin.php?saved=1'); }
}
if ($action === 'login'){
  $j = json_decode(@file_get_contents($PASSF), true); $h = $j['hash'] ?? '';
  if ($h && password_verify($_POST['pw'] ?? '', $h)) { $_SESSION['auth']=true; redirect('admin.php'); }
  $err = 'Mot de passe incorrect.';
}
if ($action === 'logout'){ unset($_SESSION['auth']); session_destroy(); redirect('admin.php'); }

/* Edit : seulement si authentifié + CSRF valide */
if ($action === 'save' && !empty($_SESSION['auth']) && hash_equals($CSRF, $_POST['csrf'] ?? '')){
  $tel = trim($_POST['telephone'] ?? $cfg['telephone']);
  $mail = trim($_POST['email'] ?? $cfg['email']);
  $tok = trim($_POST['whatsapp_token'] ?? $cfg['whatsapp_token']);
  if (!filter_var($mail, FILTER_VALIDATE_EMAIL)){ $err = 'Email invalide.'; }
  else {
    $cfg['telephone'] = $tel;
    $cfg['telephone_href'] = clean_tel($tel);
    $cfg['email'] = $mail;
    $cfg['whatsapp_token'] = $tok;
    $cfg['whatsapp_connected'] = ($tok !== '');
    write_cfg($CFG, $cfg);
    redirect('admin.php?saved=1');
  }
}
$authed = !empty($_SESSION['auth']);
$saved  = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Admin · Assistance client — Suisse Intervention</title>
<style>
  :root{--paper:#FBF8F1;--ink:#0d1520;--accent:#c9a254;--accent2:#d8b978;--line:rgba(245,239,227,.14);--glass:rgba(13,21,32,.55)}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:radial-gradient(1200px 600px at 70% -10%,#1a2536,#0d1520 60%);color:#f5efe3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{width:min(520px,100%);background:var(--glass);backdrop-filter:blur(20px);border:1px solid var(--line);border-radius:22px;box-shadow:0 40px 110px rgba(0,0,0,.5);padding:34px 34px 30px}
  .brand{display:flex;align-items:center;gap:12px;margin-bottom:6px}.brand .logo{height:34px;width:auto}
  .brand b{font-size:1.05rem;letter-spacing:.03em}
  h1{font-size:1.25rem;font-weight:700;margin:2px 0 4px}
  .sub{color:rgba(245,239,227,.6);font-size:.86rem;margin-bottom:22px}
  .eyebrow{font-size:.7rem;letter-spacing:.18em;text-transform:uppercase;color:var(--accent2);margin-bottom:8px}
  label{display:block;font-size:.78rem;color:rgba(245,239,227,.75);margin:16px 0 6px;letter-spacing:.02em}
  input{width:100%;padding:13px 14px;border-radius:12px;border:1px solid rgba(245,239,227,.18);background:rgba(8,12,17,.5);color:#f5efe3;font-size:.95rem;outline:none;transition:border-color .2s}
  input:focus{border-color:var(--accent)}
  .row{display:flex;gap:12px;flex-wrap:wrap}.row>.col{flex:1;min-width:180px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;margin-top:22px;padding:14px;border-radius:999px;border:1px solid rgba(201,162,84,.38);background:rgba(201,162,84,.12);color:var(--accent2);font-weight:600;font-size:.95rem;cursor:pointer;transition:background .25s,color .25s,transform .15s}
  .btn:hover{background:var(--accent);color:#0d1520;transform:translateY(-1px)}
  .btn.ghost{background:transparent;border-color:rgba(245,239,227,.2);color:rgba(245,239,227,.8);margin-top:10px}.btn.ghost:hover{background:rgba(245,239,227,.08);color:#f5efe3}
  .badge{display:inline-flex;align-items:center;gap:6px;font-size:.74rem;padding:5px 11px;border-radius:999px;margin-top:6px}
  .badge.ok{color:#4ade80;background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.3)}
  .badge.no{color:rgba(245,239,227,.6);background:rgba(245,239,227,.06);border:1px solid rgba(245,239,227,.14)}
  .msg{padding:12px 14px;border-radius:12px;margin-top:16px;font-size:.85rem}
  .msg.ok{background:rgba(74,222,128,.12);color:#a7f3c4;border:1px solid rgba(74,222,128,.25)}
  .msg.err{background:rgba(255,90,90,.12);color:#fca5a5;border:1px solid rgba(255,90,90,.25)}
  .hint{font-size:.74rem;color:rgba(245,239,227,.5);margin-top:8px;line-height:1.5}
  .foot{margin-top:26px;padding-top:16px;border-top:1px solid var(--line);font-size:.72rem;color:rgba(245,239,227,.45);display:flex;justify-content:space-between;align-items:center}
  .foot a{color:var(--accent2);text-decoration:none}
  .guide{margin-top:26px;padding:18px 20px;border-radius:16px;background:rgba(245,239,227,.05);border:1px solid var(--line)}
  .guide-t{font-size:.82rem;font-weight:700;color:#f5efe3;margin-bottom:12px}
  .guide ol{margin:0;padding-left:20px;display:flex;flex-direction:column;gap:8px;font-size:.82rem;color:rgba(245,239,227,.82);line-height:1.5}
  .guide li strong{color:var(--accent2)}
  .guide .ok-t{color:#4ade80;font-weight:600}
  .guide-note{margin-top:14px;font-size:.78rem;color:rgba(245,239,227,.6);line-height:1.5;padding-top:12px;border-top:1px dashed var(--line)}
</style>
</head>
<body>
<div class="card">
  <div class="brand"><b>Suisse Intervention</b></div>
  <p class="eyebrow">Assistance client · Admin</p>
  <h1><?= $authed ? 'Paramètres d\'assistance' : ($hasPass ? 'Connexion' : 'Premier accès') ?></h1>
  <p class="sub">Modifiez le téléphone, l'email et le WhatsApp Business affichés sur le site. Tout est appliqué instantanément.</p>

  <?php if (!empty($err)): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
  <?php if ($saved): ?><div class="msg ok">✔ Configuration enregistrée.</div><?php endif; ?>

  <?php if (!$authed && !$hasPass): ?>
    <!-- Premier accès : créer le mot de passe -->
    <form method="post">
      <input type="hidden" name="action" value="setup_pass">
      <label>Créez votre mot de passe admin (min. 6 caractères)</label>
      <input type="password" name="pw" required placeholder="••••••••">
      <label>Confirmez le mot de passe</label>
      <input type="password" name="pw2" required placeholder="••••••••">
      <button class="btn" type="submit">Créer mon accès admin →</button>
    </form>
    <p class="hint">Ce mot de passe protège l'édition du téléphone / email / WhatsApp. Il n'est jamais stocké en clair (hashé).</p>

  <?php elseif (!$authed): ?>
    <!-- Connexion -->
    <form method="post">
      <input type="hidden" name="action" value="login">
      <label>Mot de passe admin</label>
      <input type="password" name="pw" required placeholder="••••••••" autofocus>
      <button class="btn" type="submit">Se connecter →</button>
    </form>

  <?php else: ?>
    <!-- Édition -->
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <div class="row">
        <div class="col">
          <label>Téléphone (affiché)</label>
          <input name="telephone" value="<?= e($cfg['telephone']) ?>" required>
        </div>
        <div class="col">
          <label>Email</label>
          <input name="email" type="email" value="<?= e($cfg['email']) ?>" required>
        </div>
      </div>
      <label>Token WhatsApp Business (pour le hub assistance)</label>
      <input name="whatsapp_token" value="<?= e($cfg['whatsapp_token']) ?>" placeholder="ex. 41798567890 ou jeton API">
      <div class="badge <?= $cfg['whatsapp_connected'] ? 'ok' : 'no' ?>">
        <?= $cfg['whatsapp_connected'] ? '● WhatsApp connecté' : '○ WhatsApp non connecté' ?>
      </div>
      <p class="hint">Renseignez un numéro (format international sans +, ex. 41798567890) pour activer le bouton WhatsApp du hub. Un jeton API Business peut être ajouté pour un envoi automatique.</p>
      <button class="btn" type="submit">Enregistrer & appliquer →</button>
    </form>
    <form method="post" style="margin-top:0">
      <input type="hidden" name="action" value="logout">
      <button class="btn ghost" type="submit">Se déconnecter</button>
    </form>
  <?php endif; ?>

  <?php if ($authed): ?>
    <!-- Mode d'emploi hyper simple (pour Sofiane) -->
    <div class="guide">
      <p class="guide-t">📖 Comment ça marche ? (très simple)</p>
      <ol>
        <li>Ce que tu écris ici, ça <strong>change sur le site</strong> pour tous les clients.</li>
        <li><strong>Téléphone</strong> : écris le numéro, exemple <em>+41 79 856 78 90</em>. C'est lui qui s'affiche partout (en haut, en bas, bouton "Assistance").</li>
        <li><strong>Email</strong> : écris l'adresse, exemple <em>info@suisseintervention.ch</em>. C'est elle qui reçoit les messages.</li>
        <li><strong>WhatsApp</strong> : écris le numéro <strong>sans le + et sans espaces</strong>, exemple <em>41798567890</em>. Quand c'est rempli, le bouton WhatsApp du chat devient <span class="ok-t">connecté</span>.</li>
        <li>Tu n'as <strong>rien d'autre à faire</strong>. Clique sur <strong>"Enregistrer & appliquer"</strong> et c'est déjà en ligne. ✔</li>
      </ol>
      <p class="guide-note">💡 Si tu te trompes, c'est pas grave : tu reviens ici, tu changes, tu ré-enregistres. Le site se met à jour tout seul.</p>
    </div>
  <?php endif; ?>

  <div class="foot">
    <span>Suisse Intervention · admin v1</span>
    <a href="./" target="_blank">Voir le site →</a>
  </div>
</div>
</body>
</html>
