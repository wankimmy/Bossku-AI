// BosskuAI Dashboard — frontend logic.
// vanilla JS + D3 v7

// ---------------------------------------------------------------------------
// Helpers

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));

async function api(path, opts = {}) {
  const res = await fetch(path, opts);
  return await res.json();
}

function toast(msg, kind = "info", ms = 4000) {
  const t = document.createElement("div");
  t.className = `toast ${kind}`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), ms);
}

function escapeHtml(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;").replaceAll("'", "&#39;");
}

// ---------------------------------------------------------------------------
// Tabs

$$(".tab").forEach((t) => t.addEventListener("click", () => {
  $$(".tab").forEach((x) => x.classList.remove("active"));
  $$(".view").forEach((x) => x.classList.remove("active"));
  t.classList.add("active");
  const id = `view-${t.dataset.view}`;
  $(`#${id}`).classList.add("active");
  // Lazy-load tab data
  if (t.dataset.view === "memory" && !window._memoryLoaded) loadMemory();
  if (t.dataset.view === "vectordb" && !window._vectordbLoaded) loadVectordb();
  if (t.dataset.view === "evals" && !window._evalsLoaded) loadEvals();
}));

// ---------------------------------------------------------------------------
// Graph view

let graphData = null;
let simulation = null;
let nodeSel, linkSel, labelSel;

function categoryColor(cat) {
  const map = {
    engineering: "#6da3ff", infra: "#b58cff", runtime: "#f48fb1",
    data: "#5dd29c", security: "#f06a6a", growth: "#f5c062",
    sales: "#ff9966", design: "#62d2dc", operating: "#ffffff",
    research: "#c0a3ff", quality: "#f0a3a3", meta: "#888", other: "#555",
  };
  return map[cat] || "#888";
}

function depthColor(d) {
  if (d === "DEEP") return "#5dd29c";
  if (d === "OK") return "#f5c062";
  return "#f06a6a";
}

function renderLegend() {
  const mode = $("#color-mode").value;
  let items;
  if (mode === "depth") {
    items = [
      ["DEEP (≥250 lines)", "#5dd29c"],
      ["OK (100-250)", "#f5c062"],
      ["THIN (<100)", "#f06a6a"],
    ];
  } else {
    items = [
      ["engineering", "#6da3ff"], ["infra", "#b58cff"], ["runtime", "#f48fb1"],
      ["data", "#5dd29c"], ["security", "#f06a6a"], ["growth", "#f5c062"],
      ["sales", "#ff9966"], ["design", "#62d2dc"], ["operating", "#fff"],
      ["research", "#c0a3ff"], ["quality", "#f0a3a3"], ["meta", "#888"],
    ];
  }
  $("#legend").innerHTML = items.map(([label, c]) =>
    `<div class="legend-item"><span class="legend-dot" style="background:${c}"></span>${label}</div>`
  ).join("");
}

async function loadGraph() {
  const w = $("#view-graph").clientWidth;
  const h = $("#view-graph").clientHeight;
  const data = await api("/api/workspace");
  if (data.error) { $("#status").textContent = data.error; return; }
  graphData = data;
  $("#version").textContent = "v" + data.version;
  $("#status").textContent = `${data.node_count} skills · ${data.edge_count} relations`;

  const svg = d3.select("#graph").attr("viewBox", [0, 0, w, h]);
  svg.selectAll("*").remove();

  const g = svg.append("g");
  svg.call(d3.zoom().scaleExtent([0.3, 3]).on("zoom", (e) => g.attr("transform", e.transform)));

  // Filter edges based on toggles
  const showOverlap = $("#show-overlap").checked;
  const showCrossref = $("#show-crossref").checked;
  const edges = data.edges
    .filter(e => (showOverlap && e.kind === "overlap") || (showCrossref && e.kind === "cross_ref"))
    .map(e => ({ ...e }));

  // Build simulation
  const nodes = data.nodes.map(n => ({ ...n }));

  simulation = d3.forceSimulation(nodes)
    .force("link", d3.forceLink(edges).id(d => d.id).distance(d => d.kind === "overlap" ? 80 : 60).strength(d => d.kind === "overlap" ? 0.05 : 0.4))
    .force("charge", d3.forceManyBody().strength(-180))
    .force("center", d3.forceCenter(w/2, h/2))
    .force("collision", d3.forceCollide().radius(d => nodeRadius(d) + 4));

  linkSel = g.append("g").attr("class", "links")
    .selectAll("line")
    .data(edges)
    .enter().append("line")
    .attr("class", d => `link ${d.kind}`);

  const nodeG = g.append("g").attr("class", "nodes")
    .selectAll("g")
    .data(nodes)
    .enter().append("g")
    .attr("class", d => "node" + (d.is_marquee ? " marquee" : ""))
    .call(d3.drag()
      .on("start", (e, d) => { if (!e.active) simulation.alphaTarget(0.3).restart(); d.fx = d.x; d.fy = d.y; })
      .on("drag", (e, d) => { d.fx = e.x; d.fy = e.y; })
      .on("end", (e, d) => { if (!e.active) simulation.alphaTarget(0); d.fx = null; d.fy = null; }));

  nodeG.append("circle")
    .attr("r", d => nodeRadius(d))
    .attr("fill", d => $("#color-mode").value === "depth" ? depthColor(d.depth) : categoryColor(d.category))
    .on("click", (e, d) => showNodeDetail(d));

  nodeG.append("title").text(d => `${d.id}\n${d.depth} · ${d.category}\n${d.total_lines} lines`);

  nodeG.append("text")
    .attr("dx", d => nodeRadius(d) + 4)
    .attr("dy", "0.35em")
    .text(d => d.label.length > 22 ? d.label.slice(0, 21) + "…" : d.label);

  nodeSel = nodeG;

  simulation.on("tick", () => {
    linkSel
      .attr("x1", d => d.source.x).attr("y1", d => d.source.y)
      .attr("x2", d => d.target.x).attr("y2", d => d.target.y);
    nodeG.attr("transform", d => `translate(${d.x},${d.y})`);
  });

  renderLegend();
}

function nodeRadius(d) {
  if (d.id === "cofounder") return 16;
  if (d.is_marquee) return 12;
  return 6 + Math.min(6, Math.sqrt(d.trigger_count));
}

function showNodeDetail(d) {
  const refs = (d.playbook_refs || []).map(p => `<li><code>${escapeHtml(p)}</code></li>`).join("") || "<li class='muted'>none</li>";
  const triggers = (d.triggers || []).map(t => `<span class="pill">${escapeHtml(t)}</span>`).join(" ") || "<span class='muted'>none</span>";
  const keywords = (d.keywords || []).map(k => `<code>${escapeHtml(k)}</code>`).join(", ") || "<span class='muted'>none</span>";
  const depthClass = d.depth === "DEEP" ? "good" : d.depth === "OK" ? "warn" : "bad";

  $("#side-panel").innerHTML = `
    <h2>${escapeHtml(d.id)}</h2>
    <div class="row">
      <span class="pill ${depthClass}">${d.depth}</span>
      <span class="pill">${escapeHtml(d.category)}</span>
      ${d.is_marquee ? '<span class="pill marquee">MARQUEE</span>' : ""}
      ${d.is_core ? '<span class="pill">CORE</span>' : ""}
    </div>
    <div class="row">
      <div class="label">Description</div>
      <div>${escapeHtml(d.description || "—")}</div>
    </div>
    <div class="row">
      <div class="label">Lines (skill + playbook)</div>
      <div>${d.skill_lines} + ${d.playbook_lines} = <strong>${d.total_lines}</strong></div>
    </div>
    <div class="row">
      <div class="label">Triggers (${d.trigger_count})</div>
      <div>${triggers}</div>
    </div>
    <div class="row">
      <div class="label">Keywords</div>
      <div style="font-size: 12px;">${keywords}</div>
    </div>
    <div class="row">
      <div class="label">Referenced playbooks</div>
      <ul style="margin: 4px 0; padding-left: 20px;">${refs}</ul>
    </div>
  `;
}

["#show-overlap", "#show-crossref", "#color-mode"].forEach(s => $(s).addEventListener("change", () => loadGraph()));

// ---------------------------------------------------------------------------
// Memory view

async function loadMemory() {
  const data = await api("/api/memory");
  $("#memory-files").innerHTML = data.files.map(f => `
    <div class="file-card">
      <h4>${escapeHtml(f.name)}</h4>
      <div class="file-meta">${f.lines} lines · ${f.size} bytes · modified ${escapeHtml(f.modified)}</div>
      <pre>${escapeHtml(f.content)}</pre>
    </div>
  `).join("");
  window._memoryLoaded = true;
}

// ---------------------------------------------------------------------------
// Vector DB view

async function loadVectordb() {
  const data = await api("/api/vectordb");
  let html = "";
  if (data.status === "not_built") {
    html = `<div class="file-card"><h4>DB not built yet</h4><div class="muted">${escapeHtml(data.message)}</div><div style="margin-top:8px;"><button onclick="reindexVectordb()">Build now</button></div></div>`;
  } else if (data.status === "ready") {
    const tableCounts = Object.entries(data).filter(([k]) => k.startsWith("count:"))
      .map(([k, v]) => `<span class="pill">${escapeHtml(k.slice(6))}: ${v}</span>`).join(" ");
    const sources = (data.top_sources || []).map(s =>
      `<li><code>${escapeHtml((s.source || "").split("/").pop())}</code> <span class="muted">(${s.count} chunks)</span></li>`
    ).join("");
    html = `
      <div class="file-card">
        <h4>Vector DB ready</h4>
        <div class="file-meta">Path: <code>${escapeHtml(data.db_path)}</code> · ${data.size_bytes} bytes</div>
        <div style="margin: 8px 0;">${tableCounts}</div>
        ${sources ? `<div class="label" style="margin-top: 10px;">Indexed sources</div><ul style="margin: 4px 0; padding-left: 20px;">${sources}</ul>` : ""}
        <p class="muted" style="font-size: 11px; margin-top: 10px;">Note: indexed scope is configured in <code>ai-assistant/memory/vector-config.json</code> via the <code>include</code> list. Marquee playbooks are NOT indexed by default — the vector DB is for memory, not playbook search.</p>
      </div>
    `;
  } else {
    html = `<pre>${escapeHtml(JSON.stringify(data, null, 2))}</pre>`;
  }
  $("#vectordb-status").innerHTML = html;
  window._vectordbLoaded = true;
}

async function vectorQuery() {
  const q = $("#query-input").value.trim();
  const k = parseInt($("#query-k").value, 10) || 6;
  if (!q) return toast("query required", "error");
  $("#vector-results").innerHTML = '<span class="muted">querying…</span>';
  const data = await api(`/api/vectordb/query?q=${encodeURIComponent(q)}&k=${k}`);
  if (data.error) {
    $("#vector-results").innerHTML = `<div class="file-card"><strong>Error:</strong> ${escapeHtml(data.error)}</div>`;
    return;
  }
  if (!data.results || data.results.length === 0) {
    $("#vector-results").innerHTML = '<div class="muted">No results.</div>';
    return;
  }
  $("#vector-results").innerHTML = data.results.map(r => {
    const fname = (r.path || "").split("/").pop();
    const comps = Object.entries(r.components || {}).map(([k, v]) => `${k}=${v.toFixed(3)}`).join(" · ");
    return `
      <div class="vector-result">
        <span class="score">${r.score.toFixed(3)}</span>
        <span class="src">${escapeHtml(fname)}</span>
        <span class="muted">· ${escapeHtml(r.heading || "")}</span>
        <div class="muted" style="font-size: 10px;">${escapeHtml(comps)}</div>
        <div class="preview">${escapeHtml(r.preview || "")}</div>
      </div>
    `;
  }).join("");
}

async function reindexVectordb() {
  const out = $("#reindex-out") || document.createElement("div");
  out.innerHTML = '<span class="muted">reindexing… this may take 30-60s</span>';
  const data = await api("/api/vectordb/reindex", { method: "POST", headers: {"Content-Type": "application/json"}, body: "{}" });
  if (data.ok) {
    out.innerHTML = `<div class="file-card"><strong>Reindexed.</strong><pre>${escapeHtml(data.stdout_tail || "")}</pre></div>`;
    toast("Vector DB reindexed", "success");
    window._vectordbLoaded = false;
    loadVectordb();
  } else {
    out.innerHTML = `<div class="file-card"><strong>Failed:</strong> ${escapeHtml(data.error || "exit " + data.exit)}<pre>${escapeHtml(data.stderr_tail || "")}</pre></div>`;
    toast("Reindex failed", "error");
  }
}

// ---------------------------------------------------------------------------
// Evals view

async function loadEvals() {
  await runEvals();
}

async function runEvals() {
  $("#evals-running").textContent = "running… this can take 30s";
  $("#evals-out").innerHTML = "";
  const data = await api("/api/evals");
  $("#evals-running").textContent = "";
  $("#evals-out").innerHTML = Object.entries(data).map(([name, r]) => {
    const cls = r.status === "pass" ? "" : "fail";
    const target = r.target ? `<div class="target">Target: <code>${escapeHtml(r.target)}</code></div>` : "";
    const command = r.command ? `<div class="target">Command: <code>${escapeHtml(r.command)}</code></div>` : "";
    return `
      <div class="eval-card ${cls}">
        <h4>${escapeHtml(name)} <span class="pill ${r.status === "pass" ? "good" : "bad"}">${r.status}</span></h4>
        <div class="headline">${escapeHtml(r.headline || "")}</div>
        ${target}
        ${command}
        <pre style="font-size: 11px;">${escapeHtml(r.output_tail || "")}</pre>
      </div>
    `;
  }).join("");
  window._evalsLoaded = true;
}

// ---------------------------------------------------------------------------
// Actions view

async function generateUnderstand() {
  const path = $("#understand-path").value.trim();
  if (!path) return toast("target path required", "error");
  $("#understand-out").innerHTML = '<span class="muted">generating…</span>';
  const data = await api("/api/understand", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({target_path: path}),
  });
  if (data.ok) {
    $("#understand-out").innerHTML = `<div class="file-card"><strong>Wrote:</strong> <code>${escapeHtml(data.wrote)}</code><br><span class="muted">${escapeHtml(data.next_step)}</span></div>`;
    toast("Prompt file written", "success");
  } else {
    $("#understand-out").innerHTML = `<div class="file-card"><strong>Failed:</strong> ${escapeHtml(data.error)}</div>`;
    toast(data.error, "error");
  }
}

$("#sync-scope").addEventListener("change", (e) => {
  $("#sync-custom").style.display = e.target.value === "custom" ? "block" : "none";
});

let _lastSyncToken = null;
let _lastSyncPath = null;
let _lastSyncScope = null;

async function syncDryRun() {
  const path = $("#sync-path").value.trim();
  if (!path) return toast("target path required", "error");
  let scope = $("#sync-scope").value;
  if (scope === "custom") {
    const ids = $("#sync-custom-ids").value.trim();
    if (!ids) return toast("comma-separated skill ids required", "error");
    scope = "custom:" + ids;
  }
  $("#sync-out").innerHTML = '<span class="muted">computing diff…</span>';
  const data = await api("/api/sync/dry-run", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({target_path: path, scope}),
  });
  if (!data.ok) {
    $("#sync-out").innerHTML = `<div class="file-card"><strong>Failed:</strong> ${escapeHtml(data.error)}</div>`;
    return;
  }
  _lastSyncToken = data.confirm_token;
  _lastSyncPath = data.target;
  _lastSyncScope = data.scope;
  const summary = data.summary;
  const planRows = data.plans.map(p => {
    const cls = p.action === "skip" ? "muted" : (p.action === "overwrite" ? "warn" : "good");
    return `<tr><td><span class="pill ${cls === "muted" ? "" : cls}">${p.action}</span></td><td><code>${escapeHtml(p.src)}</code></td><td><code>${escapeHtml(p.dst.replace(data.target, "<target>"))}</code></td><td class="muted">${escapeHtml(p.reason)}</td></tr>`;
  }).join("");
  $("#sync-out").innerHTML = `
    <div class="file-card">
      <h4>Dry-run plan</h4>
      <div>Target: <code>${escapeHtml(data.target)}</code></div>
      <div>Scope: <code>${escapeHtml(data.scope)}</code></div>
      <div style="margin: 6px 0;">
        <span class="pill good">create: ${summary.create || 0}</span>
        <span class="pill warn">overwrite: ${summary.overwrite || 0}</span>
        <span class="pill">skip: ${summary.skip || 0}</span>
      </div>
      <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
        <thead><tr style="text-align: left;"><th>action</th><th>src</th><th>dst</th><th>reason</th></tr></thead>
        <tbody>${planRows}</tbody>
      </table>
      <div style="margin-top: 12px;">
        <button onclick="syncApply()">Apply (creates backup first)</button>
        <span class="muted" style="margin-left: 8px;">confirm token: <code>${escapeHtml(data.confirm_token)}</code></span>
      </div>
    </div>
  `;
}

async function syncApply() {
  if (!_lastSyncToken) return toast("run dry-run first", "error");
  if (!confirm(`Apply sync to ${_lastSyncPath}?\n\nA backup will be written to ${_lastSyncPath}/.bosskuai-backup/<timestamp>/ before any overwrite.`)) return;
  $("#sync-out").innerHTML += '<div class="muted" style="margin-top: 8px;">applying…</div>';
  const data = await api("/api/sync/apply", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({target_path: _lastSyncPath, scope: _lastSyncScope, confirm_token: _lastSyncToken}),
  });
  if (data.ok) {
    $("#sync-out").innerHTML += `<div class="file-card"><strong>Applied:</strong> ${data.applied} files copied. Backup: <code>${escapeHtml(data.backup_dir)}</code></div>`;
    toast(`Sync complete: ${data.applied} files`, "success");
    _lastSyncToken = null;
  } else {
    $("#sync-out").innerHTML += `<div class="file-card"><strong>Failed:</strong> ${escapeHtml(data.error)}</div>`;
    toast(data.error, "error");
  }
}

// ---------------------------------------------------------------------------
// Init

window.addEventListener("load", () => {
  loadGraph();
});
window.addEventListener("resize", () => {
  if ($("#view-graph").classList.contains("active")) loadGraph();
});
