const els = {
  employeeSelect: document.getElementById('employee-select'),
  employeeAssignSelect: document.getElementById('employee-assign-select'),
  departmentSelect: document.getElementById('department-select'),
  departmentForm: document.getElementById('department-form'),
  assignmentForm: document.getElementById('assignment-form'),
  departmentId: document.getElementById('department-id'),
  departmentName: document.getElementById('department-name'),
  departmentHours: document.getElementById('department-hours'),
  departmentList: document.getElementById('department-list'),
  departmentStats: document.getElementById('department-stats'),
  btnDepartmentSave: document.getElementById('btn-department-save'),
  btnDepartmentReset: document.getElementById('btn-department-reset'),
  btnRemoveDepartment: document.getElementById('btn-remove-department'),
  btnIn: document.getElementById('btn-in'),
  btnOut: document.getElementById('btn-out'),
  refreshBtn: document.getElementById('refresh-btn'),
  statTotal: document.getElementById('stat-total'),
  statPresent: document.getElementById('stat-present'),
  statAbsent: document.getElementById('stat-absent'),
  statEvents: document.getElementById('stat-events'),
  statDepartments: document.getElementById('stat-departments'),
  employeeList: document.getElementById('employee-list'),
  eventsBody: document.getElementById('events-body'),
  toast: document.getElementById('toast'),
};

let toastTimer = null;
let autoRefreshTimer = null;
let dashboardRequestInFlight = false;

const AUTO_REFRESH_MS = 15000;
const UNASSIGNED_LABEL = 'Sans departement';

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

function formatHours(value) {
  return `${Number(value || 0).toFixed(1).replace('.', ',')} h`;
}

function resetDepartmentForm() {
  els.departmentId.value = '';
  els.departmentName.value = '';
  els.departmentHours.value = '35';
  els.btnDepartmentSave.textContent = 'Enregistrer';
}

function fillDepartmentForm(department) {
  els.departmentId.value = department.id;
  els.departmentName.value = department.name;
  els.departmentHours.value = department.weekly_target_hours ?? 35;
  els.btnDepartmentSave.textContent = 'Mettre a jour';
}

function populateEmployeeSelect(selectEl, employees, previousSelection) {
  selectEl.innerHTML = '';

  employees.forEach((employee) => {
    const option = document.createElement('option');
    option.value = employee.id;
    option.textContent = `${employee.name} — ${employee.department_name || UNASSIGNED_LABEL}`;
    selectEl.append(option);
  });

  if (previousSelection && employees.some((employee) => String(employee.id) === String(previousSelection))) {
    selectEl.value = previousSelection;
  } else if (employees.length) {
    selectEl.value = String(employees[0].id);
  }
}

function renderEmployees(employees) {
  const previousManualSelection = els.employeeSelect.value;
  const previousAssignSelection = els.employeeAssignSelect.value;

  populateEmployeeSelect(els.employeeSelect, employees, previousManualSelection);
  populateEmployeeSelect(els.employeeAssignSelect, employees, previousAssignSelection);

  els.employeeList.innerHTML = '';

  employees.forEach((employee) => {
    const item = document.createElement('article');
    item.className = 'employee-item';
    item.innerHTML = `
      <p>${employee.name}</p>
      <small>${employee.badge_id}</small>
      <div class="employee-meta">${employee.department_name || UNASSIGNED_LABEL}</div>
      <span class="status ${employee.status}">${employee.status}</span>
    `;
    els.employeeList.append(item);
  });
}

function renderDepartments(departments) {
  const previousDepartmentSelection = els.departmentSelect.value;

  els.departmentSelect.innerHTML = `<option value="">${UNASSIGNED_LABEL}</option>`;
  els.departmentList.innerHTML = '';

  if (!departments.length) {
    els.departmentList.innerHTML = '<p class="muted">Aucun departement pour le moment.</p>';
    return;
  }

  departments.forEach((department) => {
    const option = document.createElement('option');
    option.value = department.id;
    option.textContent = `${department.name} (${department.employee_count} collab.)`;
    els.departmentSelect.append(option);

    const item = document.createElement('article');
    item.className = 'department-item';
    item.innerHTML = `
      <div>
        <strong>${department.name}</strong>
        <div class="department-meta">${department.employee_count} collab. • ${formatHours(department.weekly_target_hours)} / semaine</div>
      </div>
      <button type="button" class="ghost" data-action="edit-department" data-id="${department.id}" data-name="${department.name}" data-hours="${department.weekly_target_hours}">Modifier</button>
    `;
    els.departmentList.append(item);
  });

  if ([...els.departmentSelect.options].some((option) => option.value === previousDepartmentSelection)) {
    els.departmentSelect.value = previousDepartmentSelection;
  }
}

function renderDepartmentStats(stats) {
  els.departmentStats.innerHTML = '';

  if (!stats.length) {
    els.departmentStats.innerHTML = '<p class="muted">Aucune statistique de departement disponible.</p>';
    return;
  }

  stats.forEach((department) => {
    const presentMembers = department.present_members && department.present_members.length
      ? department.present_members.join(', ')
      : 'Aucun pour le moment';

    const item = document.createElement('article');
    item.className = 'department-stat-card';
    item.innerHTML = `
      <div class="panel-head compact">
        <div>
          <h3>${department.name}</h3>
          <p class="muted">${department.present_count}/${department.employees_total} presents</p>
        </div>
        <span class="pill">${department.employees_total} collab.</span>
      </div>
      <p class="department-people"><strong>Presents :</strong> ${presentMembers}</p>
      <dl>
        <div><dt>Prevu / jour</dt><dd>${formatHours(department.planned_hours_day)}</dd></div>
        <div><dt>Preste / jour</dt><dd>${formatHours(department.worked_hours_day)}</dd></div>
        <div><dt>Prevu / semaine</dt><dd>${formatHours(department.planned_hours_week)}</dd></div>
        <div><dt>Preste / semaine</dt><dd>${formatHours(department.worked_hours_week)}</dd></div>
        <div><dt>Prevu / mois</dt><dd>${formatHours(department.planned_hours_month)}</dd></div>
        <div><dt>Preste / mois</dt><dd>${formatHours(department.worked_hours_month)}</dd></div>
        <div><dt>Taux d'absenteisme</dt><dd>${department.absenteeism_rate}%</dd></div>
      </dl>
    `;
    els.departmentStats.append(item);
  });
}

function renderEvents(events) {
  els.eventsBody.innerHTML = '';

  events.forEach((event) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${formatTime(event.timestamp)}</td>
      <td>${event.name}</td>
      <td>${event.department_name || UNASSIGNED_LABEL}</td>
      <td class="code">${event.badge_id}</td>
      <td>${event.event_type === 'in' ? 'Entree' : 'Sortie'}</td>
      <td>${event.source === 'rfid' ? 'RFID' : 'Manuel'}</td>
    `;
    els.eventsBody.append(row);
  });
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
    els.statDepartments.textContent = data.summary.departments_total ?? (data.departments || []).length;

    renderEmployees(data.employees || []);
    renderDepartments(data.departments || []);
    renderDepartmentStats(data.department_stats || []);
    renderEvents(data.events || []);
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
    showToast('Selectionnez un collaborateur.', true);
    return;
  }

  try {
    const res = await api('/api/attendance/manual', {
      method: 'POST',
      body: JSON.stringify({ employee_id, event_type: eventType }),
    });
    showToast(res.message);
    await loadDashboard(true);
  } catch (error) {
    showToast(error.message, true);
  }
}

async function submitDepartmentForm(event) {
  event.preventDefault();

  const departmentId = els.departmentId.value.trim();
  const name = els.departmentName.value.trim();
  const weekly_target_hours = Number(els.departmentHours.value || 35);

  if (!name) {
    showToast('Indique un nom de departement.', true);
    return;
  }

  try {
    const response = await api(departmentId ? `/api/departments/${departmentId}` : '/api/departments', {
      method: departmentId ? 'PUT' : 'POST',
      body: JSON.stringify({ name, weekly_target_hours }),
    });
    showToast(response.message);
    resetDepartmentForm();
    await loadDashboard(true);
  } catch (error) {
    showToast(error.message, true);
  }
}

async function updateEmployeeDepartment(remove = false) {
  const employeeId = Number(els.employeeAssignSelect.value);
  if (!employeeId) {
    showToast('Selectionnez un collaborateur.', true);
    return;
  }

  const departmentId = remove ? null : (els.departmentSelect.value || null);

  try {
    const response = await api(`/api/employees/${employeeId}/department`, {
      method: 'POST',
      body: JSON.stringify({ department_id: departmentId }),
    });
    showToast(response.message);
    if (remove) {
      els.departmentSelect.value = '';
    }
    await loadDashboard(true);
  } catch (error) {
    showToast(error.message, true);
  }
}

function wireEvents() {
  els.departmentForm.addEventListener('submit', submitDepartmentForm);
  els.assignmentForm.addEventListener('submit', (event) => {
    event.preventDefault();
    updateEmployeeDepartment(false);
  });

  els.btnDepartmentReset.addEventListener('click', resetDepartmentForm);
  els.btnRemoveDepartment.addEventListener('click', () => updateEmployeeDepartment(true));
  els.btnIn.addEventListener('click', () => submitManual('in'));
  els.btnOut.addEventListener('click', () => submitManual('out'));
  els.refreshBtn.addEventListener('click', () => {
    loadDashboard(true).catch((error) => showToast(error.message, true));
  });

  els.departmentList.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="edit-department"]');
    if (!button) {
      return;
    }

    fillDepartmentForm({
      id: button.dataset.id,
      name: button.dataset.name,
      weekly_target_hours: button.dataset.hours,
    });
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
  resetDepartmentForm();

  try {
    await loadDashboard(true);
  } catch (error) {
    showToast(error.message, true);
  }
}

boot();
