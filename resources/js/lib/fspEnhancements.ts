const THEME_KEY = 'appearance';
const GRAPH_VIEW_KEY = 'fsp.skill.view';

let observer: MutationObserver | null = null;

function setCookie(name: string, value: string): void {
    document.cookie = `${name}=${value};path=/;max-age=31536000;SameSite=Lax`;
}

function resolvedDark(): boolean {
    return document.documentElement.classList.contains('dark');
}

function applyTheme(theme: 'light' | 'dark'): void {
    localStorage.setItem(THEME_KEY, theme);
    setCookie(THEME_KEY, theme);
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.querySelectorAll<HTMLButtonElement>('.fsp-theme-toggle').forEach((button) => {
        const dark = theme === 'dark';
        button.dataset.theme = theme;
        button.title = dark ? 'Включить светлую тему' : 'Включить тёмную тему';
        button.setAttribute('aria-label', button.title);
        button.innerHTML = `<span aria-hidden="true">${dark ? '☀' : '◐'}</span><span class="fsp-theme-label">${dark ? 'Светлая' : 'Тёмная'}</span>`;
    });
}

function toggleTheme(): void {
    applyTheme(resolvedDark() ? 'light' : 'dark');
}

function makeThemeButton(compact = false): HTMLButtonElement {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `fsp-theme-toggle${compact ? ' compact' : ''}`;
    button.addEventListener('click', toggleTheme);
    return button;
}

function ensureThemeControls(): void {
    const targets: Array<{ selector: string; compact?: boolean }> = [
        { selector: '.candidate-actions' },
        { selector: '.public-actions', compact: true },
        { selector: '.topbar-inner', compact: true },
    ];

    let attached = false;
    for (const target of targets) {
        document.querySelectorAll<HTMLElement>(target.selector).forEach((host) => {
            if (host.querySelector('.fsp-theme-toggle')) return;
            const button = makeThemeButton(Boolean(target.compact));
            if (target.selector === '.topbar-inner') {
                const role = host.querySelector('.role');
                host.insertBefore(button, role ?? host.lastElementChild);
            } else {
                host.insertBefore(button, host.firstChild);
            }
            attached = true;
        });
    }

    if (!attached && !document.querySelector('.fsp-theme-toggle') && document.body) {
        const floating = makeThemeButton(true);
        floating.classList.add('floating');
        document.body.appendChild(floating);
    }

    applyTheme(resolvedDark() ? 'dark' : 'light');
}

type GraphState = 'confirmed' | 'partial' | 'claimed' | 'unknown';

type SkillGraphNode = {
    id: string;
    code: string;
    name: string;
    state: GraphState;
    source: string;
    score: string;
    depth: number;
    x: number;
    y: number;
    vx: number;
    vy: number;
};

type SkillGraphEdge = { source: string; target: string };

function hash(value: string): number {
    let out = 2166136261;
    for (let i = 0; i < value.length; i += 1) {
        out ^= value.charCodeAt(i);
        out = Math.imul(out, 16777619);
    }
    return out >>> 0;
}

function graphState(article: HTMLElement): GraphState {
    if (article.classList.contains('confirmed')) return 'confirmed';
    if (article.classList.contains('partial')) return 'partial';
    if (article.classList.contains('claimed')) return 'claimed';
    return 'unknown';
}

function parseKnowledgeTree(tree: HTMLElement): { nodes: SkillGraphNode[]; edges: SkillGraphEdge[] } {
    const articles = Array.from(tree.querySelectorAll<HTMLElement>('article'));
    const nodes: SkillGraphNode[] = articles.map((article, index) => {
        const left = Number.parseFloat(article.style.marginLeft || '0');
        const name = article.querySelector('div b')?.textContent?.trim() || `Навык ${index + 1}`;
        const code = article.querySelector('div small')?.textContent?.trim() || `skill.${index}`;
        const detail = article.querySelector('div:last-child');
        const source = detail?.querySelector('small')?.textContent?.trim() || '';
        const score = detail?.querySelector('strong')?.textContent?.trim() || '';
        const depth = Math.max(0, Math.round(left / 30));
        const seed = hash(code);
        const angle = ((seed % 360) / 180) * Math.PI;
        const radius = 55 + depth * 105 + ((seed >>> 8) % 34);
        return {
            id: code,
            code,
            name,
            state: graphState(article),
            source,
            score,
            depth,
            x: 600 + Math.cos(angle) * radius,
            y: 340 + Math.sin(angle) * radius,
            vx: 0,
            vy: 0,
        };
    });

    const byCode = new Map(nodes.map((node) => [node.code, node]));
    const edges: SkillGraphEdge[] = [];
    const depthStack: SkillGraphNode[] = [];

    for (const node of nodes) {
        let parent: SkillGraphNode | undefined;
        const bits = node.code.split('.');
        if (bits.length > 1) {
            parent = byCode.get(bits.slice(0, -1).join('.'));
        }
        if (!parent && node.depth > 0) {
            parent = depthStack[node.depth - 1];
        }
        if (parent) edges.push({ source: parent.id, target: node.id });
        depthStack[node.depth] = node;
        depthStack.length = node.depth + 1;
    }

    settleGraph(nodes, edges);
    return { nodes, edges };
}

function settleGraph(nodes: SkillGraphNode[], edges: SkillGraphEdge[]): void {
    if (!nodes.length) return;
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const centerX = 600;
    const centerY = 340;

    for (let iteration = 0; iteration < 180; iteration += 1) {
        for (let i = 0; i < nodes.length; i += 1) {
            const a = nodes[i];
            for (let j = i + 1; j < nodes.length; j += 1) {
                const b = nodes[j];
                let dx = b.x - a.x;
                let dy = b.y - a.y;
                const dist2 = Math.max(140, dx * dx + dy * dy);
                const dist = Math.sqrt(dist2);
                const force = Math.min(2.2, 4600 / dist2);
                dx /= dist;
                dy /= dist;
                a.vx -= dx * force;
                a.vy -= dy * force;
                b.vx += dx * force;
                b.vy += dy * force;
            }
        }

        for (const edge of edges) {
            const a = byId.get(edge.source);
            const b = byId.get(edge.target);
            if (!a || !b) continue;
            const dx = b.x - a.x;
            const dy = b.y - a.y;
            const dist = Math.max(1, Math.sqrt(dx * dx + dy * dy));
            const desired = 82 + Math.min(60, b.depth * 7);
            const spring = (dist - desired) * 0.012;
            const nx = dx / dist;
            const ny = dy / dist;
            a.vx += nx * spring;
            a.vy += ny * spring;
            b.vx -= nx * spring;
            b.vy -= ny * spring;
        }

        for (const node of nodes) {
            const gravity = node.depth === 0 ? 0.045 : 0.006;
            node.vx += (centerX - node.x) * gravity;
            node.vy += (centerY - node.y) * gravity;
            node.vx *= 0.82;
            node.vy *= 0.82;
            node.x = Math.max(36, Math.min(1164, node.x + node.vx));
            node.y = Math.max(38, Math.min(642, node.y + node.vy));
        }
    }
}

function svgPoint(svg: SVGSVGElement, event: PointerEvent, panX: number, panY: number, zoom: number): { x: number; y: number } {
    const rect = svg.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 1200;
    const y = ((event.clientY - rect.top) / rect.height) * 680;
    return { x: (x - panX) / zoom, y: (y - panY) / zoom };
}

function createKnowledgeGraph(tree: HTMLElement): HTMLElement {
    const { nodes, edges } = parseKnowledgeTree(tree);
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const graph = document.createElement('div');
    graph.className = 'fsp-knowledge-graph';

    const counts = nodes.reduce<Record<GraphState, number>>((acc, node) => {
        acc[node.state] += 1;
        return acc;
    }, { confirmed: 0, partial: 0, claimed: 0, unknown: 0 });

    graph.innerHTML = `
        <div class="fsp-graph-head">
            <div>
                <strong>Граф компетенций</strong>
                <span>${nodes.length} узлов · ${edges.length} связей</span>
            </div>
            <div class="fsp-graph-controls">
                <label class="fsp-graph-search"><span>⌕</span><input type="search" placeholder="Найти навык..." aria-label="Поиск навыка" /></label>
                <button type="button" data-graph-action="minus" title="Уменьшить">−</button>
                <button type="button" data-graph-action="plus" title="Увеличить">+</button>
                <button type="button" data-graph-action="reset" title="Сбросить вид">Центр</button>
            </div>
        </div>
        <div class="fsp-graph-stats">
            <span class="confirmed">${counts.confirmed} доказано</span>
            <span class="partial">${counts.partial} частично</span>
            <span class="claimed">${counts.claimed} заявлено</span>
            <span>${counts.unknown} не проверено</span>
        </div>
        <div class="fsp-graph-stage">
            <svg class="fsp-graph-svg" viewBox="0 0 1200 680" role="img" aria-label="Интерактивный граф компетенций">
                <g class="fsp-graph-viewport">
                    <g class="fsp-graph-edges"></g>
                    <g class="fsp-graph-nodes"></g>
                </g>
            </svg>
            <div class="fsp-graph-hint">Колесо — масштаб · тяните фон — перемещение · тяните узел — перестроение</div>
        </div>
        <div class="fsp-node-detail" aria-live="polite"><span>Выберите узел, чтобы увидеть источник доказательства.</span></div>
    `;

    const svg = graph.querySelector<SVGSVGElement>('.fsp-graph-svg')!;
    const viewport = graph.querySelector<SVGGElement>('.fsp-graph-viewport')!;
    const edgeLayer = graph.querySelector<SVGGElement>('.fsp-graph-edges')!;
    const nodeLayer = graph.querySelector<SVGGElement>('.fsp-graph-nodes')!;
    const detail = graph.querySelector<HTMLElement>('.fsp-node-detail')!;
    const search = graph.querySelector<HTMLInputElement>('.fsp-graph-search input')!;

    let panX = 0;
    let panY = 0;
    let zoom = 1;
    let draggingNode: SkillGraphNode | null = null;
    let panning = false;
    let lastPoint = { x: 0, y: 0 };

    const updateViewport = () => viewport.setAttribute('transform', `translate(${panX} ${panY}) scale(${zoom})`);

    function draw(): void {
        edgeLayer.innerHTML = edges.map((edge) => {
            const a = byId.get(edge.source)!;
            const b = byId.get(edge.target)!;
            return `<line class="fsp-graph-edge" data-source="${CSS.escape(a.id)}" data-target="${CSS.escape(b.id)}" x1="${a.x.toFixed(1)}" y1="${a.y.toFixed(1)}" x2="${b.x.toFixed(1)}" y2="${b.y.toFixed(1)}" />`;
        }).join('');

        nodeLayer.innerHTML = nodes.map((node) => {
            const radius = node.depth === 0 ? 18 : node.depth === 1 ? 14 : 11;
            const short = node.name.length > 23 ? `${node.name.slice(0, 22)}…` : node.name;
            return `<g class="fsp-graph-node ${node.state}" data-node-id="${encodeURIComponent(node.id)}" transform="translate(${node.x.toFixed(1)} ${node.y.toFixed(1)})" tabindex="0" role="button" aria-label="${node.name}">
                <circle r="${radius}" />
                <text x="${radius + 7}" y="4">${escapeHtml(short)}</text>
            </g>`;
        }).join('');
        updateViewport();
    }

    function updateNodePositions(): void {
        for (const node of nodes) {
            nodeLayer.querySelector<SVGGElement>(`[data-node-id="${encodeURIComponent(node.id)}"]`)?.setAttribute('transform', `translate(${node.x.toFixed(1)} ${node.y.toFixed(1)})`);
        }
        for (const line of Array.from(edgeLayer.querySelectorAll<SVGLineElement>('line'))) {
            const source = line.dataset.source ? byId.get(line.dataset.source) : undefined;
            const target = line.dataset.target ? byId.get(line.dataset.target) : undefined;
            if (!source || !target) continue;
            line.setAttribute('x1', source.x.toFixed(1));
            line.setAttribute('y1', source.y.toFixed(1));
            line.setAttribute('x2', target.x.toFixed(1));
            line.setAttribute('y2', target.y.toFixed(1));
        }
    }

    function selectNode(node: SkillGraphNode): void {
        nodeLayer.querySelectorAll('.selected').forEach((item) => item.classList.remove('selected'));
        nodeLayer.querySelector(`[data-node-id="${encodeURIComponent(node.id)}"]`)?.classList.add('selected');
        const statusText: Record<GraphState, string> = { confirmed: 'Подтверждено', partial: 'Частично', claimed: 'Заявлено', unknown: 'Не проверено' };
        detail.innerHTML = `<div><span class="fsp-node-dot ${node.state}"></span><div><b>${escapeHtml(node.name)}</b><small>${escapeHtml(node.code)}</small></div></div><div><strong>${statusText[node.state]}${node.score ? ` · ${escapeHtml(node.score)}` : ''}</strong><span>${escapeHtml(node.source || 'Источник доказательства пока отсутствует')}</span></div>`;
    }

    function setZoom(next: number): void {
        zoom = Math.max(0.45, Math.min(2.4, next));
        updateViewport();
    }

    draw();

    nodeLayer.addEventListener('pointerdown', (event) => {
        const target = (event.target as Element).closest<SVGGElement>('.fsp-graph-node');
        if (!target) return;
        event.preventDefault();
        const id = decodeURIComponent(target.dataset.nodeId || '');
        draggingNode = byId.get(id) ?? null;
        if (draggingNode) selectNode(draggingNode);
        svg.setPointerCapture(event.pointerId);
    });

    svg.addEventListener('pointerdown', (event) => {
        if ((event.target as Element).closest('.fsp-graph-node')) return;
        panning = true;
        lastPoint = { x: event.clientX, y: event.clientY };
        svg.setPointerCapture(event.pointerId);
    });

    svg.addEventListener('pointermove', (event) => {
        if (draggingNode) {
            const point = svgPoint(svg, event, panX, panY, zoom);
            draggingNode.x = point.x;
            draggingNode.y = point.y;
            updateNodePositions();
            return;
        }
        if (panning) {
            const rect = svg.getBoundingClientRect();
            panX += ((event.clientX - lastPoint.x) / rect.width) * 1200;
            panY += ((event.clientY - lastPoint.y) / rect.height) * 680;
            lastPoint = { x: event.clientX, y: event.clientY };
            updateViewport();
        }
    });

    const endPointer = (event: PointerEvent) => {
        draggingNode = null;
        panning = false;
        if (svg.hasPointerCapture(event.pointerId)) svg.releasePointerCapture(event.pointerId);
    };
    svg.addEventListener('pointerup', endPointer);
    svg.addEventListener('pointercancel', endPointer);

    svg.addEventListener('wheel', (event) => {
        event.preventDefault();
        setZoom(zoom * (event.deltaY > 0 ? 0.9 : 1.1));
    }, { passive: false });

    nodeLayer.addEventListener('click', (event) => {
        const target = (event.target as Element).closest<SVGGElement>('.fsp-graph-node');
        if (!target) return;
        const node = byId.get(decodeURIComponent(target.dataset.nodeId || ''));
        if (node) selectNode(node);
    });

    nodeLayer.addEventListener('keydown', (event) => {
        if (!(event instanceof KeyboardEvent) || !['Enter', ' '].includes(event.key)) return;
        const target = (event.target as Element).closest<SVGGElement>('.fsp-graph-node');
        if (!target) return;
        const node = byId.get(decodeURIComponent(target.dataset.nodeId || ''));
        if (node) selectNode(node);
    });

    graph.querySelector('[data-graph-action="plus"]')?.addEventListener('click', () => setZoom(zoom * 1.15));
    graph.querySelector('[data-graph-action="minus"]')?.addEventListener('click', () => setZoom(zoom / 1.15));
    graph.querySelector('[data-graph-action="reset"]')?.addEventListener('click', () => {
        panX = 0;
        panY = 0;
        zoom = 1;
        updateViewport();
    });

    search.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase('ru-RU');
        nodeLayer.querySelectorAll<SVGGElement>('.fsp-graph-node').forEach((element) => {
            const id = decodeURIComponent(element.dataset.nodeId || '');
            const node = byId.get(id);
            const matches = !query || Boolean(node && `${node.name} ${node.code} ${node.source}`.toLocaleLowerCase('ru-RU').includes(query));
            element.classList.toggle('dimmed', !matches);
            element.classList.toggle('search-hit', Boolean(query && matches));
        });
    });

    return graph;
}

function escapeHtml(value: string): string {
    return value.replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char] || char);
}

function ensureKnowledgeGraph(): void {
    document.querySelectorAll<HTMLElement>('.skill-tree').forEach((tree) => {
        if (tree.dataset.graphEnhanced === 'true') return;
        const card = tree.closest<HTMLElement>('.card');
        if (!card) return;
        tree.dataset.graphEnhanced = 'true';

        const switcher = document.createElement('div');
        switcher.className = 'fsp-knowledge-switch';
        switcher.innerHTML = `<div><button type="button" data-skill-view="graph">Граф</button><button type="button" data-skill-view="tree">Дерево</button></div><span>Граф показывает связи и пробелы как knowledge graph; дерево — строгую иерархию.</span>`;
        const graph = createKnowledgeGraph(tree);
        tree.parentElement?.insertBefore(switcher, tree);
        tree.parentElement?.insertBefore(graph, tree);

        const show = (view: 'graph' | 'tree') => {
            localStorage.setItem(GRAPH_VIEW_KEY, view);
            graph.hidden = view !== 'graph';
            tree.hidden = view !== 'tree';
            switcher.querySelectorAll<HTMLButtonElement>('[data-skill-view]').forEach((button) => button.classList.toggle('active', button.dataset.skillView === view));
        };

        switcher.querySelector('[data-skill-view="graph"]')?.addEventListener('click', () => show('graph'));
        switcher.querySelector('[data-skill-view="tree"]')?.addEventListener('click', () => show('tree'));
        show(localStorage.getItem(GRAPH_VIEW_KEY) === 'tree' ? 'tree' : 'graph');
    });
}

function runEnhancements(): void {
    ensureThemeControls();
    ensureKnowledgeGraph();
}

export function installFspEnhancements(): void {
    if (typeof window === 'undefined' || typeof document === 'undefined') return;
    runEnhancements();
    if (observer) return;
    observer = new MutationObserver(() => window.requestAnimationFrame(runEnhancements));
    observer.observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('inertia:finish', runEnhancements);
}
