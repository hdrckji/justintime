const els = {
  rfidForm: document.getElementById('rfid-form'),
  badgeInput: document.getElementById('badge-input'),
  employeeSelect: document.getElementById('employee-select'),
  btnIn: document.getElementById('btn-in'),
  btnOut: document.getElementById('btn-out'),
  refreshBtn: document.getElementById('refresh-btn'),
  statTotal: document.getElementById('stat-total'),
  statPresent: document.getElementById('stat-present'),
  statAbsent: document.getElementById('stat-absent'),
  statEvents: document.getElementById('stat-events'),
  employeeList: document.getElementById('employee-list'),
  eventsBody: document.getElementById('events-body'),
  toast: document.getElementById('toast'),
};

let toastTimer = null;

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

function formatTime(iso) {
  if (!iso) {
    return '-';
  }

  const normalized = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(iso)
    ? `${iso}Z`
    : iso;

  const d = new Date(normalized);
  if (Number.isNaN(d.getTime())) {
    return iso;
  }

  return d.toLocaleString('fr-FR', {
    timeZone: 'Europe/Paris',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    day: '2-digit',
    month: '2-digit',
  });
}

function renderEmployees(employees) {
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
}

function renderEvents(events) {
  els.eventsBody.innerHTML = '';

  events.forEach((event) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${formatTime(event.timestamp)}</td>
      <td>${event.name}</td>
      <td class="code">${event.badge_id}</td>
      <td>${event.event_type === 'in' ? 'Entree' : 'Sortie'}</td>
      <td>${event.source === 'rfid' ? 'RFID' : 'Manuel'}</td>
    `;
    els.eventsBody.append(row);
  });
}

async function loadDashboard() {
  const data = await api('/api/dashboard');

  els.statTotal.textContent = data.summary.employees_total;
  els.statPresent.textContent = data.summary.present;
  els.statAbsent.textContent = data.summary.absent;
  els.statEvents.textContent = data.summary.events_today;

  renderEmployees(data.employees);
  renderEvents(data.events);
}

async function submitRfid(event) {
  event.preventDefault();
  const badge_id = els.badgeInput.value.trim();

  if (!badge_id) {
    return;
  }

  try {
    const res = await api('/api/attendance/rfid', {
      method: 'POST',
      body: JSON.stringify({ badge_id }),
    });
    showToast(res.message);
    els.badgeInput.value = '';
    await loadDashboard();
  } catch (error) {
    showToast(error.message, true);
  } finally {
    els.badgeInput.focus();
  }
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
  els.rfidForm.addEventListener('submit', submitRfid);
  els.btnIn.addEventListener('click', () => submitManual('in'));
  els.btnOut.addEventListener('click', () => submitManual('out'));
  els.refreshBtn.addEventListener('click', () => {
    loadDashboard().catch((error) => showToast(error.message, true));
  });
}

async function boot() {
  wireEvents();
  els.badgeInput.focus();

  try {
    await loadDashboard();
  } catch (error) {
    showToast(error.message, true);
  }
}

boot();
