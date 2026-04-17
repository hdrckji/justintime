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
  statCorrections: document.getElementById('stat-corrections'),
  employeeList: document.getElementById('employee-list'),
  eventsBody: document.getElementById('events-body'),
  toast: document.getElementById('toast'),
  employeeCalendarModal: document.getElementById('employee-calendar-modal'),
  employeeCalendarClose: document.getElementById('employee-calendar-close'),
  employeeCalendarTitle: document.getElementById('employee-calendar-title'),
  employeeCalendarSubtitle: document.getElementById('employee-calendar-subtitle'),
  employeeCalendarPeriod: document.getElementById('employee-calendar-period'),
  employeeCalendarAnchor: document.getElementById('employee-calendar-anchor'),
  employeeCalendarFromWrap: document.getElementById('employee-calendar-from-wrap'),
  employeeCalendarToWrap: document.getElementById('employee-calendar-to-wrap'),
  employeeCalendarFrom: document.getElementById('employee-calendar-from'),
  employeeCalendarTo: document.getElementById('employee-calendar-to'),
  employeeCalendarApply: document.getElementById('employee-calendar-apply'),
  employeeCalendarSummary: document.getElementById('employee-calendar-summary'),
  employeeCalendarGrid: document.getElementById('employee-calendar-grid'),
};

let toastTimer = null;
let autoRefreshTimer = null;
let dashboardRequestInFlight = false;
let calendarState = { employeeId: null };

const AUTO_REFRESH_MS = 15000;

function showToast(message, isError = false) {
  els.toast.textContent = message;
  els.toast.style.background = isError ? '#7f2323' : '#1f2f29';
  els.toast.classList.add('show');
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => els.toast.classList.remove('show'), 2400);
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
  if (!response.ok) throw new Error(payload.error || 'Erreur serveur');
  return payload;
}

function formatDateLabel(isoDate) {
  return new Date(`${isoDate}T00:00:00`).toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
}

function getLocalDateISO(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function formatHours(value) {
  return Number(value || 0).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatTime(iso) {
  const d = new Date(iso);
  return d.toLocaleString('fr-FR', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', day: '2-digit', month: '2-digit'
  });
}

function ensureDateInputValue(input, isoDate) {
  if (input && !input.value) input.value = isoDate;
}

function updateCalendarPeriodInputs() {
  if (!els.employeeCalendarPeriod) return;
  const isCustom = els.employeeCalendarPeriod.value === 'custom';
  if (els.employeeCalendarFromWrap) els.employeeCalendarFromWrap.style.display = isCustom ? 'block' : 'none';
  if (els.employeeCalendarToWrap) els.employeeCalendarToWrap.style.display = isCustom ? 'block' : 'none';
}

function renderEvents(events = []) {
  if (!els.eventsBody) return;
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
      <button type="button" class="employee-link" data-employee-id="${employee.id}" data-employee-name="${employee.name}">
        <p>${employee.name}</p>
      </button>
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
  if (dashboardRequestInFlight && !force) return;
  dashboardRequestInFlight = true;
  try {
    const data = await api('/api/dashboard');
    els.statTotal.textContent = data.summary.employees_total;
    els.statPresent.textContent = data.summary.present;
    els.statAbsent.textContent = data.summary.absent;
    els.statEvents.textContent = data.summary.events_today;
    if (els.statCorrections) els.statCorrections.textContent = data.summary.corrections_pending ?? 0;
    renderEmployees(data.employees);
    renderEvents(data.events || []);
  } finally {
    dashboardRequestInFlight = false;
  }
}

function openEmployeeCalendar(employeeId, employeeName) {
  if (!els.employeeCalendarModal) return;
  calendarState.employeeId = Number(employeeId);
  const today = getLocalDateISO();
  if (els.employeeCalendarPeriod) els.employeeCalendarPeriod.value = 'week';
  if (els.employeeCalendarAnchor) els.employeeCalendarAnchor.value = today;
  if (els.employeeCalendarFrom) els.employeeCalendarFrom.value = today;
  if (els.employeeCalendarTo) els.employeeCalendarTo.value = today;
  updateCalendarPeriodInputs();
  if (els.employeeCalendarTitle) els.employeeCalendarTitle.textContent = `Pointages de ${employeeName}`;
  if (els.employeeCalendarSubtitle) els.employeeCalendarSubtitle.textContent = 'Chargement...';
  els.employeeCalendarModal.classList.add('open');
  els.employeeCalendarModal.setAttribute('aria-hidden', 'false');
  loadEmployeeCalendar().catch((error) => showToast(error.message, true));
}

function closeEmployeeCalendar() {
  if (!els.employeeCalendarModal) return;
  els.employeeCalendarModal.classList.remove('open');
  els.employeeCalendarModal.setAttribute('aria-hidden', 'true');
}

function buildEmployeeCalendarQuery() {
  const params = new URLSearchParams({
    action: 'employee_calendar',
    employee_id: String(calendarState.employeeId || ''),
    period: els.employeeCalendarPeriod?.value || 'week',
    anchor_date: els.employeeCalendarAnchor?.value || getLocalDateISO(),
  });
  if ((els.employeeCalendarPeriod?.value || 'week') === 'custom') {
    params.set('from_date', els.employeeCalendarFrom?.value || '');
    params.set('to_date', els.employeeCalendarTo?.value || '');
  }
  return `/api/dashboard?${params.toString()}`;
}

function renderEmployeeCalendarSummary(days = [], employee = {}, period = {}) {
  if (!els.employeeCalendarSummary) return;
  const totalHours = days.reduce((sum, day) => sum + Number(day.worked_hours || 0), 0);
  const activeDays = days.filter((day) => Number(day.event_count || 0) > 0).length;
  const incompleteDays = days.filter((day) => day.status === 'incomplete').length;
  els.employeeCalendarSummary.innerHTML = `
    <div class="calendar-summary-card"><p>Collaborateur</p><strong>${employee.name || '-'}</strong></div>
    <div class="calendar-summary-card"><p>Période</p><strong>${period.from_date || ''} → ${period.to_date || ''}</strong></div>
    <div class="calendar-summary-card"><p>Heures pointées</p><strong>${formatHours(totalHours)} h</strong></div>
    <div class="calendar-summary-card"><p>Jours avec pointage</p><strong>${activeDays}</strong></div>
    <div class="calendar-summary-card"><p>Jours incomplets</p><strong>${incompleteDays}</strong></div>
  `;
}

function renderEmployeeCalendarGrid(days = [], period = {}) {
  if (!els.employeeCalendarGrid) return;
  if (!days.length) {
    els.employeeCalendarGrid.innerHTML = '<div class="calendar-empty">Aucun jour à afficher sur cette période.</div>';
    return;
  }
  const weekdays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
  const firstWeekday = days[0].weekday === 0 ? 6 : days[0].weekday - 1;
  const useWeekdayGrid = period.type === 'week' || period.type === 'month';
  const headers = useWeekdayGrid ? weekdays.map((label) => `<div class="calendar-weekday">${label}</div>`).join('') : '';
  const spacers = period.type === 'month' ? Array.from({ length: firstWeekday }, () => '<div class="calendar-spacer"></div>').join('') : '';
  const cards = days.map((day) => {
    const eventsHtml = (day.events || []).length
      ? day.events.map((event) => `<span class="calendar-chip ${event.event_type}">${event.time} ${event.event_type === 'in' ? 'Entrée' : 'Sortie'}</span>`).join('')
      : '<span class="calendar-chip">Aucun pointage</span>';
    return `
      <article class="calendar-day ${day.status}">
        <div class="calendar-day-head"><strong>${day.day_number}</strong><span>${day.event_count || 0} pointage(s)</span></div>
        <div class="calendar-day-meta">
          <span>${day.label}</span>
          <span>Première entrée: ${day.first_in || '-'}</span>
          <span>Dernière sortie: ${day.last_out || '-'}</span>
          <span>Total: ${formatHours(day.worked_hours)} h</span>
        </div>
        <div class="calendar-events">${eventsHtml}</div>
      </article>`;
  }).join('');
  const gridClassName = useWeekdayGrid ? 'calendar-grid' : 'calendar-grid calendar-grid-custom';
  els.employeeCalendarGrid.innerHTML = `<div class="${gridClassName}">${headers}${spacers}${cards}</div>`;
}

async function loadEmployeeCalendar() {
  if (!calendarState.employeeId) return;
  updateCalendarPeriodInputs();
  const data = await api(buildEmployeeCalendarQuery());
  renderEmployeeCalendarSummary(data.days || [], data.employee || {}, data.period || {});
  renderEmployeeCalendarGrid(data.days || [], data.period || {});
  if (els.employeeCalendarSubtitle) {
    const startDate = data.period?.from_date || data.period?.anchor_date || getLocalDateISO();
    const endDate = data.period?.to_date || startDate;
    const periodLabel = startDate === endDate
      ? formatDateLabel(startDate)
      : `${formatDateLabel(startDate)} → ${formatDateLabel(endDate)}`;
    els.employeeCalendarSubtitle.textContent = `${data.employee?.badge_id || ''} • ${periodLabel}`;
  }
}

async function submitRfid(event) {
  event.preventDefault();
  const badge_id = els.badgeInput?.value?.trim();
  if (!badge_id) return;
  try {
    const res = await api('/api/rfid', { method: 'POST', body: JSON.stringify({ badge_id }) });
    showToast(res.message);
    els.badgeInput.value = '';
    await loadDashboard(true);
  } catch (error) {
    showToast(error.message, true);
  } finally {
    els.badgeInput?.focus();
  }
}

function startAutoRefresh() {
  if (autoRefreshTimer) clearInterval(autoRefreshTimer);
  autoRefreshTimer = setInterval(() => {
    if (!document.hidden) loadDashboard().catch((error) => showToast(error.message, true));
  }, AUTO_REFRESH_MS);
}

async function submitManual(eventType) {
  const employee_id = Number(els.employeeSelect.value);
  if (!employee_id) {
    showToast('Selectionnez un employe.', true);
    return;
  }
  try {
    const res = await api('/api/attendance/manual', { method: 'POST', body: JSON.stringify({ employee_id, event_type: eventType }) });
    showToast(res.message);
    await loadDashboard();
  } catch (error) {
    showToast(error.message, true);
  }
}

function wireEvents() {
  if (els.rfidForm) els.rfidForm.addEventListener('submit', submitRfid);
  els.btnIn.addEventListener('click', () => submitManual('in'));
  els.btnOut.addEventListener('click', () => submitManual('out'));
  els.refreshBtn.addEventListener('click', () => loadDashboard(true).catch((error) => showToast(error.message, true)));
  if (els.employeeList) {
    els.employeeList.addEventListener('click', (event) => {
      const button = event.target.closest('.employee-link');
      if (!button) return;
      openEmployeeCalendar(button.dataset.employeeId, button.dataset.employeeName || 'Collaborateur');
    });
  }
  if (els.employeeCalendarClose) els.employeeCalendarClose.addEventListener('click', closeEmployeeCalendar);
  if (els.employeeCalendarModal) {
    els.employeeCalendarModal.addEventListener('click', (event) => {
      if (event.target === els.employeeCalendarModal) closeEmployeeCalendar();
    });
  }
  if (els.employeeCalendarApply) els.employeeCalendarApply.addEventListener('click', () => loadEmployeeCalendar().catch((error) => showToast(error.message, true)));
  if (els.employeeCalendarPeriod) els.employeeCalendarPeriod.addEventListener('change', updateCalendarPeriodInputs);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) loadDashboard(true).catch((error) => showToast(error.message, true));
  });
  window.addEventListener('focus', () => loadDashboard(true).catch((error) => showToast(error.message, true)));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeEmployeeCalendar(); });
}

async function boot() {
  wireEvents();
  startAutoRefresh();
  updateCalendarPeriodInputs();
  if (els.badgeInput) els.badgeInput.focus(); else els.employeeSelect.focus();
  try {
    await loadDashboard(true);
  } catch (error) {
    showToast(error.message, true);
  }
}

boot();
