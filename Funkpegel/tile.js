/* Funkpegel – Kachel.
   mini (1×1) = Anzahl der Geräte mit schwachem Empfang; compact (3×2) = Liste mit Filtern; wide/full (7×4) = links Anzahl je Qualität
   (filtert wie die Chips), in voller Größe die Funkmodule mit Duty Cycle, rechts die Liste mit Filtern und Suche.
   Je Gerät beide Richtungen: ↓ das Gerät empfängt die Zentrale, ↑ die Zentrale (Gateway) empfängt das Gerät; bewertet wird die schwächere. */
(function () {
  'use strict';
  HMT.miniWidth = 300;
  const { T, esc, icon } = HMT;
  const QUALITY = [['all', 'All'], ['weak', 'Weak'], ['medium', 'Medium'], ['good', 'Good'], ['unknown', 'Unknown']];
  const QUALITY_CLASS = { good: 'good', medium: 'warn', weak: 'error', unknown: '' };
  const BARS = { good: 3, medium: 2, weak: 1, unknown: 0 };
  const IFACE = { 'BidCos-RF': 'RF', 'HmIP-RF': 'HmIP' };
  const ifaceLabel = i => IFACE[i] || i || '?';

  // Balken wie eine Empfangsanzeige: 3 gut, 2 mittel, 1 schwach, 0 unbekannt
  const bars = q => '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">' + [[3, 15, 5], [9.5, 10, 10], [16, 4, 16]].map((b, i) =>
    '<rect x="' + b[0] + '" y="' + b[1] + '" width="5" height="' + b[2] + '" rx="1"' + (i < BARS[q] ? '' : ' opacity=".25"') + '/>').join('') + '</svg>';
  const dbm = v => v === null || v === undefined ? '–' : String(v);
  const deviceLabel = x => x.instanceName || x.name || x.address;

  function render(d, size) {
    const all = d.devices || [];
    const gateways = d.gateways || [];
    const gwByAddress = {};
    gateways.forEach(g => { gwByAddress[g.address] = g; });
    const gwLabel = a => {
      const g = gwByAddress[a];
      if (!g) return a;
      return (g.name || g.type || g.address) + (g.connected ? '' : ' (' + T(g.configured ? 'not connected' : 'no longer set up') + ')');
    };
    const count = q => all.filter(x => x.quality === q).length;
    const qualities = QUALITY.slice(1).filter(q => count(q[0]));
    let quality = HMT.chip('quality', 'all');
    if (quality !== 'all' && !qualities.some(q => q[0] === quality)) quality = 'all';

    const weak = count('weak');
    const kpi = '<div class="kpi' + (weak ? ' alert' : '') + '"><div class="big">' + weak + '<small> / ' + all.length + '</small></div><div class="sub">' +
      esc(T('Weak reception')) + '</div>' +
      // Anzahl je Qualität als Legende untereinander; filtert wie die Chips über der Liste, Klick auf die aktive Zeile: alle
      (size !== 'mini' && all.length ? '<div class="parts">' + qualities.map(q => {
        const on = quality === q[0];
        return '<button class="part' + (on ? ' on' : '') + '" data-chip="quality" data-v="' + (on ? 'all' : q[0]) + '">' +
          '<span class="ico ' + QUALITY_CLASS[q[0]] + '">' + bars(q[0]) + '</span><span class="lbl">' + esc(T(q[1])) + '</span><b>' + count(q[0]) + '</b></button>';
      }).join('') + '</div>' : '') +
      (size === 'full' && gateways.length ? modules(gateways) : '') +
      (size !== 'mini' ? stamp(d) : '') + '</div>';
    if (size === 'mini') return kpi;

    // Filter: Qualität, Schnittstelle und bei BidCos-RF das Gateway (nur vorhandene; eine gemerkte, nicht mehr vorhandene Auswahl gilt als „Alle“)
    const ifaces = Array.from(new Set(all.map(x => x.interface))).sort();
    let iface = HMT.chip('iface', 'all');
    if (iface !== 'all' && !ifaces.includes(iface)) iface = 'all';
    // Gateways, die Geräte hören (zugeordnet oder mit Pegel in rssiInfo): verbundene zuerst, dann nach Name
    const hears = (x, g) => x.gateway === g || (x.links || []).some(l => l.gateway === g);
    const rfGateways = Array.from(new Set(all.filter(x => x.interface === 'BidCos-RF')
      .flatMap(x => [x.gateway].concat((x.links || []).map(l => l.gateway))).filter(g => g)))
      .sort((a, b) => ((gwByAddress[b] || {}).connected ? 1 : 0) - ((gwByAddress[a] || {}).connected ? 1 : 0) || gwLabel(a).localeCompare(gwLabel(b)));
    let gateway = HMT.chip('gateway', 'all');
    if (iface !== 'BidCos-RF' || !rfGateways.includes(gateway)) gateway = 'all';
    // Mit gewähltem Gateway: alle Geräte, die es hört, mit den Pegeln zu diesem Gateway (Bewertung bleibt beim zugeordneten)
    const list = all.filter(x => (quality === 'all' || x.quality === quality) && (iface === 'all' || x.interface === iface) &&
      (gateway === 'all' || hears(x, gateway)) &&
      HMT.matches([deviceLabel(x), x.name, x.address, x.type, x.interface === 'BidCos-RF' ? gwLabel(x.gateway) : '']))
      .map(x => {
        if (gateway === 'all' || x.gateway === gateway) return Object.assign({}, x, { shown: x.gateway });
        const link = (x.links || []).find(l => l.gateway === gateway) || { device: null, peer: null };
        return Object.assign({}, x, { shown: gateway, device: link.device, peer: link.peer });
      });
    // Sortierung nach einer Richtung (Klick auf die Legende), aufsteigend = schwächster Pegel zuerst; ohne Wert immer am Ende
    const [sortKey, sortDir] = currentSort();
    const other = sortKey === 'peer' ? 'device' : 'peer';
    const num = v => (v === null || v === undefined) ? null : v;
    list.sort((a, b) => {
      const va = num(a[sortKey]);
      const vb = num(b[sortKey]);
      if (va === null || vb === null) {
        if (va !== vb) return va === null ? 1 : -1;
      } else if (va !== vb) {
        return sortDir === 'asc' ? va - vb : vb - va;
      }
      const oa = num(a[other]);
      const ob = num(b[other]);
      if (oa !== ob) return oa === null ? 1 : ob === null ? -1 : (sortDir === 'asc' ? oa - ob : ob - oa);
      return deviceLabel(a).localeCompare(deviceLabel(b));
    });
    const def = size === 'compact' ? 10 : 50;
    const items = HMT.slice('devices', list, def);

    const tools = (qualities.length > 1 ? HMT.chips('quality', QUALITY.slice(0, 1).concat(qualities), 'all', quality) : '') +
      (ifaces.length > 1 ? HMT.chips('iface', [['all', 'All']].concat(ifaces.map(i => [i, ifaceLabel(i), i])), 'all', iface) : '') +
      (iface === 'BidCos-RF' && rfGateways.length > 1 ? HMT.chips('gateway', [['all', 'All gateways']].concat(rfGateways.map(g => [g, gwLabel(g), g])), 'all', gateway) : '') +
      (size === 'compact' ? '' : '<span class="spacer"></span>' + HMT.searchInput());
    const actions = HMT.limitSelect('devices', def, list.length) + '<span class="spacer"></span>' +
      '<span class="muted legend">' + sorter('device', '↓ ' + T('Device receives'), sortKey, sortDir) + '<span>·</span>' +
        sorter('peer', '↑ ' + T('Central receives'), sortKey, sortDir) + '</span>' +
      (size === 'compact' ? '<button class="btn" data-act="TileRead" data-wait title="' + esc(T('Read CCU')) + '">' + icon('refresh') + '</button>' : '');

    function row(x) {
      // Nur der Name der Symcon-Instanz, kein Öffnen per Klick: die Instanzen liegen meist außerhalb des Objektbaums der Visu
      const name = x.instanceID ? '<span class="l1">' + esc(deviceLabel(x)) + '</span>'
        : '<span class="l1" title="' + esc(T('Device not set up in Symcon')) + '">' + esc(deviceLabel(x)) + '</span>';
      // Weitere Gateways, die das Gerät hören, nur mit Namen (Pegel zeigt der Gateway-Filter)
      // Zeigen ↓/↑ ein anderes als das zugeordnete Gateway: vorn „zugeordnet: …“, und es fehlt unter „Empfangen auch von“
      const foreign = x.gateway !== '' && x.shown !== x.gateway;
      const also = size === 'full' ? Array.from(new Set((x.links || []).map(l => l.gateway)))
        .filter(g => g && g !== x.shown && g !== x.gateway).map(gwLabel).join(', ') : '';
      const gwText = x.interface !== 'BidCos-RF' ? ifaceLabel(x.interface) : (foreign ? T('assigned') + ': ' : '') + gwLabel(x.gateway);
      const l2 = [x.address, size === 'compact' ? '' : x.type, size === 'compact' ? '' : gwText,
        size === 'full' && x.time ? HMT.time(x.time) : '', also ? T('Received also by') + ': ' + also : ''].filter(v => v).map(esc).join(' · ');
      const badges = size === 'compact' ? '' : (gateway !== 'all' && x.gateway === gateway ? '<span class="badge">' + esc(T('assigned')) + '</span>' : '') +
        (x.roaming ? '<span class="badge" title="' + esc(T('The CCU chooses the gateway itself')) + '">' + esc(T('Roaming')) + '</span>' : '');
      return '<li class="row"><span class="ico ' + QUALITY_CLASS[x.quality] + '" title="' + esc(T(QUALITY.find(q => q[0] === x.quality)[1])) + '">' + bars(x.quality) + '</span>' +
        '<span class="main">' + name + '<span class="l2">' + l2 + '</span></span>' +
        '<span class="side levels">' + badges + '<span title="' + esc(T('Device receives')) + '">↓ ' + esc(dbm(x.device)) + '</span><span title="' + esc(T('Central receives')) + '">↑ ' +
        esc(dbm(x.peer)) + '</span>' + (size === 'full' ? '<span>dBm</span>' : '') + '</span></li>';
    }
    const body = '<div class="group grow">' + (tools ? '<div class="ghead">' + tools + '</div>' : '') + '<div class="ghead">' + actions + '</div>' +
      (items.length ? '<ul class="list">' + items.map(row).join('') + '</ul>' : '<div class="empty">' + esc(T('No radio devices')) + '</div>') + '</div>';
    if (size === 'compact') return body;
    return '<div class="cols"><div class="col side">' + kpi + '</div><div class="col main">' + body + '</div></div>';
  }

  // Funkmodule (Gateways) mit Duty Cycle und Carrier Sense
  function modules(gateways) {
    return '<div class="modules"><div class="mhead">' + esc(T('Radio modules')) + '</div>' + gateways.map(g => {
      const dc = typeof g.dutyCycle === 'number' ? g.dutyCycle : null;
      const label = (g.name || g.type || g.address) + ' · ' + ifaceLabel(g.interface);
      const cs = typeof g.carrierSense === 'number' ? ' · ' + T('Carrier sense') + ' ' + g.carrierSense + ' %' : '';
      return '<div class="module' + (g.connected ? '' : ' off') + '" title="' + esc(g.address + (g.connected ? '' : ' – ' + T(g.configured ? 'not connected' : 'no longer set up')) + cs) + '">' +
        '<span class="dot' + (g.connected ? ' on' : '') + '"></span><span class="lbl">' + esc(label) + '</span>' +
        (dc !== null ? '<span class="bar" title="' + esc(T('Duty cycle')) + ' ' + dc + ' %"><i style="width:' + Math.min(100, dc) + '%"></i></span><b>' + esc(dc) + ' %</b>' : '') +
        '</div>';
    }).join('') + '</div>';
  }

  // Sortierung: Richtung `device` (Gerät empfängt) oder `peer` (Zentrale empfängt), `asc`/`desc`; Werk: Zentrale aufsteigend
  const currentSort = () => {
    const [key, dir] = String(HMT.load('sort', 'peer:asc')).split(':');
    return [key === 'device' ? 'device' : 'peer', dir === 'desc' ? 'desc' : 'asc'];
  };
  const sorter = (key, label, cur, dir) => '<button class="sorter' + (key === cur ? ' on' : '') + '" data-on="sort" data-key="' + key + '" title="' + esc(T('Sort')) + '">' +
    esc(label) + (key === cur ? ' ' + (dir === 'asc' ? '▲' : '▼') : '') + '</button>';
  HMT.handlers.sort = el => {
    const [cur, dir] = currentSort();
    const key = el.dataset.key;
    HMT.save('sort', key + ':' + (key === cur && dir === 'asc' ? 'desc' : 'asc'));
    HMT.draw();
  };

  // Stand der Daten; Klick liest neu, das Symbol dreht sich bis zur Antwort (data-wait)
  const stamp = d => '<button class="stamp" data-act="TileRead" data-wait title="' + esc(T('Read CCU')) + '">' + icon('refresh') +
    '<span>' + esc(T('As of %s').replace('%s', d.time ? HMT.time(d.time) : '–')) + '</span></button>';

  HMT.render = render;
})();
