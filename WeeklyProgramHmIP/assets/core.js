/* HomeMatic IP Weekly Program – Kachel: Wochenübersicht und Liste der Schaltzeitpunkte, Bearbeiten und Übernehmen.
   Die Instanz liefert die Schaltzeitpunkte (Felder wie im Paramset MASTER ohne Präfix NN_WP_) mit der Konfiguration und
   sendet Änderungen als Nachricht (handleMessage). Die Größe der Kachel bestimmt, was angezeigt wird:
   mini = nächste Schaltung, compact = Liste, full = Wochenübersicht und Liste.
   Bearbeitet wird ein Entwurf, erst „Übernehmen“ schreibt ihn über die Instanz (requestAction 'Save') in das Gerät. */
(function () {
  'use strict';
  const cfg = window.WP_CONFIG || {};
  const texts = cfg.texts || {};
  const T = key => texts[key] || key;
  const $ = id => document.getElementById(id);
  const app = $('app');
  // Überschrift der Kachel: Die Visu übergibt in der Adresse margintop (mit Titel 60, ohne 15) und legt den Titel über die Kachel.
  // Den Inhalt um den Unterschied nach unten rücken. Ohne Überschrift liegt der Maximieren-Pfeil der Visu oben rechts: Ecke freihalten (--corner).
  try {
    const top = Number(new URLSearchParams(location.search).get('margintop'));
    if (top > 15) {
      document.documentElement.style.setProperty('--title-space', (top - 15) + 'px');
      document.documentElement.style.setProperty('--corner', '0px');
    }
  } catch (e) { /* ohne Angabe: kein Titel */ }
  // Native Eingabefelder (Uhrzeit, Auswahl) im hellen oder dunklen Stil der Visu zeichnen
  try {
    const m = getComputedStyle(document.body).color.match(/\d+/g);
    if (m) document.documentElement.style.colorScheme = (0.299 * m[0] + 0.587 * m[1] + 0.114 * m[2]) > 140 ? 'dark' : 'light';
  } catch (e) { /* Standard */ }

  const ICON = {
    chevron: 'M7.4 8.6 12 13.2l4.6-4.6L18 10l-6 6-6-6z',
    refresh: 'M17.65 6.35A7.96 7.96 0 0 0 12 4a8 8 0 1 0 7.73 10h-2.08A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4z',
    sunrise: 'M12 8a5 5 0 0 0-5 5h2a3 3 0 0 1 6 0h2a5 5 0 0 0-5-5zM3 18h18v2H3zM12 2l3.5 3.5h-2.5V8h-2V5.5H8.5z',
    sunset: 'M12 8a5 5 0 0 0-5 5h2a3 3 0 0 1 6 0h2a5 5 0 0 0-5-5zM3 18h18v2H3zM12 8 8.5 4.5H11V2h2v2.5h2.5z',
    edit: 'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75z',
    trash: 'M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6zM19 4h-3.5l-1-1h-5l-1 1H5v2h14z',
    plus: 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z'
  };
  const icon = name => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + ICON[name] + '"/></svg>';
  // Wochentage in der Anzeige von Montag bis Sonntag, Bit der Bitmaske WEEKDAY (Bit 0 = Sonntag)
  const DAYS = [[1, 'Mon'], [2, 'Tue'], [3, 'Wed'], [4, 'Thu'], [5, 'Fri'], [6, 'Sat'], [0, 'Sun']];
  const BASE_SECONDS = [0.1, 1, 5, 10, 60, 300, 600, 3600];
  const BASE_TEXT = ['100 ms', '1 s', '5 s', '10 s', '1 min', '5 min', '10 min', '1 h'];
  const WEEK_HEIGHT = 150;

  const clone = value => JSON.parse(JSON.stringify(value));
  let program = cfg.program || {};            // zuletzt von der Instanz gelieferter Stand
  let draft = clone(program);                 // Entwurf (wird bearbeitet)
  let fields = cfg.fields || [];              // Felder, die das Gerät je Schaltzeitpunkt kennt
  let info = cfg.info || {};                  // Beschreibung der Felder (Type, Min, Max, List) aus der CCU
  let modes = cfg.modes || [];                // Modus je Schaltkanal (true = manuell)
  let channels = cfg.channels || [];
  const maxEntries = cfg.maxEntries || 75;
  let selected = 0;
  let editing = false;
  let dirty = false;
  let busy = false;
  let notice = '';

  const pad = n => String(n).padStart(2, '0');
  const fixedTime = e => pad(e.FIXED_HOUR || 0) + ':' + pad(e.FIXED_MINUTE || 0);
  const hasDay = (e, bit) => ((e.WEEKDAY || 0) & (1 << bit)) !== 0;
  // Bedingung 1 = nur Astro; bei allen anderen gilt auch die feste Uhrzeit, bei 0 gilt nur sie
  const usesFixed = e => (e.CONDITION || 0) !== 1;
  const usesAstro = e => (e.CONDITION || 0) !== 0;
  // Dimmer und Rollläden kennen Rampenzeit bzw. Lamellen: dort ist der Schaltzustand ein Wert in Prozent
  const hasLevelValue = () => fields.includes('RAMP_TIME_BASE') || fields.includes('LEVEL_2');

  function astroText(e) {
    const off = e.ASTRO_OFFSET || 0;
    return T(e.ASTRO_TYPE ? 'Sunset' : 'Sunrise') + (off ? ' ' + (off > 0 ? '+' : '') + off + ' min' : '');
  }
  function whenText(e) {
    const c = e.CONDITION || 0;
    if (c === 0) return fixedTime(e);
    if (c === 1) return astroText(e);
    return fixedTime(e) + ' / ' + astroText(e) + ' (' + T('Condition ' + c) + ')';
  }
  function seconds(e) {
    if ((e.DURATION_BASE || 0) === 7 && (e.DURATION_FACTOR || 0) === 31) return null;
    return (BASE_SECONDS[e.DURATION_BASE || 0] || 0) * (e.DURATION_FACTOR || 0);
  }
  function durationText(e) {
    const s = seconds(e);
    if (s === null) return T('Permanent');
    if (s === 0 || (e.LEVEL || 0) === 0) return '–';
    if (s >= 3600) return +(s / 3600).toFixed(2) + ' h';
    if (s >= 60) return +(s / 60).toFixed(2) + ' min';
    return +s.toFixed(1) + ' s';
  }
  // Die CCU kennt je Schaltzeitpunkt nur Aus (ohne Dauer) oder Ein mit Dauer (Wert oder dauerhaft): Dauer an den Schaltzustand anpassen
  function fixDuration(e) {
    if (!('DURATION_BASE' in e)) return;
    if ((e.LEVEL || 0) === 0) { e.DURATION_BASE = 0; e.DURATION_FACTOR = 0; }
    else if ((e.DURATION_FACTOR || 0) === 0) { e.DURATION_BASE = 7; e.DURATION_FACTOR = 31; }
  }
  // Felder, die nicht zum gemeinsamen Kern gehören (Rampenzeit, Lamellen, Farbe, Effekt …), werden nach der Beschreibung des Gerätes angezeigt
  const KNOWN = ['WEEKDAY', 'CONDITION', 'FIXED_HOUR', 'FIXED_MINUTE', 'ASTRO_TYPE', 'ASTRO_OFFSET', 'LEVEL', 'DURATION_BASE', 'DURATION_FACTOR', 'TARGET_CHANNELS'];
  const LABELS = { RAMP_TIME: 'Ramp time', LEVEL_2: 'Slats', OUTPUT_BEHAVIOUR: 'Output behaviour' };
  function humanize(name) {
    const text = name.toLowerCase().replace(/_/g, ' ');
    return text.charAt(0).toUpperCase() + text.slice(1);
  }
  const labelOf = name => LABELS[name] ? T(LABELS[name]) : humanize(name);
  // Namen der Werteliste lesbar darstellen (BASE_100_MS = 100 ms, BASE_5_M = 5 min …)
  function enumText(name) {
    const m = /^BASE_(\d+)_(MS|S|M|H)$/.exec(name);
    if (m) return m[1] + ' ' + { MS: 'ms', S: 's', M: 'min', H: 'h' }[m[2]];
    return humanize(name);
  }
  // Zusätzliche Felder: Paare NAME_BASE und NAME_FACTOR sind eine Zeit, alle anderen einzelne Werte
  function extraFields() {
    const names = fields.filter(f => !KNOWN.includes(f) && info[f]);
    const result = [];
    names.forEach(f => {
      if (f.endsWith('_FACTOR') && names.includes(f.slice(0, -7) + '_BASE')) return;
      if (f.endsWith('_BASE') && names.includes(f.slice(0, -5) + '_FACTOR')) { result.push({ kind: 'time', name: f.slice(0, -5) }); return; }
      result.push({ kind: 'single', name: f });
    });
    return result;
  }
  function extraText(x, e) {
    if (x.kind === 'time') {
      const base = (info[x.name + '_BASE'].List || [])[e[x.name + '_BASE'] || 0];
      return (e[x.name + '_FACTOR'] || 0) + ' × ' + (base ? enumText(base) : '');
    }
    const i = info[x.name], v = e[x.name] || 0;
    if (i.Type === 'ENUM') return enumText((i.List || [])[v] || String(v));
    if (i.Type === 'FLOAT' && Number(i.Max) <= 1.01) return Math.round(v * 100) + ' %';
    return String(v);
  }
  function levelText(e) {
    const l = e.LEVEL || 0;
    if (l === 0) return T('Off');
    if (l === 1 && !('RAMP_TIME_BASE' in e) && !('LEVEL_2' in e)) return T('On');
    return Math.round(Math.min(l, 1) * 100) + ' %' + (l > 1 ? ' (' + l + ')' : '');
  }
  const channelLabel = bit => channels[bit] || (T('Channel') + ' ' + (bit + 1));
  function channelNames(mask) {
    const names = [];
    for (let bit = 0; bit < 24; bit++) if (mask & (1 << bit)) names.push(channelLabel(bit));
    return names.join(', ');
  }
  const sorted = source => Object.keys(source).map(Number).sort((a, b) => a - b).map(n => [n, source[n]]);
  const entries = () => sorted(draft);
  const dayIndex = bit => DAYS.findIndex(d => d[0] === bit);
  const channelCount = () => Math.max(channels.length, 1);

  // Nächste Schaltung aus den Schaltzeitpunkten mit fester Uhrzeit (Astro-Zeiten kennt die Kachel nicht)
  function nextSwitch() {
    const now = new Date();
    let best = null;
    entries().forEach(([n, e]) => {
      if (!usesFixed(e)) return;
      for (let offset = 0; offset < 8; offset++) {
        const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + offset, e.FIXED_HOUR || 0, e.FIXED_MINUTE || 0);
        if (d <= now || !hasDay(e, d.getDay())) continue;
        if (!best || d < best.date) best = { date: d, entry: e };
        break;
      }
    });
    return best;
  }
  function summaryText() {
    if (notice) return notice;
    const list = entries();
    if (!list.length) return T('No switching times');
    let text = list.length + ' ' + T(list.length === 1 ? 'switching time' : 'switching times');
    if (dirty) return text + ' · ' + T('Unsaved changes');
    const next = nextSwitch();
    if (next) {
      const day = T(DAYS[dayIndex(next.date.getDay())][1]);
      text += ' · ' + T('Next') + ': ' + day + ' ' + pad(next.date.getHours()) + ':' + pad(next.date.getMinutes()) + ' ' + levelText(next.entry);
    }
    return text;
  }

  function el(tag, cls, html) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html !== undefined) e.innerHTML = html;
    return e;
  }
  function select(n, scroll) {
    selected = selected === n && !scroll ? 0 : n;
    document.querySelectorAll('.item').forEach(i => {
      const open = Number(i.dataset.n) === selected;
      i.classList.toggle('open', open);
      i.classList.toggle('sel', open);
    });
    document.querySelectorAll('.mark, .amark').forEach(m => m.classList.toggle('sel', Number(m.dataset.n) === selected));
    if (scroll && selected) {
      const item = document.querySelector('.item[data-n="' + selected + '"]');
      if (item) item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }
  function changed() {
    dirty = true;
    notice = '';
    refreshBar();
  }

  // Eingabefelder des Formulars
  function field(label, control) {
    const wrap = el('div', 'f');
    wrap.append(el('label', '', T(label)), control);
    return wrap;
  }
  function selectBox(options, value, onChange) {
    const s = el('select');
    options.forEach(([v, text]) => { const o = el('option', '', text); o.value = v; s.append(o); });
    s.value = String(value);
    s.onchange = () => onChange(s.value);
    return s;
  }
  function numberBox(value, min, max, onChange) {
    const i = el('input');
    i.type = 'number';
    i.min = min;
    i.max = max;
    i.value = value;
    i.onchange = () => {
      const v = Math.min(max, Math.max(min, parseInt(i.value, 10) || 0));
      i.value = v;
      onChange(v);
    };
    return i;
  }
  function checks(items, isOn, onToggle) {
    const box = el('div', 'checks');
    items.forEach(([key, text]) => {
      const lab = el('label', 'chk');
      const c = el('input');
      c.type = 'checkbox';
      c.checked = isOn(key);
      c.onchange = () => onToggle(key, c.checked);
      lab.append(c, el('span', '', text));
      box.append(lab);
    });
    return box;
  }

  function form(n, e) {
    const box = el('div', 'form');
    const redraw = () => { render(); select(n, true); };
    const set = (name, value) => { e[name] = value; changed(); };
    // Bedingung, Uhrzeit, Astro
    box.append(field('Condition', selectBox([0, 1, 2, 3, 4, 5, 6, 7].map(c => [c, (c + 1) + ': ' + T('Condition ' + c)]), e.CONDITION || 0, v => { set('CONDITION', Number(v)); redraw(); })));
    if (usesFixed(e)) {
      const t = el('input');
      t.type = 'time';
      t.value = fixedTime(e);
      t.onchange = () => {
        const p = t.value.split(':');
        if (p.length === 2) { e.FIXED_HOUR = Number(p[0]); e.FIXED_MINUTE = Number(p[1]); changed(); renderWeek(); renderRowOf(n); }
      };
      box.append(field('Time', t));
    }
    if (usesAstro(e)) {
      const astro = el('div', 'inline');
      astro.append(selectBox([[0, T('Sunrise')], [1, T('Sunset')]], e.ASTRO_TYPE || 0, v => { set('ASTRO_TYPE', Number(v)); redraw(); }),
        numberBox(e.ASTRO_OFFSET || 0, -128, 127, v => { set('ASTRO_OFFSET', v); redraw(); }), el('span', 'unit', 'min'));
      box.append(field('Astro', astro));
    }
    // Schaltzustand: Ein/Aus oder Wert in Prozent
    if (hasLevelValue()) {
      const level = el('div', 'inline');
      level.append(numberBox(Math.round(Math.min(e.LEVEL || 0, 1) * 100), 0, 100, v => { set('LEVEL', v / 100); fixDuration(e); redraw(); }), el('span', 'unit', '%'));
      box.append(field('State', level));
    } else {
      box.append(field('State', selectBox([[0, T('Off')], [1, T('On')]], (e.LEVEL || 0) > 0 ? 1 : 0, v => { set('LEVEL', Number(v)); fixDuration(e); redraw(); })));
    }
    // Dauer (nur bei Ein): dauerhaft oder Wert mit Einheit
    if (fields.includes('DURATION_BASE') && (e.LEVEL || 0) > 0) {
      const mode = seconds(e) === null || (e.DURATION_FACTOR || 0) === 0 ? 'perm' : 'value';
      const dur = el('div', 'inline');
      dur.append(selectBox([['perm', T('Permanent')], ['value', T('Value')]], mode, v => {
        if (v === 'perm') { e.DURATION_BASE = 7; e.DURATION_FACTOR = 31; }
        else { e.DURATION_BASE = 4; e.DURATION_FACTOR = 1; }
        changed(); redraw();
      }));
      if (mode === 'value') {
        dur.append(numberBox(e.DURATION_FACTOR || 1, 1, 31, v => { set('DURATION_FACTOR', v); redraw(); }),
          selectBox(BASE_TEXT.map((text, i) => [i, text]), e.DURATION_BASE || 0, v => { set('DURATION_BASE', Number(v)); redraw(); }));
      }
      box.append(field('Duration', dur));
    }
    // Felder einzelner Kanaltypen nach der Beschreibung des Gerätes
    extraFields().forEach(x => {
      if (x.kind === 'time') {
        const f = info[x.name + '_FACTOR'], b = info[x.name + '_BASE'];
        const row = el('div', 'inline');
        row.append(numberBox(e[x.name + '_FACTOR'] || 0, Number(f.Min) || 0, Number(f.Max) || 31, v => { set(x.name + '_FACTOR', v); renderRowOf(n); }),
          selectBox((b.List || []).map((name, i) => [i, enumText(name)]), e[x.name + '_BASE'] || 0, v => { set(x.name + '_BASE', Number(v)); renderRowOf(n); }));
        box.append(field(labelOf(x.name), row));
        return;
      }
      const i = info[x.name];
      let control;
      if (i.Type === 'ENUM') {
        control = selectBox((i.List || []).map((name, k) => [k, enumText(name)]), e[x.name] || 0, v => { set(x.name, Number(v)); renderRowOf(n); });
      } else if (i.Type === 'FLOAT' && Number(i.Max) <= 1.01) {
        control = el('div', 'inline');
        control.append(numberBox(Math.round((e[x.name] || 0) * 100), 0, 100, v => { set(x.name, v / 100); renderRowOf(n); }), el('span', 'unit', '%'));
      } else {
        control = numberBox(e[x.name] || 0, Number(i.Min) || 0, Number(i.Max) || 255, v => { set(x.name, v); renderRowOf(n); });
      }
      box.append(field(labelOf(x.name), control));
    });
    // Wochentage und Zielkanäle
    box.append(field('Weekdays', checks(DAYS.map(([bit, name]) => [bit, T(name).slice(0, 2)]), bit => hasDay(e, bit),
      (bit, on) => { set('WEEKDAY', on ? (e.WEEKDAY || 0) | (1 << bit) : (e.WEEKDAY || 0) & ~(1 << bit)); renderWeek(); renderRowOf(n); })));
    const chItems = [];
    for (let bit = 0; bit < channelCount(); bit++) chItems.push([bit, channelLabel(bit)]);
    const chBox = el('div', 'inline wrap');
    chBox.append(checks(chItems, bit => ((e.TARGET_CHANNELS || 0) & (1 << bit)) !== 0,
      (bit, on) => { set('TARGET_CHANNELS', on ? (e.TARGET_CHANNELS || 0) | (1 << bit) : (e.TARGET_CHANNELS || 0) & ~(1 << bit)); }));
    const all = el('button', 'tool', T('All'));
    const none = el('button', 'tool', T('None'));
    all.type = none.type = 'button';
    all.onclick = () => { set('TARGET_CHANNELS', (1 << channelCount()) - 1); redraw(); };
    none.onclick = () => { set('TARGET_CHANNELS', 0); redraw(); };
    chBox.append(all, none);
    box.append(field('Channels', chBox));
    return box;
  }

  // Zeile des Eintrags (Nummer, Wochentage, Zeit, Schaltzustand) neu zeichnen, ohne das Formular zu ersetzen
  function rowContent(n, e) {
    const row = el('button', 'row');
    row.type = 'button';
    row.append(el('span', 'nr', pad(n)));
    const days = el('span', 'days');
    DAYS.forEach(([bit, name]) => days.append(el('span', 'day-chip' + (hasDay(e, bit) ? ' on' : ''), T(name).slice(0, 2))));
    row.append(days, el('span', 'when', whenText(e)), el('span', 'state' + ((e.LEVEL || 0) === 0 ? ' off' : ''), levelText(e)), el('span', 'chev', icon('chevron')));
    row.onclick = () => select(n, false);
    return row;
  }
  function renderRowOf(n) {
    const item = document.querySelector('.item[data-n="' + n + '"] .head-row');
    if (!item) return;
    item.replaceChild(rowContent(n, draft[n]), item.querySelector('.row'));
  }

  function renderList() {
    const list = $('list');
    list.innerHTML = '';
    entries().forEach(([n, e]) => {
      const item = el('li', 'item');
      item.dataset.n = n;
      const head = el('div', 'head-row');
      head.append(rowContent(n, e));
      if (editing) {
        const del = el('button', 'icon-btn small', icon('trash'));
        del.type = 'button';
        del.title = T('Delete');
        del.onclick = () => { delete draft[n]; renumber(); selected = 0; changed(); render(); };
        head.append(del);
      }
      item.append(head);
      if (editing) {
        item.append(form(n, e));
      } else {
        const detail = el('dl', 'detail');
        const add = (k, v) => { detail.append(el('dt', '', T(k)), el('dd', '', v)); };  // T() liefert unbekannte Schlüssel unverändert
        add('Condition', T('Condition ' + (e.CONDITION || 0)));
        if (usesFixed(e)) add('Time', fixedTime(e));
        if (usesAstro(e)) add('Astro', astroText(e));
        add('State', levelText(e));
        add('Duration', durationText(e));
        extraFields().forEach(x => add(labelOf(x.name), extraText(x, e)));
        add('Weekdays', DAYS.filter(d => hasDay(e, d[0])).map(d => T(d[1])).join(', '));
        add('Channels', channelNames(e.TARGET_CHANNELS || 0) || '–');
        item.append(detail);
      }
      list.append(item);
    });
    $('add').hidden = !editing || entries().length >= maxEntries;
  }

  // Nummern lückenlos ab 1
  function renumber() {
    const next = {};
    entries().forEach(([n, e], i) => { next[i + 1] = e; });
    draft = next;
  }

  function renderWeek() {
    const week = $('week');
    week.innerHTML = '';
    if (!entries().length) return;
    const today = new Date().getDay();
    // Kopfzeile mit den Wochentagen
    week.append(el('div', 'head'));
    DAYS.forEach(([bit, name]) => week.append(el('div', 'head' + (bit === today ? ' today' : ''), T(name).slice(0, 2))));
    // Astro-Zeile
    week.append(el('div', 'astro-label', icon('sunrise')));
    const astro = el('div', 'astro');
    DAYS.forEach(([bit]) => {
      const cell = el('div', '');
      entries().filter(([n, e]) => usesAstro(e) && hasDay(e, bit)).forEach(([n, e]) => {
        const m = el('button', 'amark' + ((e.LEVEL || 0) === 0 ? ' off' : ''), icon(e.ASTRO_TYPE ? 'sunset' : 'sunrise'));
        m.type = 'button';
        m.dataset.n = n;
        m.title = whenText(e);
        m.onclick = () => select(n, true);
        cell.append(m);
      });
      astro.append(cell);
    });
    week.append(astro);
    // Uhrzeiten und Spalten der Tage
    const hours = el('div', 'hours');
    [0, 6, 12, 18, 24].forEach(h => { const s = el('span', '', pad(h)); s.style.top = (h / 24 * 100) + '%'; hours.append(s); });
    week.append(hours);
    DAYS.forEach(([bit]) => {
      const col = el('div', 'day' + (bit === today ? ' today' : ''));
      entries().filter(([n, e]) => usesFixed(e) && hasDay(e, bit)).forEach(([n, e]) => {
        const start = ((e.FIXED_HOUR || 0) * 60 + (e.FIXED_MINUTE || 0)) / 1440;
        const length = seconds(e) === null ? 1 - start : (seconds(e) || 0) / 86400;
        const m = el('button', 'mark' + ((e.LEVEL || 0) === 0 ? ' off' : '') + (seconds(e) === null ? ' perm' : ''));
        m.type = 'button';
        m.dataset.n = n;
        m.title = fixedTime(e) + ' ' + levelText(e);
        m.style.top = (start * 100) + '%';
        m.style.height = Math.max(5, length * WEEK_HEIGHT) + 'px';
        m.onclick = () => select(n, true);
        col.append(m);
      });
      week.append(col);
    });
  }

  // Modus je Schaltkanal (manuell/automatisch): mit Erlaubnis ein Klick zum Umschalten
  function renderModes() {
    const box = $('modes');
    box.innerHTML = '';
    box.hidden = !modes.length;
    if (!modes.length) return;
    box.append(el('span', 'modes-label', T('Mode')));
    modes.forEach((manual, i) => {
      const chip = el(cfg.editable ? 'button' : 'span', 'mode' + (manual ? ' manual' : ''), '<b>' + channelLabel(i) + '</b> ' + T(manual ? 'Manual' : 'Automatic'));
      if (cfg.editable) {
        chip.type = 'button';
        chip.title = T('Mode');
        chip.onclick = () => {
          notice = T('Saving…');
          refreshBar();
          try { requestAction('Mode', JSON.stringify({ channel: i, mode: manual ? 'AUTO_MODE_WITHOUT_RESET' : 'MANU_MODE' })); } catch (e) { notice = ''; refreshBar(); }
        };
      }
      box.append(chip);
    });
  }
  function refreshBar() {
    $('summary').textContent = summaryText();
    $('apply').hidden = !dirty;
    $('discard').hidden = !dirty;
    $('apply').disabled = busy;
    $('edit').classList.toggle('on', editing);
  }
  function render() {
    renderList();
    renderWeek();
    renderModes();
    refreshBar();
    $('empty').textContent = T('No switching times');
    $('empty').hidden = entries().length > 0;
    select(selected, true);
  }

  // Schaltzeitpunkte nach Uhrzeit ordnen (Astro-Zeiten nach 6 Uhr bzw. 18 Uhr plus Versatz)
  function sortKey(e) {
    if (usesFixed(e)) return (e.FIXED_HOUR || 0) * 60 + (e.FIXED_MINUTE || 0);
    return (e.ASTRO_TYPE ? 1080 : 360) + (e.ASTRO_OFFSET || 0);
  }
  function apply() {
    const list = entries().map(([n, e]) => e);
    if (list.some(e => !(e.WEEKDAY || 0))) {
      notice = T('Every switching time needs at least one weekday.');
      refreshBar();
      return;
    }
    list.forEach(fixDuration);
    list.sort((a, b) => sortKey(a) - sortKey(b));
    busy = true;
    notice = T('Saving…');
    refreshBar();
    try { requestAction('Save', JSON.stringify(list)); } catch (e) { busy = false; notice = ''; refreshBar(); }
  }
  function discard() {
    draft = clone(program);
    dirty = false;
    notice = '';
    selected = 0;
    render();
  }
  function newEntry() {
    const e = {};
    fields.forEach(f => {
      const i = info[f];
      let v = 0;
      if (i && i.Default !== null && i.Default !== undefined) v = i.Type === 'ENUM' ? Math.max(0, (i.List || []).indexOf(i.Default)) : Number(i.Default) || 0;
      e[f] = v;
    });
    Object.assign(e, { WEEKDAY: 127, CONDITION: 0, FIXED_HOUR: 12, FIXED_MINUTE: 0, LEVEL: 1, TARGET_CHANNELS: (1 << channelCount()) - 1 });
    fixDuration(e);
    return e;
  }

  // Größenstufen nach der Größe der Kachel
  function layout() {
    const w = app.clientWidth, h = app.clientHeight;
    const size = h < 150 ? 'mini' : (w >= 560 && h >= 360 ? 'full' : 'compact');
    ['mini', 'compact', 'full'].forEach(s => app.classList.toggle('size-' + s, s === size));
    app.classList.toggle('narrow', w < 480);
  }
  new ResizeObserver(layout).observe(app);

  $('reload').innerHTML = icon('refresh');
  $('reload').title = T('Reload');
  $('reload').onclick = () => { try { requestAction('Reload', ''); } catch (e) { /* außerhalb von Symcon */ } };
  // Die Instanz erlaubt das Bearbeiten (Eigenschaft); ohne Erlaubnis bleibt die Kachel eine reine Anzeige
  $('edit').hidden = !cfg.editable;
  $('edit').innerHTML = icon('edit');
  $('edit').title = T('Edit');
  $('edit').onclick = () => { editing = !editing; render(); };
  $('apply').textContent = T('Apply');
  $('apply').onclick = apply;
  $('discard').textContent = T('Discard');
  $('discard').onclick = discard;
  $('add').innerHTML = icon('plus') + '<span>' + T('Add switching time') + '</span>';
  $('add').onclick = () => {
    const n = entries().length + 1;
    draft[n] = newEntry();
    selected = n;
    changed();
    render();
  };

  // Nachrichten der Instanz
  window.handleMessage = function (data) {
    let msg;
    try { msg = JSON.parse(data); } catch (e) { return; }
    if (!msg) return;
    if (msg.type === 'error') {
      busy = false;
      notice = msg.text || '';
      refreshBar();
      return;
    }
    if (msg.type !== 'program') return;
    program = msg.program || {};
    channels = msg.channels || channels;
    fields = msg.fields || fields;
    info = msg.info || info;
    modes = msg.modes || modes;
    if (notice === T('Saving…') && !busy && !dirty) notice = '';
    // Ein Entwurf bleibt erhalten, bis er übernommen oder verworfen wird; nach dem Speichern (busy) gilt der gelesene Stand
    if (!dirty || busy) {
      draft = clone(program);
      dirty = false;
      notice = '';
    }
    busy = false;
    render();
  };

  layout();
  render();
  // Die nächste Schaltung wird jede Minute neu berechnet
  setInterval(() => { if (!dirty) $('summary').textContent = summaryText(); }, 60000);
})();
