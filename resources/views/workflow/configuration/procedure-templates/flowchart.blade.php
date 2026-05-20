<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $procedureTemplate->name }} — Flowchart</title>
    <style>
        :root {
            --bg: #fafaf7;
            --grid: #e7e7e2;
            --ink: #1a1a1a;
            --muted: #777777;
            --orange: #c2410c;
            --orange-bg: #fff7ed;
            --panel: #ffffff;
            --panel-border: #d6d3d1;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: 100%; height: 100%; overflow: hidden;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: var(--bg); color: var(--ink);
        }
        body {
            background-image: radial-gradient(circle at 1px 1px, var(--grid) 1px, transparent 0);
            background-size: 24px 24px;
        }
        #app { width: 100%; height: 100%; position: relative; }
        #stage { position: absolute; inset: 0; overflow: hidden; cursor: grab; }
        #stage.dragging { cursor: grabbing; }
        #world { position: absolute; left: 50%; top: 50%; transform-origin: 0 0; }
        #edges { position: absolute; inset: 0; overflow: visible; pointer-events: none; }
        #nodes { position: absolute; inset: 0; }

        .node {
            position: absolute; min-height: 96px;
            background: #fff; border: 2px solid var(--ink); border-radius: 14px;
            padding: 16px 20px; box-shadow: 5px 5px 0 var(--ink); z-index: 2;
        }
        .node { cursor: move; }
        .node.dragging-node { z-index: 4; opacity: .92; }
        .node .node-body { position: relative; z-index: 2; width: 100%; pointer-events: none; }
        .node.choice { border: 2px solid var(--orange); box-shadow: 5px 5px 0 var(--orange); border-radius: 999px; }
        .node.start { border-color: #15803d; box-shadow: 5px 5px 0 #15803d; border-radius: 999px; }
        .node.start_choice { border-color: var(--ink); box-shadow: 5px 5px 0 var(--ink); border-radius: 999px; }
        .node.end { border-color: #991b1b; box-shadow: 5px 5px 0 #991b1b; border-radius: 999px; }
        .node.subprocedure { border-color: #1d4ed8; border-style: dashed; background: #eff6ff; box-shadow: 5px 5px 0 #1d4ed8; }

        .tag { font-family: "SFMono-Regular", Consolas, monospace; font-size: 10px; color: var(--muted); margin-bottom: 8px; letter-spacing: .04em; text-transform: uppercase; }
        .title { font-size: 17px; font-weight: 700; line-height: 1.35; white-space: normal; word-break: break-word; }
        .choice .tag, .choice .title { color: var(--orange); }
        .start_choice .tag, .start_choice .title { color: var(--ink); }
        .start .tag, .start .title { color: #15803d; }
        .end .tag, .end .title { color: #991b1b; }
        .subprocedure .tag, .subprocedure .title { color: #1d4ed8; }

        .badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .badge { display: inline-flex; align-items: center; min-height: 20px; padding: 2px 8px; border-radius: 999px; background: rgba(15,23,42,.06); font-size: 11px; line-height: 1.3; color: #334155; }
        .choice .badge { background: #fff; color: #9a3412; border: 1px solid rgba(194,65,12,.22); }
        .start_choice .badge { background: #fff; color: #1f2937; border: 1px solid rgba(15,23,42,.18); }
        .start .badge { background: #fff; color: #166534; border: 1px solid rgba(21,128,61,.22); }
        .end .badge { background: #fff; color: #991b1b; border: 1px solid rgba(153,27,27,.22); }
        .subprocedure .badge { background: rgba(29,78,216,.1); color: #1d4ed8; }

        .edge { fill: none; stroke: var(--ink); stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
        .edge-buffer { fill: none; stroke: var(--bg); stroke-width: 8; stroke-linecap: round; stroke-linejoin: round; }
        .edge.choice { stroke: var(--orange); stroke-dasharray: 7 5; }
        .edge.subprocedure { stroke: #1d4ed8; stroke-dasharray: 10 6; }
        .arrow { fill: var(--ink); }
        .arrow.choice { fill: var(--orange); }
        .arrow.subprocedure { fill: #1d4ed8; }
        .label-bg { fill: var(--bg); stroke: var(--orange); stroke-width: 1; }
        .label { font-family: "SFMono-Regular", Consolas, monospace; font-size: 12px; fill: var(--orange); }
        .label.subprocedure { fill: #1d4ed8; }

        .zoom {
            position: fixed; right: 18px; bottom: 18px; z-index: 20;
            display: flex; align-items: center; gap: 4px;
            background: #fff; border: 2px solid #111; border-radius: 999px;
            padding: 6px; box-shadow: 4px 4px 0 #111;
        }
        .zoom button { width: 32px; height: 32px; border: 0; border-radius: 999px; background: transparent; cursor: pointer; font-size: 18px; font-weight: 700; }
        .zoom button:hover { background: #eee; }
        .zoom span { width: 56px; text-align: center; font-size: 13px; font-weight: 600; }

        .layout-tools {
            position: fixed; right: 18px; top: 18px; z-index: 20;
            display: flex; align-items: center; gap: 8px;
            background: #fff; border: 2px solid #111; border-radius: 999px;
            padding: 6px; box-shadow: 4px 4px 0 #111;
        }
        .layout-tools button { min-width: 64px; height: 32px; border: 0; border-radius: 999px; background: transparent; cursor: pointer; padding: 0 12px; font-size: 13px; font-weight: 700; }
        .layout-tools button:hover { background: #eee; }
        .layout-tools button:disabled { cursor: default; color: #9ca3af; background: transparent; }
        #layoutStatus { min-width: 76px; padding-right: 8px; font-size: 12px; color: #57534e; text-align: right; }

        .empty-state {
            position: absolute; inset: 50% auto auto 50%; transform: translate(-50%, -50%);
            width: min(420px, calc(100vw - 32px)); padding: 24px;
            border: 1px solid var(--panel-border); border-radius: 18px;
            background: rgba(255,255,255,.94); text-align: center;
            box-shadow: 0 10px 30px rgba(15,23,42,.08);
        }
        .empty-state h2 { font-size: 18px; margin-bottom: 8px; }
        .empty-state p { font-size: 13px; line-height: 1.5; color: #57534e; }

        /* Node hover overlay — container always pointer-events:none so drag still works */
        .node-overlay {
            position: absolute; inset: 0; border-radius: inherit;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            opacity: 0; transition: opacity 0.18s; z-index: 10; pointer-events: none;
            background: rgba(15,23,42,.04);
        }
        .node:hover:not(.dragging-node) .node-overlay { opacity: 1; }
        .node:hover:not(.dragging-node) .node-overlay .nab { pointer-events: auto; }
        .nab {
            padding: 5px 13px; border: 1.5px solid currentColor; border-radius: 999px;
            font-size: 12px; font-weight: 700; cursor: pointer; background: rgba(255,255,255,.96);
            transition: background 0.1s, transform 0.1s; line-height: 1.4;
        }
        .nab:hover { transform: translateY(-1px); }
        .nab-view { color: #1d4ed8; }
        .nab-view:hover { background: #eff6ff; }
        .nab-edit { color: #111; }
        .nab-edit:hover { background: #f5f5f5; }
        .nab-del { color: #991b1b; }
        .nab-del:hover { background: #fef2f2; }

        /* Step detail popup */
        .stp-popup {
            position: fixed; inset: 0; z-index: 100;
            display: flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,.35); backdrop-filter: blur(3px);
        }
        .stp-box {
            background: #fff; border: 2px solid #111; border-radius: 18px;
            box-shadow: 8px 8px 0 #111; width: min(460px, calc(100vw - 32px));
            max-height: 80vh; display: flex; flex-direction: column; overflow: hidden;
        }
        .stp-hd { padding: 20px 24px 16px; border-bottom: 1px solid #e7e7e2; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .stp-tag { font-family: monospace; font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
        .stp-ttl { font-size: 20px; font-weight: 700; line-height: 1.3; }
        .stp-cls { width: 32px; height: 32px; flex-shrink: 0; border: 2px solid #ddd; border-radius: 50%; background: #fff; cursor: pointer; font-size: 20px; line-height: 1.5; }
        .stp-cls:hover { border-color: #111; background: #f5f5f5; }
        .stp-body { padding: 16px 24px 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
        .stp-row { display: flex; gap: 14px; font-size: 14px; align-items: flex-start; }
        .stp-k { min-width: 84px; color: #888; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding-top: 2px; }
        .stp-v { color: #1a1a1a; flex: 1; line-height: 1.5; }
    </style>
</head>
<body>
<div id="app" data-flowchart="{{ json_encode($payload) }}">
    <div id="stage">
        <div id="world">
            <svg id="edges"></svg>
            <div id="nodes"></div>
        </div>
    </div>

    <div class="zoom">
        <button id="zoomOut" type="button">−</button>
        <span id="pct">100%</span>
        <button id="zoomIn" type="button">+</button>
        <button id="fit" type="button">Fit</button>
    </div>

    @can('update', $procedureTemplate)
    <div class="layout-tools">
        <button id="saveLayout" type="button" disabled>Save</button>
        <button id="resetLayout" type="button">Reset</button>
        <span id="layoutStatus">Saved</span>
    </div>
    @endcan
</div>

{{-- Step detail popup (show mode) --}}
<div id="stp-popup" class="stp-popup" style="display:none" onclick="if(event.target===this)closePopup()">
    <div class="stp-box">
        <div class="stp-hd">
            <div style="flex:1;min-width:0">
                <div class="stp-ttl" id="pp-ttl"></div>
            </div>
            <button class="stp-cls" onclick="closePopup()">×</button>
        </div>
        <div class="stp-body" id="pp-body"></div>
    </div>
</div>

<script>
    const root           = document.getElementById('app');
    const flowchart      = JSON.parse(root.dataset.flowchart || '{}');
    const NODE_W         = flowchart.node_width  || 260;
    const NODE_H         = flowchart.node_height || 96;
    const nodes          = flowchart.nodes || [];
    const edges          = flowchart.edges || [];
    const worldSize      = flowchart.world || { width: 1200, height: 900 };
    const templateId     = flowchart.template?.id;
    const SAVE_URL       = @js($saveUrl);
    const RESET_URL      = @js($resetUrl);
    const CSRF_TOKEN     = @js($csrfToken);
    const FLOWCHART_MODE = @js($mode);

    const stage              = document.getElementById('stage');
    const world              = document.getElementById('world');
    const svg                = document.getElementById('edges');
    const pct                = document.getElementById('pct');
    const saveLayoutBtn      = document.getElementById('saveLayout');
    const resetLayoutBtn     = document.getElementById('resetLayout');
    const layoutStatus       = document.getElementById('layoutStatus');

    world.style.width  = `${worldSize.width}px`;
    world.style.height = `${worldSize.height}px`;
    svg.setAttribute('width',  String(worldSize.width));
    svg.setAttribute('height', String(worldSize.height));

    if (!nodes.length) {
        const empty = document.createElement('div');
        empty.className = 'empty-state';
        empty.innerHTML = '<h2>No steps to draw</h2><p>Add procedure template steps and connect them to preview the flowchart here.</p>';
        root.appendChild(empty);
    }

    const byId      = new Map(nodes.map(n => [n.id, n]));
    const taskNodes = nodes.filter(n => n.type !== 'subprocedure');

    function escHtml(v) {
        return String(v).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function nodeWidth(n)  { return n.width  || NODE_W; }
    function nodeHeight(n) { return n.height || NODE_H; }
    function point(n, side) {
        const w = nodeWidth(n), h = nodeHeight(n);
        if (side === 'top')    return { x: n.x + w/2, y: n.y };
        if (side === 'bottom') return { x: n.x + w/2, y: n.y + h };
        if (side === 'left')   return { x: n.x,       y: n.y + h/2 };
        return { x: n.x + w, y: n.y + h/2 };
    }

    function renderNodes() {
        const box = document.getElementById('nodes');
        box.innerHTML = '';
        for (const node of nodes) {
            const el     = document.createElement('div');
            const badges = (node.badges || []).map(b => `<span class="badge">${escHtml(b)}</span>`).join('');
            el.className        = `node ${node.type || 'task'}`;
            el.dataset.nodeId   = String(node.id);
            el.style.left       = `${node.x}px`;
            el.style.top        = `${node.y}px`;
            el.style.width      = `${nodeWidth(node)}px`;
            el.style.minHeight  = `${nodeHeight(node)}px`;
            el.innerHTML = `<div class="node-body"><div class="title">${escHtml(node.label)}</div>${badges ? `<div class="badges">${badges}</div>` : ''}</div>`;

            el.addEventListener('mousedown', e => beginNodeDrag(e, node, el));

            if (node.type !== 'subprocedure') {
                // Hover overlay
                const ov = document.createElement('div');
                ov.className = 'node-overlay';
                if (FLOWCHART_MODE === 'edit') {
                    ov.innerHTML = `<button class="nab nab-edit" data-id="${node.id}">Edit</button><button class="nab nab-del" data-id="${node.id}">Delete</button>`;
                } else {
                    ov.innerHTML = `<button class="nab nab-view" data-id="${node.id}">View</button>`;
                }
                el.appendChild(ov);
            }

            box.appendChild(el);
        }

        // Event delegation for overlay buttons
        box.addEventListener('click', e => {
            const viewBtn = e.target.closest('.nab-view');
            const editBtn = e.target.closest('.nab-edit');
            const delBtn  = e.target.closest('.nab-del');
            if (viewBtn) {
                e.stopPropagation();
                const n = nodes.find(x => String(x.id) === viewBtn.dataset.id);
                if (n) openPopup(n);
            }
            if (editBtn) {
                e.stopPropagation();
                const n = nodes.find(x => String(x.id) === editBtn.dataset.id);
                if (n?.edit_url) window.parent.postMessage({ action: 'edit-step', url: n.edit_url + '?frame=1' }, '*');
            }
            if (delBtn) {
                e.stopPropagation();
                const n = nodes.find(x => String(x.id) === delBtn.dataset.id);
                if (n?.delete_url) window.parent.postMessage({ action: 'confirm-delete-step', url: n.delete_url, name: n.label }, '*');
            }
        });
    }

    function drawPath(d, kind, isChoice) {
        if (isChoice && kind !== 'subprocedure') {
            const buf = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            buf.setAttribute('d', d); buf.setAttribute('class', 'edge-buffer'); svg.appendChild(buf);
        }
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', d);
        let cls = 'edge', marker = 'url(#arrow)';
        if (kind === 'subprocedure') { cls += ' subprocedure'; marker = 'url(#arrowSub)'; }
        else if (isChoice)           { cls += ' choice';       marker = 'url(#arrowChoice)'; }
        path.setAttribute('class', cls); path.setAttribute('marker-end', marker); svg.appendChild(path);
    }

    function drawLabel(text, x, y, kind) {
        const lbl = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        lbl.setAttribute('x', x); lbl.setAttribute('y', y);
        lbl.setAttribute('text-anchor', 'middle'); lbl.setAttribute('dominant-baseline', 'middle');
        lbl.setAttribute('class', kind === 'subprocedure' ? 'label subprocedure' : 'label');
        lbl.textContent = text; svg.appendChild(lbl);
        const bb  = lbl.getBBox();
        const bg  = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        bg.setAttribute('x',      bb.x - 8);    bg.setAttribute('y',      bb.y - 4);
        bg.setAttribute('width',  bb.width + 16); bg.setAttribute('height', bb.height + 8);
        bg.setAttribute('rx', 4); bg.setAttribute('ry', 4); bg.setAttribute('class', 'label-bg');
        svg.insertBefore(bg, lbl);
    }

    function buildEdgePath(edge) {
        const src = byId.get(edge.from), tgt = byId.get(edge.to);
        if (!src || !tgt) return null;
        const start = edge.kind === 'subprocedure' ? point(src, 'right') : point(src, 'bottom');
        const end   = edge.kind === 'subprocedure' ? point(tgt, 'left')  : point(tgt, 'top');
        const midY  = start.y + Math.max((end.y - start.y) / 2, 40);

        if (edge.kind === 'subprocedure') {
            const midX = Math.round((start.x + end.x) / 2);
            const path = Math.abs(start.y - end.y) < 8
                ? `M ${start.x} ${start.y} L ${end.x - 5} ${end.y}`
                : `M ${start.x} ${start.y} L ${midX} ${start.y} L ${midX} ${end.y} L ${end.x - 5} ${end.y}`;
            return { path, labelPoint: { x: midX, y: Math.round((start.y + end.y) / 2) } };
        }
        if (edge.choice) {
            const above  = tgt.y < src.y;
            const same   = Math.abs(src.y - tgt.y) < 8;
            const offset = Math.max(30, Math.min(58, (Math.abs(end.y - start.y) || 120) / 3));
            const trackY = same  ? Math.max(18, src.y - offset)
                : above ? tgt.y + nodeHeight(tgt) + offset
                    : Math.max(start.y + 24, tgt.y - offset);
            const stem = Math.max(18, Math.min(46, Math.abs(end.x - start.x) / 4));
            const sx   = start.x + (end.x >= start.x ? stem : -stem);
            return { path: `M ${start.x} ${start.y} L ${sx} ${start.y} L ${sx} ${trackY} L ${end.x} ${trackY} L ${end.x} ${end.y - 5}`, labelPoint: { x: (sx + end.x) / 2, y: trackY - 14 } };
        }
        if (Math.abs(src.y - tgt.y) < 8) {
            const ss  = src.x <= tgt.x ? 'right' : 'left';
            const ts  = src.x <= tgt.x ? 'left'  : 'right';
            const ps  = point(src, ss), pe = point(tgt, ts);
            const mx  = ps.x + (pe.x - ps.x) / 2;
            const ly  = Math.max(18, Math.min(src.y, tgt.y) - 32);
            return { path: `M ${ps.x} ${ps.y} L ${ps.x} ${ly} L ${mx} ${ly} L ${pe.x} ${ly} L ${pe.x} ${pe.y}`, labelPoint: { x: mx, y: ly - 14 } };
        }
        return { path: `M ${start.x} ${start.y} L ${start.x} ${midY} L ${end.x} ${midY} L ${end.x} ${end.y - 5}`, labelPoint: { x: (start.x + end.x) / 2, y: midY - 18 } };
    }

    function renderEdges() {
        svg.innerHTML = `<defs>
            <marker id="arrow"      viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0 L10,5 L0,10 Z" class="arrow"></path></marker>
            <marker id="arrowChoice" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0 L10,5 L0,10 Z" class="arrow choice"></path></marker>
            <marker id="arrowSub"   viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0 L10,5 L0,10 Z" class="arrow subprocedure"></path></marker>
        </defs>`;
        const ordered = [
            ...edges.filter(e => !e.choice && e.kind !== 'subprocedure'),
            ...edges.filter(e => e.kind === 'subprocedure'),
            ...edges.filter(e => e.choice && e.kind !== 'subprocedure'),
        ];
        for (const edge of ordered) {
            const data = buildEdgePath(edge);
            if (!data) continue;
            drawPath(data.path, edge.kind, Boolean(edge.choice));
            if (edge.label) drawLabel(edge.label, data.labelPoint.x, data.labelPoint.y, edge.kind);
        }
    }

    let scale = 0.85, tx = 0, ty = 0;
    let dragging = false, nodeDragging = null, layoutDirty = false;
    let lastX = 0, lastY = 0;

    function applyTransform() {
        world.style.transform = `translate(${tx}px, ${ty}px) scale(${scale})`;
        pct.textContent = `${Math.round(scale * 100)}%`;
    }
    function markDirty() {
        if (layoutDirty) return;
        layoutDirty = true;
        if (saveLayoutBtn) { saveLayoutBtn.disabled = false; }
        if (layoutStatus)  { layoutStatus.textContent = 'Unsaved'; }
    }
    function updateWorldBounds() {
        if (!nodes.length) return;
        const pad  = 120;
        const maxX = Math.max(...nodes.map(n => n.x + nodeWidth(n)))  + pad;
        const maxY = Math.max(...nodes.map(n => n.y + nodeHeight(n))) + pad;
        worldSize.width  = Math.max(worldSize.width,  maxX, 600);
        worldSize.height = Math.max(worldSize.height, maxY, 420);
        world.style.width  = `${worldSize.width}px`;
        world.style.height = `${worldSize.height}px`;
        svg.setAttribute('width',  String(worldSize.width));
        svg.setAttribute('height', String(worldSize.height));
    }
    function beginNodeDrag(e, node, el) {
        if (e.target.closest('.nab')) return; // a button inside the overlay was clicked — don't start drag
        e.preventDefault(); e.stopPropagation();
        nodeDragging = { node, el, startX: e.clientX, startY: e.clientY, originX: node.x, originY: node.y };
        el.classList.add('dragging-node'); stage.classList.add('dragging');
    }
    function moveNodeDrag(e) {
        if (!nodeDragging) return false;
        nodeDragging.node.x = Math.max(Math.round(nodeDragging.originX + (e.clientX - nodeDragging.startX) / scale), 0);
        nodeDragging.node.y = Math.max(Math.round(nodeDragging.originY + (e.clientY - nodeDragging.startY) / scale), 0);
        nodeDragging.el.style.left = `${nodeDragging.node.x}px`;
        nodeDragging.el.style.top  = `${nodeDragging.node.y}px`;
        updateWorldBounds(); renderEdges(); markDirty(); return true;
    }
    function endNodeDrag() {
        if (!nodeDragging) return false;
        nodeDragging.el.classList.remove('dragging-node');
        nodeDragging = null; stage.classList.remove('dragging'); return true;
    }

    async function post(url, body) {
        const r = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify(body),
        });
        if (!r.ok) throw new Error(`${r.status}`);
        const d = await r.json();
        if (d.status !== 'success') throw new Error(d.message || 'Failed');
        return d;
    }

    async function saveLayout() {
        if (!layoutDirty || !saveLayoutBtn) return;
        saveLayoutBtn.disabled = true; if (layoutStatus) layoutStatus.textContent = 'Saving…';
        try {
            await post(SAVE_URL, { positions: nodes.map(n => ({ id: n.id, x: n.x, y: n.y })) });
            layoutDirty = false; if (layoutStatus) layoutStatus.textContent = 'Saved';
        } catch { saveLayoutBtn.disabled = false; if (layoutStatus) layoutStatus.textContent = 'Failed'; }
    }

    async function resetLayout() {
        if (!resetLayoutBtn) return;
        if (saveLayoutBtn)  saveLayoutBtn.disabled = true;
        resetLayoutBtn.disabled = true; if (layoutStatus) layoutStatus.textContent = 'Resetting…';
        try { await post(RESET_URL, {}); window.location.reload(); }
        catch { resetLayoutBtn.disabled = false; if (saveLayoutBtn) saveLayoutBtn.disabled = !layoutDirty; if (layoutStatus) layoutStatus.textContent = 'Failed'; }
    }

    function fitView() {
        scale = Math.min(stage.clientWidth / worldSize.width, stage.clientHeight / worldSize.height, 1);
        tx = -(worldSize.width * scale) / 2; ty = -(worldSize.height * scale) / 2; applyTransform();
    }
    function zoomAt(px, py, f) {
        const ns = Math.max(0.25, Math.min(2.5, scale * f)), r = ns / scale;
        tx = (px - stage.clientWidth/2) - ((px - stage.clientWidth/2 - tx) * r) + tx - tx * (r - 1);
        // simpler re-derivation:
        const cx = px - stage.clientWidth/2, cy = py - stage.clientHeight/2;
        tx = cx - (cx - tx) * r; ty = cy - (cy - ty) * r; scale = ns; applyTransform();
    }

    document.getElementById('fit').addEventListener('click', fitView);
    document.getElementById('zoomIn').addEventListener('click',  () => zoomAt(stage.clientWidth/2, stage.clientHeight/2, 1.2));
    document.getElementById('zoomOut').addEventListener('click', () => zoomAt(stage.clientWidth/2, stage.clientHeight/2, 1/1.2));
    if (saveLayoutBtn)  saveLayoutBtn.addEventListener('click',  saveLayout);
    if (resetLayoutBtn) resetLayoutBtn.addEventListener('click', resetLayout);

    stage.addEventListener('mousedown', e => { dragging = true; lastX = e.clientX; lastY = e.clientY; stage.classList.add('dragging'); });
    window.addEventListener('mousemove', e => { if (moveNodeDrag(e)) return; if (!dragging) return; tx += e.clientX - lastX; ty += e.clientY - lastY; lastX = e.clientX; lastY = e.clientY; applyTransform(); });
    window.addEventListener('mouseup',   () => { if (endNodeDrag()) return; dragging = false; stage.classList.remove('dragging'); });
    stage.addEventListener('wheel', e => { e.preventDefault(); const rect = stage.getBoundingClientRect(); zoomAt(e.clientX - rect.left, e.clientY - rect.top, e.deltaY < 0 ? 1.1 : 1/1.1); }, { passive: false });
    window.addEventListener('resize', fitView);

    // Step detail popup
    function openPopup(node) {
        document.getElementById('pp-ttl').textContent = node.label || 'Untitled';
        let html = '';
        if (node.description) html += `<div class="stp-row"><span class="stp-k">Description</span><span class="stp-v">${escHtml(node.description)}</span></div>`;
        if (node.department)  html += `<div class="stp-row"><span class="stp-k">Department</span><span class="stp-v">${escHtml(node.department)}</span></div>`;
        if (node.sla)         html += `<div class="stp-row"><span class="stp-k">SLA</span><span class="stp-v">${escHtml(String(node.sla))}h</span></div>`;
        if (node.next_steps?.length) html += `<div class="stp-row"><span class="stp-k">Next Steps</span><span class="stp-v">${escHtml(node.next_steps.join(', '))}</span></div>`;
        if (node.badges?.length) {
            const bdg = node.badges.map(b => `<span class="badge">${escHtml(b)}</span>`).join('');
            html += `<div class="stp-row"><span class="stp-k">Flags</span><div class="badges stp-v">${bdg}</div></div>`;
        }
        document.getElementById('pp-body').innerHTML = html || '<p style="color:#aaa;font-size:13px">No additional details available.</p>';
        document.getElementById('stp-popup').style.display = 'flex';
    }
    function closePopup() { document.getElementById('stp-popup').style.display = 'none'; }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closePopup(); });

    renderNodes(); renderEdges(); fitView();
</script>
</body>
</html>
