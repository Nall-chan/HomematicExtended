/* Servicemeldungen – Kachel.
   mini (1×1) = Anzahl der Meldungen; compact (3×2) = Liste mit Filtern; wide/full (7×4) = links die Anzahl je Art, rechts die Liste
   mit Filtern, Suche, „CCU lesen“ und „Alle bestätigen“. Bestätigen gibt es nur für Meldungen, die die CCU bestätigen lässt. */
(function () {
  'use strict';
  // Filterleiste braucht Platz: unter ca. 4 Spalten Breite nur die Anzahl
  HMT.miniWidth = 300;
  const { T, esc, icon, val } = HMT;
  const GROUPS = [['all', 'All'], ['unreach', 'Reachability'], ['battery', 'Battery'], ['sabotage', 'Sabotage'], ['config', 'Configuration'], ['other', 'Other']];
  const GROUP_ICON = { unreach: 'linkOff', battery: 'battery', sabotage: 'shield', config: 'sync', other: 'warning' };
  const GROUP_CLASS = { unreach: 'error', battery: 'warn', sabotage: 'error', config: '', other: 'warn' };
  // Kurzname der Schnittstelle
  const IFACE = { 'BidCos-RF': 'RF', 'HmIP-RF': 'HmIP', 'BidCos-Wired': 'Wired', 'VirtualDevices': 'Virtual' };
  const ifaceLabel = i => IFACE[i] || i || '?';

  // Text der Meldung: Wert einer Aufzählung, sonst der Datenpunkt; Zahlen (z.B. ERROR_CODE) mit Wert
  const messageText = m => {
    if (m.text) return T(m.text);
    const dp = T(m.datapoint);
    return (typeof m.value === 'number' && m.value !== 1) ? dp + ' (' + m.value + ')' : dp;
  };
  const deviceLabel = m => m.instanceName || m.channelName || m.deviceName || m.address;

  function row(m, size) {
    // Nur der Name der Symcon-Instanz, kein Öffnen per Klick: die Instanzen liegen meist außerhalb des Objektbaums der Visu
    const name = m.instanceID ? '<span class="l1">' + esc(deviceLabel(m)) + '</span>'
      : '<span class="l1" title="' + esc(T('Device not set up in Symcon')) + '">' + esc(deviceLabel(m)) + '</span>';
    const l2 = [messageText(m), size === 'compact' ? '' : m.address, size === 'full' ? m.type : '', size === 'compact' ? '' : m.rooms,
      size === 'full' ? ifaceLabel(m.interface) : ''].filter(x => x).map(esc).join(' · ');
    const times = size === 'full' && m.first && m.first !== m.last
      ? '<span title="' + esc(T('First')) + ' / ' + esc(T('Last')) + '">' + esc(HMT.time(m.first)) + ' – ' + esc(HMT.time(m.last)) + '</span>'
      : '<span title="' + esc(T('Last')) + '">' + esc(HMT.time(m.last)) + '</span>';
    return '<li class="row"><span class="ico ' + (GROUP_CLASS[m.group] || '') + '">' + icon(GROUP_ICON[m.group] || 'warning') + '</span>' +
      '<span class="main">' + name + '<span class="l2">' + l2 + '</span></span>' +
      '<span class="side">' + times +
      (m.confirmable ? '<button class="btn" data-act="TileConfirm" data-value="' + val(m.id) + '" title="' + esc(T('Confirm')) + '">' + icon('check') + '</button>' : '') +
      '</span></li>';
  }

  // Stand der Liste (Uhrzeit der CCU beim letzten Lesen); Klick liest neu, das Symbol dreht sich bis zur Antwort (data-wait)
  const stamp = d => '<button class="stamp" data-act="TileRead" data-wait title="' + esc(T('Read CCU')) + '">' + icon('refresh') +
    '<span>' + esc(T('As of %s').replace('%s', d.time ? HMT.time(d.time) : '–')) + '</span></button>';

  HMT.render = (d, size) => {
    const all = d.messages || [];
    const count = g => all.filter(m => m.group === g).length;
    // Nur Arten mit Meldungen; eine gemerkte Art ohne Meldung gilt als „Alle“
    const groups = GROUPS.slice(1).filter(g => count(g[0]));
    let group = HMT.chip('group', 'all');
    if (group !== 'all' && !groups.some(g => g[0] === group)) group = 'all';
    const kpi = '<div class="kpi' + (all.length ? ' alert' : '') + '"><div class="big">' + (all.length ? all.length : icon('ok')) + '</div><div class="sub">' +
      esc(all.length ? T('Service messages') : T('No service messages')) + '</div>' +
      // Anzahl je Art als Legende untereinander; filtert wie die Chips über der Liste (gleiche Auswahl), Klick auf die aktive Zeile: alle
      (size !== 'mini' && all.length ? '<div class="parts">' + groups.map(g => {
        const on = group === g[0];
        return '<button class="part' + (on ? ' on' : '') + '" data-chip="group" data-v="' + (on ? 'all' : g[0]) + '">' +
          '<span class="ico ' + (GROUP_CLASS[g[0]] || '') + '">' + icon(GROUP_ICON[g[0]]) + '</span><span class="lbl">' + esc(T(g[1])) + '</span><b>' + count(g[0]) + '</b></button>';
      }).join('') + '</div>' : '') +
      (size !== 'mini' ? stamp(d) : '') + '</div>';
    if (size === 'mini') return kpi;

    // Filter: Art der Meldung und Schnittstelle (nur vorhandene); eine gemerkte Schnittstelle ohne Meldung gilt als „Alle“
    const ifaces = Array.from(new Set(all.map(m => m.interface))).sort();
    let iface = HMT.chip('iface', 'all');
    if (iface !== 'all' && !ifaces.includes(iface)) iface = 'all';
    const list = all.filter(m => (group === 'all' || m.group === group) && (iface === 'all' || m.interface === iface) &&
      HMT.matches([deviceLabel(m), m.deviceName, m.address, m.type, m.rooms, m.functions, messageText(m)]));
    const def = size === 'compact' ? 10 : 50;
    const items = HMT.slice('messages', list, def);
    const confirmable = all.filter(m => m.confirmable).length;
    // Zeile 1: Filter Art und Schnittstelle (nur vorhandene, Gruppen nur, wenn es etwas zu wählen gibt), ab 7×4 rechts die Suche;
    // Zeile 2: Einträge links, rechts Alle bestätigen (ab 7×4) bzw. CCU lesen (3×2)
    const tools = (groups.length > 1 ? HMT.chips('group', [['all', 'All']].concat(groups), 'all', group) : '') +
      (ifaces.length > 1 ? HMT.chips('iface', [['all', 'All']].concat(ifaces.map(i => [i, ifaceLabel(i), i])), 'all', iface) : '') +
      (size === 'compact' ? '' : '<span class="spacer"></span>' + HMT.searchInput());
    const actions = HMT.limitSelect('messages', def, list.length) + '<span class="spacer"></span>' +
      (confirmable && size !== 'compact' ? '<button class="tool" data-act="TileConfirm" data-value="0" data-confirm="' + esc(T('Confirm all service messages that can be confirmed?')) + '">' +
        icon('checkAll') + esc(T('Confirm all')) + ' (' + confirmable + ')</button>' : '') +
      // In 3×2 fehlt die linke Spalte mit dem Stand: dort die Schaltfläche zum Neulesen in der Werkzeugleiste
      (size === 'compact' ? '<button class="btn" data-act="TileRead" data-wait title="' + esc(T('Read CCU')) + '">' + icon('refresh') + '</button>' : '');
    const body = '<div class="group grow">' + (tools ? '<div class="ghead">' + tools + '</div>' : '') + '<div class="ghead">' + actions + '</div>' +
      (items.length ? '<ul class="list">' + items.map(m => row(m, size)).join('') + '</ul>' : '<div class="empty">' + esc(T('No service messages')) + '</div>') + '</div>';
    if (size === 'compact') return body;
    return '<div class="cols"><div class="col side">' + kpi + '</div><div class="col main">' + body + '</div></div>';
  };

})();
