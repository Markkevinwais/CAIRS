import { db, setting } from './store.js';

export const BASE_URL = '';

export function e(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

export function nl2br(str) {
  return e(str).replace(/\r?\n/g, '<br>');
}

export function refNo(id) {
  return 'CAIRS-' + String(id).padStart(5, '0');
}

export function fmtDate(d) {
  const dt = new Date(d);
  if (Number.isNaN(dt.getTime())) return '';
  return dt.toLocaleString('en-US', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

export function fmtManilaClock() {
  return new Date().toLocaleString('en-US', {
    timeZone: 'Asia/Manila',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

export function fmtManilaDateLong() {
  return new Date().toLocaleDateString('en-US', {
    timeZone: 'Asia/Manila',
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

export function statusLabel(s) {
  return String(s || '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

export function badge(s) {
  return `<span class="badge s-${e(s)}">${e(statusLabel(s))}</span>`;
}

export function prio(p) {
  const str = String(p || 'medium');
  const cap = str.charAt(0).toUpperCase() + str.slice(1);
  return `<span class="badge p-${e(str)}">${e(cap)}</span>`;
}

export function homeFor(role) {
  const map = {
    resident: 'resident/dashboard.php',
    admin: 'admin/dashboard.php',
    volunteer: 'volunteer/dashboard.php',
  };
  return map[role] || 'login.php';
}

export function initials(name) {
  const words = String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
  const out = words.map((w) => w.charAt(0).toUpperCase()).join('');
  return out || '?';
}

export const AVATAR_BADGE_STYLES = [
  { id: 'initials', label: 'Primary', kind: 'initials' },
  { id: 'indigo', label: 'Indigo', kind: 'initials' },
  { id: 'emerald', label: 'Emerald', kind: 'initials' },
  { id: 'amber', label: 'Amber', kind: 'initials' },
  { id: 'rose', label: 'Rose', kind: 'initials' },
  { id: 'shield', label: 'Shield', kind: 'icon', ic: 'shield' },
  { id: 'responder', label: 'Responder', kind: 'icon', ic: 'radar' },
  { id: 'rings', label: 'Highlight', kind: 'icon', ic: 'sparkles' },
  { id: 'shapes', label: 'Shapes', kind: 'icon', ic: 'shapes' },
  { id: 'grid', label: 'Grid', kind: 'icon', ic: 'grid' },
];

export function sanitizeAvatarUrl(url) {
  const raw = String(url || '').trim();
  if (!raw) return '';
  if (/^\/assets\/uploads\/[A-Za-z0-9_.\-]+$/.test(raw)) return raw;
  try {
    const parsed = new URL(raw);
    if (parsed.protocol === 'https:' || parsed.protocol === 'http:') return parsed.href;
  } catch {}
  return '';
}

export function renderAvatar(profile, extraClass = '', withStatus = false) {
  const name = profile?.full_name || '';
  const init = initials(name);
  const type = profile?.avatar_type === 'image' ? 'image' : 'badge';
  const styleId = AVATAR_BADGE_STYLES.some((s) => s.id === profile?.avatar_style) ? profile.avatar_style : 'initials';
  const shape = profile?.avatar_shape === 'badge' ? 'badge' : 'circle';
  const imgUrl = type === 'image' ? sanitizeAvatarUrl(profile?.avatar_url) : '';
  const styleMeta = AVATAR_BADGE_STYLES.find((s) => s.id === styleId) || AVATAR_BADGE_STYLES[0];

  const classes = ['avatar'];
  if (extraClass) classes.push(extraClass);
  classes.push(shape === 'badge' ? 'av-shape-badge' : 'av-shape-circle');
  if (imgUrl) {
    classes.push('av-has-img');
  } else {
    classes.push('av-style-' + styleId);
  }

  let inner = '';
  if (imgUrl) {
    inner = `<img class="avatar-img" src="${e(imgUrl)}" alt="${e(name)}">`;
  } else if (styleMeta.kind === 'icon') {
    const sz = extraClass.includes('avatar-xl') ? 28 : extraClass.includes('avatar-lg') ? 22 : 17;
    inner = `<span class="avatar-glyph" aria-hidden="true">${icon(styleMeta.ic, sz)}</span>`;
  } else {
    inner = `<span data-user-initials>${e(init)}</span>`;
  }

  const statusHtml = withStatus ? '<i class="avatar-status" aria-hidden="true"></i>' : '';
  return `<span class="${classes.join(' ')}" data-user-avatar>${inner}${statusHtml}</span>`;
}

export function greeting() {
  const hourStr = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    hour12: false,
  }).format(new Date());
  const h = parseInt(hourStr, 10) || 0;
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
}

export function statusTones() {
  return {
    pending: 'warning',
    assigned: 'info',
    in_progress: 'progress',
    resolved: 'success',
    rejected: 'danger',
  };
}

export function priorityTones() {
  return { high: 'danger', medium: 'warning', low: 'muted' };
}

export function defaultPriority(categoryName) {
  const high = ['fire', 'medical emergency', 'flood', 'road accident', 'theft / crime'];
  const low = ['noise disturbance', 'garbage / sanitation', 'broken streetlight / road'];
  const n = String(categoryName || '').trim().toLowerCase();
  if (high.includes(n)) return 'high';
  if (low.includes(n)) return 'low';
  return 'medium';
}

export function isOverdue(r) {
  return r?.status === 'pending' && new Date(r.created_at).getTime() < Date.now() - 86400 * 1000;
}

export function csvSafe(v) {
  const s = String(v ?? '');
  return /^[=+\-@\t\r]/.test(s) ? "'" + s : s;
}

export function icon(name, size = 20) {
  const p = {
    dashboard: '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
    file: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    plus: '<circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/>',
    list: '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
    bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
    chart: '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
    sliders: '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    clipboard: '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
    pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    menu: '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    loader: '<path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/>',
    check: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
    tag: '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    siren: '<path d="M7 18v-6a5 5 0 1 1 10 0v6"/><path d="M5 21a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-1a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2z"/><path d="M21 12h1"/><path d="M18.5 4.5 18 5"/><path d="M2 12h1"/><path d="M12 2v1"/><path d="m4.929 4.929.707.707"/><path d="M12 12v6"/>',
    megaphone: '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
    flame: '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
    radar: '<path d="M19.07 4.93A10 10 0 0 0 6.99 3.34"/><path d="M4 6h.01"/><path d="M2.29 9.62A10 10 0 1 0 21.31 8.35"/><path d="M16.24 7.76A6 6 0 1 0 8.23 16.67"/><path d="M12 18h.01"/><path d="M17.99 11.66A6 6 0 0 1 15.77 16.67"/><circle cx="12" cy="12" r="2"/><path d="m13.41 10.59 5.66-5.66"/>',
    calendar: '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
    trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    'check-double': '<path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/>',
    x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    'arrow-right': '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    'panel-close': '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="m16 15-3-3 3-3"/>',
    'panel-open': '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="m14 9 3 3-3 3"/>',
    'chevrons-up-down': '<path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/>',
    mail: '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
    phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
    lock: '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    key: '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
    camera: '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/>',
    crop: '<path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    sparkles: '<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>',
    shapes: '<path d="M8.3 10a.7.7 0 0 1-.626-1.079L11.4 3a.7.7 0 0 1 1.198-.043L16.3 8.9a.7.7 0 0 1-.572 1.1Z"/><rect x="3" y="14" width="7" height="7" rx="1"/><circle cx="17.5" cy="17.5" r="3.5"/>',
    grid: '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
    'check-simple': '<path d="M20 6 9 17l-5-5"/>',
  };
  return `<svg class="icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${p[name] || p.file}</svg>`;
}

export function renderWelcome(name, subtitle, actionsHtml = '') {
  return `<section class="welcome"><div><p class="eyebrow" data-clock-date>${e(fmtManilaDateLong())}</p><h1>${e(greeting())}, ${e(name)}</h1><p class="muted">${e(subtitle)}</p></div><div class="welcome-side"><span class="clock" data-clock>${e(fmtManilaClock())}</span>${actionsHtml}</div></section>`;
}

export function statCard(ic, label, value, note = '', tone = 'primary', trend = null) {
  const trendHtml = trend ? `<span class="trend ${e(trend[0])}">${e(trend[1])}</span>` : '';
  return `<div class="stat tone-${e(tone)}"><span class="stat-label">${e(label)}</span><span class="stat-icon">${icon(ic, 22)}</span><b>${e(value)}</b><span class="stat-note">${trendHtml}${e(note)}</span></div>`;
}

export function renderReportCard(r, link) {
  const pr = r.priority || 'medium';
  const sosCls = r.is_sos ? ' rc-sos' : '';
  const sosBadge = r.is_sos ? ' <span class="badge sos">SOS</span>' : '';
  const overdueBadge = isOverdue(r) ? ' <span class="badge overdue">Overdue</span>' : '';
  let meta = '';
  if (r.category) meta += `<li>${icon('tag', 15)}${e(r.category)}</li>`;
  if (r.location) meta += `<li>${icon('pin', 15)}${e(r.location)}</li>`;
  meta += `<li>${icon('calendar', 15)}${e(fmtDate(r.created_at))}</li>`;
  if (r.resident_name) meta += `<li>${icon('user', 15)}Reported by ${e(r.resident_name)}</li>`;
  meta += `<li>${icon('shield', 15)}${r.volunteer_name ? 'Assigned to ' + e(r.volunteer_name) : '<span>Not assigned yet</span>'}</li>`;

  return `<article class="report-card rc-${e(pr)}${sosCls}"><div class="rc-top"><span class="rc-ref">${refNo(r.id)}${sosBadge}${overdueBadge}</span>${badge(r.status)}</div><h3 class="rc-title"><a href="${e(link)}">${e(r.title)}</a></h3><ul class="rc-meta">${meta}</ul><div class="rc-foot">${prio(pr)}<a class="btn ghost small" href="${e(link)}">View details</a></div></article>`;
}

export function renderBars(dataObj, tones = {}) {
  const entries = Object.entries(dataObj || {});
  if (!entries.length) return '<p class="empty">No data yet.</p>';
  const values = entries.map(([, n]) => Number(n));
  const total = Math.max(1, values.reduce((a, b) => a + b, 0));
  const max = Math.max(1, ...values);
  return entries
    .map(([label, raw]) => {
      const n = Number(raw);
      const pct = Math.round((n / total) * 100);
      const w = n > 0 ? Math.max(3, Math.round((n / max) * 100)) : 0;
      const barStyle = n > 0 ? `width:${w}%` : 'width:0;min-width:0';
      return `<div class="bar" title="${e(statusLabel(label))}: ${n} (${pct}%)"><span class="l">${e(statusLabel(label))}</span><span class="track"><i class="tone-${e(tones[label] || 'primary')}" style="${barStyle}"></i></span><b>${n}</b><em>${pct}%</em></div>`;
    })
    .join('');
}

export function renderDonut(counts) {
  const tones = statusTones();
  const total = Object.values(counts || {}).reduce((a, b) => a + Number(b), 0);
  const parts = [];
  let acc = 0;
  if (total > 0) {
    for (const [s, t] of Object.entries(tones)) {
      const n = Number(counts[s] || 0);
      if (!n) continue;
      const from = Math.round(acc * 100) / 100;
      acc += (n / total) * 100;
      const to = Math.round(acc * 100) / 100;
      parts.push(`var(--${t}) ${from}% ${to}%`);
    }
  }
  const bg = parts.length ? `conic-gradient(${parts.join(',')})` : 'var(--border)';
  const legend = Object.keys(tones)
    .map((s) => `<li><i class="dot dot-${s}"></i>${e(statusLabel(s))}<b>${Number(counts[s] || 0)}</b></li>`)
    .join('');
  return `<div class="donut-wrap"><div class="donut" style="background:${bg}"><div class="donut-center"><b>${total}</b><span>reports</span></div></div><ul class="legend-list">${legend}</ul></div>`;
}

export function renderColumns(dataInput, tone = 'primary') {
  const items = Array.isArray(dataInput)
    ? dataInput.map((d) => ({
        label: String(d.label ?? ''),
        subLabel: String(d.subLabel ?? ''),
        ym: String(d.ym ?? ''),
        fullLabel: String(d.fullLabel ?? d.label ?? ''),
        value: Number(d.value ?? 0),
        resolved: d.resolved !== undefined ? Number(d.resolved) : null,
      }))
    : Object.entries(dataInput || {}).map(([k, v]) => {
        const parts = String(k).split(' ');
        return {
          label: parts[0] || String(k),
          subLabel: parts.slice(1).join(' '),
          ym: '',
          fullLabel: String(k),
          value: Number(v || 0),
          resolved: null,
        };
      });

  if (!items.length) return '<p class="empty">No data yet.</p>';

  const values = items.map((it) => it.value);
  const maxVal = Math.max(0, ...values);
  const totalSum = values.reduce((a, b) => a + b, 0);
  const avgVal = items.length ? (totalSum / items.length).toFixed(1) : '0.0';
  const peakItem = items.reduce((best, cur) => (cur.value > best.value ? cur : best), items[0]);

  let step = 1;
  if (maxVal > 4) {
    step = Math.ceil(maxVal / 4);
  }
  const maxTick = step * 4;
  const ticks = [maxTick, step * 3, step * 2, step, 0];

  const summaryHtml = `<div class="chart-summary">
    <span><strong>${totalSum}</strong> in period</span>
    <span class="sep" aria-hidden="true">&middot;</span>
    <span><strong>${avgVal}</strong> / month avg</span>
    <span class="sep" aria-hidden="true">&middot;</span>
    <span>Peak: <strong>${peakItem.value > 0 ? e(peakItem.fullLabel) + ' (' + peakItem.value + ')' : 'None'}</strong></span>
  </div>`;

  const yAxisHtml = `<div class="col-yaxis" aria-hidden="true">${ticks.map((t) => `<span>${t}</span>`).join('')}</div>`;

  const gridLinesHtml = `<div class="col-gridlines" aria-hidden="true">${ticks
    .map((_, idx) => `<span class="${idx === ticks.length - 1 ? 'is-base' : ''}"></span>`)
    .join('')}</div>`;

  const colsHtml = items
    .map((it) => {
      const pct = maxTick > 0 ? (it.value / maxTick) * 100 : 0;
      const h = it.value > 0 ? Math.max(3, Math.round(pct * 10) / 10) : 0;
      const share = totalSum > 0 ? Math.round((it.value / totalSum) * 100) : 0;
      const resNote = it.resolved !== null ? `, ${it.resolved} resolved` : '';
      const tip = `${it.fullLabel}: ${it.value} report${it.value === 1 ? '' : 's'} (${share}%${resNote})`;
      const zeroCls = it.value === 0 ? ' is-zero' : '';
      const tipBottom = it.value > 0 ? `calc(${h}% + 26px)` : '28px';
      const tipMeta = it.ym || it.subLabel || '';
      const resolvedRow =
        it.resolved !== null
          ? `<div class="col-tip-row"><span class="col-tip-k"><i class="dot dot-resolved"></i>Resolved</span><b class="col-tip-v tone-emerald">${it.resolved}</b></div>`
          : '';
      return `<div class="col tone-${e(tone)}${zeroCls}" tabindex="0" aria-label="${e(tip)}">
        <div class="col-plot">
          <div class="col-tip" role="tooltip" style="bottom:${tipBottom}">
            <div class="col-tip-head"><strong>${e(it.label)}</strong>${tipMeta ? `<span>${e(tipMeta)}</span>` : ''}</div>
            <div class="col-tip-rows">
              <div class="col-tip-row"><span class="col-tip-k"><i class="dot dot-reports"></i>Reports</span><b class="col-tip-v tone-primary">${it.value}</b></div>
              ${resolvedRow}
            </div>
          </div>
          <span class="col-val" style="bottom:${it.value > 0 ? `calc(${h}% + 6px)` : '8px'}">${it.value}</span>
          <span class="col-track"><i style="height:${h}%"></i></span>
        </div>
        <span class="col-label"><b>${e(it.label)}</b>${it.subLabel ? `<small>${e(it.subLabel)}</small>` : ''}</span>
      </div>`;
    })
    .join('');

  return `<div class="col-chart-wrap">
    ${summaryHtml}
    <div class="col-chart">
      ${yAxisHtml}
      <div class="col-canvas">
        ${gridLinesHtml}
        <div class="cols" style="grid-template-columns:repeat(${items.length},minmax(0,1fr))">${colsHtml}</div>
      </div>
    </div>
  </div>`;
}

export function renderActivity(rows, linkBase) {
  if (!rows || !rows.length) return '<p class="empty">No activity yet.</p>';
  const items = rows
    .map(
      (a) =>
        `<li><a href="${e(linkBase + a.report_id)}"><strong>${refNo(a.report_id)}</strong> ${e(a.title)}</a> ${badge(a.status)}<br><span class="muted">${e(fmtDate(a.created_at))} by ${e(a.full_name)}</span>${a.remark ? `<p>${e(a.remark)}</p>` : ''}</li>`
    )
    .join('');
  return `<ol class="timeline">${items}</ol>`;
}

export function renderTimeline(reportId) {
  const rows = db.report_updates
    .filter((ru) => ru.report_id === Number(reportId))
    .sort((a, b) => b.id - a.id)
    .map((ru) => {
      const u = db.users.find((user) => user.id === ru.user_id);
      return { ...ru, full_name: u ? u.full_name : 'System' };
    });
  const items = rows
    .map(
      (r) =>
        `<li>${badge(r.status)} <span class="muted">${e(fmtDate(r.created_at))} by ${e(r.full_name)}</span>${r.remark ? `<p>${e(r.remark)}</p>` : ''}</li>`
    )
    .join('');
  return `<ol class="timeline">${items}</ol>`;
}

export function renderAnnouncements(limit = 3) {
  const rows = db.announcements
    .filter((a) => a.is_active === 1)
    .sort((a, b) => b.id - a.id)
    .slice(0, limit)
    .map((a) => {
      const u = db.users.find((user) => user.id === a.user_id);
      return { ...a, full_name: u ? u.full_name : 'Admin' };
    });
  if (!rows.length) return '';
  const items = rows
    .map(
      (a) =>
        `<article class="ann ann-${e(a.level)}"><span class="ann-ic">${icon(a.level === 'critical' ? 'siren' : 'megaphone', 20)}</span><div><strong>${e(a.title)}</strong><p>${nl2br(a.body)}</p><span class="muted">${e(fmtDate(a.created_at))} &middot; ${e(a.full_name)}</span></div></article>`
    )
    .join('');
  return `<section class="alerts-strip" aria-label="Community announcements">${items}</section>`;
}

export function renderHotlines() {
  const items = [
    ['Emergency', setting('emergency_hotline', '911'), 'siren'],
    ['Barangay', setting('contact_phone', ''), 'shield'],
    ['Fire', setting('hotline_fire', ''), 'flame'],
    ['Police', setting('hotline_police', ''), 'shield'],
    ['Medical', setting('hotline_medical', ''), 'plus'],
  ];
  const links = items
    .filter(([, num]) => num !== '')
    .map(
      ([label, num, ic]) =>
        `<a class="hotline" href="tel:${e(num.replace(/[^0-9+]/g, ''))}">${icon(ic, 20)}<span><small>${e(label)}</small><b>${e(num)}</b></span></a>`
    )
    .join('');
  return `<div class="hotlines">${links}</div>`;
}

export function mapAssets() {
  return '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"><script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>';
}

export function mapCenter() {
  return [parseFloat(setting('map_lat', '8.4542')) || 8.4542, parseFloat(setting('map_lng', '124.6319')) || 124.6319];
}

export function renderMapPicker(lat = '', lng = '') {
  const has = lat !== '' && lng !== '' && !Number.isNaN(Number(lat)) && !Number.isNaN(Number(lng));
  const [cl, cn] = has ? [Number(lat), Number(lng)] : mapCenter();
  return `${mapAssets()}<div class="map-card"><div class="map-head"><strong>Pin the location</strong><span class="legend"><span class="legend-item"><i class="dot tone-primary"></i>Click the map to drop a pin</span></span></div><div id="map-picker" class="map" data-lat="${cl}" data-lng="${cn}" data-pin="${has ? 1 : 0}"></div><input type="hidden" id="latitude" name="latitude" value="${e(has ? lat : '')}"><input type="hidden" id="longitude" name="longitude" value="${e(has ? lng : '')}"><div class="map-foot"><button type="button" id="locate" class="btn ghost small">${icon('pin', 15)} Use my current location</button><span class="muted">The pin helps volunteers find the exact spot.</span></div></div>`;
}

export function renderMapView(r) {
  if (r.latitude === null || r.longitude === null || r.latitude === undefined || r.longitude === undefined) return '';
  const lat = Number(r.latitude);
  const lng = Number(r.longitude);
  return `${mapAssets()}<div class="map-card"><div class="map-head"><strong>Pinned location</strong><a target="_blank" rel="noopener" href="https://www.google.com/maps?q=${lat},${lng}">Open in Google Maps</a></div><div id="map-view" class="map" data-lat="${lat}" data-lng="${lng}"></div><div class="map-foot"><span class="legend-item"><i class="dot tone-primary"></i>Reported location</span><span class="muted">${e(lat.toFixed(5))}, ${e(lng.toFixed(5))}</span></div></div>`;
}

export function incidentPoints() {
  return db.reports
    .filter((p) => p.latitude !== null && p.longitude !== null)
    .map((p) => ({
      lat: Number(p.latitude),
      lng: Number(p.longitude),
      ref: refNo(p.id),
      title: p.title,
      key: p.status,
      status: statusLabel(p.status),
      url: `${BASE_URL}/admin/report_details.php?id=${p.id}`,
    }));
}

export function renderIncidentMap(title = 'Incident map', height = 420) {
  const pts = incidentPoints();
  const [mlat, mlng] = mapCenter();
  const legend = ['pending', 'assigned', 'in_progress', 'resolved', 'rejected']
    .map((s) => `<span class="legend-item"><i class="dot dot-${s}"></i>${e(statusLabel(s))}</span>`)
    .join('');
  let body = '';
  if (!pts.length) {
    body = '<p class="empty">No reports have a map pin yet.</p>';
  } else {
    body = `${mapAssets()}<div id="map-all" class="map" style="height:${Number(height)}px" data-lat="${mlat}" data-lng="${mlng}" data-points="${e(JSON.stringify(pts))}"></div>`;
  }
  return `<section class="card map-card" id="incident-map"><div class="map-head"><div><h2>${e(title)}</h2><p class="muted">${pts.length} report${pts.length === 1 ? '' : 's'} with a map pin</p></div><div class="legend">${legend}</div></div>${body}</section>`;
}

export function renderAlerts(userId) {
  const rows = db.notifications
    .filter((n) => n.user_id === Number(userId) && n.is_read === 0)
    .sort((a, b) => b.id - a.id)
    .slice(0, 8);
  if (!rows.length) return '';
  const items = rows
    .map((n) => {
      const link = n.report_id ? `report_details.php?id=${Number(n.report_id)}` : '#';
      return `<a class="notif unread" href="${link}">${e(n.message)}<br><span class="muted">${e(fmtDate(n.created_at))}</span></a>`;
    })
    .join('');
  return `<div class="card"><h2>New alerts</h2>${items}</div>`;
}
