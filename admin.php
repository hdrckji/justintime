<?php require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_login('admin');
$user = get_auth_user();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>JustInTime | Admin</title>
  <link rel="stylesheet" href="static/css/styles.css?v=20260412-2" />
  <style>
    .admin-nav {
      grid-column: span 12;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 0.5rem;
      margin-bottom: 1.5rem;
      width: 100%;
    }
    .admin-nav .tab-btn,
    .admin-nav .nav-link-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.45rem;
      min-height: 46px;
      height: 46px;
      line-height: 1.35;
      padding: 0.7rem 0.9rem;
      border: 1px solid var(--line);
      border-radius: 10px;
      cursor: pointer;
      white-space: nowrap;
      font-size: 0.9rem;
      font-weight: 600;
      font-family: 'Sora', sans-serif;
      background: var(--surface-2);
      color: var(--ink-soft);
      transform: none;
      width: 100%;
      transition: border-color 0.2s, color 0.2s, background 0.2s;
    }
    .admin-nav .tab-btn:hover,
    .admin-nav .nav-link-btn:hover {
      transform: none !important;
      filter: none;
      border-color: var(--accent);
      color: var(--ink);
    }
    .admin-nav .tab-btn.active {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
    }
    .admin-nav .nav-link-btn {
      text-decoration: none;
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 600; font-size: 0.95rem; color: var(--ink-soft); }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 0.6rem;
      background: var(--surface-2); color: var(--ink);
      border: 1px solid var(--line); border-radius: 8px; font: inherit;
    }
    .field-head-inline {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.8rem;
      margin-bottom: 0.35rem;
      flex-wrap: wrap;
    }
    .field-head-inline label {
      margin-bottom: 0;
    }
    .inline-toggle {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.35rem 0.6rem;
      border: 1px solid var(--line);
      border-radius: 999px;
      background: var(--surface);
      color: var(--ink);
      font-size: 0.84rem;
      font-weight: 600;
      white-space: nowrap;
    }
    .inline-toggle input {
      width: auto;
      margin: 0;
      padding: 0;
      accent-color: var(--accent);
    }
    .telework-locations-box {
      margin-top: 0.65rem;
      border: 1px dashed var(--line);
      border-radius: 10px;
      padding: 0.7rem 0.8rem;
      background: rgba(255, 255, 255, 0.02);
    }
    .telework-locations-note {
      margin: 0 0 0.55rem 0;
      color: var(--ink-soft);
      font-size: 0.84rem;
    }
    .employee-row {
      padding: 0.8rem; border: 1px solid var(--line);
      margin-bottom: 0.5rem; border-radius: 10px;
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 0.5rem;
      background: var(--surface-2);
    }
    .employee-row button { padding: 0.4rem 0.8rem; margin-left: 0.3rem; font-size: 0.85rem; }
    .btn-delete { background: var(--warn); color: #0a0a0a; }
    .btn-edit { background: var(--accent); }
    .department-layout {
      display: grid;
      gap: 1rem;
      grid-template-columns: minmax(240px, 320px) 1fr;
      margin-top: 1.5rem;
      align-items: start;
    }
    .department-card {
      padding: 0.8rem;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: var(--surface-2);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 0.5rem;
      flex-wrap: wrap;
    }
    .department-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      margin-top: 0.35rem;
      padding: 0.15rem 0.55rem;
      border-radius: 999px;
      background: rgba(91, 141, 239, 0.16);
      color: var(--accent);
      font-size: 0.78rem;
      font-weight: 700;
    }
    .device-settings-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      align-items: stretch;
    }
    .device-settings-grid .form-group {
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
    }
    .device-settings-grid .form-group label {
      min-height: 2.35rem;
    }
    .device-settings-note {
      margin-top: 0.8rem;
      padding: 0.8rem;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: var(--surface-2);
      color: var(--ink-soft);
      font-size: 0.92rem;
    }
    .admin-two-col-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .admin-top-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }
    .hours-visual-block {
      margin-top: 1.5rem;
      padding-top: 1.2rem;
      border-top: 1px dashed var(--line);
    }
    .hours-section-switch {
      display: flex;
      gap: 0.5rem;
      margin: 0.8rem 0 1rem;
      flex-wrap: wrap;
    }
    .hours-section-btn {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: var(--surface-2);
      color: var(--ink-soft);
      font: inherit;
      font-weight: 600;
      padding: 0.48rem 0.8rem;
      cursor: pointer;
    }
    .hours-section-btn.active {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
    }
    .hours-visual-title {
      margin: 0 0 0.3rem 0;
      font-size: 1.05rem;
    }
    .hours-visual-subtitle {
      margin: 0 0 1rem 0;
      color: var(--ink-soft);
      font-size: 0.92rem;
    }
    .hours-balance-summary {
      display: grid;
      gap: 0.8rem;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      margin-top: 1rem;
    }
    .hours-balance-card {
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 0.7rem 0.8rem;
      background: var(--surface-2);
    }
    .hours-balance-card p {
      margin: 0;
      color: var(--ink-soft);
      font-size: 0.8rem;
    }
    .hours-balance-card strong {
      display: block;
      margin-top: 0.35rem;
      font-size: 1.15rem;
      color: var(--ink);
    }
    .hours-equilibrium-badge {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 0.22rem 0.6rem;
      font-size: 0.78rem;
      font-weight: 700;
      margin-top: 0.4rem;
      background: rgba(52, 211, 153, 0.12);
      color: var(--ok);
    }
    .hours-day-list {
      display: grid;
      gap: 0.65rem;
      margin-top: 1rem;
    }
    .hours-day-item {
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 0.6rem 0.75rem;
      background: var(--surface-2);
    }
    .hours-day-head {
      display: flex;
      justify-content: space-between;
      gap: 0.5rem;
      align-items: baseline;
      margin-bottom: 0.35rem;
    }
    .hours-day-head strong {
      font-size: 0.92rem;
    }
    .hours-day-head span {
      font-size: 0.82rem;
      color: var(--ink-soft);
    }
    .hours-day-bar {
      width: 100%;
      height: 10px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.06);
      overflow: hidden;
    }
    .hours-day-bar-fill {
      height: 100%;
      border-radius: 999px;
      background: linear-gradient(90deg, var(--accent), #6dd3fb);
    }
    .hours-department-people {
      margin-top: 1rem;
      display: grid;
      gap: 0.5rem;
    }
    .hours-person-row {
      display: grid;
      grid-template-columns: minmax(150px, 1fr) 2fr auto;
      gap: 0.6rem;
      align-items: center;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 0.45rem 0.6rem;
      background: var(--surface-2);
    }
    .hours-person-name {
      font-size: 0.86rem;
      color: var(--ink);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .hours-empty {
      border: 1px dashed var(--line);
      border-radius: 10px;
      padding: 0.9rem;
      color: var(--ink-soft);
      background: var(--surface-2);
      margin-top: 1rem;
    }
    .department-card > div {
      min-height: 2.5rem;
    }
    .department-card button {
      margin-left: auto;
    }
    @media (max-width: 920px) {
      .department-layout {
        grid-template-columns: 1fr;
      }
    }
    @media (max-width: 560px) {
      .admin-nav {
        gap: 0.35rem;
        grid-template-columns: 1fr;
      }
      .admin-nav .tab-btn {
        min-height: 42px; height: 42px;
        padding: 0.55rem 0.7rem; font-size: 0.8rem;
      }
      .employee-row { flex-direction: column; align-items: flex-start; }
      .employee-row button { margin-left: 0; width: 100%; }
      .hours-person-row {
        grid-template-columns: 1fr;
        gap: 0.35rem;
      }
    }
  </style>
  <style>
    .btn-access { background: #7c5cbf; color: #fff; border: 0; border-radius: 8px; cursor: pointer; padding: 0.4rem 0.8rem; font-size: 0.85rem; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: var(--surface); border: 1px solid var(--line);
      border-radius: var(--radius); padding: clamp(1rem, 4vw, 2rem);
      width: min(90vw, 460px); box-shadow: var(--shadow);
    }
    .modal-box h3 { margin-top: 0; font-size: clamp(1rem, 2.5vw, 1.3rem); }
    .modal-close { float: right; background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--ink-soft); }
  </style>
</head>
<body>
  <div class="page-bg" aria-hidden="true"></div>

  <nav class="app-nav">
    <div class="app-nav-inner">
      <a href="index.php" class="app-nav-logo">Just In Time</a>
      <div class="app-nav-links">
        <a href="dashboard.php">📊 Tableau de bord</a>
        <a href="reporting.php">📈 Reporting</a>
        <a href="admin.php">🔧 Admin</a>
        <span class="app-nav-user">👤 <strong><?= htmlspecialchars($user['username']) ?></strong></span>
        <a href="logout.php">🚪 Logout</a>
      </div>
    </div>
  </nav>

  <main class="layout">
    <header class="hero">
      <h1>🔧 Administration</h1>
      <p class="subtitle">Gestion des collaborateurs, départements, absences et horaires</p>
    </header>

    <div class="admin-nav">
      <button class="tab-btn active" data-tab="employees">👥 Collaborateurs</button>
      <a class="nav-link-btn" href="corrections.php">✏️ Corrections</a>
      <button class="tab-btn" data-tab="departments">🏷️ Départements</button>
      <button class="tab-btn" data-tab="absences">📋 Absences</button>
      <button class="tab-btn" data-tab="hours">⏰ Horaires</button>
      <button class="tab-btn" data-tab="payroll">💼 Paie & cloture</button>
      <button class="tab-btn" data-tab="vacation-requests">🏖️ Congés</button>
      <button class="tab-btn" data-tab="device-settings">📟 Pointeuse</button>
    </div>

    <!-- Onglet Collaborateurs -->
    <div id="employees" class="tab-content active panel">
      <h2>Gestion des collaborateurs</h2>
      <form id="employee-form" class="form-group">
        <input type="hidden" id="emp-id" />
        <div class="form-grid-2">
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-first">Prenom</label>
            <input id="emp-first" type="text" required />
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-last">Nom</label>
            <input id="emp-last" type="text" required />
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-badge">Badge RFID</label>
            <input id="emp-badge" type="text" required />
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-active">Actif</label>
            <select id="emp-active">
              <option value="1">Oui</option>
              <option value="0">Non</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-department">Departement</label>
            <select id="emp-department">
              <option value="">Aucun departement</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom: 0;">
            <label for="emp-rayon">Rayon</label>
            <select id="emp-rayon">
              <option value="">Aucun rayon</option>
            </select>
          </div>
        </div>
        <div class="form-grid-auto" style="margin-top: 0.5rem;">
          <div class="form-group" style="margin: 0; grid-column: span 2;">
            <div class="field-head-inline">
              <label for="emp-address">Adresse</label>
              <label class="inline-toggle">
                <input id="emp-telework-enabled" type="checkbox" />
                Teletravail autorise
              </label>
            </div>
            <input id="emp-address" type="text" placeholder="Ex: 12 rue de la Paix, Paris" style="width:100%;" />
            <input id="emp-lat" type="hidden" />
            <input id="emp-lng" type="hidden" />
            <div id="emp-allowed-locations-wrap" class="telework-locations-box" style="display:none;">
              <p class="telework-locations-note">Ajoutez ici les adresses autorisees en plus de l'adresse principale.</p>
              <label style="margin-bottom:0.35rem;">Adresses autorisees</label>
              <div id="emp-allowed-locations-list" style="display:grid; gap:0.5rem;"></div>
              <button type="button" id="btn-add-allowed-location" class="btn-edit" style="margin-top:0.6rem;">+ Ajouter une adresse autorisee</button>
            </div>
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="emp-vacation-days">Jours de conges/an</label>
            <input id="emp-vacation-days" type="number" min="0" max="60" value="25" />
          </div>
        </div>
        <button type="submit" class="btn-in" style="margin-top: 1rem;">Enregistrer</button>
      </form>

      <h3 style="margin-top: 2rem;">Liste des collaborateurs</h3>
      <div id="employees-list"></div>
    </div>

    <div id="departments" class="tab-content panel">
      <h2>Gestion des départements</h2>
      <div class="department-layout">
        <div>
          <h3 style="margin-top: 0;">Nouveau département</h3>
          <form id="department-form" class="form-group">
            <label for="department-name">Nouveau departement</label>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
              <input id="department-name" type="text" placeholder="Ex: Production" required style="flex: 1; min-width: 180px;" />
              <select id="department-manager" style="min-width: 220px;">
                <option value="">Aucun responsable</option>
              </select>
              <button type="submit" class="btn-in">Ajouter</button>
            </div>
          </form>
          <p style="color: var(--ink-soft); font-size: 0.9rem; margin: 0;">
            Creez les rayons par departement ici, puis choisissez <strong>Departement + Rayon</strong> dans le formulaire collaborateur.
          </p>
        </div>
        <div>
          <h3 style="margin-top: 0;">Liste des departements</h3>
          <div id="departments-list">
            <p style="color: var(--ink-soft);">Chargement des departements...</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal gestion d'acces employe -->
    <div id="access-modal" class="modal-overlay">
      <div class="modal-box">
        <button class="modal-close" id="access-modal-close">&times;</button>
        <h3 id="access-modal-title">Acces employe</h3>
        <div id="access-modal-body"></div>
      </div>
    </div>
    <!-- Onglet Absences -->
    <div id="absences" class="tab-content panel">
      <h2>Gestion des absences</h2>
      <form id="absence-form" class="form-group">
        <div class="admin-two-col-grid">
          <div class="form-group">
            <label for="abs-employee">Collaborateur</label>
            <select id="abs-employee" required></select>
          </div>
          <div class="form-group">
            <label for="abs-type">Type</label>
            <select id="abs-type" required>
              <option value="sick_paid">Maladie payee</option>
              <option value="vacation_paid">Conge paye</option>
              <option value="training_paid">Formation</option>
              <option value="authorized_paid">Absence autorisee payee</option>
              <option value="unpaid_leave">Absence non payee</option>
            </select>
          </div>
          <div class="form-group">
            <label for="abs-start">Date debut</label>
            <input id="abs-start" type="date" required />
          </div>
          <div class="form-group">
            <label for="abs-end">Date fin</label>
            <input id="abs-end" type="date" required />
          </div>
        </div>
        <div class="form-group">
          <label for="abs-reason">Motif (optionnel)</label>
          <textarea id="abs-reason" placeholder="Certificat medical, etc."></textarea>
        </div>
        <p style="margin:0 0 0.8rem; color: var(--ink-soft); font-size:0.88rem;">La valorisation paie sera appliquee automatiquement selon le type choisi.</p>
        <button type="submit" class="btn-in">Ajouter absence</button>
      </form>

      <h3 style="margin-top: 2rem;">Absences en cours</h3>
      <div id="absences-list"></div>
    </div>

    <!-- Onglet Horaires -->
    <div id="hours" class="tab-content panel">
      <h2>Horaires prevus par collaborateur</h2>
      <div class="hours-section-switch">
        <button type="button" id="hours-show-editor" class="hours-section-btn active">✍️ Encodage / modification</button>
        <button type="button" id="hours-show-visual" class="hours-section-btn">📊 Consultation visuelle</button>
      </div>

      <div id="hours-editor-block">
      <div class="admin-top-grid">
        <div class="form-group">
          <label for="hours-employee">Collaborateur</label>
          <select id="hours-employee"></select>
        </div>
        <div class="form-group">
          <label for="hours-apply-to">Portee</label>
          <select id="hours-apply-to">
            <option value="default">Horaire de reference (chaque semaine)</option>
            <option value="week">Semaine specifique</option>
          </select>
        </div>
        <div class="form-group" id="hours-week-wrap" style="display:none;">
          <label for="hours-week-start">Semaine du (lundi)</label>
          <input id="hours-week-start" type="date" />
        </div>
      </div>

      <div class="admin-top-grid" style="margin-top: 0.5rem;">
        <div class="form-group">
          <label for="hours-mode">Mode d'encodage</label>
          <select id="hours-mode">
            <option value="reference">Horaire de reference (heure debut/fin)</option>
            <option value="daily">Nombre d'heures par jour</option>
            <option value="weekly">Nombre d'heures par semaine</option>
          </select>
        </div>
      </div>

      <div id="hours-reference" style="display:none; margin-top: 1rem; border:1px solid var(--line); border-radius: 10px; padding: 0.9rem;">
        <h3 style="margin-top:0; font-size:1rem;">Horaire de reference</h3>
        <p style="margin: 0 0 0.7rem; color: var(--ink-soft); font-size: 0.86rem;">Temps de pause par jour: 60 min par defaut (modifiable par jour).</p>
        <div style="display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
          <div class="form-group" style="margin:0;" id="ref-recurrence-wrap">
            <label for="ref-recurrence-interval">Recurrence</label>
            <select id="ref-recurrence-interval">
              <option value="1">Toutes les semaines</option>
              <option value="2">Toutes les 2 semaines</option>
              <option value="3">Toutes les 3 semaines</option>
            </select>
          </div>
          <div class="form-group" style="margin:0; display:none;" id="ref-recurrence-slot-wrap">
            <label for="ref-recurrence-slot">Semaine du cycle</label>
            <select id="ref-recurrence-slot">
              <option value="1">Semaine 1</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-top: 0.8rem;">
          <label>Encodage jour par jour</label>
          <div id="hours-reference-grid" class="ref-hours-grid"></div>
        </div>
      </div>

      <div id="hours-daily" style="margin-top: 1rem;">
        <div id="hours-grid" style="display: grid; gap: 0.5rem;"></div>
      </div>

      <div id="hours-weekly" style="display:none; margin-top: 1rem; border:1px solid var(--line); border-radius: 10px; padding: 0.9rem;">
        <h3 style="margin-top:0; font-size:1rem;">Volume hebdomadaire</h3>
        <div style="display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
          <div class="form-group" style="margin:0;">
            <label for="weekly-hours-total">Heures a prester par semaine</label>
            <input id="weekly-hours-total" type="number" min="0" max="80" step="0.5" value="40" />
          </div>
        </div>
        <div class="form-group" style="margin-top: 0.8rem;">
          <label>Repartir sur les jours</label>
          <div style="display:flex; gap:0.8rem; flex-wrap:wrap;">
            <label><input type="checkbox" class="weekly-day" value="1" checked /> Lun</label>
            <label><input type="checkbox" class="weekly-day" value="2" checked /> Mar</label>
            <label><input type="checkbox" class="weekly-day" value="3" checked /> Mer</label>
            <label><input type="checkbox" class="weekly-day" value="4" checked /> Jeu</label>
            <label><input type="checkbox" class="weekly-day" value="5" checked /> Ven</label>
            <label><input type="checkbox" class="weekly-day" value="6" /> Sam</label>
            <label><input type="checkbox" class="weekly-day" value="0" /> Dim</label>
          </div>
        </div>
      </div>
        <div id="hours-summary" style="margin-top: 1.5rem; padding: 1.2rem; background: var(--surface-2); border-radius: 10px; border-left: 4px solid var(--accent); display:none;">
          <h3 style="margin-top:0; font-size:0.95rem; color: var(--accent);">📊 Récapitulatif horaire</h3>
          <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-top: 0.8rem;">
            <div>
              <strong style="color: var(--text-muted);">Total heures</strong>
              <div id="hours-summary-total" style="font-size: 1.4rem; font-weight: 700; color: var(--accent);">0 h</div>
            </div>
            <div>
              <strong style="color: var(--text-muted);">Jours travaillés</strong>
              <div id="hours-summary-workdays" style="font-size: 1.4rem; font-weight: 700; color: var(--accent);">0</div>
            </div>
            <div>
              <strong style="color: var(--text-muted);">Moyenne par jour</strong>
              <div id="hours-summary-avg" style="font-size: 1.4rem; font-weight: 700; color: var(--accent);">0 h</div>
            </div>
          </div>
        </div>
      <button id="btn-save-hours" class="btn-in" style="margin-top: 1rem;">Enregistrer horaires</button>
      </div>

      <div id="hours-visual-block" class="hours-visual-block" style="display:none;">
        <h3 class="hours-visual-title">Consultation visuelle des horaires</h3>
        <p class="hours-visual-subtitle">Consulte l'equilibre des plages horaires par collaborateur ou par departement.</p>

        <div class="admin-top-grid">
          <div class="form-group">
            <label for="hours-view-scope">Vue</label>
            <select id="hours-view-scope">
              <option value="employee">Par collaborateur</option>
              <option value="department">Par departement</option>
            </select>
          </div>
          <div class="form-group" id="hours-view-employee-wrap">
            <label for="hours-view-employee">Collaborateur</label>
            <select id="hours-view-employee"></select>
          </div>
          <div class="form-group" id="hours-view-department-wrap" style="display:none;">
            <label for="hours-view-department">Departement</label>
            <select id="hours-view-department"></select>
          </div>
          <div class="form-group">
            <label for="hours-view-apply-to">Portee</label>
            <select id="hours-view-apply-to">
              <option value="default">Horaire de reference</option>
              <option value="week">Semaine specifique</option>
            </select>
          </div>
          <div class="form-group" id="hours-view-week-wrap" style="display:none;">
            <label for="hours-view-week-start">Semaine du (lundi)</label>
            <input id="hours-view-week-start" type="date" />
          </div>
        </div>

        <button type="button" id="btn-print-hours" class="btn-edit" style="margin-top: 0.2rem;">🖨️ Imprimer la semaine</button>
        <button type="button" id="btn-clean-hours-duplicates" class="btn-delete" style="margin-top: 0.2rem;">🧹 Nettoyer les doublons horaires</button>

        <div id="hours-balance-summary" class="hours-balance-summary"></div>
        <div id="hours-visual-container"></div>
      </div>
    </div>

    <div id="payroll" class="tab-content panel">
      <h2>Paie, heures et cloture</h2>
      <p style="color: var(--ink-soft); margin-top: 0;">Exporte la paie, consulte les heures payables, les absences valorisees et verrouille les periodes finalisees.</p>

      <div class="admin-top-grid">
        <div class="form-group">
          <label for="payroll-period">Periode</label>
          <input id="payroll-period" type="month" />
        </div>
        <div class="form-group">
          <label for="payroll-department">Departement</label>
          <select id="payroll-department">
            <option value="">Tous les departements</option>
          </select>
        </div>
        <div class="form-group">
          <label for="payroll-employee">Collaborateur</label>
          <select id="payroll-employee">
            <option value="">Tous les collaborateurs</option>
          </select>
        </div>
      </div>

      <div style="display:flex; gap:0.6rem; flex-wrap:wrap; margin: 0.8rem 0 1rem;">
        <button type="button" id="btn-payroll-export" class="btn-edit">⬇️ Export comptable CSV</button>
        <button type="button" id="btn-payroll-close" class="btn-delete">🔒 Cloturer la periode</button>
        <button type="button" id="btn-payroll-reopen" class="btn-access">🔓 Reouvrir la periode</button>
        <button type="button" id="btn-payroll-refresh" class="btn-in">↺ Actualiser</button>
      </div>

      <div id="payroll-overtime-summary" class="hours-balance-summary"></div>
      <div id="payroll-overtime-table"></div>

      <div class="admin-two-col-grid" style="margin-top: 1.4rem; align-items:start;">
        <div>
          <h3 style="margin-top:0;">Periodes cloturees</h3>
          <div id="payroll-closures-list" class="hours-empty">Chargement...</div>
        </div>
        <div>
          <h3 style="margin-top:0;">Journal d'audit recent</h3>
          <div id="payroll-audit-list" class="hours-empty">Chargement...</div>
        </div>
      </div>
    </div>

    <!-- Onglet Demandes de congés -->
    <div id="vacation-requests" class="tab-content panel">
      <h2>Demandes de congés</h2>
      <style>
        .vac-filter { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .vac-filter select { padding: 0.6rem; border: 1px solid var(--line); border-radius: 6px; font: inherit; }
        .vac-balance-panel {
          padding: 1rem;
          border: 1px solid var(--line);
          border-radius: 10px;
          margin-bottom: 1.2rem;
          background: var(--surface-2);
        }
        .vac-balance-note {
          margin: 0 0 0.9rem;
          color: var(--ink-soft);
          font-size: 0.9rem;
        }
        .vac-request-item {
          padding: 1rem;
          border: 1px solid var(--line);
          border-radius: 8px;
          margin-bottom: 0.8rem;
          background: var(--surface-2);
        }
        .vac-request-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; flex-wrap: wrap; gap: 0.5rem; }
        .vac-request-name { font-weight: 600; }
        .vac-request-dates { color: var(--ink-soft); font-size: 0.9rem; }
        .vac-request-reason { color: var(--ink-soft); font-size: 0.9rem; margin: 0.5rem 0; }
        .vac-request-comment { color: var(--ink-soft); font-size: 0.85rem; font-style: italic; margin: 0.5rem 0; }
        .vac-status { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.8rem; font-weight: 600; }
        .vac-status.pending { background: rgba(248, 113, 113, 0.15); color: var(--warn); }
        .vac-status.approved { background: rgba(52, 211, 153, 0.15); color: var(--ok); }
        .vac-status.rejected { background: rgba(248, 113, 113, 0.2); color: #f87171; }
        .vac-actions { display: flex; gap: 0.5rem; margin-top: 0.8rem; flex-wrap: wrap; }
        .vac-actions button { padding: 0.5rem 1rem; font-size: 0.85rem; border: 0; border-radius: 6px; cursor: pointer; }
        .vac-actions .approve { background: var(--ok); color: #0a0a0a; }
        .vac-actions .reject { background: var(--warn); color: #0a0a0a; }
        @media (max-width: 560px) {
          .vac-filter { flex-direction: column; }
          .vac-filter select { width: 100%; }
          .vac-request-header { flex-direction: column; align-items: flex-start; }
        }
      </style>
      <div class="vac-filter">
        <select id="vac-employee-filter">
          <option value="">Tous les collaborateurs</option>
        </select>
        <select id="vac-status-filter">
          <option value="">Tous les statuts</option>
          <option value="pending">En attente</option>
          <option value="approved">Approuvé</option>
          <option value="rejected">Rejeté</option>
        </select>
      </div>
      <div class="vac-balance-panel">
        <p class="vac-balance-note">Le collaborateur sélectionné ci-dessus sert à consulter le solde de congés, le cumul d'heures supplémentaires et à appliquer un ajustement manuel si nécessaire.</p>
        <div id="vac-balance-summary" class="hours-balance-summary"></div>
        <div class="admin-two-col-grid" style="margin-top: 1rem;">
          <div class="form-group" style="margin: 0;">
            <label for="vac-adjustment-days">Ajustement congés (jours)</label>
            <input id="vac-adjustment-days" type="number" step="0.5" value="0" />
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="vac-adjustment-hours">Ajustement heures supp. (heures)</label>
            <input id="vac-adjustment-hours" type="number" step="0.25" value="0" />
          </div>
        </div>
        <button type="button" id="btn-save-vac-balances" class="btn-in" style="margin-top: 1rem;">Enregistrer les ajustements RH</button>
      </div>
      <div id="vac-requests-list" style="min-height: 200px;">
        <p style="color: var(--ink-soft);">Chargement des demandes...</p>
      </div>
    </div>

    <div id="device-settings" class="tab-content panel">
      <h2>Configuration de la pointeuse</h2>
      <p style="color: var(--ink-soft); margin-top: 0;">Ces options sont lues automatiquement par la pointeuse ESP32.</p>

      <form id="device-settings-form" class="form-group">
        <div class="device-settings-grid">
          <div class="form-group" style="margin: 0;">
            <label for="cfg-site-name">Nom affiche en haut de l'ecran</label>
            <input id="cfg-site-name" type="text" maxlength="40" required />
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="cfg-display-message">Message d'accueil (ecran attente)</label>
            <input id="cfg-display-message" type="text" maxlength="60" required />
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="cfg-success-message">Message apres badge reconnu</label>
            <input id="cfg-success-message" type="text" maxlength="60" required />
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="cfg-led-enabled">LED actives</label>
            <select id="cfg-led-enabled">
              <option value="1">Oui</option>
              <option value="0">Non</option>
            </select>
          </div>
          <div class="form-group" style="margin: 0;">
            <label for="cfg-buzzer-enabled">Haut-parleur actif</label>
            <select id="cfg-buzzer-enabled">
              <option value="1">Oui</option>
              <option value="0">Non</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn-in" style="margin-top: 1rem;">Enregistrer la configuration pointeuse</button>
      </form>
    </div>

    <section id="toast" class="toast" role="status" aria-live="polite"></section>
  </main>

  <script>
    const els = {
      empForm: document.getElementById('employee-form'),
      empId: document.getElementById('emp-id'),
      empFirst: document.getElementById('emp-first'),
      empLast: document.getElementById('emp-last'),
      empBadge: document.getElementById('emp-badge'),
      empActive: document.getElementById('emp-active'),
      empDepartment: document.getElementById('emp-department'),
      empRayon: document.getElementById('emp-rayon'),
      employeesList: document.getElementById('employees-list'),
      departmentForm: document.getElementById('department-form'),
      departmentName: document.getElementById('department-name'),
      departmentManager: document.getElementById('department-manager'),
      departmentsList: document.getElementById('departments-list'),
      empAddress:     document.getElementById('emp-address'),
      empLat:         document.getElementById('emp-lat'),
      empLng:         document.getElementById('emp-lng'),
      empTeleworkEnabled: document.getElementById('emp-telework-enabled'),
      empAllowedLocationsWrap: document.getElementById('emp-allowed-locations-wrap'),
      empAllowedLocationsList: document.getElementById('emp-allowed-locations-list'),
      btnAddAllowedLocation: document.getElementById('btn-add-allowed-location'),
      empVacDays:     document.getElementById('emp-vacation-days'),
      absenceForm: document.getElementById('absence-form'),
      absEmployee: document.getElementById('abs-employee'),
      absType: document.getElementById('abs-type'),
      absStart: document.getElementById('abs-start'),
      absEnd: document.getElementById('abs-end'),
      absReason: document.getElementById('abs-reason'),
      absencesList: document.getElementById('absences-list'),
      hoursEmployee: document.getElementById('hours-employee'),
      hoursApplyTo: document.getElementById('hours-apply-to'),
      hoursWeekWrap: document.getElementById('hours-week-wrap'),
      hoursWeekStart: document.getElementById('hours-week-start'),
      hoursMode: document.getElementById('hours-mode'),
      hoursReference: document.getElementById('hours-reference'),
      hoursDaily: document.getElementById('hours-daily'),
      hoursWeekly: document.getElementById('hours-weekly'),
      refRecurrenceWrap: document.getElementById('ref-recurrence-wrap'),
      refRecurrenceInterval: document.getElementById('ref-recurrence-interval'),
      refRecurrenceSlotWrap: document.getElementById('ref-recurrence-slot-wrap'),
      refRecurrenceSlot: document.getElementById('ref-recurrence-slot'),
      hoursReferenceGrid: document.getElementById('hours-reference-grid'),
      weeklyHoursTotal: document.getElementById('weekly-hours-total'),
      hoursGrid: document.getElementById('hours-grid'),
      btnSaveHours: document.getElementById('btn-save-hours'),
      hoursShowEditor: document.getElementById('hours-show-editor'),
      hoursShowVisual: document.getElementById('hours-show-visual'),
      hoursEditorBlock: document.getElementById('hours-editor-block'),
      hoursVisualBlock: document.getElementById('hours-visual-block'),
      hoursSummary: document.getElementById('hours-summary'),
      hoursSummaryTotal: document.getElementById('hours-summary-total'),
      hoursSummaryWorkdays: document.getElementById('hours-summary-workdays'),
      hoursSummaryAvg: document.getElementById('hours-summary-avg'),
      hoursViewScope: document.getElementById('hours-view-scope'),
      hoursViewEmployeeWrap: document.getElementById('hours-view-employee-wrap'),
      hoursViewEmployee: document.getElementById('hours-view-employee'),
      hoursViewDepartmentWrap: document.getElementById('hours-view-department-wrap'),
      hoursViewDepartment: document.getElementById('hours-view-department'),
      hoursViewApplyTo: document.getElementById('hours-view-apply-to'),
      hoursViewWeekWrap: document.getElementById('hours-view-week-wrap'),
      hoursViewWeekStart: document.getElementById('hours-view-week-start'),
      btnPrintHours: document.getElementById('btn-print-hours'),
      btnCleanHoursDuplicates: document.getElementById('btn-clean-hours-duplicates'),
      hoursBalanceSummary: document.getElementById('hours-balance-summary'),
      hoursVisualContainer: document.getElementById('hours-visual-container'),
      payrollPeriod: document.getElementById('payroll-period'),
      payrollDepartment: document.getElementById('payroll-department'),
      payrollEmployee: document.getElementById('payroll-employee'),
      btnPayrollExport: document.getElementById('btn-payroll-export'),
      btnPayrollClose: document.getElementById('btn-payroll-close'),
      btnPayrollReopen: document.getElementById('btn-payroll-reopen'),
      btnPayrollRefresh: document.getElementById('btn-payroll-refresh'),
      payrollOvertimeSummary: document.getElementById('payroll-overtime-summary'),
      payrollOvertimeTable: document.getElementById('payroll-overtime-table'),
      payrollClosuresList: document.getElementById('payroll-closures-list'),
      payrollAuditList: document.getElementById('payroll-audit-list'),
      vacEmployeeFilter: document.getElementById('vac-employee-filter'),
      vacStatusFilter: document.getElementById('vac-status-filter'),
      vacBalanceSummary: document.getElementById('vac-balance-summary'),
      vacAdjustmentDays: document.getElementById('vac-adjustment-days'),
      vacAdjustmentHours: document.getElementById('vac-adjustment-hours'),
      btnSaveVacBalances: document.getElementById('btn-save-vac-balances'),
      vacRequestsList: document.getElementById('vac-requests-list'),
      deviceSettingsForm: document.getElementById('device-settings-form'),
      cfgSiteName: document.getElementById('cfg-site-name'),
      cfgDisplayMessage: document.getElementById('cfg-display-message'),
      cfgSuccessMessage: document.getElementById('cfg-success-message'),
      cfgLedEnabled: document.getElementById('cfg-led-enabled'),
      cfgBuzzerEnabled: document.getElementById('cfg-buzzer-enabled'),
      toast: document.getElementById('toast'),
    };

    let toastTimer = null;

    function showToast(msg, isError = false) {
      els.toast.textContent = msg;
      els.toast.style.background = isError ? '#7f2323' : '#1f2f29';
      els.toast.classList.add('show');
      if (toastTimer) clearTimeout(toastTimer);
      toastTimer = setTimeout(() => els.toast.classList.remove('show'), 2400);
    }

    async function api(path, opts = {}) {
      const res = await fetch(path, {
        headers: { 'Content-Type': 'application/json', ...opts.headers },
        ...opts,
      });
      const raw = await res.text();
      let data = {};
      try { data = raw ? JSON.parse(raw) : {}; } catch (_) {}
      if (!res.ok) {
        const fallback = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
        throw new Error(data.error || fallback || `Erreur serveur (${res.status})`);
      }
      return data;
    }

    // Collaborateurs
    let empCache = [];
    let departmentsCache = [];
    let rayonsByDepartment = {};
    const panelLoadState = {
      departments: false,
      absences: false,
      hours: false,
      payroll: false,
      'vacation-requests': false,
      'device-settings': false,
    };

    function getActiveTabId() {
      const active = document.querySelector('.tab-content.active');
      return active ? active.id : 'employees';
    }

    function toggleTeleworkLocationsVisibility() {
      els.empAllowedLocationsWrap.style.display = els.empTeleworkEnabled.checked ? 'block' : 'none';
    }

    function addAllowedLocationRow(location = {}) {
      const row = document.createElement('div');
      row.className = 'allowed-location-row';
      row.style.display = 'grid';
      row.style.gridTemplateColumns = '1fr auto';
      row.style.gap = '0.5rem';
      row.style.alignItems = 'center';

      const address = String(location.address || '');
      const lat = location.latitude ?? '';
      const lng = location.longitude ?? '';

      row.innerHTML = `
        <div>
          <input type="text" class="allowed-location-address" placeholder="Ex: Avenue Louise 123, Bruxelles" value="${escapeHtml(address)}" style="width:100%;" />
          <input type="hidden" class="allowed-location-lat" value="${lat}" />
          <input type="hidden" class="allowed-location-lng" value="${lng}" />
        </div>
        <button type="button" class="btn-delete allowed-location-remove">Supprimer</button>
      `;

      row.querySelector('.allowed-location-remove').addEventListener('click', () => row.remove());
      els.empAllowedLocationsList.appendChild(row);
    }

    function renderAllowedLocations(locations = []) {
      els.empAllowedLocationsList.innerHTML = '';
      if (Array.isArray(locations)) {
        locations.forEach(loc => addAllowedLocationRow(loc));
      }
    }

    function collectAllowedLocations() {
      return Array.from(els.empAllowedLocationsList.querySelectorAll('.allowed-location-row')).map(row => {
        const address = row.querySelector('.allowed-location-address')?.value?.trim() || '';
        const latRaw = row.querySelector('.allowed-location-lat')?.value ?? '';
        const lngRaw = row.querySelector('.allowed-location-lng')?.value ?? '';

        return {
          address,
          latitude: latRaw !== '' ? Number(latRaw) : null,
          longitude: lngRaw !== '' ? Number(lngRaw) : null,
        };
      }).filter(loc => loc.address !== '');
    }

    async function loadTabData(tabId, options = {}) {
      const force = Boolean(options.force);

      if (tabId === 'departments') {
        if (!force && panelLoadState.departments) return;
        await loadDepartments();
        return;
      }

      if (tabId === 'absences') {
        if (!force && panelLoadState.absences) return;
        await loadAbsences();
        return;
      }

      if (tabId === 'hours') {
        if (!force && panelLoadState.hours) return;
        await Promise.all([loadHoursForSelected(), loadHoursVisual()]);
        return;
      }

      if (tabId === 'payroll') {
        if (!force && panelLoadState.payroll) return;
        await loadPayrollDashboard();
        return;
      }

      if (tabId === 'vacation-requests') {
        if (!force && panelLoadState['vacation-requests']) return;
        await Promise.all([loadVacationRequests(), loadVacationBalanceSummary()]);
        return;
      }

      if (tabId === 'device-settings') {
        if (!force && panelLoadState['device-settings']) return;
        await loadDeviceSettings();
      }
    }

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const tabId = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(tabId).classList.add('active');

        if (tabId === 'departments') {
          renderDepartmentManagerOptions();
        }

        try {
          await loadTabData(tabId);
        } catch (e) {
          showToast(e.message, true);
        }
      });
    });

    function renderDepartmentOptions(preferredValue = '') {
      const wanted = preferredValue !== '' && preferredValue !== null && preferredValue !== undefined
        ? String(preferredValue)
        : String(els.empDepartment.value || '');

      els.empDepartment.innerHTML = [
        '<option value="">Aucun departement</option>',
        ...departmentsCache.map(d => `<option value="${d.id}">${d.name}</option>`),
      ].join('');

      if (wanted && departmentsCache.some(d => String(d.id) === wanted)) {
        els.empDepartment.value = wanted;
      } else {
        els.empDepartment.value = '';
      }

      renderRayonOptions();
    }

    function renderRayonOptions(preferredValue = '') {
      const departmentId = Number(els.empDepartment.value || 0);
      const rayons = departmentId > 0 ? (rayonsByDepartment[departmentId] || []) : [];
      const wanted = preferredValue !== '' && preferredValue !== null && preferredValue !== undefined
        ? String(preferredValue)
        : String(els.empRayon.value || '');

      els.empRayon.innerHTML = [
        '<option value="">Aucun rayon</option>',
        ...rayons.map(r => `<option value="${r.id}">${escapeHtml(r.name)}</option>`),
      ].join('');

      if (wanted && rayons.some(r => String(r.id) === wanted)) {
        els.empRayon.value = wanted;
      } else {
        els.empRayon.value = '';
      }
    }

    function renderDepartmentManagerOptions(preferredValue = '') {
      const wanted = preferredValue !== '' && preferredValue !== null && preferredValue !== undefined
        ? String(preferredValue)
        : String(els.departmentManager.value || '');

      els.departmentManager.innerHTML = [
        '<option value="">Aucun responsable</option>',
        ...empCache.map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`),
      ].join('');

      if (wanted && empCache.some(e => String(e.id) === wanted)) {
        els.departmentManager.value = wanted;
      } else {
        els.departmentManager.value = '';
      }
    }

    function renderHoursViewDepartmentOptions(preferredValue = '') {
      const wanted = preferredValue !== '' && preferredValue !== null && preferredValue !== undefined
        ? String(preferredValue)
        : String(els.hoursViewDepartment.value || '');

      els.hoursViewDepartment.innerHTML = [
        '<option value="">-- Choisir un departement --</option>',
        ...departmentsCache.map(d => `<option value="${d.id}">${d.name}</option>`),
      ].join('');

      if (wanted && departmentsCache.some(d => String(d.id) === wanted)) {
        els.hoursViewDepartment.value = wanted;
      }
    }

    function renderHoursViewEmployeeOptions(preferredValue = '') {
      const wanted = preferredValue !== '' && preferredValue !== null && preferredValue !== undefined
        ? String(preferredValue)
        : String(els.hoursViewEmployee.value || '');

      els.hoursViewEmployee.innerHTML = [
        '<option value="">-- Choisir un collaborateur --</option>',
        ...empCache.map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`),
      ].join('');

      if (wanted && empCache.some(e => String(e.id) === wanted)) {
        els.hoursViewEmployee.value = wanted;
      }
    }

    function renderPayrollDepartmentOptions(preferredValue = '') {
      const wanted = preferredValue !== '' && preferredValue !== null && preferredValue !== undefined
        ? String(preferredValue)
        : String(els.payrollDepartment.value || '');

      els.payrollDepartment.innerHTML = [
        '<option value="">Tous les departements</option>',
        ...departmentsCache.map(d => `<option value="${d.id}">${d.name}</option>`),
      ].join('');

      if (wanted && departmentsCache.some(d => String(d.id) === wanted)) {
        els.payrollDepartment.value = wanted;
      }
    }

    function renderPayrollEmployeeOptions(preferredValue = '') {
      const wanted = preferredValue !== '' && preferredValue !== null && preferredValue !== undefined
        ? String(preferredValue)
        : String(els.payrollEmployee.value || '');

      els.payrollEmployee.innerHTML = [
        '<option value="">Tous les collaborateurs</option>',
        ...empCache.map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`),
      ].join('');

      if (wanted && empCache.some(e => String(e.id) === wanted)) {
        els.payrollEmployee.value = wanted;
      }
    }

    function renderVacationEmployeeOptions(selectedValue = '') {
      const wanted = selectedValue !== '' && selectedValue !== null && selectedValue !== undefined
        ? String(selectedValue)
        : String(els.vacEmployeeFilter.value || '');

      const options = empCache.map(e => `<option value="${e.id}">${e.first_name} ${e.last_name}</option>`).join('');
      els.vacEmployeeFilter.innerHTML = '<option value="">Tous les collaborateurs</option>' + options;

      if (wanted && empCache.some(e => String(e.id) === wanted)) {
        els.vacEmployeeFilter.value = wanted;
      } else {
        els.vacEmployeeFilter.value = '';
      }
    }

    async function loadDepartments(preferredValue = '') {
      try {
        const prevHoursViewDepartment = els.hoursViewDepartment.value;
        const prevPayrollDepartment = els.payrollDepartment.value;
        const data = await api('api/departments.php?action=list');
        departmentsCache = Array.isArray(data.departments) ? data.departments : [];
        rayonsByDepartment = departmentsCache.reduce((acc, dep) => {
          const depId = Number(dep.id || 0);
          if (depId > 0) {
            acc[depId] = Array.isArray(dep.rayons) ? dep.rayons : [];
          }
          return acc;
        }, {});
        renderDepartmentOptions(preferredValue);
        renderHoursViewDepartmentOptions(prevHoursViewDepartment);
        renderPayrollDepartmentOptions(prevPayrollDepartment);

        els.departmentsList.innerHTML = departmentsCache.length
          ? departmentsCache.map(d => `
              <div class="department-card">
                <div>
                  <strong>${d.name}</strong><br/>
                  <small>${Number(d.employee_count) || 0} collaborateur(s)</small><br/>
                  <label style="display:block; margin-top:0.4rem;">Responsable</label>
                  <div style="display:flex; gap:0.4rem; flex-wrap:wrap; margin-top:0.2rem;">
                    <select id="dept-manager-${d.id}" style="min-width:200px;">
                      <option value="">Aucun responsable</option>
                      ${empCache.map(e => `<option value="${e.id}" ${String(d.manager_employee_id || '') === String(e.id) ? 'selected' : ''}>${e.first_name} ${e.last_name}</option>`).join('')}
                    </select>
                    <button class="btn-edit" onclick="updateDepartmentManager(${d.id})">Affecter</button>
                  </div>
                  <label style="display:block; margin-top:0.55rem;">Rayons</label>
                  <div style="display:flex; gap:0.4rem; flex-wrap:wrap; margin-top:0.2rem;">
                    <input id="dept-rayon-name-${d.id}" type="text" placeholder="Ex: Fruits et legumes" style="min-width:200px; flex:1;" />
                    <button class="btn-in" onclick="addDepartmentRayon(${d.id})">Ajouter rayon</button>
                  </div>
                  <div style="display:flex; gap:0.35rem; flex-wrap:wrap; margin-top:0.35rem;">
                    ${(Array.isArray(d.rayons) ? d.rayons : []).map(r => `
                      <span style="display:inline-flex; align-items:center; gap:0.3rem; border:1px solid var(--line); border-radius:999px; padding:0.18rem 0.5rem;">
                        <small>${escapeHtml(r.name)}</small>
                        <button class="btn-delete" style="padding:0.1rem 0.45rem; line-height:1;" onclick="deleteDepartmentRayon(${r.id})">×</button>
                      </span>
                    `).join('') || '<small style="color:var(--ink-soft);">Aucun rayon</small>'}
                  </div>
                </div>
                <button class="btn-delete" onclick="deleteDepartment(${d.id})">Supprimer</button>
              </div>
            `).join('')
          : '<p style="color: var(--ink-soft);">Aucun departement pour le moment.</p>';
        panelLoadState.departments = true;
      } catch (e) { showToast(e.message, true); }
    }

    async function loadEmployees(options = {}) {
      const { refreshPanels = true } = options;
      try {
        const prevAbsValue = els.absEmployee.value;
        const prevHoursValue = els.hoursEmployee.value;
        const prevHoursViewEmployee = els.hoursViewEmployee.value;
        const prevPayrollEmployee = els.payrollEmployee.value;
        const prevVacationFilter = els.vacEmployeeFilter.value;
        const currentEditId = els.empId.value;

        const data = await api('api/employees.php?action=list');
        empCache = data.employees;
        els.employeesList.innerHTML = data.employees.map(e => `
          <div class="employee-row">
            <div>
              <strong>${e.first_name} ${e.last_name}</strong><br/>
              <small>${e.badge_id}</small>
              ${e.department_name ? `<br/><span class="department-pill">🏷️ ${e.department_name}</span>` : '<br/><small style="color:var(--ink-soft)">Aucun departement</small>'}
              ${e.rayon ? `<br/><small style="color:var(--ink-soft)">Rayon: ${escapeHtml(e.rayon)}</small>` : ''}
              ${e.address ? `<br/><small style="color:var(--ink-soft)">📍 ${e.address}</small>` : ''}
              ${Number(e.telework_enabled || 0) === 1 ? '<br/><small style="color:var(--ok)">🏠 Teletravail autorise</small>' : ''}
              <br/><small style="color:${e.login_username ? 'var(--ok)' : 'var(--warn)'}">
                🔑 ${e.login_username ? e.login_username : "Pas d'acces"}
              </small>
            </div>
            <div>
              <button class="btn-edit" onclick="editEmployee(${e.id})">Modifier</button>
              <button class="btn-access" onclick="openAccess(${e.id})">🔑 Acces</button>
              <button class="btn-delete" onclick="deleteEmployee(${e.id})">Supprimer</button>
            </div>
          </div>
        `).join('');

        const optionsHtml = `<option value="">-- Choisir --</option>` + data.employees.map(e => `
          <option value="${e.id}">${e.first_name} ${e.last_name}</option>
        `).join('');

        els.absEmployee.innerHTML = optionsHtml;
        els.hoursEmployee.innerHTML = optionsHtml;
        renderHoursViewEmployeeOptions(prevHoursViewEmployee);
        renderPayrollEmployeeOptions(prevPayrollEmployee);
        renderVacationEmployeeOptions(prevVacationFilter);

        const existingIds = new Set(data.employees.map(e => String(e.id)));
        const preferredId = currentEditId || prevHoursValue;
        const currentEmployee = data.employees.find(e => String(e.id) === String(currentEditId));
        renderDepartmentOptions(currentEmployee ? currentEmployee.department_id : '');
        renderRayonOptions(currentEmployee ? currentEmployee.rayon_id : '');
        renderDepartmentManagerOptions();

        if (existingIds.has(prevAbsValue)) {
          els.absEmployee.value = prevAbsValue;
        }
        if (existingIds.has(preferredId)) {
          els.hoursEmployee.value = preferredId;
        }

        if (!els.hoursViewEmployee.value && empCache.length) {
          els.hoursViewEmployee.value = String(empCache[0].id);
        }

        if (!els.hoursViewDepartment.value && departmentsCache.length) {
          els.hoursViewDepartment.value = String(departmentsCache[0].id);
        }

        if (refreshPanels) {
          const activeTabId = getActiveTabId();
          if (activeTabId === 'hours') {
            loadHoursForSelected();
            loadHoursVisual();
          } else if (activeTabId === 'payroll') {
            loadPayrollDashboard();
          } else if (activeTabId === 'vacation-requests') {
            loadVacationBalanceSummary();
            loadVacationRequests();
          }
        }
      } catch (e) { showToast(e.message, true); }
    }

    window.editEmployee = (id) => {
      const emp = empCache.find(e => e.id === id);
      if (!emp) return;
      els.empId.value     = emp.id;
      els.empFirst.value  = emp.first_name;
      els.empLast.value   = emp.last_name;
      els.empBadge.value  = emp.badge_id;
      els.empActive.value = emp.active;
      els.empDepartment.value = emp.department_id ? String(emp.department_id) : '';
      const rayons = rayonsByDepartment[Number(emp.department_id || 0)] || [];
      const rayonMatch = rayons.find(r => String(r.name || '').toLowerCase() === String(emp.rayon || '').toLowerCase());
      const preferredRayonId = emp.rayon_id ? String(emp.rayon_id) : (rayonMatch ? String(rayonMatch.id) : '');
      renderRayonOptions(preferredRayonId);
      els.empAddress.value = emp.address || '';
      els.empLat.value     = emp.latitude ?? '';
      els.empLng.value     = emp.longitude ?? '';
      els.empTeleworkEnabled.checked = Number(emp.telework_enabled || 0) === 1;
      renderAllowedLocations(Array.isArray(emp.allowed_locations) ? emp.allowed_locations : []);
      toggleTeleworkLocationsVisibility();
      els.empVacDays.value = emp.vacation_days || 25;
      document.getElementById('employees').scrollIntoView({ behavior: 'smooth' });
      els.empFirst.focus();
    };

    window.deleteEmployee = async (id) => {
      if (!confirm('Confirmer la suppression ?')) return;
      try {
        await api('api/employees.php?action=delete&id=' + id, { method: 'POST' });
        showToast('Employe supprime');
        await loadEmployees();
        await loadDepartments();
      } catch (e) { showToast(e.message, true); }
    };

    els.empForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      try {
        await api('api/employees.php?action=save', {
          method: 'POST',
          body: JSON.stringify({
            id: els.empId.value || null,
            first_name: els.empFirst.value,
            last_name: els.empLast.value,
            badge_id: els.empBadge.value,
            active: els.empActive.value,
            department_id: els.empDepartment.value || null,
            rayon_id: els.empRayon.value || null,
            address: els.empAddress.value,
            latitude: els.empLat.value !== '' ? parseFloat(els.empLat.value) : null,
            longitude: els.empLng.value !== '' ? parseFloat(els.empLng.value) : null,
            geo_radius: 300,
            telework_enabled: els.empTeleworkEnabled.checked ? 1 : 0,
            allowed_locations: collectAllowedLocations(),
            vacation_days: parseInt(els.empVacDays.value, 10) || 25,
          }),
        });
        showToast('Collaborateur enregistre');
        els.empForm.reset();
        els.empDepartment.value = '';
        renderRayonOptions('');
        els.empTeleworkEnabled.checked = false;
        renderAllowedLocations([]);
        toggleTeleworkLocationsVisibility();
        els.empVacDays.value = 25;
        await loadEmployees();
        await loadDepartments();
      } catch (e) { showToast(e.message, true); }
    });

    window.deleteDepartment = async (id) => {
      const dept = departmentsCache.find(d => Number(d.id) === Number(id));
      const label = dept ? `le departement "${dept.name}"` : 'ce departement';
      if (!confirm(`Supprimer ${label} ? Les collaborateurs resteront actifs mais sans departement.`)) return;
      try {
        await api('api/departments.php?action=delete&id=' + id, { method: 'POST' });
        showToast('Departement supprime');
        await loadEmployees({ refreshPanels: false });
        await loadDepartments();
      } catch (e) { showToast(e.message, true); }
    };

    els.departmentForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const name = els.departmentName.value.trim();
      const managerEmployeeId = els.departmentManager.value ? Number(els.departmentManager.value) : null;
      if (!name) {
        showToast('Indiquez un nom de departement.', true);
        return;
      }
      try {
        await api('api/departments.php?action=create', {
          method: 'POST',
          body: JSON.stringify({ name, manager_employee_id: managerEmployeeId }),
        });
        showToast('Departement ajoute');
        els.departmentForm.reset();
        await loadEmployees({ refreshPanels: false });
        await loadDepartments();
      } catch (e) { showToast(e.message, true); }
    });

    window.updateDepartmentManager = async (departmentId) => {
      const select = document.getElementById(`dept-manager-${departmentId}`);
      if (!select) {
        showToast('Selection du responsable introuvable.', true);
        return;
      }

      const managerEmployeeId = select.value ? Number(select.value) : null;
      try {
        await api('api/departments.php?action=set_manager&id=' + encodeURIComponent(departmentId), {
          method: 'POST',
          body: JSON.stringify({ manager_employee_id: managerEmployeeId }),
        });
        showToast('Responsable mis a jour');
        await loadDepartments();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    window.addDepartmentRayon = async (departmentId) => {
      const input = document.getElementById(`dept-rayon-name-${departmentId}`);
      if (!input) {
        showToast('Champ rayon introuvable.', true);
        return;
      }
      const name = input.value.trim();
      if (!name) {
        showToast('Indiquez un nom de rayon.', true);
        return;
      }

      try {
        await api('api/departments.php?action=create_rayon', {
          method: 'POST',
          body: JSON.stringify({ department_id: Number(departmentId), name }),
        });
        showToast('Rayon ajoute');
        input.value = '';
        await loadDepartments();
        renderRayonOptions();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    window.deleteDepartmentRayon = async (rayonId) => {
      if (!confirm('Supprimer ce rayon ?')) return;
      try {
        await api('api/departments.php?action=delete_rayon&id=' + encodeURIComponent(rayonId), { method: 'POST' });
        showToast('Rayon supprime');
        await loadDepartments();
        renderRayonOptions();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    els.empDepartment.addEventListener('change', () => renderRayonOptions(''));

    // Absences
    async function loadAbsences() {
      try {
        const data = await api('api/absences.php?action=list');
        els.absencesList.innerHTML = data.absences.map(a => `
          <div style="padding: 0.8rem; border: 1px solid var(--line); margin-bottom: 0.5rem; border-radius: 8px;">
            <strong>${escapeHtml(a.employee_name)}</strong> - ${escapeHtml(a.type_label || a.type)}<br/>
            <small>${a.start_date} au ${a.end_date}</small><br/>
            <small style="color:${a.is_paid ? 'var(--ok)' : 'var(--warn)'};">Code paie: ${escapeHtml(a.export_code || '')} ${a.is_paid ? '• paye' : '• non paye'}</small><br/>
            ${a.reason ? '<em>' + escapeHtml(a.reason) + '</em>' : ''}
            <button class="btn-delete" onclick="deleteAbsence(${a.id})" style="float: right;">Supprimer</button>
          </div>
        `).join('');
        panelLoadState.absences = true;
      } catch (e) { showToast(e.message, true); }
    }

    window.deleteAbsence = async (id) => {
      if (!confirm('Confirmer ?')) return;
      try {
        await api('api/absences.php?action=delete&id=' + id, { method: 'POST' });
        showToast('Absence supprimee');
        loadAbsences();
      } catch (e) { showToast(e.message, true); }
    };

    els.absenceForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      try {
        await api('api/absences.php', {
          method: 'POST',
          body: JSON.stringify({
            employee_id: els.absEmployee.value,
            payroll_code: els.absType.value,
            start_date: els.absStart.value,
            end_date: els.absEnd.value,
            reason: els.absReason.value,
          }),
        });
        showToast('Absence enregistree');
        els.absenceForm.reset();
        loadAbsences();
      } catch (e) { showToast(e.message, true); }
    });

    // Horaires
    const dayLabels = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

    function toMondayISO(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr + 'T00:00:00');
      if (Number.isNaN(d.getTime())) return '';
      const day = d.getDay();
      const diff = day === 0 ? -6 : 1 - day;
      d.setDate(d.getDate() + diff);
      return d.toISOString().slice(0, 10);
    }

    function ensureWeekDefault() {
      if (!els.hoursWeekStart.value) {
        els.hoursWeekStart.value = toMondayISO(new Date().toISOString().slice(0, 10));
      }
    }

    function renderHoursGrid(rows = []) {
      els.hoursGrid.innerHTML = dayLabels.map((day, idx) => {
        const existing = rows.find(h => Number(h.day_of_week) === idx);
        const val = existing ? Number(existing.hours) : 0;
        return `
          <div class="form-group">
            <label>${day}</label>
            <input type="number" data-day="${idx}" min="0" max="24" step="0.25" value="${Number.isFinite(val) ? val : 0}" />
          </div>
        `;
      }).join('');

      calcAndRenderHoursSummary();
    }

    function renderReferenceHoursGrid(rows = []) {
      const order = [1, 2, 3, 4, 5, 6, 0];
      els.hoursReferenceGrid.innerHTML = order.map((day) => {
        const existing = rows.find(h => Number(h.day_of_week) === day);
        const checked = !!existing;
        const start = existing?.start_time ? String(existing.start_time).slice(0, 5) : '08:00';
        const end = existing?.end_time ? String(existing.end_time).slice(0, 5) : '17:00';
        const breakMinutes = Number(existing?.break_minutes ?? 60);

        return `
          <div class="ref-hours-row">
            <label class="ref-hours-day">
              <input type="checkbox" class="ref-day-enabled" data-day="${day}" ${checked ? 'checked' : ''} />
              <span>${dayLabels[day]}</span>
            </label>
            <input type="time" class="ref-day-start ref-hours-time" data-day="${day}" value="${start}" />
            <input type="time" class="ref-day-end ref-hours-time" data-day="${day}" value="${end}" />
            <input type="number" class="ref-day-break ref-hours-time" data-day="${day}" min="0" max="720" step="5" value="${Number.isFinite(breakMinutes) ? breakMinutes : 60}" title="Pause (minutes)" />
          </div>
        `;
      }).join('');

      calcAndRenderHoursSummary();
    }

    function parseTimeToMinutes(value) {
      if (!value || !String(value).includes(':')) return null;
      const [h, m] = String(value).split(':').map(Number);
      if (!Number.isFinite(h) || !Number.isFinite(m)) return null;
      return (h * 60) + m;
    }

    function calcAndRenderHoursSummary() {
      if (!els.hoursSummary || !els.hoursSummaryTotal || !els.hoursSummaryWorkdays || !els.hoursSummaryAvg) {
        return;
      }

      const mode = els.hoursMode.value;
      let totalHours = 0;
      let workingDays = 0;

      if (mode === 'daily') {
        document.querySelectorAll('#hours-grid input[type="number"][data-day]').forEach((inp) => {
          const h = Math.max(0, Number(inp.value) || 0);
          if (h > 0) {
            workingDays += 1;
            totalHours += h;
          }
        });
      } else if (mode === 'reference') {
        const enabled = Array.from(document.querySelectorAll('.ref-day-enabled:checked'));
        workingDays = enabled.length;

        enabled.forEach((cb) => {
          const day = cb.dataset.day;
          const start = document.querySelector(`.ref-day-start[data-day="${day}"]`);
          const end = document.querySelector(`.ref-day-end[data-day="${day}"]`);
          const brk = document.querySelector(`.ref-day-break[data-day="${day}"]`);
          const startMins = parseTimeToMinutes(start ? start.value : '');
          const endMins = parseTimeToMinutes(end ? end.value : '');
          const breakMins = Math.max(0, Number(brk ? brk.value : 60) || 0);

          if (startMins !== null && endMins !== null && endMins > startMins) {
            totalHours += Math.max(0, (endMins - startMins - breakMins) / 60);
          }
        });
      } else if (mode === 'weekly') {
        totalHours = Math.max(0, Number(els.weeklyHoursTotal.value) || 0);
        workingDays = document.querySelectorAll('.weekly-day:checked').length;
      }

      const avg = workingDays > 0 ? totalHours / workingDays : 0;
      els.hoursSummary.style.display = 'block';
      els.hoursSummaryTotal.textContent = `${totalHours.toFixed(2)} h`;
      els.hoursSummaryWorkdays.textContent = String(workingDays);
      els.hoursSummaryAvg.textContent = `${avg.toFixed(2)} h`;
    }

    function ensureHoursViewWeekDefault() {
      if (!els.hoursViewWeekStart.value) {
        els.hoursViewWeekStart.value = toMondayISO(new Date().toISOString().slice(0, 10));
      }
    }

    function showHoursSection(section) {
      const showEditor = section !== 'visual';
      els.hoursEditorBlock.style.display = showEditor ? 'block' : 'none';
      els.hoursVisualBlock.style.display = showEditor ? 'none' : 'block';
      els.hoursShowEditor.classList.toggle('active', showEditor);
      els.hoursShowVisual.classList.toggle('active', !showEditor);

      if (!showEditor) {
        loadHoursVisual();
      }
    }

    function updateHoursViewVisibility() {
      const scope = els.hoursViewScope.value;
      const applyTo = els.hoursViewApplyTo.value;

      els.hoursViewEmployeeWrap.style.display = scope === 'employee' ? 'block' : 'none';
      els.hoursViewDepartmentWrap.style.display = scope === 'department' ? 'block' : 'none';
      els.hoursViewWeekWrap.style.display = applyTo === 'week' ? 'block' : 'none';
    }

    function formatHours(value) {
      const num = Number(value) || 0;
      return num.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
    }

    function scheduleFromRows(rows = []) {
      const hours = Array(7).fill(0);
      const ranges = Array(7).fill('');

      rows.forEach(row => {
        const day = Number(row.day_of_week);
        if (!Number.isInteger(day) || day < 0 || day > 6) {
          return;
        }
        const dayHours = Number(row.hours || 0);
        hours[day] += Number.isFinite(dayHours) ? dayHours : 0;

        if (row.start_time && row.end_time) {
          ranges[day] = `${String(row.start_time).slice(0, 5)} - ${String(row.end_time).slice(0, 5)}`;
        }
      });

      return { hours, ranges };
    }

    function renderBalanceSummary(hours = [], extraLabel = '', extraValue = '') {
      const total = hours.reduce((acc, h) => acc + h, 0);
      const nonZero = hours.filter(h => h > 0);
      const maxDay = Math.max(...hours, 0);
      const minNonZero = nonZero.length ? Math.min(...nonZero) : 0;
      const spread = nonZero.length ? maxDay - minNonZero : 0;

      let equilibriumText = 'Equilibre excellent';
      let equilibriumClass = 'hours-equilibrium-badge';
      if (spread > 3) {
        equilibriumText = 'Equilibre a lisser';
      } else if (spread > 1.5) {
        equilibriumText = 'Equilibre moyen';
      }

      els.hoursBalanceSummary.innerHTML = `
        <div class="hours-balance-card">
          <p>Total hebdomadaire</p>
          <strong>${formatHours(total)} h</strong>
        </div>
        <div class="hours-balance-card">
          <p>Charge max sur un jour</p>
          <strong>${formatHours(maxDay)} h</strong>
        </div>
        <div class="hours-balance-card">
          <p>Ecarts de charge</p>
          <strong>${formatHours(spread)} h</strong>
          <span class="${equilibriumClass}">${equilibriumText}</span>
        </div>
        <div class="hours-balance-card">
          <p>${extraLabel || 'Jours actifs'}</p>
          <strong>${extraValue || String(nonZero.length)}</strong>
        </div>
      `;
    }

    function renderDayVisual(hours = [], ranges = []) {
      const maxHours = Math.max(...hours, 0, 1);

      els.hoursVisualContainer.innerHTML = `
        <div class="hours-day-list">
          ${dayLabels.map((day, idx) => {
            const h = Number(hours[idx] || 0);
            const percent = Math.max(0, Math.min(100, (h / maxHours) * 100));
            const rangeLabel = ranges[idx] ? `Plage: ${ranges[idx]}` : (h > 0 ? 'Volume sans plage precise' : 'Aucune plage');

            return `
              <div class="hours-day-item">
                <div class="hours-day-head">
                  <strong>${day}</strong>
                  <span>${formatHours(h)} h</span>
                </div>
                <div class="hours-day-bar"><div class="hours-day-bar-fill" style="width: ${percent.toFixed(1)}%;"></div></div>
                <span style="display:block; margin-top:0.35rem; color: var(--ink-soft); font-size:0.8rem;">${rangeLabel}</span>
              </div>
            `;
          }).join('')}
        </div>
      `;
    }

    function hoursViewWeekParam() {
      if (els.hoursViewApplyTo.value !== 'week') {
        return '';
      }
      ensureHoursViewWeekDefault();
      return '&week_start=' + encodeURIComponent(toMondayISO(els.hoursViewWeekStart.value));
    }

    async function fetchScheduleRows(employeeId) {
      if (!employeeId) {
        return [];
      }
      const url = 'api/scheduled_hours.php?action=get&employee_id=' + encodeURIComponent(employeeId) + hoursViewWeekParam();
      const data = await api(url);
      return data.hours || [];
    }

    function selectedWeekLabel() {
      if (els.hoursViewApplyTo.value !== 'week') {
        return 'Horaire de reference';
      }

      ensureHoursViewWeekDefault();
      const mondayIso = toMondayISO(els.hoursViewWeekStart.value);
      const monday = new Date(mondayIso + 'T00:00:00');
      const sunday = new Date(monday);
      sunday.setDate(sunday.getDate() + 6);

      const from = monday.toLocaleDateString('fr-FR');
      const to = sunday.toLocaleDateString('fr-FR');
      return `Semaine du ${from} au ${to}`;
    }

    function openPrintableHours(title, contentHtml) {
      const popup = window.open('', '_blank', 'width=1080,height=760');
      if (!popup) {
        showToast('Autorise les popups pour lancer l\'impression.', true);
        return;
      }

      popup.document.write(`<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>${escapeHtml(title)}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
    h1 { margin: 0 0 6px; font-size: 20px; }
    p.meta { margin: 0 0 14px; color: #444; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #bbb; padding: 6px 8px; font-size: 12px; text-align: left; }
    th { background: #f2f4f7; }
    .total-row td { font-weight: 700; background: #f7f9fb; }
    .section-title { margin-top: 18px; font-size: 15px; }
    @media print {
      body { margin: 10mm; }
    }
  </style>
</head>
<body>
  ${contentHtml}
</body>
</html>`);
      popup.document.close();
      popup.focus();
      setTimeout(() => popup.print(), 220);
    }

    async function printHoursView() {
      const dayOrder = [1, 2, 3, 4, 5, 6, 0];
      const scope = els.hoursViewScope.value;
      const weekLabel = selectedWeekLabel();

      if (scope === 'employee') {
        const employeeId = Number(els.hoursViewEmployee.value || 0);
        if (!employeeId) {
          showToast('Choisis un collaborateur avant impression.', true);
          return;
        }

        const employee = empCache.find(e => Number(e.id) === employeeId);
        const rows = await fetchScheduleRows(employeeId);
        const schedule = scheduleFromRows(rows);

        const bodyRows = dayOrder.map(day => {
          const h = schedule.hours[day] || 0;
          const range = schedule.ranges[day] || '-';
          return `<tr><td>${escapeHtml(dayLabels[day])}</td><td>${escapeHtml(range)}</td><td>${escapeHtml(formatHours(h))} h</td></tr>`;
        }).join('');

        const total = schedule.hours.reduce((acc, h) => acc + h, 0);
        const title = `Horaire hebdomadaire - ${employee ? `${employee.first_name} ${employee.last_name}` : 'Collaborateur'}`;
        const html = `
          <h1>${escapeHtml(title)}</h1>
          <p class="meta">${escapeHtml(weekLabel)}</p>
          <table>
            <thead><tr><th>Jour</th><th>Plage</th><th>Heures</th></tr></thead>
            <tbody>
              ${bodyRows}
              <tr class="total-row"><td colspan="2">Total semaine</td><td>${escapeHtml(formatHours(total))} h</td></tr>
            </tbody>
          </table>
        `;
        openPrintableHours(title, html);
        return;
      }

      const departmentId = Number(els.hoursViewDepartment.value || 0);
      if (!departmentId) {
        showToast('Choisis un departement avant impression.', true);
        return;
      }

      const department = departmentsCache.find(d => Number(d.id) === departmentId);
      const employees = empCache.filter(e => Number(e.department_id) === departmentId);
      if (!employees.length) {
        showToast('Aucun collaborateur dans ce departement.', true);
        return;
      }

      const perEmployeeRows = await Promise.all(
        employees.map(async e => ({
          employee: e,
          schedule: scheduleFromRows(await fetchScheduleRows(e.id)),
        }))
      );

      const headerDays = dayOrder.map(day => `<th>${escapeHtml(dayLabels[day].slice(0, 3))}</th>`).join('');
      const tableRows = perEmployeeRows.map(item => {
        const hoursByDay = dayOrder.map(day => `<td>${escapeHtml(formatHours(item.schedule.hours[day] || 0))}</td>`).join('');
        const total = item.schedule.hours.reduce((acc, h) => acc + h, 0);
        return `<tr><td>${escapeHtml(item.employee.first_name + ' ' + item.employee.last_name)}</td>${hoursByDay}<td>${escapeHtml(formatHours(total))}</td></tr>`;
      }).join('');

      const aggregate = Array(7).fill(0);
      perEmployeeRows.forEach(item => {
        dayOrder.forEach(day => {
          aggregate[day] += Number(item.schedule.hours[day] || 0);
        });
      });
      const aggregateCells = dayOrder.map(day => `<td>${escapeHtml(formatHours(aggregate[day]))}</td>`).join('');
      const aggregateTotal = aggregate.reduce((acc, h) => acc + h, 0);

      const title = `Horaire equipe - ${department ? department.name : 'Departement'}`;
      const html = `
        <h1>${escapeHtml(title)}</h1>
        <p class="meta">${escapeHtml(weekLabel)}</p>
        <table>
          <thead><tr><th>Collaborateur</th>${headerDays}<th>Total</th></tr></thead>
          <tbody>
            ${tableRows}
            <tr class="total-row"><td>Equipe</td>${aggregateCells}<td>${escapeHtml(formatHours(aggregateTotal))}</td></tr>
          </tbody>
        </table>
      `;
      openPrintableHours(title, html);
    }

    async function loadHoursVisual() {
      try {
        updateHoursViewVisibility();

        const scope = els.hoursViewScope.value;
        if (scope === 'employee') {
          const employeeId = Number(els.hoursViewEmployee.value || 0);
          if (!employeeId) {
            els.hoursBalanceSummary.innerHTML = '';
            els.hoursVisualContainer.innerHTML = '<div class="hours-empty">Choisis un collaborateur pour visualiser son horaire.</div>';
            return;
          }

          const employee = empCache.find(e => Number(e.id) === employeeId);
          const rows = await fetchScheduleRows(employeeId);
          const schedule = scheduleFromRows(rows);
          renderBalanceSummary(schedule.hours, 'Collaborateur', employee ? `${employee.first_name} ${employee.last_name}` : 'Selection');
          renderDayVisual(schedule.hours, schedule.ranges);
          return;
        }

        const departmentId = Number(els.hoursViewDepartment.value || 0);
        if (!departmentId) {
          els.hoursBalanceSummary.innerHTML = '';
          els.hoursVisualContainer.innerHTML = '<div class="hours-empty">Choisis un departement pour visualiser les horaires consolides.</div>';
          return;
        }

        const departmentEmployees = empCache.filter(e => Number(e.department_id) === departmentId);
        if (!departmentEmployees.length) {
          els.hoursBalanceSummary.innerHTML = '';
          els.hoursVisualContainer.innerHTML = '<div class="hours-empty">Aucun collaborateur actif dans ce departement.</div>';
          return;
        }

        const perEmployeeRows = await Promise.all(
          departmentEmployees.map(async e => ({
            employee: e,
            rows: await fetchScheduleRows(e.id),
          }))
        );

        const aggregate = Array(7).fill(0);
        const peopleTotals = [];

        perEmployeeRows.forEach(item => {
          const schedule = scheduleFromRows(item.rows);
          const total = schedule.hours.reduce((acc, h) => acc + h, 0);
          peopleTotals.push({
            name: `${item.employee.first_name} ${item.employee.last_name}`,
            total,
          });
          for (let day = 0; day < 7; day++) {
            aggregate[day] += schedule.hours[day];
          }
        });

        const department = departmentsCache.find(d => Number(d.id) === departmentId);
        renderBalanceSummary(
          aggregate,
          'Collaborateurs',
          `${departmentEmployees.length} (${department ? department.name : 'Departement'})`
        );
        renderDayVisual(aggregate, Array(7).fill('Somme des plages du departement'));

        const maxTotal = Math.max(...peopleTotals.map(p => p.total), 1);
        const rowsHtml = peopleTotals
          .sort((a, b) => b.total - a.total)
          .map(p => {
            const percent = Math.max(0, Math.min(100, (p.total / maxTotal) * 100));
            return `
              <div class="hours-person-row">
                <span class="hours-person-name">${p.name}</span>
                <div class="hours-day-bar"><div class="hours-day-bar-fill" style="width:${percent.toFixed(1)}%;"></div></div>
                <strong>${formatHours(p.total)} h</strong>
              </div>
            `;
          })
          .join('');

        els.hoursVisualContainer.insertAdjacentHTML(
          'beforeend',
          `<div class="hours-department-people"><h4 style="margin:0.8rem 0 0.3rem;">Repartition par collaborateur</h4>${rowsHtml}</div>`
        );
        panelLoadState.hours = true;
      } catch (e) {
        els.hoursBalanceSummary.innerHTML = '';
        els.hoursVisualContainer.innerHTML = `<div class="hours-empty">Erreur de chargement: ${e.message}</div>`;
      }
    }

    async function cleanupDuplicateSchedules() {
      if (!confirm('Lancer le nettoyage des doublons d\'horaires en base ?')) {
        return;
      }

      try {
        const result = await api('api/scheduled_hours.php?action=cleanup_duplicates', {
          method: 'POST',
          body: JSON.stringify({}),
        });

        showToast(
          `Nettoyage termine: ${result.deleted_rows || 0} supprimes (${result.duplicate_rows_before || 0} -> ${result.duplicate_rows_after || 0}).`
        );
        loadHoursVisual();
      } catch (e) {
        showToast(e.message, true);
      }
    }

    function ensurePayrollPeriodDefault() {
      if (!els.payrollPeriod.value) {
        els.payrollPeriod.value = new Date().toISOString().slice(0, 7);
      }
    }

    function payrollQueryString() {
      ensurePayrollPeriodDefault();
      const params = new URLSearchParams({ period: els.payrollPeriod.value });
      if (els.payrollDepartment.value) params.set('department_id', els.payrollDepartment.value);
      if (els.payrollEmployee.value) params.set('employee_id', els.payrollEmployee.value);
      return params.toString();
    }

    async function loadPayrollDashboard() {
      if (!els.payrollOvertimeTable) {
        return;
      }

      ensurePayrollPeriodDefault();
      const query = payrollQueryString();

      try {
        const [overtimeData, closuresData, auditData] = await Promise.all([
          api('api/overtime.php?' + query),
          api('api/period_closures.php?months=12'),
          api('api/audit_logs.php?limit=20'),
        ]);

        const overtimeRows = overtimeData.rows || [];
        const totalPeriod = overtimeRows.reduce((acc, row) => acc + Number(row.period_balance || 0), 0);
        const totalCumulative = overtimeRows.reduce((acc, row) => acc + Number(row.cumulative_balance || 0), 0);
        const totalPayable = overtimeRows.reduce((acc, row) => acc + Number(row.payable_hours || 0), 0);
        const totalPaidAbsence = overtimeRows.reduce((acc, row) => acc + Number(row.paid_absence_hours || 0), 0);
        const totalUnpaidAbsence = overtimeRows.reduce((acc, row) => acc + Number(row.unpaid_absence_hours || 0), 0);

        els.payrollOvertimeSummary.innerHTML = `
          <div class="hours-balance-card">
            <p>Heures payables</p>
            <strong>${formatHours(totalPayable)} h</strong>
          </div>
          <div class="hours-balance-card">
            <p>Absences payees</p>
            <strong>${formatHours(totalPaidAbsence)} h</strong>
          </div>
          <div class="hours-balance-card">
            <p>Absences non payees</p>
            <strong>${formatHours(totalUnpaidAbsence)} h</strong>
          </div>
          <div class="hours-balance-card">
            <p>Solde periode</p>
            <strong>${formatHours(totalPeriod)} h</strong>
          </div>
          <div class="hours-balance-card">
            <p>Banque d'heures cumulée</p>
            <strong>${formatHours(totalCumulative)} h</strong>
          </div>
          <div class="hours-balance-card">
            <p>Periode</p>
            <strong>${escapeHtml(els.payrollPeriod.value)}</strong>
          </div>
        `;

        els.payrollOvertimeTable.innerHTML = overtimeRows.length ? `
          <table class="week-table">
            <thead>
              <tr>
                <th>Collaborateur</th>
                <th>Departement</th>
                <th>Prevu</th>
                <th>Travaille</th>
                <th>Abs. payees</th>
                <th>Abs. non payees</th>
                <th>Heures payables</th>
                <th>Solde periode</th>
                <th>Banque cumulée</th>
              </tr>
            </thead>
            <tbody>
              ${overtimeRows.map(row => `
                <tr>
                  <td>${escapeHtml(row.employee_name)}</td>
                  <td>${escapeHtml(row.department_name || '-')}</td>
                  <td>${formatHours(row.scheduled_hours)} h</td>
                  <td>${formatHours(row.worked_hours)} h</td>
                  <td>${formatHours(row.paid_absence_hours)} h</td>
                  <td>${formatHours(row.unpaid_absence_hours)} h</td>
                  <td>${formatHours(row.payable_hours)} h</td>
                  <td class="${Number(row.period_balance) >= 0 ? 'status-ok' : 'status-diff'}">${Number(row.period_balance) >= 0 ? '+' : ''}${formatHours(row.period_balance)} h</td>
                  <td class="${Number(row.cumulative_balance) >= 0 ? 'status-ok' : 'status-diff'}">${Number(row.cumulative_balance) >= 0 ? '+' : ''}${formatHours(row.cumulative_balance)} h</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        ` : '<div class="hours-empty">Aucune donnée sur cette période.</div>';

        const closures = closuresData.periods || [];
        els.payrollClosuresList.innerHTML = closures.length ? closures.map(period => `
          <div class="hours-day-item" style="margin-bottom:0.45rem;">
            <div class="hours-day-head">
              <strong>${escapeHtml(period.period_key)}</strong>
              <span>${period.closed ? '🔒 Cloturee' : '🟢 Ouverte'}</span>
            </div>
            <span style="display:block; color: var(--ink-soft); font-size:0.8rem;">${period.closed ? `par ${escapeHtml(period.closed_by || '')} le ${escapeHtml(String(period.closed_at || '').slice(0, 16))}` : 'Periode editable'}</span>
          </div>
        `).join('') : '<div class="hours-empty">Aucune periode suivie.</div>';

        const logs = auditData.logs || [];
        els.payrollAuditList.innerHTML = logs.length ? logs.map(log => `
          <div class="hours-day-item" style="margin-bottom:0.45rem;">
            <div class="hours-day-head">
              <strong>${escapeHtml(log.summary || log.action_type)}</strong>
              <span>${escapeHtml(String(log.created_at || '').slice(0, 16))}</span>
            </div>
            <span style="display:block; color: var(--ink-soft); font-size:0.8rem;">${escapeHtml(log.actor_username || 'system')} · ${escapeHtml(log.target_type || '')} #${escapeHtml(log.target_id || '')}</span>
          </div>
        `).join('') : '<div class="hours-empty">Aucune trace recente.</div>';
        panelLoadState.payroll = true;
      } catch (e) {
        els.payrollOvertimeSummary.innerHTML = '';
        els.payrollOvertimeTable.innerHTML = `<div class="hours-empty">Erreur de chargement: ${escapeHtml(e.message)}</div>`;
        els.payrollClosuresList.innerHTML = '<div class="hours-empty">Impossible de charger les clotures.</div>';
        els.payrollAuditList.innerHTML = '<div class="hours-empty">Impossible de charger l\'audit.</div>';
      }
    }

    function updateRecurrenceSlotOptions(intervalValue, selectedSlotValue = 1) {
      const interval = Math.max(1, Math.min(3, Number(intervalValue) || 1));
      const selected = Math.max(1, Math.min(interval, Number(selectedSlotValue) || 1));
      const options = [];
      for (let slot = 1; slot <= interval; slot++) {
        options.push(`<option value="${slot}">Semaine ${slot}</option>`);
      }
      els.refRecurrenceSlot.innerHTML = options.join('');
      els.refRecurrenceSlot.value = String(selected);
    }

    function updateRecurrenceVisibility() {
      const applyTo = els.hoursApplyTo.value;
      const mode = els.hoursMode.value;
      const interval = Math.max(1, Math.min(3, Number(els.refRecurrenceInterval.value) || 1));
      const showRecurrence = mode === 'reference' && applyTo === 'default';
      const showSlot = showRecurrence && interval > 1;

      els.refRecurrenceWrap.style.display = showRecurrence ? 'block' : 'none';
      els.refRecurrenceSlotWrap.style.display = showSlot ? 'block' : 'none';
    }

    function updateHoursModeVisibility() {
      const mode = els.hoursMode.value;
      const applyTo = els.hoursApplyTo.value;

      els.hoursWeekWrap.style.display = applyTo === 'week' ? 'block' : 'none';
      els.hoursReference.style.display = mode === 'reference' ? 'block' : 'none';
      els.hoursDaily.style.display = mode === 'daily' ? 'block' : 'none';
      els.hoursWeekly.style.display = mode === 'weekly' ? 'block' : 'none';
      updateRecurrenceVisibility();
    }

    function applyHoursDataToForm(rows = []) {
      renderHoursGrid(rows);
      renderReferenceHoursGrid(rows);

      if (!rows.length) {
        const mode = els.hoursMode.value || 'reference';
        const recurrenceInterval = Math.max(1, Math.min(3, Number(els.refRecurrenceInterval.value) || 1));
        const recurrenceSlot = Math.max(1, Math.min(recurrenceInterval, Number(els.refRecurrenceSlot.value) || 1));
        els.hoursMode.value = mode;
        updateRecurrenceSlotOptions(recurrenceInterval, recurrenceSlot);
        updateHoursModeVisibility();
        calcAndRenderHoursSummary();
        return;
      }

      const mode = rows[0].entry_mode || 'daily';
      els.hoursMode.value = mode;

      if (mode === 'reference') {
        renderReferenceHoursGrid(rows);
        const first = rows.find(r => r.start_time && r.end_time) || rows[0];
        const recurrenceInterval = Math.max(1, Math.min(3, Number(first.recurrence_interval) || 1));
        const recurrenceSlot = Math.max(1, Math.min(recurrenceInterval, Number(first.recurrence_slot) || 1));
        els.refRecurrenceInterval.value = String(recurrenceInterval);
        updateRecurrenceSlotOptions(recurrenceInterval, recurrenceSlot);
      }

      if (mode === 'weekly') {
        const weeklyTotal = rows.reduce((acc, r) => acc + Number(r.hours || 0), 0);
        els.weeklyHoursTotal.value = Math.round(weeklyTotal * 100) / 100;
        const selectedDays = new Set(rows.map(r => Number(r.day_of_week)));
        document.querySelectorAll('.weekly-day').forEach(cb => {
          cb.checked = selectedDays.has(Number(cb.value));
        });
      }

      updateHoursModeVisibility();
      calcAndRenderHoursSummary();
    }

    async function loadHoursForSelected() {
      const empId = els.hoursEmployee.value;
      if (!empId) {
        renderHoursGrid([]);
        renderReferenceHoursGrid([]);
        calcAndRenderHoursSummary();
        return;
      }

      try {
        let url = 'api/scheduled_hours.php?action=get&employee_id=' + encodeURIComponent(empId);
        if (els.hoursApplyTo.value === 'week') {
          ensureWeekDefault();
          url += '&week_start=' + encodeURIComponent(toMondayISO(els.hoursWeekStart.value));
        } else {
          const recurrenceInterval = Math.max(1, Math.min(3, Number(els.refRecurrenceInterval.value) || 1));
          const recurrenceSlot = Math.max(1, Math.min(recurrenceInterval, Number(els.refRecurrenceSlot.value) || 1));
          url += '&recurrence_interval=' + encodeURIComponent(recurrenceInterval);
          url += '&recurrence_slot=' + encodeURIComponent(recurrenceSlot);
        }
        const data = await api(url);
        applyHoursDataToForm(data.hours || []);
        if (!els.hoursViewEmployee.value) {
          els.hoursViewEmployee.value = String(empId);
        }
        panelLoadState.hours = true;
      } catch (e) {
        showToast(e.message, true);
      }
    }

    els.hoursEmployee.addEventListener('change', loadHoursForSelected);
    els.hoursApplyTo.addEventListener('change', () => {
      updateHoursModeVisibility();
      loadHoursForSelected();
    });
    els.hoursWeekStart.addEventListener('change', () => {
      els.hoursWeekStart.value = toMondayISO(els.hoursWeekStart.value);
      loadHoursForSelected();
    });
    els.hoursMode.addEventListener('change', updateHoursModeVisibility);
    els.hoursMode.addEventListener('change', calcAndRenderHoursSummary);
    els.refRecurrenceInterval.addEventListener('change', () => {
      const interval = Math.max(1, Math.min(3, Number(els.refRecurrenceInterval.value) || 1));
      updateRecurrenceSlotOptions(interval, 1);
      updateRecurrenceVisibility();
      loadHoursForSelected();
    });
    els.refRecurrenceSlot.addEventListener('change', loadHoursForSelected);

    els.hoursViewScope.addEventListener('change', loadHoursVisual);
    els.hoursViewEmployee.addEventListener('change', loadHoursVisual);
    els.hoursViewDepartment.addEventListener('change', loadHoursVisual);
    els.hoursViewApplyTo.addEventListener('change', loadHoursVisual);
    els.hoursViewWeekStart.addEventListener('change', () => {
      els.hoursViewWeekStart.value = toMondayISO(els.hoursViewWeekStart.value);
      loadHoursVisual();
    });
    els.hoursShowEditor.addEventListener('click', () => showHoursSection('editor'));
    els.hoursShowVisual.addEventListener('click', () => showHoursSection('visual'));
    els.btnPrintHours.addEventListener('click', async () => {
      try {
        await printHoursView();
      } catch (e) {
        showToast(e.message, true);
      }
    });
    els.btnCleanHoursDuplicates.addEventListener('click', cleanupDuplicateSchedules);

    els.hoursGrid.addEventListener('input', calcAndRenderHoursSummary);
    els.hoursGrid.addEventListener('change', calcAndRenderHoursSummary);
    els.hoursReferenceGrid.addEventListener('input', calcAndRenderHoursSummary);
    els.hoursReferenceGrid.addEventListener('change', calcAndRenderHoursSummary);
    els.weeklyHoursTotal.addEventListener('input', calcAndRenderHoursSummary);
    els.weeklyHoursTotal.addEventListener('change', calcAndRenderHoursSummary);
    document.querySelectorAll('.weekly-day').forEach((cb) => {
      cb.addEventListener('change', calcAndRenderHoursSummary);
    });

    els.payrollPeriod.addEventListener('change', loadPayrollDashboard);
    els.payrollDepartment.addEventListener('change', loadPayrollDashboard);
    els.payrollEmployee.addEventListener('change', loadPayrollDashboard);
    els.btnPayrollRefresh.addEventListener('click', loadPayrollDashboard);
    els.btnPayrollExport.addEventListener('click', () => {
      window.location.href = 'api/payroll_export.php?' + payrollQueryString();
    });
    els.btnPayrollClose.addEventListener('click', async () => {
      ensurePayrollPeriodDefault();
      if (!confirm(`Cloturer la periode ${els.payrollPeriod.value} ? Les corrections y seront bloquees.`)) return;
      try {
        await api('api/period_closures.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'close', period_key: els.payrollPeriod.value }),
        });
        showToast('Periode cloturee');
        await loadPayrollDashboard();
      } catch (e) {
        showToast(e.message, true);
      }
    });
    els.btnPayrollReopen.addEventListener('click', async () => {
      ensurePayrollPeriodDefault();
      if (!confirm(`Reouvrir la periode ${els.payrollPeriod.value} ?`)) return;
      try {
        await api('api/period_closures.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'reopen', period_key: els.payrollPeriod.value }),
        });
        showToast('Periode rouverte');
        await loadPayrollDashboard();
      } catch (e) {
        showToast(e.message, true);
      }
    });

    els.btnSaveHours.addEventListener('click', async () => {
      const empId = els.hoursEmployee.value;
      if (!empId) {
        showToast('Selectionnez un collaborateur', true);
        return;
      }

      const mode = els.hoursMode.value;
      const applyTo = els.hoursApplyTo.value;
      const payload = {
        employee_id: Number(empId),
        mode,
        apply_to: applyTo,
      };

      if (applyTo === 'week') {
        ensureWeekDefault();
        payload.week_start = toMondayISO(els.hoursWeekStart.value);
      }

      if (mode === 'daily') {
        const hours = {};
        document.querySelectorAll('[data-day]').forEach(inp => {
          hours[inp.dataset.day] = parseFloat(inp.value) || 0;
        });
        payload.hours = hours;
      }

      if (mode === 'reference') {
        const referenceDays = [];
        const enabledDays = Array.from(document.querySelectorAll('.ref-day-enabled:checked')).map(cb => Number(cb.dataset.day));
        if (!enabledDays.length) {
          showToast('Selectionnez au moins un jour pour l\'horaire de reference.', true);
          return;
        }

        for (const day of enabledDays) {
          const startInput = document.querySelector(`.ref-day-start[data-day="${day}"]`);
          const endInput = document.querySelector(`.ref-day-end[data-day="${day}"]`);
          const breakInput = document.querySelector(`.ref-day-break[data-day="${day}"]`);
          const start = startInput ? startInput.value : '';
          const end = endInput ? endInput.value : '';
          const breakMinutes = Math.max(0, parseInt(breakInput ? breakInput.value : '60', 10) || 0);

          if (!start || !end) {
            showToast(`Complete l'heure debut/fin pour ${dayLabels[day]}.`, true);
            return;
          }

          const [sh, sm] = start.split(':').map(Number);
          const [eh, em] = end.split(':').map(Number);
          const startMins = (sh * 60) + sm;
          const endMins = (eh * 60) + em;
          if (!Number.isFinite(startMins) || !Number.isFinite(endMins) || endMins <= startMins) {
            showToast(`Plage invalide pour ${dayLabels[day]} (fin > debut).`, true);
            return;
          }
          if (breakMinutes >= (endMins - startMins)) {
            showToast(`Pause invalide pour ${dayLabels[day]} (doit etre inferieure a la plage).`, true);
            return;
          }

          referenceDays.push({
            day,
            start_time: start,
            end_time: end,
            break_minutes: breakMinutes,
          });
        }

        payload.reference_days = referenceDays;

        if (applyTo === 'default') {
          const recurrenceInterval = Math.max(1, Math.min(3, Number(els.refRecurrenceInterval.value) || 1));
          const recurrenceSlot = Math.max(1, Math.min(recurrenceInterval, Number(els.refRecurrenceSlot.value) || 1));
          payload.recurrence_interval = recurrenceInterval;
          payload.recurrence_slot = recurrenceSlot;
        }
      }

      if (mode === 'weekly') {
        const days = Array.from(document.querySelectorAll('.weekly-day:checked')).map(cb => Number(cb.value));
        if (!days.length) {
          showToast('Selectionnez au moins un jour de prestation.', true);
          return;
        }
        payload.weekly_hours = parseFloat(els.weeklyHoursTotal.value) || 0;
        payload.days = days;
      }

      try {
        await api('api/scheduled_hours.php?action=save', {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        showToast('Horaires enregistres');
        await loadHoursForSelected();
        await loadHoursVisual();
      } catch (e) {
        showToast(e.message, true);
      }
    });

    ensureWeekDefault();
    ensureHoursViewWeekDefault();
    ensurePayrollPeriodDefault();
    updateRecurrenceSlotOptions(1, 1);
    updateHoursModeVisibility();
    updateHoursViewVisibility();
    showHoursSection('editor');
    renderHoursGrid([]);
    renderReferenceHoursGrid([]);
    calcAndRenderHoursSummary();
    renderAllowedLocations([]);
    toggleTeleworkLocationsVisibility();

    // Geocodage silencieux via Nominatim (declenchement auto au blur)
    async function silentGeocode(addr) {
      if (!addr) return;
      try {
        const res = await fetch(
          'https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(addr) + '&format=json&limit=1',
          { headers: { 'Accept-Language': 'fr' } }
        );
        const data = await res.json();
        if (data.length) {
          return {
            latitude: parseFloat(data[0].lat).toFixed(7),
            longitude: parseFloat(data[0].lon).toFixed(7),
          };
        }
      } catch (_) { /* silencieux */ }
      return null;
    }
    els.empAddress.addEventListener('blur', () => {
      const addr = els.empAddress.value.trim();
      if (!addr) return;
      silentGeocode(addr).then((geo) => {
        if (!geo) return;
        els.empLat.value = geo.latitude;
        els.empLng.value = geo.longitude;
      });
    });

    els.empTeleworkEnabled.addEventListener('change', toggleTeleworkLocationsVisibility);
    els.btnAddAllowedLocation.addEventListener('click', () => addAllowedLocationRow({}));

    els.empAllowedLocationsList.addEventListener('blur', (ev) => {
      const input = ev.target;
      if (!input.classList.contains('allowed-location-address')) {
        return;
      }

      const addr = input.value.trim();
      if (!addr) {
        return;
      }

      const row = input.closest('.allowed-location-row');
      if (!row) {
        return;
      }

      silentGeocode(addr).then((geo) => {
        if (!geo) return;
        const latEl = row.querySelector('.allowed-location-lat');
        const lngEl = row.querySelector('.allowed-location-lng');
        if (latEl) latEl.value = geo.latitude;
        if (lngEl) lngEl.value = geo.longitude;
      });
    }, true);

    // Modal acces employe
    const accessModal = document.getElementById('access-modal');
    document.getElementById('access-modal-close').addEventListener('click', () => accessModal.classList.remove('open'));
    accessModal.addEventListener('click', (e) => {
      if (e.target === accessModal) accessModal.classList.remove('open');
    });

    window.openAccess = (empId) => {
      const emp = empCache.find(e => e.id === empId);
      if (!emp) return;
      document.getElementById('access-modal-title').textContent = 'Acces : ' + emp.first_name + ' ' + emp.last_name;
      const body = document.getElementById('access-modal-body');
      if (emp.login_username) {
        body.innerHTML = `
          <p>Compte actuel : <strong>${emp.login_username}</strong></p>
          <button class="btn-delete" onclick="deleteAccess(${empId})">Supprimer l'acces</button>
        `;
      } else {
        body.innerHTML = `
          <div class="form-group"><label>Identifiant</label>
            <input id="acc-username" type="text" placeholder="prenom.nom" /></div>
          <div class="form-group"><label>Mot de passe (6 car. min)</label>
            <input id="acc-password" type="password" /></div>
          <button class="btn-in" onclick="createAccess(${empId})">Creer l'acces</button>
        `;
      }
      accessModal.classList.add('open');
    };

    window.createAccess = async (empId) => {
      const usernameEl = document.getElementById('acc-username');
      const passwordEl = document.getElementById('acc-password');
      const username = usernameEl ? usernameEl.value.trim() : '';
      const password = passwordEl ? passwordEl.value : '';
      try {
        await api('api/employees.php?action=create_access', {
          method: 'POST',
          body: JSON.stringify({ employee_id: empId, username, password }),
        });
        showToast('Acces cree !');
        accessModal.classList.remove('open');
        loadEmployees();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    window.deleteAccess = async (empId) => {
      if (!confirm('Supprimer le compte de cet employe ?')) return;
      try {
        await api('api/employees.php?action=delete_access', {
          method: 'POST',
          body: JSON.stringify({ employee_id: empId }),
        });
        showToast('Acces supprime.');
        accessModal.classList.remove('open');
        loadEmployees();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    // Demandes de congés
    async function loadVacationRequests() {
      try {
        const filter = els.vacStatusFilter.value;
        const employeeId = els.vacEmployeeFilter.value;
        const url = 'api/vacation_requests.php?action=list'
          + (filter ? '&status=' + encodeURIComponent(filter) : '')
          + (employeeId ? '&emp_id=' + encodeURIComponent(employeeId) : '');
        const data = await api(url);
        const requests = data.requests || [];

        if (!requests.length) {
          els.vacRequestsList.innerHTML = '<p style="color: var(--ink-soft);">Aucune demande de congé.</p>';
          return;
        }

        els.vacRequestsList.innerHTML = requests.map(req => {
          const sd = new Date(req.start_date).toLocaleDateString('fr-FR');
          const ed = new Date(req.end_date).toLocaleDateString('fr-FR');
          const statusLabel = { pending: 'En attente', approved: 'Approuvé', rejected: 'Rejeté' }[req.status] || '?';
          return `<div class="vac-request-item">
            <div class="vac-request-header">
              <span class="vac-request-name">${req.emp_first} ${req.emp_last}</span>
              <span class="vac-status ${req.status}">${statusLabel}</span>
            </div>
            <div class="vac-request-dates">📅 ${sd} au ${ed}</div>
            ${req.reason ? '<div class="vac-request-reason"><strong>Motif:</strong> ' + req.reason + '</div>' : ''}
            ${req.admin_comment ? '<div class="vac-request-comment"><strong>Commentaire:</strong> ' + req.admin_comment + '</div>' : ''}
            <div class="vac-actions">
              ${req.status === 'pending' ? `
                <button class="approve" onclick="approveRequest(${req.id})">✓ Approuver</button>
                <button class="reject" onclick="rejectRequest(${req.id})">✗ Rejeter</button>
              ` : ''}
            </div>
          </div>`;
        }).join('');
        panelLoadState['vacation-requests'] = true;
      } catch (e) {
        els.vacRequestsList.innerHTML = '<p style="color: var(--warn);">Erreur : ' + e.message + '</p>';
      }
    }

    async function loadVacationBalanceSummary() {
      const employeeId = els.vacEmployeeFilter.value;
      if (!employeeId) {
        els.vacBalanceSummary.innerHTML = '<div class="hours-empty">Sélectionne un collaborateur pour consulter les soldes.</div>';
        els.vacAdjustmentDays.value = '0';
        els.vacAdjustmentHours.value = '0';
        return;
      }

      try {
        const data = await api('api/employee_balances.php?employee_id=' + encodeURIComponent(employeeId));
        const summary = data.summary || {};
        const vacation = summary.vacation || {};
        const overtime = summary.overtime || {};

        els.vacAdjustmentDays.value = Number(vacation.adjustment_days || 0).toFixed(2);
        els.vacAdjustmentHours.value = Number(overtime.adjustment_hours || 0).toFixed(2);

        els.vacBalanceSummary.innerHTML = `
          <div class="hours-balance-card">
            <p>Droits annuels</p>
            <strong>${Number(vacation.entitled_days || 0).toFixed(2)} j</strong>
          </div>
          <div class="hours-balance-card">
            <p>Congés approuvés</p>
            <strong>${Number(vacation.approved_days || 0).toFixed(2)} j</strong>
          </div>
          <div class="hours-balance-card">
            <p>Demandes en attente</p>
            <strong>${Number(vacation.pending_days || 0).toFixed(2)} j</strong>
          </div>
          <div class="hours-balance-card">
            <p>Solde congés</p>
            <strong>${Number(vacation.balance_days || 0).toFixed(2)} j</strong>
          </div>
          <div class="hours-balance-card">
            <p>Heures supp. calculées</p>
            <strong>${Number(overtime.computed_hours || 0).toFixed(2)} h</strong>
          </div>
          <div class="hours-balance-card">
            <p>Solde heures supp.</p>
            <strong>${Number(overtime.balance_hours || 0).toFixed(2)} h</strong>
          </div>
        `;
      } catch (e) {
        els.vacBalanceSummary.innerHTML = '<div class="hours-empty">' + e.message + '</div>';
      }
    }

    window.approveRequest = async (requestId) => {
      const comment = prompt('Commentaire (optionnel):');
      if (comment === null) return; // Annulation
      try {
        await api('api/vacation_requests.php?action=review', {
          method: 'POST',
          body: JSON.stringify({ request_id: requestId, status: 'approved', comment }),
        });
        showToast('✓ Demande approuvée');
        loadVacationRequests();
        loadVacationBalanceSummary();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    window.rejectRequest = async (requestId) => {
      const comment = prompt('Motif du rejet:');
      if (comment === null) return; // Annulation
      try {
        await api('api/vacation_requests.php?action=review', {
          method: 'POST',
          body: JSON.stringify({ request_id: requestId, status: 'rejected', comment }),
        });
        showToast('✗ Demande rejetée');
        loadVacationRequests();
        loadVacationBalanceSummary();
      } catch (e) {
        showToast(e.message, true);
      }
    };

    els.vacEmployeeFilter.addEventListener('change', () => {
      loadVacationBalanceSummary();
      loadVacationRequests();
    });

    els.vacStatusFilter.addEventListener('change', loadVacationRequests);

    els.btnSaveVacBalances.addEventListener('click', async () => {
      const employeeId = els.vacEmployeeFilter.value;
      if (!employeeId) {
        showToast('Choisis un collaborateur.', true);
        return;
      }

      try {
        await api('api/employee_balances.php', {
          method: 'POST',
          body: JSON.stringify({
            employee_id: Number(employeeId),
            vacation_adjustment_days: parseFloat(els.vacAdjustmentDays.value || '0') || 0,
            overtime_adjustment_hours: parseFloat(els.vacAdjustmentHours.value || '0') || 0,
          }),
        });
        showToast('Soldes RH mis à jour');
        loadVacationBalanceSummary();
      } catch (e) {
        showToast(e.message, true);
      }
    });

    // Configuration boitier RFID
    async function loadDeviceSettings() {
      try {
        const data = await api('api/device_settings.php');
        const s = data.settings || {};
        els.cfgSiteName.value = s.site_name || 'JustInTime';
        els.cfgDisplayMessage.value = s.display_message || 'Passe un badge';
        els.cfgSuccessMessage.value = s.success_message || 'Pointage enregistre';
        els.cfgLedEnabled.value = s.led_enabled ? '1' : '0';
        els.cfgBuzzerEnabled.value = s.buzzer_enabled ? '1' : '0';
        panelLoadState['device-settings'] = true;
      } catch (e) {
        showToast(e.message, true);
      }
    }

    els.deviceSettingsForm.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      try {
        await api('api/device_settings.php', {
          method: 'POST',
          body: JSON.stringify({
            site_name: els.cfgSiteName.value.trim(),
            display_message: els.cfgDisplayMessage.value.trim(),
            success_message: els.cfgSuccessMessage.value.trim(),
            led_enabled: els.cfgLedEnabled.value === '1',
            buzzer_enabled: els.cfgBuzzerEnabled.value === '1',
          }),
        });
        showToast('Configuration pointeuse enregistree');
      } catch (e) {
        showToast(e.message, true);
      }
    });

    (async () => {
      await loadEmployees({ refreshPanels: false });
      await loadDepartments();
    })();
  </script>
</body>
</html>
