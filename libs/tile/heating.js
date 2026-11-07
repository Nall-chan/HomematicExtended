/* Heizungsmodule – Kachel: Wählrad für die Solltemperatur, Betriebsart (Automatik, Manuell, Party), Boost und der Wochenplan.
   Die Instanz liefert die Rollen (Soll, Ist, Modus, Boost …) mit dem Ident ihrer Statusvariable und den aktuellen Werten.
   Rollen ohne Statusvariable (in der Konfiguration abgewählt) fehlen, die Kachel blendet das Bedienelement aus.
   Bedient wird über requestAction mit dem Ident der Statusvariable, der Wochenplan über die Aktion TileSave.
   Die Größe der Kachel bestimmt, was angezeigt wird:
   mini = Wählrad, compact = Wählrad, Betriebsart und Chips, wide = zusätzlich der Wochenplan, full = Bedienfeld und Wochenplan bearbeiten. */
(function () {
  'use strict';
  const cfg = window.HT_CONFIG || {};
  const texts = cfg.texts || {};
  const T = key => texts[key] || key;
  const app = document.getElementById('app');
  const boostMode = (cfg.boostMode === null || cfg.boostMode === undefined) ? null : cfg.boostMode;
  // Überschrift der Kachel: Die Visu übergibt in der Adresse margintop (mit Titel 60, ohne 15) und legt den Titel über die Kachel.
  try {
    const top = Number(new URLSearchParams(location.search).get('margintop'));
    if (top > 15) {
      document.documentElement.style.setProperty('--title-space', (top - 15) + 'px');
      document.documentElement.style.setProperty('--corner', '0px');
    }
  } catch (e) { /* ohne Angabe: kein Titel */ }

  const ICON = {
    flame: 'M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z',
    hand: 'M23 5.5V20c0 2.2-1.8 4-4 4h-7.3c-1.08 0-2.1-.43-2.85-1.19L1 14.83s1.26-1.23 1.3-1.25c.22-.19.49-.29.79-.29.22 0 .42.06.6.16.04.01 4.31 2.46 4.31 2.46V4c0-.83.67-1.5 1.5-1.5S11 3.17 11 4v7h1V1.5c0-.83.67-1.5 1.5-1.5S15 .67 15 1.5V11h1V2.5c0-.83.67-1.5 1.5-1.5S19 1.67 19 2.5V11h1V5.5c0-.83.67-1.5 1.5-1.5S23 4.67 23 5.5z',
    clock: 'M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z',
    party: 'M2 22l14-5-9-9-5 14zm12.53-9.47l5.47-5.47c.78-.78.78-2.05 0-2.83l-.17-.17-2.12 2.12-1.41-1.41 2.12-2.12-.17-.17c-.78-.78-2.05-.78-2.83 0l-5.47 5.47 4.58 4.58z',
    window: 'M3 3v18h18V3H3zm8 16H5v-6h6v6zm0-8H5V5h6v6zm8 8h-6v-6h6v6zm0-8h-6V5h6v6z',
    windowOpen: 'M3 3v18h9v-2H5V5h7V3H3zm11 2v14l7 3V2l-7 3z',
    snow: 'M22 11h-4.17l3.24-3.24-1.41-1.42L15 11h-2V9l4.66-4.66-1.42-1.41L13 6.17V2h-2v4.17L7.76 2.93 6.34 4.34 11 9v2H9L4.34 6.34 2.93 7.76 6.17 11H2v2h4.17l-3.24 3.24 1.41 1.42L9 13h2v2l-4.66 4.66 1.42 1.41L11 17.83V22h2v-4.17l3.24 3.24 1.42-1.41L13 15v-2h2l4.66 4.66 1.41-1.42L17.83 13H22z',
    plus: 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z',
    chevron: 'M7.4 8.6 12 13.2l4.6-4.6L18 10l-6 6-6-6z',
    minus: 'M19 13H5v-2h14z',
    close: 'M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
    valve: 'M15 13V5c0-1.66-1.34-3-3-3S9 3.34 9 5v8c-1.21.91-2 2.37-2 4 0 2.76 2.24 5 5 5s5-2.24 5-5c0-1.63-.79-3.09-2-4z'
  };
  const icon = name => '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="' + ICON[name] + '"/></svg>';
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const clone = v => JSON.parse(JSON.stringify(v));
  const state = {
    roles: cfg.roles || {},
    actions: cfg.actions || [],
    values: cfg.values || {},
    limits: cfg.limits || { min: 4.5, max: 30.5, step: 0.5, special: [] },
    partyLimits: cfg.partyLimits || { min: 4.5, max: 30.5, step: 0.5, special: [] },
    schedule: cfg.schedule || null,
    presetModes: !!cfg.presetModes,
    presetWindow: !!cfg.presetWindow,
    temps: cfg.temps || {}
  };
  let size = 'compact';
  let selDay = (new Date().getDay() + 6) % 7;     // Montag = 0
  let draft = null;                               // bearbeiteter Wochenplan (Tage mit [Endzeit, Temperatur]), null = unverändert
  let busy = false;
  let notice = '';
  let dragging = false;
  let setTimer = 0;
  let party = null;                               // Eingaben des Party-Formulars (Beginn, Ende, Temperatur) bis zum Start

  const has = role => role in state.roles;
  const can = role => has(role) && state.actions.includes(role);
  const val = role => state.values[role];
  const act = (role, value) => {
    try { requestAction(state.roles[role], value); } catch (e) { /* außerhalb von Symcon */ }
  };
  const pad = n => String(n).padStart(2, '0');
  const hm = m => pad(Math.floor(m / 60)) + ':' + pad(m % 60);
  const fmt = (v, digits = 1) => Number(v).toFixed(digits).replace('.', ',');
  const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const DAYS_LONG = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

  // Betriebsart und Boost: Bei Geräten ohne Datenpunkt Boost (boostMode) ist der Boost ein Wert des Modus
  const mode = () => val('mode');
  const hasBoost = () => has('boost') || (boostMode !== null && has('mode'));
  const boostOn = () => has('boost') ? !!val('boost') : (boostMode !== null && mode() === boostMode);
  const hasParty = () => has('mode') && has('partyStart') && has('partyEnd') && has('partyTemp');
  const partyOn = () => mode() === 2;
  const canParty = () => can('partyStart') && can('partyEnd') && can('partyTemp');
  const coolingOn = () => has('cooling') && Number(val('cooling')) === 1;
  const windowOpen = () => has('window') && Number(val('window')) > 0;

  // Farbverlauf nach der Darstellung der Solltemperatur (Instanz: gradient = Liste aus Position 0..1 und RGB-Wert).
  // Ohne Verlauf (Darstellung „Standard“) gilt die Akzentfarbe der Visualisierung.
  const rgb = c => [(c >> 16) & 255, (c >> 8) & 255, c & 255];
  function colorAt(frac) {
    const g = state.limits.gradient || [];
    if (!g.length) return 'var(--accent)';
    const t = Math.min(1, Math.max(0, frac));
    let i = 1;
    while (i < g.length - 1 && t > g[i][0]) i++;
    const a = g[i - 1], b = g[i], span = b[0] - a[0];
    const p = span > 0 ? Math.min(1, Math.max(0, (t - a[0]) / span)) : 1;
    const ca = rgb(a[1]), cb = rgb(b[1]);
    return 'rgb(' + ca.map((x, k) => Math.round(x + (cb[k] - x) * p)).join(',') + ')';
  }
  const tempColor = v => colorAt((v - state.limits.min) / (state.limits.max - state.limits.min));
  // Verlauf als CSS-Farbverlauf (Legende des Wochenplans)
  const gradientCss = () => 'linear-gradient(90deg, ' + [0, 1 / 6, 2 / 6, 3 / 6, 4 / 6, 5 / 6, 1].map(p => colorAt(p)).join(', ') + ')';
  // Werte mit festem Text (z.B. Aus und An bei 4,5 und 30,5 °C) laut Darstellung der Variable, sonst die Temperatur
  const setText = v => {
    const s = (state.limits.special || []).find(x => v >= x.min - 0.001 && v <= x.max + 0.001);
    return s ? esc(s.text) : fmt(v) + '<small> °C</small>';
  };

  // ---------- Wählrad ----------
  const A0 = 130, A1 = 410, CX = 100, CY = 100, R = 82;
  const angOf = v => A0 + (v - state.limits.min) / (state.limits.max - state.limits.min) * (A1 - A0);
  const point = (r, ang) => [CX + r * Math.cos(ang * Math.PI / 180), CY + r * Math.sin(ang * Math.PI / 180)];
  function arc(a0, a1) {
    const p0 = point(R, a0), p1 = point(R, a1);
    return 'M' + p0[0].toFixed(1) + ' ' + p0[1].toFixed(1) + ' A' + R + ' ' + R + ' 0 ' + (a1 - a0 > 180 ? 1 : 0) + ' 1 ' + p1[0].toFixed(1) + ' ' + p1[1].toFixed(1);
  }
  // Bogen aus kleinen Stücken, jedes in der Farbe seiner Position im Verlauf (Position = Anteil am ganzen Bogen)
  function arcSegments(a0, a1) {
    const n = Math.max(1, Math.ceil((a1 - a0) / 4));
    let h = '';
    for (let k = 0; k < n; k++) {
      const s0 = a0 + (a1 - a0) * k / n, s1 = a0 + (a1 - a0) * (k + 1) / n + (k < n - 1 ? 0.6 : 0);
      h += '<path d="' + arc(s0, s1) + '" stroke="' + colorAt((s0 - A0) / (A1 - A0)) + '"/>';
    }
    const e0 = point(R, a0), e1 = point(R, a1);
    return h + '<circle cx="' + e0[0].toFixed(1) + '" cy="' + e0[1].toFixed(1) + '" r="3.5" fill="' + colorAt((a0 - A0) / (A1 - A0)) + '"/>'
      + '<circle cx="' + e1[0].toFixed(1) + '" cy="' + e1[1].toFixed(1) + '" r="3.5" fill="' + colorAt((a1 - A0) / (A1 - A0)) + '"/>';
  }
  function dialHTML() {
    const withSet = has('set');
    const actual = has('actual') ? val('actual') : null;
    let svg = '<svg class="dial" viewBox="0 0 200 200" id="dial"><g class="track">' + arcSegments(A0, A1) + '</g>';
    if (withSet) svg += '<g class="active" id="dial-active"></g>';
    if (actual !== null && withSet) svg += '<circle class="actual" id="dial-actual" r="2.6"/>';
    if (withSet) svg += '<circle class="knob" id="dial-knob" r="10"/>';
    svg += '</svg>';
    const big = withSet ? setText(val('set')) : (actual !== null ? fmt(actual) + '<small> °C</small>' : '');
    let is = [];
    if (withSet && actual !== null) is.push(T('Actual') + ' <b>' + fmt(actual) + ' °C</b>');
    if (has('humidity')) is.push('<b>' + Math.round(val('humidity')) + ' %</b>');
    const sub = boostOn() ? T('Boost') + (has('boostRemain') && val('boostRemain') > 0 ? ' ' + Math.ceil(val('boostRemain')) + ' ' + T('min') : '')
      : windowOpen() ? T('Window open') : modeName();
    return '<div class="dialwrap' + (withSet && !can('set') ? ' inactive' : '') + '">' + svg
      + '<div class="center"><div class="set" id="dial-set">' + big + '</div>'
      + (is.length ? '<div class="is muted">' + is.join(' · ') + '</div>' : '')
      + '<div class="sub muted" id="dial-sub" ' + (size === 'mini' ? '' : 'hidden') + '>' + esc(sub) + '</div></div>'
      + (withSet ? '<button class="nudge minus" data-nudge="-1" aria-label="-"' + (can('set') ? '' : ' disabled') + '>' + icon('minus') + '</button><button class="nudge plus" data-nudge="1" aria-label="+"' + (can('set') ? '' : ' disabled') + '>' + icon('plus') + '</button>' : '')
      + '</div>';
  }
  // Bogen, Knopf und Zahl des Wählrads, ohne die Kachel neu aufzubauen
  function updateDial() {
    if (!has('set')) return;
    const v = val('set');
    const a = document.getElementById('dial-active'), k = document.getElementById('dial-knob'), s = document.getElementById('dial-set');
    if (!a || !k) return;
    a.innerHTML = arcSegments(A0, Math.max(A0 + 0.5, angOf(v)));
    const p = point(R, angOf(v));
    k.setAttribute('cx', p[0].toFixed(1)); k.setAttribute('cy', p[1].toFixed(1)); k.setAttribute('fill', tempColor(v));
    if (s) s.innerHTML = setText(v);
    const c = document.getElementById('dial-actual');
    if (c && has('actual')) {
      const q = point(R - 13, angOf(Math.min(state.limits.max, Math.max(state.limits.min, val('actual')))));
      c.setAttribute('cx', q[0].toFixed(1)); c.setAttribute('cy', q[1].toFixed(1));
    }
  }
  const clampSet = v => Math.min(state.limits.max, Math.max(state.limits.min, Math.round(v / state.limits.step) * state.limits.step));
  function targetChanged(now) {
    updateDial();
    clearTimeout(setTimer);
    if (now) commitSet(); else setTimer = setTimeout(commitSet, 700);
  }
  function commitSet() { clearTimeout(setTimer); setTimer = 0; act('set', val('set')); }
  function dragTo(e) {
    const svg = document.getElementById('dial');
    if (!svg) return;
    const r = svg.getBoundingClientRect();
    let a = Math.atan2(e.clientY - (r.top + r.height / 2), e.clientX - (r.left + r.width / 2)) * 180 / Math.PI;
    if (a < 0) a += 360;
    // Lücke unten (50° bis 130°): auf das nächste Ende des Bogens
    if (a > A1 - 360 && a < A0) a = a < 90 ? A1 - 360 : A0;
    if (a <= A1 - 360) a += 360;
    const t = Math.min(1, Math.max(0, (a - A0) / (A1 - A0)));
    state.values.set = clampSet(state.limits.min + t * (state.limits.max - state.limits.min));
    updateDial();
  }

  // ---------- Bedienelemente ----------
  function modeName() {
    if (boostOn()) return T('Boost');
    return { 0: T('Automatic'), 1: T('Manual'), 2: T('Party') }[mode()] || '';
  }
  function segHTML() {
    if (!has('mode')) return '';
    const dis = can('mode') ? '' : ' disabled';
    const m = mode();
    let h = '<div class="seg"><button data-mode="0"' + dis + ' class="' + (m === 0 ? 'on' : '') + '">' + icon('clock') + T('Automatic') + '</button>'
      + '<button data-mode="1"' + dis + ' class="' + (m === 1 ? 'on' : '') + '">' + icon('hand') + T('Manual') + '</button>';
    if (hasParty()) h += '<button data-mode="2"' + (can('mode') && canParty() ? '' : ' disabled') + ' class="' + (m === 2 ? 'on' : '') + '">' + icon('party') + T('Party') + '</button>';
    return h + '</div>';
  }
  function chipsHTML() {
    let h = '';
    if (hasBoost()) {
      h += '<button class="chip grow ' + (boostOn() ? 'on' : '') + '" data-boost' + ((has('boost') ? can('boost') : can('mode')) ? '' : ' disabled') + '>' + icon('flame')
        + (boostOn() ? T('Boost') + (has('boostRemain') && val('boostRemain') > 0 ? ' · ' + Math.ceil(val('boostRemain')) + ' ' + T('min') : '') : T('Boost')) + '</button>';
    }
    if (has('valve')) h += '<span class="chip">' + icon('valve') + T('Valve') + ' ' + Math.round(val('valve')) + ' %</span>';
    // Fensterzustand: nur Symbol und Zustand, bedienbar (wie die Statusvariable) schaltet er um
    if (has('window')) {
      const open = windowOpen(), tag = can('window') ? 'button' : 'span';
      h += '<' + tag + ' class="chip' + (open ? ' warn' : '') + (tag === 'span' ? ' dim' : '') + '"' + (tag === 'button' ? ' data-window' : '') + ' title="' + esc(T('Window')) + '">'
        + icon(open ? 'windowOpen' : 'window') + (open ? T('Window state open') : T('Window state closed')) + '</' + tag + '>';
    }
    // Betriebsart Heizen / Kühlen (nur mit der Statusvariable), bedienbar schaltet sie um
    if (has('cooling')) {
      const cool = coolingOn(), tag = can('cooling') ? 'button' : 'span';
      // Hintergrund: Kühlen in der Farbe des Minimums, Heizen in der des Maximums der Solltemperatur-Darstellung
      h += '<' + tag + ' class="chip mode' + (cool ? ' cool' : ' heat') + (tag === 'span' ? ' dim' : '') + '" style="background:' + colorAt(cool ? 0 : 1) + '"' + (tag === 'button' ? ' data-cooling' : '') + '>'
        + icon(cool ? 'snow' : 'flame') + (cool ? T('Cooling') : T('Heating')) + '</' + tag + '>';
    }
    return h ? '<div class="chips">' + h + '</div>' : '';
  }
  function headHTML() {
    if (size === 'mini') return '';
    const warn = windowOpen() ? ' warn' : '', hot = boostOn() ? ' hot' : '';
    const name = windowOpen() ? T('Window open') : modeName();
    return '<div class="head">' + (name ? '<span class="pill' + warn + hot + '">' + esc(name) + '</span>' : '') + (notice ? '<span class="notice">' + esc(notice) + '</span>' : '') + '</div>';
  }

  // ---------- Wochenplan ----------
  function weekHTML(interactive) {
    const days = (draft || state.schedule.days);
    const today = (new Date().getDay() + 6) % 7;
    const now = new Date().getHours() * 60 + new Date().getMinutes();
    const hrs = [0, 6, 12, 18, 24].map(h => '<span style="top:' + (h / 24 * 100) + '%">' + pad(h) + '</span>').join('');
    let heads = '<div></div>';
    DAYS.forEach((d, i) => { heads += '<div class="dh' + (i === today ? ' today' : '') + (interactive && i === selDay ? ' sel' : '') + '" data-day="' + i + '">' + T(d) + '</div>'; });
    let cols = '';
    days.forEach((slots, i) => {
      let start = 0, segs = '';
      slots.forEach(s => {
        const top = start / 1440 * 100, h = (s[0] - start) / 1440 * 100;
        start = s[0];
        segs += '<div class="seg1" style="top:' + top + '%;height:calc(' + h + '% - 2px);margin-top:1px;background:' + tempColor(s[1]) + '">' + (h > 9 ? fmt(s[1]) : '') + '</div>';
      });
      cols += '<div class="day' + (i === today ? ' today' : '') + (interactive && i === selDay ? ' sel' : '') + '" data-day="' + i + '">' + segs
        + (i === today ? '<div class="now" style="top:' + (now / 1440 * 100) + '%"></div>' : '') + '</div>';
    });
    return '<div class="weekbox"><div class="week">' + heads + '<div class="hours">' + hrs + '</div>' + cols + '</div>'
      + '<div class="legend">' + fmt(state.schedule.min) + ' °C<i style="background:' + gradientCss() + '"></i>' + fmt(state.schedule.max) + ' °C</div></div>';
  }
  function editorHTML() {
    const sch = state.schedule, slots = (draft || sch.days)[selDay];
    let start = 0, rows = '';
    slots.forEach((s, i) => {
      const last = i === slots.length - 1;
      rows += '<li class="prow"><span class="nr">' + pad(i + 1) + '</span><span class="dot" style="background:' + tempColor(s[1]) + '"></span>'
        + '<span class="t">' + hm(start) + ' ' + T('until') + '</span>'
        + (last ? '<span style="width:92px;text-align:center">24:00</span>' : '<input type="time" step="' + sch.step * 60 + '" value="' + hm(s[0]) + '" data-end="' + i + '">')
        + '<input type="number" step="0.5" min="' + sch.min + '" max="' + sch.max + '" value="' + Number(s[1]).toFixed(1) + '" data-temp="' + i + '"><span class="muted">°C</span>'
        + (slots.length > 1 ? '<button class="del" data-del="' + i + '" title="' + esc(T('Remove')) + '">' + icon('close') + '</button>' : '') + '</li>';
      start = s[0];
    });
    const profile = (sch.plans > 1 && can('profile'))
      ? '<select data-profile>' + Array.from({ length: sch.plans }, (_, i) => '<option value="' + (i + 1) + '"' + (i + 1 === sch.plan ? ' selected' : '') + '>' + T('Profile') + ' ' + (i + 1) + '</option>').join('') + '</select>'
      : '<span class="pill">' + T('Profile') + ' ' + sch.plan + '</span>';
    return '<div class="dayrow"><span class="title">' + T(DAYS_LONG[selDay]) + '</span>' + profile + '</div>'
      + '<ol class="plist">' + rows + '</ol>'
      + '<div class="btnrow"><button class="tool" data-add' + (slots.length >= sch.slots ? ' disabled' : '') + '>+ ' + T('Add switching point') + '</button>'
      + '<select data-copy><option value="">' + T('Copy to all days') + ' …</option><option value="all">' + T('Copy to all days') + '</option><option value="work">' + T('Copy to weekdays') + '</option><option value="end">' + T('Copy to weekend') + '</option></select>'
      + '<span style="flex:1"></span>'
      + '<button class="tool" data-discard' + (draft && !busy ? '' : ' disabled') + '>' + T('Discard') + '</button>'
      + '<button class="tool primary" data-apply' + (draft && !busy ? '' : ' disabled') + '>' + (busy ? T('Saving…') : T('Apply')) + '</button></div>';
  }

  // ---------- Bedienfeld (maximiert) ----------
  const toLocal = ts => { const d = new Date(ts * 1000); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); };
  function presetsHTML() {
    // Komfort und Absenkung: Die Aktionsvariable (COMFORT_MODE, LOWERING_MODE) löst die Aktion aus, die Temperatur dahinter ist nur die
    // Anzeige und richtet sich nach der Betriebsart (Heizen oder Kühlen). Sonst, und für Fenster offen, wird die Solltemperatur gesetzt.
    // Ein Eintrag erscheint nur, wenn seine Statusvariable vorhanden (in der Instanz aktiv) ist.
    // Fenster offen: Mit Fensterzustand (HomematicIP) schaltet der Eintrag den Zustand und erscheint nur mit dieser Variablen.
    const list = [['comfort', 'Comfort', state.presetModes ? 'comfortMode' : null], ['lowering', 'Lowering', state.presetModes ? 'loweringMode' : null], ['windowTemp', 'Window open', state.presetWindow ? 'window' : null]]
      .map(p => ({ temp: p[0] + ((p[2] && p[2] !== 'window' && coolingOn()) ? 'Cooling' : ''), label: p[1], mode: p[2] }))
      .filter(p => p.mode ? has(p.mode) : has(p.temp));
    if (!list.length) return '';
    return '<div class="presets">' + list.map(p => {
      const viaMode = !!p.mode;
      const enabled = viaMode ? can(p.mode) : can('set');
      // Die Temperatur kommt aus der Statusvariable, ist sie abgewählt, aus dem gemerkten Paramset des Gerätes
      const value = has(p.temp) ? val(p.temp) : (p.temp in state.temps ? state.temps[p.temp] : null);
      const on = p.mode === 'window' ? windowOpen() : (value !== null && has('set') && Math.abs(val('set') - value) < 0.01);
      return '<button' + (enabled ? '' : ' disabled') + ' class="preset' + (on ? ' on' : '') + '" data-preset="' + p.temp + '"' + (viaMode ? ' data-via="' + p.mode + '"' : '') + '><span>' + T(p.label) + '</span><span>'
        + (value !== null ? fmt(value) + ' °C' : '&nbsp;') + '</span></button>';
    }).join('') + '</div>';
  }
  function partyHTML() {
    if (!hasParty()) return '';
    const on = partyOn();
    if (!party || on) party = { start: val('partyStart'), end: val('partyEnd'), temp: val('partyTemp') };
    // Nie gesetzte Zeiten: Beginn jetzt (volle halbe Stunde), Ende eine Stunde später
    if (!on && party.end <= party.start) {
      const now = Math.floor(Date.now() / 1000);
      party.start = now - now % 1800;
      party.end = party.start + 3600;
    }
    const dis = canParty() ? '' : ' disabled';
    return '<div class="form">'
      + '<label>' + T('Begin') + '</label><input' + dis + ' type="datetime-local" data-party="start" value="' + toLocal(party.start) + '">'
      + '<label>' + T('End') + '</label><input' + dis + ' type="datetime-local" data-party="end" value="' + toLocal(party.end) + '">'
      + '<label>' + T('Temperature') + '</label><input' + dis + ' type="number" step="0.5" min="' + state.partyLimits.min + '" max="' + state.partyLimits.max + '" data-party="temp" value="' + Number(party.temp).toFixed(1) + '">'
      + '<span></span><button' + (can('mode') && canParty() ? '' : ' disabled') + ' class="tool primary" data-party-' + (on ? 'stop' : 'start') + '>' + (on ? T('End party') : T('Start party')) + '</button></div>';
  }

  // ---------- Gruppen (Karten, einklappbar) ----------
  // Der Zustand wird je Instanz im Browser gemerkt (ohne Speicher gilt der Standard: Party zu)
  const storeKey = 'HeatingTile' + (cfg.instance || '');
  let collapsed = { presets: false, week: false, party: true };
  try { Object.assign(collapsed, JSON.parse(localStorage.getItem(storeKey) || '{}')); } catch (e) { /* ohne Speicher */ }
  function groupHTML(id, title, body, badge) {
    if (!body) return '';
    return '<section class="group' + (collapsed[id] ? ' closed' : '') + '"><button class="ghead" data-group="' + id + '" aria-expanded="' + !collapsed[id] + '"><span>' + esc(title) + '</span>'
      + (badge ? '<span class="pill">' + esc(badge) + '</span>' : '') + '<span class="chev">' + icon('chevron') + '</span></button><div class="gbody">' + body + '</div></section>';
  }

  // ---------- Aufbau ----------
  function render() {
    if (dragging) return;
    const hasWeek = !!state.schedule;
    const dial = dialHTML(), head = headHTML(), seg = segHTML(), chips = chipsHTML();
    let html;
    if (size === 'mini') html = dial;
    else if (size === 'compact' || !hasWeek && size === 'wide') html = head + dial + seg + chips;
    else if (size === 'wide') html = '<div class="cols"><div class="col a">' + head + dial + '</div><div class="col b">' + seg + weekHTML(false) + chips + '</div></div>';
    else {
      html = '<div class="cols"><div class="col a">' + head + dial + seg + chips + '</div><div class="col b">'
        + groupHTML('presets', T('Presets'), presetsHTML())
        + groupHTML('week', T('Weekly program'), hasWeek ? weekHTML(true) + '<div class="gdiv"></div>' + editorHTML() : '')
        + groupHTML('party', T('Party'), partyHTML(), hasParty() && partyOn() ? T('On') : '')
        + '</div></div>';
    }
    app.innerHTML = html;
    updateDial();
  }
  function layout() {
    const w = app.clientWidth, h = app.clientHeight;
    let s = (h < 250 || w < 250) ? 'mini' : 'compact';
    if (s !== 'mini' && w >= 560 && h >= 300) s = 'wide';
    if (s === 'wide' && w >= 880 && h >= 520) s = 'full';
    if (s === size && app.classList.contains('size-' + s)) return;
    size = s;
    ['mini', 'compact', 'wide', 'full'].forEach(x => app.classList.toggle('size-' + x, x === s));
    render();
  }
  new ResizeObserver(layout).observe(app);

  // ---------- Eingaben ----------
  const dirty = () => { if (!draft) draft = clone(state.schedule.days); };
  function copyDay(target) {
    dirty();
    const src = clone(draft[selDay]);
    draft.forEach((_, i) => { if (i !== selDay && (target === 'all' || (target === 'work' && i < 5) || (target === 'end' && i >= 5))) draft[i] = clone(src); });
  }
  app.addEventListener('pointerdown', e => {
    if (!has('set') || !can('set') || !e.target.closest('#dial')) return;
    dragging = true;
    e.target.closest('#dial').setPointerCapture?.(e.pointerId);
    dragTo(e);
  });
  app.addEventListener('pointermove', e => { if (dragging) dragTo(e); });
  const endDrag = () => { if (dragging) { dragging = false; commitSet(); } };
  app.addEventListener('pointerup', endDrag);
  app.addEventListener('pointercancel', endDrag);

  app.addEventListener('click', e => {
    const b = e.target.closest('button'), d = e.target.closest('[data-day]');
    if (d && !b && size === 'full') { selDay = Number(d.dataset.day); render(); return; }
    if (!b) return;
    if (b.dataset.group) {
      collapsed[b.dataset.group] = !collapsed[b.dataset.group];
      try { localStorage.setItem(storeKey, JSON.stringify(collapsed)); } catch (err) { /* ohne Speicher */ }
      render();
    }
    else if (b.dataset.nudge) { state.values.set = clampSet(val('set') + Number(b.dataset.nudge) * state.limits.step); targetChanged(false); }
    else if (b.dataset.mode !== undefined) { state.values.mode = Number(b.dataset.mode); act('mode', Number(b.dataset.mode)); render(); }
    else if ('boost' in b.dataset) {
      if (has('boost')) { state.values.boost = !boostOn(); act('boost', state.values.boost); }
      else { state.values.mode = boostOn() ? 0 : boostMode; act('mode', state.values.mode); }
      render();
    }
    else if ('cooling' in b.dataset) { state.values.cooling = coolingOn() ? 0 : 1; act('cooling', state.values.cooling); render(); }
    else if ('window' in b.dataset) { state.values.window = windowOpen() ? 0 : 1; act('window', state.values.window); render(); }
    else if (b.dataset.via === 'window') { state.values.window = windowOpen() ? 0 : 1; act('window', state.values.window); render(); }
    else if (b.dataset.via) { act(b.dataset.via, true); }
    else if (b.dataset.preset) { state.values.set = clampSet(has(b.dataset.preset) ? val(b.dataset.preset) : state.temps[b.dataset.preset]); commitSet(); render(); }
    else if ('partyStart' in b.dataset) {
      act('partyStart', party.start); act('partyEnd', party.end); act('partyTemp', party.temp); act('mode', 2);
    }
    else if ('partyStop' in b.dataset) { act('mode', 0); }
    else if ('add' in b.dataset) {
      dirty();
      const slots = draft[selDay], n = slots.length, prev = n > 1 ? slots[n - 2][0] : 0, step = state.schedule.step;
      slots.splice(n - 1, 0, [Math.max(prev + step, Math.round((prev + 1440) / 2 / step) * step), slots[n - 1][1]]);
      render();
    }
    else if (b.dataset.del !== undefined) {
      dirty();
      const i = Number(b.dataset.del), slots = draft[selDay];
      if (i === slots.length - 1) slots[i - 1][0] = 1440;
      slots.splice(i, 1);
      if (i === slots.length) slots[slots.length - 1][0] = 1440;
      render();
    }
    else if ('discard' in b.dataset) { draft = null; render(); }
    else if ('apply' in b.dataset) {
      busy = true; notice = ''; render();
      try { requestAction('TileSave', JSON.stringify({ days: draft })); } catch (err) { busy = false; render(); }
    }
  });
  app.addEventListener('change', e => {
    const t = e.target;
    if (t.dataset.end !== undefined) {
      dirty();
      const i = Number(t.dataset.end), slots = draft[selDay], step = state.schedule.step;
      const [h, m] = t.value.split(':').map(Number);
      let v = Math.round((h * 60 + m) / step) * step;
      v = Math.max((i > 0 ? slots[i - 1][0] : 0) + step, Math.min(slots[i + 1][0] - step, v));
      slots[i][0] = v;
      render();
    } else if (t.dataset.temp !== undefined) {
      dirty();
      const i = Number(t.dataset.temp);
      draft[selDay][i][1] = Math.min(state.schedule.max, Math.max(state.schedule.min, Math.round(Number(t.value) * 2) / 2));
      render();
    } else if ('profile' in t.dataset) {
      draft = null;
      act('profile', Number(t.value));
    } else if ('copy' in t.dataset) {
      if (t.value) { copyDay(t.value); render(); }
    } else if (t.dataset.party) {
      const k = t.dataset.party;
      if (k === 'temp') party.temp = Number(t.value);
      else party[k] = Math.floor(new Date(t.value).getTime() / 1000);
      // Läuft die Party, wirken die Eingaben direkt
      if (partyOn()) act(k === 'temp' ? 'partyTemp' : k === 'start' ? 'partyStart' : 'partyEnd', party[k]);
    }
  });

  // ---------- Nachrichten der Instanz ----------
  window.handleMessage = function (data) {
    let msg;
    try { msg = JSON.parse(data); } catch (e) { return; }
    if (!msg) return;
    if (msg.type === 'error') { busy = false; notice = msg.text || ''; render(); return; }
    if (msg.type === 'state') {
      state.roles = msg.roles || {}; state.actions = msg.actions || []; state.values = msg.values || {};
      state.limits = msg.limits || state.limits;
      state.partyLimits = msg.partyLimits || state.partyLimits;
      state.presetModes = !!msg.presetModes;
      state.presetWindow = !!msg.presetWindow;
      state.temps = msg.temps || {};
      scheduleFrom(msg.schedule);
    } else if (msg.type === 'schedule') {
      scheduleFrom(msg.schedule);
    } else return;
    render();
  };
  function scheduleFrom(sch) {
    const planChanged = sch && state.schedule && sch.plan !== state.schedule.plan;
    state.schedule = sch || null;
    // Ein Entwurf bleibt erhalten, bis er übernommen oder verworfen wird; nach dem Speichern oder beim Profilwechsel gilt der gelesene Stand
    if (busy || planChanged || !sch) { draft = null; }
    if (busy) { busy = false; notice = ''; }
  }
  setInterval(() => { if (!draft && !dragging && state.schedule && size !== 'compact' && size !== 'mini') render(); }, 60000);
  layout();
  render();
})();
