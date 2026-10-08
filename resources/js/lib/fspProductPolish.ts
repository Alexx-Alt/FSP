import { router } from '@inertiajs/vue3';

let observer: MutationObserver | null = null;
let keyboardBound = false;
let scheduled = false;

function props(): any {
    const state = window.history.state as any;
    if (state?.page?.props) return state.page.props;
    const app = document.getElementById('app');
    if (app?.dataset.page) {
        try { return JSON.parse(app.dataset.page).props ?? {}; } catch { return {}; }
    }
    return {};
}

function workspace(): any { return props()?.workspace ?? {}; }
function mode(): 'candidate'|'employer'|'admin'|null {
    const value = workspace()?.mode;
    return value === 'candidate' || value === 'employer' || value === 'admin' ? value : null;
}

function esc(value: unknown): string {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char] ?? char));
}

function tabButton(labels: string[]): HTMLButtonElement | null {
    const buttons = Array.from(document.querySelectorAll<HTMLButtonElement>('.candidate-topbar nav button,.topbar nav button'));
    return buttons.find((button) => labels.some((label) => (button.textContent ?? '').toLocaleLowerCase('ru-RU').includes(label))) ?? null;
}

function openTab(labels: string[]): void {
    const button = tabButton(labels.map((x) => x.toLocaleLowerCase('ru-RU')));
    if (button) {
        button.click();
        window.setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 30);
    }
}

function roleActions(): Array<{title:string;note:string;metric:string;kind:string;run:()=>void}> {
    const w = workspace();
    const m = mode();
    const actions: Array<{title:string;note:string;metric:string;kind:string;run:()=>void}> = [];

    if (m === 'candidate') {
        const s = w.summary ?? {};
        if (!s.confirmedGrade || s.gradeStatus !== 'confirmed') {
            actions.push({
                title: 'Подтвердить уровень', note: 'Грейд меняется только assessment',
                metric: s.assessmentScore == null ? 'не пройден' : `${Math.round(Number(s.assessmentScore))}/100`, kind: 'primary',
                run: () => router.visit('/candidate/assessment/session'),
            });
        }
        if ((w.alerts ?? []).length) {
            actions.push({ title: 'Разобрать процессы', note: `${w.alerts.length} сигналов требуют внимания`, metric: String(w.alerts.length), kind: 'warning', run: () => openTab(['процес']) });
        }
        if ((w.matches ?? []).length) {
            const best = w.matches[0];
            actions.push({ title: 'Лучший Match', note: `${best?.company ?? 'Компания'} · ${best?.title ?? 'вакансия'}`, metric: `${best?.score ?? '—'}%`, kind: 'success', run: () => openTab(['подбор','главная']) });
        }
        if (Number(s.confirmedSkills ?? 0) < 5) {
            actions.push({ title: 'Усилить evidence', note: 'Закройте partial / claimed узлы', metric: `${s.confirmedSkills ?? 0} доказано`, kind: 'neutral', run: () => openTab(['карта навыков','древо']) });
        }
    }

    if (m === 'employer') {
        const candidates = w.candidateMatches ?? [];
        const invite = candidates.filter((x:any) => x.route === 'invite').length;
        const cases = w.cases ?? [];
        const urgent = cases.filter((x:any) => ['high','critical'].includes(String(x.priority ?? '').toLowerCase())).length;
        actions.push({ title: 'Shortlist на сегодня', note: 'Кандидаты с маршрутом «пригласить»', metric: String(invite), kind: invite ? 'success' : 'neutral', run: () => openTab(['подбор']) });
        actions.push({ title: 'Process Center', note: urgent ? `${urgent} приоритетных кейса` : 'Проверьте следующие шаги', metric: `${cases.length} кейсов`, kind: urgent ? 'warning' : 'neutral', run: () => openTab(['процес']) });
        actions.push({ title: 'Сравнить кандидатов', note: 'Assessment, integrity, evidence и gaps', metric: candidates.length ? `${candidates.length} профилей` : 'нет данных', kind: 'primary', run: () => openTab(['подбор']) });
        actions.push({ title: 'Рынок и города', note: 'Компания относительно рынка', metric: `${(w.cityBenchmark ?? []).length} городов`, kind: 'neutral', run: () => openTab(['аналит']) });
    }

    if (m === 'admin') {
        const s = w.summary ?? {};
        actions.push({ title: 'Готовность к защите', note: 'Матрица ТЗ и критериев', metric: 'Jury Demo', kind: 'primary', run: () => router.visit('/jury-demo') });
        actions.push({ title: 'Integrity / assessment', note: 'Test-retest и стабильность', metric: s.gradeStability == null ? '—' : `${Math.round(Number(s.gradeStability))}%`, kind: 'success', run: () => openTab(['assessment']) });
        actions.push({ title: 'Алерты платформы', note: 'Очередь операторских действий', metric: String(s.alerts ?? (w.alerts ?? []).length ?? 0), kind: Number(s.alerts ?? 0) ? 'warning' : 'neutral', run: () => openTab(['процес']) });
        actions.push({ title: '3-срезная аналитика', note: 'Люди · компании · города', metric: `${(w.cityBenchmark ?? []).length} городов`, kind: 'neutral', run: () => openTab(['аналит']) });
    }

    return actions.slice(0, 4);
}

function focusHost(): {host:HTMLElement; after:Element|null} | null {
    const candidate = document.querySelector<HTMLElement>('.candidate-container');
    const candidateHero = candidate?.querySelector('.candidate-hero') ?? null;
    if (candidate) return { host: candidate, after: candidateHero };
    const container = document.querySelector<HTMLElement>('.fsp-shell .container');
    const hero = container?.querySelector('.hero') ?? null;
    if (container) return { host: container, after: hero };
    return null;
}

function renderFocusCenter(): void {
    const target = focusHost();
    const m = mode();
    if (!target || !m || document.querySelector('.fsp-focus-center')) return;
    const actions = roleActions();
    if (!actions.length) return;

    const section = document.createElement('section');
    section.className = 'fsp-focus-center';
    section.innerHTML = `<div class="fsp-focus-heading"><div><span>СЕГОДНЯ · ФОКУС</span><strong>${m === 'candidate' ? 'Следующие шаги по профилю' : m === 'employer' ? 'Что требует решения работодателя' : 'Контроль качества платформы'}</strong></div><button type="button" data-fsp-help>Как это работает?</button></div><div class="fsp-focus-actions"></div>`;
    const holder = section.querySelector<HTMLElement>('.fsp-focus-actions')!;
    actions.forEach((action) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `fsp-focus-action ${action.kind}`;
        button.innerHTML = `<span><b>${esc(action.title)}</b><small>${esc(action.note)}</small></span><strong>${esc(action.metric)}</strong><i>→</i>`;
        button.addEventListener('click', action.run);
        holder.appendChild(button);
    });
    section.querySelector('[data-fsp-help]')?.addEventListener('click', openHelp);
    if (target.after?.nextSibling) target.host.insertBefore(section, target.after.nextSibling);
    else target.host.appendChild(section);
}

function helpContent(): string {
    const m = mode();
    const role = m === 'candidate' ? 'кандидата' : m === 'employer' ? 'работодателя' : 'администратора';
    const blocks = m === 'candidate'
        ? [
            ['Грейд', 'Вы выбираете желаемый уровень. Подтвердить его может только assessment.'],
            ['Evidence', 'FSP ID, практические задачи и подтверждённые навыки усиливают профиль, но не подменяют тест.'],
            ['Match', 'Смотрите не только итоговый score: важны стек, грейд, зарплата, формат и конкретные gaps.'],
            ['Процесс', 'После отклика или принятия приглашения появляется кейс с задачами, комментариями и историей.'],
        ]
        : m === 'employer'
        ? [
            ['Shortlist', 'Сначала отберите кандидатов по фильтрам и маршруту Match, затем сравните 2–4 профиля.'],
            ['Почему', 'Разбор показывает доказанные совпадения и gaps, чтобы решение не зависело от одного числа.'],
            ['Приглашение', 'Адресное приглашение создаёт кейс. Контакты открываются после взаимного шага.'],
            ['Process Center', 'Несколько ЛПР могут вести задачи, этапы, комментарии и SLA в одной истории.'],
        ]
        : [
            ['Quality', 'Смотрите test-retest, integrity и стабильность грейда, а не только количество тестов.'],
            ['Audit', 'Ключевые изменения процессов сохраняются как события.'],
            ['Analytics', 'Люди, компании и география вынесены в независимые срезы.'],
            ['Jury Demo', 'Отдельный экран связывает требования ТЗ с работающими механизмами.'],
        ];
    return `<div class="fsp-help-intro"><span>Режим ${esc(role)}</span><h2>Как читать ИТ-MATCH PRO</h2><p>Система построена вокруг доказательств и следующего действия, а не вокруг одного непрозрачного рейтинга.</p></div><div class="fsp-help-grid">${blocks.map(([title,text]) => `<article><b>${esc(title)}</b><p>${esc(text)}</p></article>`).join('')}</div><div class="fsp-help-shortcuts"><span><kbd>?</kbd> помощь</span><span><kbd>/</kbd> поиск / фильтр</span><span><kbd>Esc</kbd> закрыть окно</span></div>`;
}

function openHelp(): void {
    document.querySelector('.fsp-help-backdrop')?.remove();
    const back = document.createElement('div');
    back.className = 'fsp-help-backdrop';
    back.innerHTML = `<aside class="fsp-help-drawer"><header><span>FSP MATCH</span><button type="button" aria-label="Закрыть">×</button></header>${helpContent()}</aside>`;
    const close = () => back.remove();
    back.addEventListener('click', (event) => { if (event.target === back) close(); });
    back.querySelector('header button')?.addEventListener('click', close);
    document.body.appendChild(back);
}

function focusSearch(): void {
    const candidates = Array.from(document.querySelectorAll<HTMLInputElement | HTMLSelectElement>('input[type="search"],.fsp-filter-bar input,.fsp-filter-bar select,.fsp-graph-search input'));
    const visible = candidates.find((el) => !el.disabled && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    if (visible) { visible.focus(); if (visible instanceof HTMLInputElement) visible.select(); }
}

function stampSourceIndexes(): void {
    const w = workspace();
    document.querySelectorAll<HTMLElement>('.candidate-shell .match-card,.fsp-shell .matches > article').forEach((card, index) => {
        if (card.dataset.fspIndex == null) card.dataset.fspIndex = String(index);
        if (card.dataset.fspSourceIndex == null) card.dataset.fspSourceIndex = String(index);
        if (!card.dataset.fspJobId && w.matches?.[index]?.jobId) card.dataset.fspJobId = String(w.matches[index].jobId);
    });
    document.querySelectorAll<HTMLElement>('.fsp-shell .candidate').forEach((card, index) => {
        if (card.dataset.fspIndex == null) card.dataset.fspIndex = String(index);
        if (card.dataset.fspSourceIndex == null) card.dataset.fspSourceIndex = String(index);
        if (!card.dataset.fspCandidateId && w.candidateMatches?.[index]?.candidateId) card.dataset.fspCandidateId = String(w.candidateMatches[index].candidateId);
    });
}

function enhanceTables(): void {
    document.querySelectorAll<HTMLTableElement>('table').forEach((table) => {
        if (table.dataset.fspResponsive === '1') return;
        table.dataset.fspResponsive = '1';
        const heads = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent?.trim() ?? '');
        table.querySelectorAll<HTMLTableRowElement>('tbody tr').forEach((row) => {
            Array.from(row.cells).forEach((cell, index) => { if (!cell.dataset.label && heads[index]) cell.dataset.label = heads[index]; });
        });
    });
}

function bindKeyboard(): void {
    if (keyboardBound) return;
    keyboardBound = true;
    document.addEventListener('keydown', (event) => {
        const target = event.target as HTMLElement | null;
        const typing = target?.matches('input,textarea,select,[contenteditable="true"]');
        if (event.key === 'Escape') {
            document.querySelector('.fsp-help-backdrop')?.remove();
            return;
        }
        if (!typing && event.key === '?') { event.preventDefault(); openHelp(); }
        if (!typing && event.key === '/') { event.preventDefault(); focusSearch(); }
    });
}

function run(): void {
    scheduled = false;
    if (!document.body) return;
    stampSourceIndexes();
    renderFocusCenter();
    enhanceTables();
}

function schedule(): void {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(run);
}

export function installFspProductPolish(): void {
    if (typeof window === 'undefined') return;
    bindKeyboard();
    schedule();
    observer?.disconnect();
    observer = new MutationObserver(schedule);
    observer.observe(document.body, { childList:true, subtree:true });
    document.addEventListener('inertia:navigate', () => window.setTimeout(schedule, 0));
}
