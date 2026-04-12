const els = {
  employeeSelect: document.getElementById('employee-select'),
  btnIn: document.getElementById('btn-in'),
  btnOut: document.getElementById('btn-out'),
  refreshBtn: document.getElementById('refresh-btn'),
  statTotal: document.getElementById('stat-total'),
  statPresent: document.getElementById('stat-present'),
  statAbsent: document.getElementById('stat-absent'),
  statEvents: document.getElementById('stat-events'),
  statCorrections: document.getElementById('stat-corrections'),
  employeeList: document.getElementById('employee-list'),
  toast: document.getElementById('toast'),
};

let toastTimer = null;
let autoRefreshTimer = null;
let dashboardRequestInFlight = false;

const AUTO_REFRESH_MS = 15000;

function showToast(message, isError = false) {
  els.toast.textContent = message;
  els.toast.style.background = isError ? '#7f2323' : '#1f2f29';
  els.toast.classList.add('show');

  if (toastTimer) {
    clearTimeout(toastTimer);
  }

  toastTimer = setTimeout(() => {
    els.toast.classList.remove('show');
  }, 2400);
}

async function api(path, options = {}) {
  const response = await fetch(path, {
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || 'Erreur serveur');
  }

  return payload;
}

function renderEmployees(employees) {
  const previousSelection = els.employeeSelect.value;

  els.employeeSelect.innerHTML = '';
  els.employeeList.innerHTML = '';

  employees.forEach((employee) => {
    const option = document.createElement('option');
    option.value = employee.id;
    option.textContent = `${employee.name} (${employee.badge_id})`;
    els.employeeSelect.append(option);

    const item = document.createElement('article');
    item.className = 'employee-item';
    item.innerHTML = `
      <p>${employee.name}</p>
      <small>${employee.badge_id}</small>
      <span class="status ${employee.status}">${employee.status}</span>
    `;
    els.employeeList.append(item);
  });

  if (previousSelection && employees.some((employee) => String(employee.id) === String(previousSelection))) {
    els.employeeSelect.value = previousSelection;
  }
}

async function loadDashboard(force = false) {
  if (dashboardRequestInFlight && !force) {
    return;
  }

  dashboardRequestInFlight = true;

  try {
    const data = await api('/api/dashboard');

    els.statTotal.textContent = data.summary.employees_total;
    els.statPresent.textContent = data.summary.present;
    els.statAbsent.textContent = data.summary.absent;
    els.statEvents.textContent = data.summary.events_today;
    els.statCorrections.textContent = data.summary.corrections_pending ?? 0;

    renderEmployees(data.employees);
  } finally {
    dashboardRequestInFlight = false;
  }
}

function startAutoRefresh() {
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer);
  }

  autoRefreshTimer = setInterval(() => {
    if (!document.hidden) {
      loadDashboard().catch((error) => showToast(error.message, true));
    }
  }, AUTO_REFRESH_MS);
}

async function submitManual(eventType) {
  const employee_id = Number(els.employeeSelect.value);
  if (!employee_id) {
    showToast('Selectionnez un employe.', true);
    return;
  }

  try {
    const res = await api('/api/attendance/manual', {
      method: 'POST',
      body: JSON.stringify({ employee_id, event_type: eventType }),
    });
    showToast(res.message);
    await loadDashboard();
  } catch (error) {
    showToast(error.message, true);
  }
}

function wireEvents() {
  els.btnIn.addEventListener('click', () => submitManual('in'));
  els.btnOut.addEventListener('click', () => submitManual('out'));
  els.refreshBtn.addEventListener('click', () => {
    loadDashboard(true).catch((error) => showToast(error.message, true));
  });

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      loadDashboard(true).catch((error) => showToast(error.message, true));
    }
  });

  window.addEventListener('focus', () => {
    loadDashboard(true).catch((error) => showToast(error.message, true));
  });
}

async function boot() {
  wireEvents();
  startAutoRefresh();
  els.employeeSelect.focus();

  try {
    await loadDashboard(true);
  } catch (error) {
    showToast(error.message, true);
  }
}

boot();
