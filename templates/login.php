<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

start_session();

$already_logged = is_logged_in();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

  try {
    if (login(get_pdo(), $username, $password)) {
      $authUser = get_auth_user();
      header('Location: ' . (!empty($authUser['employee_id']) ? 'employee.php' : 'dashboard.php'));
      exit;
    }

    $error = 'Identifiants invalides.';
  } catch (Throwable $e) {
    $error = 'Base de donnees indisponible pour le moment. Reessaie dans quelques secondes.';
    error_log('Erreur connexion DB login: ' . $e->getMessage());
  }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>JustInTime | Connexion</title>
  <link rel="stylesheet" href="static/css/styles.css" />
  <style>
    .login-wrap {
      min-height: 100vh;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 2rem;
    }
    .login-container {
      max-width: 420px; width: 100%;
      padding: clamp(1.5rem, 4vw, 2.2rem);
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }
    .login-container h1 {
      text-align: center; margin-bottom: 1.5rem;
      font-size: 1.6rem; font-weight: 800;
    }
    .login-container h1 span {
      background: linear-gradient(135deg, var(--accent), #ff8c42);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 600; font-size: 0.92rem; color: var(--ink-soft); }
    .form-group input {
      width: 100%; padding: 0.75rem;
      background: var(--surface-2); color: var(--ink);
      border: 1px solid var(--line); border-radius: 10px;
      font: inherit; transition: border-color 0.2s;
    }
    .form-group input:focus { outline: none; border-color: var(--accent); }
    .btn-login {
      width: 100%; padding: 0.8rem; margin-top: 0.5rem;
      background: var(--accent); color: #fff; border: 0;
      font-weight: 700; border-radius: 10px; cursor: pointer;
      font-size: 1rem;
      box-shadow: 0 4px 16px rgba(232, 87, 26, 0.25);
      transition: filter 0.2s, transform 0.15s;
    }
    .btn-login:hover { filter: brightness(1.08); transform: translateY(-1px); }
    .error {
      color: var(--warn); padding: 0.8rem;
      background: rgba(248, 113, 113, 0.1);
      border: 1px solid rgba(248, 113, 113, 0.2);
      border-radius: 10px; margin-bottom: 1rem; font-size: 0.92rem;
    }
    .logged-panel { text-align: center; }
    .logged-panel p { margin-bottom: 1.2rem; color: var(--ink-soft); }
    .logged-panel .links { display: flex; flex-direction: column; gap: 0.6rem; }
    .logged-panel .links a {
      display: block; padding: 0.75rem; border-radius: 10px;
      font-weight: 600; text-align: center; font-size: 0.95rem;
      transition: filter 0.2s, transform 0.15s;
    }
    .logged-panel .links a:hover { filter: brightness(1.08); transform: translateY(-1px); }
    .link-dashboard { background: var(--accent); color: #fff; box-shadow: 0 4px 16px rgba(232, 87, 26, 0.2); }
    .link-admin { background: var(--teal); color: #fff; }
    .link-logout { background: transparent; border: 1px solid var(--line); color: var(--ink-soft); }
    .back-home {
      display: inline-flex; align-items: center; gap: 0.4rem;
      margin-top: 1.2rem; color: var(--ink-soft); font-size: 0.88rem;
      transition: color 0.2s;
    }
    .back-home:hover { color: var(--accent); }
  </style>
</head>
<body>
  <div class="page-bg" aria-hidden="true"></div>
  <div class="login-wrap">
    <div class="login-container">
      <h1>🔐 <span>JustInTime</span></h1>
      
      <?php if ($already_logged): ?>
        <?php $authUser = get_auth_user(); ?>
        <div class="logged-panel">
          <p>Connecté en tant que <strong style="color: var(--ink);"><?= htmlspecialchars($authUser['username']) ?></strong></p>
          <div class="links">
            <a href="dashboard.php" class="link-dashboard">📊 Tableau de bord</a>
            <?php if ($authUser['role'] === 'admin'): ?>
              <a href="admin.php" class="link-admin">📋 Administration</a>
            <?php endif; ?>
            <a href="reporting.php" class="link-dashboard">📈 Reporting</a>
            <a href="logout.php" class="link-logout">🚪 Se déconnecter</a>
          </div>
        </div>
      <?php else: ?>

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label for="username">Identifiant</label>
          <input id="username" name="username" type="text" autocomplete="username" required autofocus />
        </div>
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required />
        </div>
        <button type="submit" class="btn-login">Se connecter</button>
      </form>

      <?php endif; ?>

    </div>
    <a href="index.php" class="back-home">← Retour à l'accueil</a>
  </div>
</body>
</html>
