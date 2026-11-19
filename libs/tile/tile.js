/* HomematicExtended – gemeinsame Bausteine der Listen-Kacheln (HTML-SDK), nach der FritzBox-Library (gleicher Autor).
   Das Skript des Moduls setzt HMT.render(data, size) und liefert damit das HTML der Kachel; die Kachel wird bei neuen Daten
   und beim Wechsel der Größenstufe neu gezeichnet. Bedient wird über Attribute im HTML:
   data-act="Ident" data-value="JSON"  -> requestAction(Ident, Wert), optional mit data-confirm="Text" (Sicherheitsabfrage)
   data-on="Name"                      -> HMT.handlers[Name](Element, Ereignis) des Modulskripts
   data-chip="Gruppe" data-v="Wert"    -> Auswahl einer Chip-Gruppe (HMT.chips), im Browser gemerkt
   select[data-limit="Liste"]          -> Anzahl der angezeigten Einträge einer Liste (im Browser gemerkt)
   input[data-search]                  -> Suchtext (HMT.search), Fokus bleibt beim Neuzeichnen erhalten */
(function () {
  'use strict';
  // Workaround Kachel-Visu: Beim Maximieren setzt die Visu die Adresse des vorhandenen iframes neu (anderes margintop).
  // Das erzeugt einen Eintrag im Browserverlauf; „Zurück“ lädt dann nur den alten iframe-Inhalt (Titel überlagert die
  // Kachel) statt den Dialog zu schließen. Ist dieser Inhalt per Zurück geladen und passt nicht mehr zur Adresse des
  // iframes, gleich einen Schritt weiter zurück.
  try {
    const nav = performance.getEntriesByType('navigation')[0];
    const frame = window.frameElement;
    if (nav && nav.type === 'back_forward' && frame && frame.src && new URL(frame.src, location.href).search !== location.search) {
      history.back();
      return;
    }
  } catch (e) { /* ohne Zugriff auf das iframe: nichts tun */ }
  const cfg = window.HMT_CONFIG || { data: {}, texts: {} };
  const app = document.getElementById('app');
  // Sprache des Browsers für die Silbentrennung (lange Wörter in der kleinen Kachel, CSS hyphens)
  try { document.documentElement.lang = (navigator.languages && navigator.languages[0]) || navigator.language || 'de'; } catch (e) { /* ohne Angabe */ }
  const HMT = window.HMT = {
    cfg: cfg,
    data: cfg.data || {},
    size: 'compact',
    search: '',
    handlers: {},
    render: () => '',
    afterRender: null,
    onMessage: null
  };

  // Übersetzung in der Sprache des Browsers über translate() der Visu (locale.json des Moduls).
  // Die Texte der Instanz gelten nur ohne Visu (z.B. Vorschau).
  HMT.T = key => {
    if (typeof window.translate === 'function') {
      try {
        const text = window.translate(key);
        if (typeof text === 'string' && text !== '') {
          return text;
        }
      } catch (e) { /* ohne Visu */ }
    }
    return (cfg.texts || {})[key] || key;
  };
  HMT.esc = s => String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  // Symbole wie in der Heizungs- und Wochenprogramm-Kachel: Material-Symbole (24er Raster) als Inline-SVG.
  // Module ergänzen eigene Pfade in HMT.icons.
  HMT.icons = {
    linkOff: 'M17 7h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1 0 1.43-.98 2.63-2.31 2.98l1.46 1.46C20.88 15.61 22 13.95 22 12c0-2.76-2.24-5-5-5zm-1 4h-2.19l2 2H16zM2 4.27l3.11 3.11C3.29 8.12 2 9.91 2 12c0 2.76 2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1 0-1.59 1.21-2.9 2.76-3.07L8.73 11H8v2h2.73L13 15.27V17h1.73l4.01 4L20 19.74 3.27 3 2 4.27z',
    battery: 'M15.67 4H14V2h-4v2H8.33C7.6 4 7 4.6 7 5.33v15.33C7 21.4 7.6 22 8.33 22h7.33c.74 0 1.34-.6 1.34-1.33V5.33C17 4.6 16.4 4 15.67 4zM13 18h-2v-2h2v2zm0-4h-2V9h2v5z',
    sync: 'M3 12c0 2.21.91 4.2 2.36 5.64L3 20h6v-6l-2.24 2.24C5.68 15.15 5 13.66 5 12c0-2.61 1.67-4.83 4-5.65V4.26C5.55 5.15 3 8.27 3 12zm8 5h2v-2h-2v2zM21 4h-6v6l2.24-2.24C18.32 8.85 19 10.34 19 12c0 2.61-1.67 4.83-4 5.65v2.09c3.45-.89 6-4.01 6-7.74 0-2.21-.91-4.2-2.36-5.64L21 4zm-10 9h2V7h-2v6z',
    warning: 'M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z',
    check: 'M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z',
    checkAll: 'M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41 6 19l1.41-1.41L1.83 12 .41 13.41z',
    ok: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z',
    shield: 'M12 2 4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z',
    refresh: 'M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z'
  };
  HMT.icon = name => HMT.icons[name] ? '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="' + HMT.icons[name] + '"/></svg>' : '';
  HMT.act = (ident, value) => {
    try { requestAction(ident, value); } catch (e) { /* außerhalb von Symcon */ }
  };
  // Wert als Attribut data-value
  HMT.val = v => HMT.esc(JSON.stringify(v));
  // Datum/Uhrzeit (Unix-Zeit) in der Sprache des Browsers; heute nur die Uhrzeit
  HMT.time = t => {
    if (!t) return '';
    const d = new Date(t * 1000);
    const now = new Date();
    const time = d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    return d.toDateString() === now.toDateString() ? time : d.toLocaleDateString(undefined, { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + time;
  };

  // Im Browser gemerkte Einstellungen je Instanz (Chip-Auswahl, Anzahl der Einträge)
  const storeKey = name => 'hmt.' + cfg.instance + '.' + name;
  HMT.load = (name, def) => {
    try {
      const v = localStorage.getItem(storeKey(name));
      if (v !== null) return v;
    } catch (e) { /* ohne Speicher */ }
    return def;
  };
  HMT.save = (name, value) => {
    try { localStorage.setItem(storeKey(name), String(value)); } catch (e) { /* ohne Speicher */ }
  };

  // Chip-Gruppe (Umschalter): options = [[Wert, Beschriftung], …]; die Beschriftung wird übersetzt.
  // Die Auswahl liest das Modulskript mit HMT.chip(Gruppe, Standard).
  HMT.chip = (group, def) => HMT.load('chip.' + group, def);
  // current: wirksame Auswahl, falls das Modulskript eine gemerkte, nicht mehr vorhandene Auswahl ersetzt
  HMT.chips = (group, options, def, current) => {
    const cur = current === undefined ? HMT.chip(group, def) : current;
    return '<span class="seg">' + options.map(o => '<button data-chip="' + HMT.esc(group) + '" data-v="' + HMT.esc(o[0]) + '"' +
      (String(o[0]) === String(cur) ? ' class="on"' : '') + (o[2] ? ' title="' + HMT.esc(o[2]) + '"' : '') + '>' + HMT.esc(HMT.T(o[1])) + '</button>').join('') + '</span>';
  };
  // Suchfeld; der Text steht in HMT.search, HMT.matches prüft Texte dagegen (ohne Groß-/Kleinschreibung)
  HMT.searchInput = () => '<input type="search" data-search placeholder="' + HMT.esc(HMT.T('Search')) + '" value="' + HMT.esc(HMT.search) + '">';
  HMT.matches = texts => {
    if (!HMT.search) return true;
    const s = HMT.search.toLowerCase();
    return texts.some(x => String(x === null || x === undefined ? '' : x).toLowerCase().includes(s));
  };

  // Anzahl der angezeigten Einträge je Liste
  HMT.limit = (list, def) => Number(HMT.load('limit.' + list, def));
  HMT.limitSelect = (list, def, total) => {
    const cur = HMT.limit(list, def);
    const opts = [5, 10, 25, 50, 100, 0].filter(n => n === 0 || n < total || n === cur);
    if (total <= 5 && cur !== 0 && cur >= total) return '';
    return '<label class="muted limit">' + HMT.esc(HMT.T('Entries')) + ' <select data-limit="' + HMT.esc(list) + '">' +
      opts.map(n => '<option value="' + n + '"' + (n === cur ? ' selected' : '') + '>' + (n === 0 ? HMT.esc(HMT.T('All')) : n) + '</option>').join('') +
      '</select></label>';
  };
  HMT.slice = (list, items, def) => {
    const n = HMT.limit(list, def);
    return n > 0 ? items.slice(0, n) : items;
  };

  // Sicherheitsabfrage in der Kachel
  HMT.confirm = (text, onOk) => {
    const d = document.createElement('div');
    d.className = 'dialog';
    d.innerHTML = '<div><div>' + HMT.esc(text) + '</div><div class="btns"><button class="tool" data-x="0">' + HMT.esc(HMT.T('Cancel')) +
      '</button><button class="tool primary" data-x="1">' + HMT.esc(HMT.T('OK')) + '</button></div></div>';
    d.addEventListener('click', e => {
      const b = e.target.closest('[data-x]');
      if (!b && e.target !== d) return;
      d.remove();
      if (b && b.dataset.x === '1') onOk();
    });
    app.appendChild(d);
  };
  // Hinweis in der Kachel
  let noticeTimer = 0;
  HMT.notice = text => {
    let n = app.querySelector('.notice');
    if (!n) {
      n = document.createElement('div');
      n.className = 'notice';
      app.appendChild(n);
    }
    n.textContent = text;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => n.remove(), 6000);
  };
  // Kein Öffnen fremder Objekte per openObject: Geräte-Instanzen liegen meist außerhalb des Objektbaums der Visu
  // (Startkategorie), die Visu meldet dann „Objekt mit der ID … existiert nicht“ (Rückmeldung des Nutzers 2026-11-19).

  // Überschrift der Kachel: Die Visu übergibt margintop (mit Titel > 15) und legt den Titel über die Kachel
  try {
    const top = Number(new URLSearchParams(location.search).get('margintop'));
    if (top > 15) {
      document.documentElement.style.setProperty('--title-space', (top - 15) + 'px');
      document.documentElement.style.setProperty('--corner', '0px');
    }
  } catch (e) { /* ohne Angabe: kein Titel */ }

  HMT.draw = () => {
    app.className = 'app size-' + HMT.size;
    // Dialog und Hinweis bleiben beim Neuzeichnen erhalten
    const keep = Array.from(app.querySelectorAll('.dialog, .notice'));
    // Scrollposition der Listen merken, sonst springt die Kachel bei jeder Bedienung (neue Daten) nach oben
    const SCROLLERS = '.list, .scroll, .col';
    const scroll = Array.from(app.querySelectorAll(SCROLLERS)).map(el => [el.scrollTop, el.scrollLeft]);
    // Fokus und Cursor im Suchfeld erhalten
    const active = document.activeElement;
    const searching = active && active.matches && active.matches('[data-search]') ? active.selectionStart : null;
    let html = '';
    try {
      html = HMT.render(HMT.data, HMT.size);
    } catch (e) {
      html = '<div class="empty">' + HMT.esc(e.message) + '</div>';
    }
    app.innerHTML = html;
    const scrollers = app.querySelectorAll(SCROLLERS);
    if (scrollers.length === scroll.length) {
      scrollers.forEach((el, i) => {
        el.scrollTop = scroll[i][0];
        el.scrollLeft = scroll[i][1];
      });
    }
    keep.forEach(k => app.appendChild(k));
    if (searching !== null) {
      const input = app.querySelector('[data-search]');
      if (input) {
        input.focus();
        input.setSelectionRange(searching, searching);
      }
    }
    applyFlash();
    if (HMT.afterRender) HMT.afterRender(app);
  };
  // Optisches Feedback: ein bedientes Element (data-act) leuchtet kurz auf, auch wenn die Kachel inzwischen neu gezeichnet hat.
  // Elemente mit data-wait (z.B. Neu lesen) bleiben „busy“ (Symbol dreht sich), bis neue Daten kommen, höchstens 15 s.
  let flashKey = null;
  let waitTimer = 0;
  const actKey = el => el.dataset.act + '|' + (el.dataset.value || '');
  function applyFlash() {
    app.querySelectorAll('[data-act]').forEach(el => el.classList.toggle('busy', flashKey !== null && actKey(el) === flashKey));
  }
  function endFlash() {
    flashKey = null;
    clearTimeout(waitTimer);
    applyFlash();
  }
  function flash(el) {
    const key = actKey(el);
    flashKey = key;
    applyFlash();
    clearTimeout(waitTimer);
    waitTimer = setTimeout(() => {
      if (flashKey === key) endFlash();
    }, el.dataset.wait !== undefined ? 15000 : 700);
  }

  // Größenstufe nach der Größe der Kachel (Raster der Visu: 1×1 mini, 3×2 compact, ab ca. 7×4 wide/full);
  // neu gezeichnet wird nur beim Wechsel der Stufe. Unter HMT.miniWidth/HMT.miniHeight nur die Kennzahl.
  const stage = (w, h) => (w < (HMT.miniWidth || 250) || h < (HMT.miniHeight || 160)) ? 'mini' : (w >= 880 && h >= 520) ? 'full' : (w >= 560 && h >= 300) ? 'wide' : 'compact';
  let ready = false;
  new ResizeObserver(() => {
    const s = stage(window.innerWidth, window.innerHeight);
    if (s !== HMT.size) {
      HMT.size = s;
      if (ready) HMT.draw();
    }
  }).observe(document.documentElement);

  app.addEventListener('click', e => {
    const a = e.target.closest('[data-act]');
    if (a && !a.disabled) {
      const run = () => {
        flash(a);
        HMT.act(a.dataset.act, a.dataset.value === undefined ? true : JSON.parse(a.dataset.value));
      };
      if (a.dataset.confirm) HMT.confirm(a.dataset.confirm, run); else run();
      return;
    }
    const c = e.target.closest('[data-chip]');
    if (c) {
      HMT.save('chip.' + c.dataset.chip, c.dataset.v);
      HMT.draw();
      return;
    }
    const h = e.target.closest('[data-on]');
    if (h && !h.disabled && HMT.handlers[h.dataset.on]) {
      HMT.handlers[h.dataset.on](h, e);
    }
  });
  app.addEventListener('change', e => {
    const s = e.target.closest('select[data-limit]');
    if (!s) return;
    HMT.save('limit.' + s.dataset.limit, s.value);
    HMT.draw();
  });
  app.addEventListener('input', e => {
    const i = e.target.closest('[data-search]');
    if (!i) return;
    HMT.search = i.value;
    HMT.draw();
  });

  // Nachrichten der Instanz (UpdateVisualizationValue): state = neue Daten, notice = Hinweis
  window.handleMessage = raw => {
    let msg;
    try { msg = typeof raw === 'string' ? JSON.parse(raw) : raw; } catch (e) { return; }
    if (!msg) return;
    if (msg.type === 'state') {
      HMT.data = msg.data || {};
      // Neue Daten beenden eine Ladeanzeige (data-wait)
      if (flashKey !== null) {
        flashKey = null;
        clearTimeout(waitTimer);
      }
      if (ready) HMT.draw(); else firstDraw();
    } else if (msg.type === 'notice') {
      HMT.notice(msg.text);
    } else if (HMT.onMessage) {
      HMT.onMessage(msg);
    }
  };
  // Erstes Zeichnen: Die Visu übersetzt beim ersten Laden einmal das ganze Dokument (translateHTML), also auch Daten,
  // die zufällig einem Schlüssel der locale.json entsprechen; Daten per UpdateVisualizationValue übersetzt sie nicht.
  // In der Visu daher erst zeichnen, wenn die Daten nachgeschoben sind (TileReload -> PushTile). Ohne Visu (z.B. Vorschau)
  // bzw. ohne Antwort aus den eingebetteten Daten.
  function firstDraw() {
    if (ready) return;
    ready = true;
    HMT.size = stage(window.innerWidth, window.innerHeight);
    HMT.draw();
  }
  setTimeout(() => {
    if (typeof window.requestAction !== 'function') {
      firstDraw();
      return;
    }
    HMT.act('TileReload', true);
    setTimeout(firstDraw, 2000);
  }, 0);
})();
