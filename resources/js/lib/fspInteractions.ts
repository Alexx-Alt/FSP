import { router } from '@inertiajs/vue3';

let observer: MutationObserver | null = null;
let clickBound = false;

function pageProps(): any {
    const state = window.history.state as any;
    if (state?.page?.props) return state.page.props;
    const app = document.getElementById('app');
    if (app?.dataset.page) {
        try { return JSON.parse(app.dataset.page).props ?? {}; } catch { /* ignore */ }
    }
    return {};
}

function workspace(): any { return pageProps()?.workspace ?? {}; }
function mode(): 'candidate'|'employer'|'admin'|null {
    const m = workspace()?.mode;
    return m === 'candidate' || m === 'employer' || m === 'admin' ? m : null;
}

function roleCorrectLabels(): void {
    const m = mode();
    if (m !== 'candidate') return;
    document.querySelectorAll<HTMLElement>('.route[data-route="invite"]').forEach((el) => {
        el.textContent = 'Высокое соответствие';
        el.title = 'Это рекомендация системы кандидату, а не кнопка работодателя.';
    });
    document.querySelectorAll<HTMLElement>('.candidate-shell .route').forEach((el) => {
        if ((el.textContent ?? '').trim().toLowerCase() === 'можно приглашать') el.textContent = 'Рекомендуем откликнуться';
    });
}

function actionButton(label: string, className: string, handler: () => void): HTMLButtonElement {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `fsp-market-action ${className}`;
    button.textContent = label;
    button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        button.disabled = true;
        const old = button.textContent;
        button.textContent = 'Отправляем…';
        handler();
        window.setTimeout(() => { if (document.body.contains(button)) { button.disabled = false; button.textContent = old; } }, 2500);
    });
    return button;
}

function injectCandidateApplications(): void {
    if (mode() !== 'candidate') return;
    const matches = workspace()?.matches ?? [];
    const selectors = ['.candidate-shell .match-card', '.fsp-shell .matches > article'];
    selectors.forEach((selector) => {
        document.querySelectorAll<HTMLElement>(selector).forEach((card, index) => {
            if (card.querySelector('.fsp-market-action')) return;
            const item = matches[index];
            if (!item?.jobId) return;
            const button = actionButton('Откликнуться', 'apply', () => {
                router.post(`/interactions/jobs/${item.jobId}/apply`, {}, { preserveScroll: true });
            });
            card.appendChild(button);
        });
    });
}

function injectEmployerInvites(): void {
    if (mode() !== 'employer') return;
    const candidates = workspace()?.candidateMatches ?? [];
    document.querySelectorAll<HTMLElement>('.fsp-shell .candidate').forEach((card, index) => {
        if (card.querySelector('.fsp-market-action')) return;
        const item = candidates[index];
        if (!item?.candidateId || !item?.jobId) return;
        const label = item.route === 'invite' ? 'Пригласить' : 'Пригласить после уточнения';
        const button = actionButton(label, 'invite', () => {
            router.post(`/interactions/jobs/${item.jobId}/candidates/${item.candidateId}/invite`, {}, { preserveScroll: true });
        });
        card.appendChild(button);
    });
}

function parseCell(value: string): number|string {
    const cleaned = value.replace(/\s/g, '').replace(/₽/g, '').replace(',', '.');
    const numeric = cleaned.match(/-?\d+(?:\.\d+)?/);
    if (numeric && cleaned.replace(numeric[0], '').replace(/[%+#]/g, '') === '') return Number(numeric[0]);
    return value.trim().toLocaleLowerCase('ru-RU');
}

function makeSortable(table: HTMLTableElement): void {
    if (table.dataset.sortable === '1') return;
    table.dataset.sortable = '1';
    const headers = Array.from(table.querySelectorAll<HTMLTableCellElement>('thead th'));
    headers.forEach((th, column) => {
        th.classList.add('fsp-sortable');
        th.title = 'Нажмите для сортировки';
        th.addEventListener('click', () => {
            const body = table.tBodies[0];
            if (!body) return;
            const direction = th.dataset.dir === 'asc' ? 'desc' : 'asc';
            headers.forEach((h) => { delete h.dataset.dir; h.classList.remove('sort-asc', 'sort-desc'); });
            th.dataset.dir = direction;
            th.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');
            const rows = Array.from(body.rows);
            rows.sort((a, b) => {
                const av = parseCell(a.cells[column]?.textContent ?? '');
                const bv = parseCell(b.cells[column]?.textContent ?? '');
                const result = typeof av === 'number' && typeof bv === 'number' ? av - bv : String(av).localeCompare(String(bv), 'ru');
                return direction === 'asc' ? result : -result;
            });
            rows.forEach((row) => body.appendChild(row));
        });
    });
}

function enhanceCityAnalytics(): void {
    document.querySelectorAll<HTMLTableElement>('.analytics table').forEach(makeSortable);
    document.querySelectorAll<HTMLElement>('.analytics .card').forEach((card) => {
        const heading = (card.querySelector('h2')?.textContent ?? '').toLocaleLowerCase('ru-RU');
        const text = (card.textContent ?? '').toLocaleLowerCase('ru-RU');
        if (!heading.includes('город') && !heading.includes('географ') && !text.includes('давление спроса')) return;
        if (card.querySelector('.fsp-city-controls')) return;
        const table = card.querySelector<HTMLTableElement>('table');
        if (!table?.tBodies[0]) return;
        const rows = Array.from(table.tBodies[0].rows);
        const cities = [...new Set(rows.map((row) => row.cells[0]?.textContent?.trim()).filter(Boolean) as string[])].sort((a,b)=>a.localeCompare(b,'ru'));
        const controls = document.createElement('div');
        controls.className = 'fsp-city-controls';
        const select = document.createElement('select');
        select.innerHTML = `<option value="">Все города России</option>${cities.map((city) => `<option value="${city.replace(/"/g,'&quot;')}">${city}</option>`).join('')}`;
        const hint = document.createElement('span');
        hint.textContent = 'Столбцы таблицы также сортируются кликом по заголовку.';
        select.addEventListener('change', () => {
            rows.forEach((row) => { row.hidden = Boolean(select.value) && row.cells[0]?.textContent?.trim() !== select.value; });
        });
        controls.append(select, hint);
        table.before(controls);
    });
}

function redirectLegacyAssessmentStart(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    const button = target?.closest('button');
    if (!button || !document.querySelector('.candidate-shell')) return;
    const text = (button.textContent ?? '').trim().toLocaleLowerCase('ru-RU');
    if (!text.includes('начать assessment') && !text.includes('начать проверку')) return;
    const section = button.closest('.assessment-start');
    if (!section) return;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    router.visit('/candidate/assessment/session');
}

function enhance(): void {
    roleCorrectLabels();
    injectCandidateApplications();
    injectEmployerInvites();
    enhanceCityAnalytics();
}

export function installFspInteractions(): void {
    if (typeof window === 'undefined') return;
    if (!clickBound) {
        clickBound = true;
        document.addEventListener('click', redirectLegacyAssessmentStart, true);
    }
    enhance();
    observer?.disconnect();
    observer = new MutationObserver(() => enhance());
    observer.observe(document.body, { childList: true, subtree: true });
    document.addEventListener('inertia:navigate', () => window.setTimeout(enhance, 0));
}
