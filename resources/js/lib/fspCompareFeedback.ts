import { router } from '@inertiajs/vue3';

type Mode = 'candidate' | 'employer' | 'admin';
type FilterKind = 'candidate' | 'employer';

let observer: MutationObserver | null = null;
let captureBound = false;
const candidateCompanySelection = new Set<string>();
const employerCandidateSelection = new Set<number>();
let companyCache: any[] | null = null;
let candidateCache: any[] | null = null;

const filterState: Record<FilterKind, Record<string, string>> = {
    candidate: { sort: 'score_desc' },
    employer: { sort: 'score_desc' },
};

function pageProps(): any {
    const state = window.history.state as any;
    if (state?.page?.props) return state.page.props;
    const app = document.getElementById('app');
    if (app?.dataset.page) {
        try {
            return JSON.parse(app.dataset.page).props ?? {};
        } catch {
            return {};
        }
    }
    return {};
}

function workspace(): any {
    return pageProps()?.workspace ?? {};
}

function mode(): Mode | null {
    const value = workspace()?.mode;
    return value === 'candidate' || value === 'employer' || value === 'admin' ? value : null;
}

function esc(value: unknown): string {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[char] ?? char));
}

function percent(value: unknown): string {
    const number = Number(value ?? 0);
    const normalized = number <= 1 ? number * 100 : number;
    return `${Math.round(normalized)}%`;
}

function percentNumber(value: unknown): number {
    const number = Number(value ?? 0);
    return Math.max(0, Math.min(100, Math.round(number <= 1 ? number * 100 : number)));
}

function money(value: unknown): string {
    const number = Number(value ?? 0);
    return number ? `${new Intl.NumberFormat('ru-RU').format(number)} ₽` : '—';
}

function grade(value: unknown): string {
    return ({
        intern: 'Intern',
        junior: 'Junior',
        middle: 'Middle',
        senior: 'Senior',
        lead: 'Lead',
    } as Record<string, string>)[String(value ?? '')] ?? String(value ?? '—');
}

function formatName(value: unknown): string {
    return ({
        office: 'Офис',
        hybrid: 'Гибрид',
        remote: 'Удалённо',
        any: 'Любой',
    } as Record<string, string>)[String(value ?? '')] ?? String(value ?? '—');
}

function routeName(value: unknown): string {
    return ({
        invite: 'Высокое соответствие',
        clarify: 'Нужно уточнить',
        perspective: 'Перспектива',
        observe: 'Есть критичные пробелы',
    } as Record<string, string>)[String(value ?? '')] ?? String(value ?? '—');
}

function closeModal(): void {
    document.querySelector('.fsp-modal-backdrop')?.remove();
}

function modal(title: string, body: string, wide = false): HTMLElement {
    closeModal();
    const back = document.createElement('div');
    back.className = 'fsp-modal-backdrop';
    back.innerHTML = `<section class="fsp-modal${wide ? ' wide' : ''}">
        <header>
            <div><span>FSP MATCH</span><h2>${esc(title)}</h2></div>
            <button type="button" data-close aria-label="Закрыть">×</button>
        </header>
        <div class="fsp-modal-body">${body}</div>
    </section>`;
    back.addEventListener('click', (event) => {
        if (event.target === back) closeModal();
    });
    back.querySelector('[data-close]')?.addEventListener('click', closeModal);
    document.body.appendChild(back);
    return back;
}

function metric(label: string, value: number, text?: string): string {
    const safe = Math.max(0, Math.min(100, Math.round(value)));
    return `<div class="fsp-metric">
        <div><span>${esc(label)}</span><strong>${esc(text ?? `${safe}%`)}</strong></div>
        <i><b style="width:${safe}%"></b></i>
    </div>`;
}

function matchBreakdown(item: any, perspective: 'candidate' | 'employer'): string {
    const matched = (item?.matchedSkills ?? [])
        .map((skill: string) => `<span>${esc(skill)}</span>`)
        .join('') || '<small>Подтверждённых совпадений по стеку пока нет.</small>';
    const gaps = (item?.gaps ?? item?.missingSkills ?? [])
        .map((skill: string) => `<span>${esc(skill)}</span>`)
        .join('') || '<small>Критичных пробелов не найдено.</small>';

    const category = item?.categoryMatch ? 100 : 0;
    const gradeFit = percentNumber(item?.gradeFit);
    const stack = percentNumber(item?.stackCoverage);
    const salary = item?.salaryOverlap ? 100 : 0;
    const format = item?.formatMatch ? 100 : 0;

    return `<div class="fsp-fit-summary">
        <div>
            <strong>${esc(routeName(item?.route))}</strong>
            <small>${perspective === 'candidate'
                ? 'Как вакансия совпадает с вашим профилем'
                : 'Как кандидат совпадает с потребностью вакансии'}</small>
        </div>
        <b>${esc(item?.score ?? '—')}%</b>
    </div>
    <div class="fsp-factor-grid">
        ${metric('Категория', category, item?.categoryMatch ? '100%' : '0%')}
        ${metric('Грейд', gradeFit)}
        ${metric('Стек', stack)}
        ${metric('Зарплата', salary, item?.salaryOverlap ? '100%' : '0%')}
        ${metric('Формат', format, item?.formatMatch ? '100%' : '0%')}
    </div>
    <div class="fsp-fit-columns">
        <section><h3>Совпадает</h3><div class="fsp-tags good">${matched}</div></section>
        <section><h3>Нужно закрыть / уточнить</h3><div class="fsp-tags gap">${gaps}</div></section>
    </div>
    ${(item?.reasons ?? []).length
        ? `<div class="fsp-reasons"><h3>Почему система так решила</h3>${item.reasons
            .map((reason: string) => `<p>${esc(reason)}</p>`)
            .join('')}</div>`
        : ''}`;
}

function companyKey(item: any): string {
    return String(item?.companyId ?? item?.company ?? '');
}

function candidateName(item: any): string {
    return String(item?.name ?? item?.candidateName ?? 'Кандидат');
}

function candidateProfileBody(item: any): string {
    const skills = String(item?.skills ?? '')
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean)
        .map((value) => `<span>${esc(value)}</span>`)
        .join('');

    return `<div class="fsp-profile-hero">
        <div class="fsp-profile-avatar">${esc(candidateName(item).slice(0, 1).toUpperCase())}</div>
        <div>
            <h3>${esc(candidateName(item))}</h3>
            <p>${esc(item?.headline ?? 'Специализация не указана')}</p>
            <div class="fsp-profile-chips">
                <span>${esc(item?.city ?? 'город не указан')}</span>
                <span>${esc(item?.category ?? 'категория не указана')}</span>
                <span>${esc(grade(item?.confirmedGrade))}</span>
                <span>${esc(formatName(item?.workFormat))}</span>
            </div>
        </div>
    </div>
    <div class="fsp-profile-metrics">
        <article><span>Match</span><b>${esc(item?.score ?? '—')}%</b></article>
        <article><span>Assessment</span><b>${esc(item?.assessmentScore ?? '—')}</b></article>
        <article><span>Integrity</span><b>${esc(item?.integrityScore ?? '—')}</b></article>
        <article><span>Evidence</span><b>${esc(item?.confirmedSkills ?? '—')}</b></article>
        <article><span>FSP</span><b>${esc(item?.fspEvidence ?? '—')}</b></article>
        <article><span>Ожидание</span><b>${esc(money(item?.salaryExpected))}</b></article>
    </div>
    ${item?.bio ? `<section class="fsp-profile-section"><h3>О кандидате</h3><p>${esc(item.bio)}</p></section>` : ''}
    <section class="fsp-profile-section"><h3>Навыки профиля</h3><div class="fsp-profile-skills">${skills || '<small>Навыки в профиле пока не заполнены.</small>'}</div></section>
    <section class="fsp-profile-section">
        <h3>Уровень</h3>
        <div class="fsp-profile-level">
            <span>Желаемый <b>${esc(grade(item?.desiredGrade))}</b></span>
            <span>Подтверждённый <b>${esc(grade(item?.confirmedGrade))}</b></span>
            <span>Статус <b>${esc(item?.gradeStatus ?? '—')}</b></span>
        </div>
    </section>
    ${matchBreakdown(item, 'employer')}`;
}

function companyProfileBody(item: any): string {
    return `<div class="fsp-profile-hero company">
        <div class="fsp-profile-avatar">${esc(String(item?.company ?? 'C').slice(0, 1).toUpperCase())}</div>
        <div>
            <h3>${esc(item?.company ?? 'Компания')}</h3>
            <p>${esc(item?.industry ?? 'Отрасль не указана')}</p>
            <div class="fsp-profile-chips">
                <span>${esc(item?.companyCity ?? item?.location ?? 'город не указан')}</span>
                <span>${esc(item?.size ?? 'размер не указан')}</span>
                <span>Ответ ${esc(item?.responseHours != null ? `${item.responseHours} ч` : '—')}</span>
            </div>
        </div>
    </div>
    <div class="fsp-profile-metrics">
        <article><span>Match</span><b>${esc(item?.score ?? '—')}%</b></article>
        <article><span>Hiring quality</span><b>${esc(item?.quality ?? '—')}</b></article>
        <article><span>Acceptance</span><b>${esc(item?.acceptanceRate != null ? `${item.acceptanceRate}%` : '—')}</b></article>
        <article><span>Стек fit</span><b>${percent(item?.stackCoverage)}</b></article>
        <article><span>Грейд fit</span><b>${percent(item?.gradeFit)}</b></article>
        <article><span>Формат</span><b>${esc(formatName(item?.workFormat))}</b></article>
    </div>
    <section class="fsp-profile-section">
        <h3>Лучшая вакансия для вашего профиля</h3>
        <div class="fsp-profile-level">
            <span>Вакансия <b>${esc(item?.job ?? '—')}</b></span>
            <span>Город <b>${esc(item?.location ?? '—')}</b></span>
            <span>Зарплата <b>${esc(`${money(item?.salaryMin)} — ${money(item?.salaryMax)}`)}</b></span>
        </div>
    </section>
    ${matchBreakdown(item, 'candidate')}`;
}

function openCandidateProfile(item: any): void {
    modal(`Профиль · ${candidateName(item)}`, candidateProfileBody(item), true);
}

function openCompanyProfile(item: any): void {
    modal(`Компания · ${item?.company ?? 'профиль'}`, companyProfileBody(item), true);
}

function candidateCards(): HTMLElement[] {
    const center = Array.from(document.querySelectorAll<HTMLElement>('.candidate-shell .match-card'));
    if (center.length) return center;
    return Array.from(document.querySelectorAll<HTMLElement>('.fsp-shell .matches > article'));
}

function employerCards(): HTMLElement[] {
    return Array.from(document.querySelectorAll<HTMLElement>('.fsp-shell .candidate'));
}

function stampCards(): void {
    candidateCards().forEach((card, index) => {
        if (card.dataset.fspIndex == null) card.dataset.fspIndex = String(index);
    });
    employerCards().forEach((card, index) => {
        if (card.dataset.fspIndex == null) card.dataset.fspIndex = String(index);
    });
}

function itemForCard(card: HTMLElement, kind: FilterKind): any {
    const index = Number(card.dataset.fspIndex ?? -1);
    if (!Number.isInteger(index) || index < 0) return null;
    return kind === 'candidate'
        ? (workspace()?.matches ?? [])[index]
        : (workspace()?.candidateMatches ?? [])[index];
}

function ensureActionRow(card: HTMLElement, kind: FilterKind, item: any): void {
    let row = card.querySelector<HTMLElement>('.fsp-card-action-row');
    if (!row) {
        row = document.createElement('div');
        row.className = 'fsp-card-action-row';
        card.appendChild(row);
    }

    const marketAction = card.querySelector<HTMLButtonElement>('.fsp-market-action');
    if (marketAction && marketAction.parentElement !== row) {
        row.prepend(marketAction);
    }

    if (row.querySelector('.fsp-card-secondary-actions')) return;

    const secondary = document.createElement('div');
    secondary.className = 'fsp-card-secondary-actions';

    const detail = document.createElement('button');
    detail.type = 'button';
    detail.textContent = kind === 'candidate' ? 'Разбор совпадений' : 'Разбор кандидата';
    detail.addEventListener('click', () => {
        modal(
            kind === 'candidate'
                ? `${item?.company ?? 'Компания'} · ${item?.title ?? 'вакансия'}`
                : `${candidateName(item)} · ${item?.jobTitle ?? 'вакансия'}`,
            matchBreakdown(item, kind),
            true,
        );
    });

    const profile = document.createElement('button');
    profile.type = 'button';
    profile.textContent = kind === 'candidate' ? 'Профиль компании' : 'Профиль кандидата';
    profile.addEventListener('click', () => {
        if (kind === 'candidate') {
            const companyItem = {
                ...item,
                industry: item?.companyIndustry,
                size: item?.companySize,
                quality: item?.companyQuality,
                responseHours: item?.companyResponseHours,
                acceptanceRate: item?.companyAcceptanceRate,
                job: item?.title,
                location: item?.city,
            };
            openCompanyProfile(companyItem);
        } else {
            openCandidateProfile(item);
        }
    });

    const compare = document.createElement('button');
    compare.type = 'button';
    compare.textContent = kind === 'candidate' ? '＋ Сравнить компанию' : '＋ Сравнить кандидата';
    compare.addEventListener('click', () => {
        if (kind === 'candidate') {
            const key = companyKey(item);
            if (!key) return;
            if (candidateCompanySelection.has(key)) candidateCompanySelection.delete(key);
            else candidateCompanySelection.add(key);
            compare.classList.toggle('selected', candidateCompanySelection.has(key));
            compare.textContent = candidateCompanySelection.has(key) ? '✓ В сравнении' : '＋ Сравнить компанию';
        } else {
            const id = Number(item?.candidateId);
            if (!id) return;
            if (employerCandidateSelection.has(id)) employerCandidateSelection.delete(id);
            else employerCandidateSelection.add(id);
            compare.classList.toggle('selected', employerCandidateSelection.has(id));
            compare.textContent = employerCandidateSelection.has(id) ? '✓ В сравнении' : '＋ Сравнить кандидата';
        }
        updateCompareTray();
    });

    secondary.append(detail, profile, compare);
    row.appendChild(secondary);
}

function addCardTools(): void {
    if (mode() === 'candidate') {
        candidateCards().forEach((card) => {
            const item = itemForCard(card, 'candidate');
            if (item) ensureActionRow(card, 'candidate', item);
        });
    }
    if (mode() === 'employer') {
        employerCards().forEach((card) => {
            const item = itemForCard(card, 'employer');
            if (item) ensureActionRow(card, 'employer', item);
        });
    }
}

function uniqueOptions(values: unknown[]): string[] {
    return [...new Set(values.map((value) => String(value ?? '').trim()).filter(Boolean))]
        .sort((a, b) => a.localeCompare(b, 'ru'));
}

function optionMarkup(values: string[], formatter: (value: string) => string = (value) => value): string {
    return values.map((value) => `<option value="${esc(value)}">${esc(formatter(value))}</option>`).join('');
}

function filterPanel(kind: FilterKind, items: any[]): HTMLElement {
    const panel = document.createElement('div');
    panel.className = 'fsp-filter-panel';
    panel.dataset.filterKind = kind;

    const cities = uniqueOptions(items.map((item) => item?.city));
    const formats = uniqueOptions(items.map((item) => item?.workFormat));
    const grades = uniqueOptions(items.map((item) => kind === 'candidate' ? item?.grade : item?.confirmedGrade));
    const categories = uniqueOptions(items.map((item) => item?.category));

    panel.innerHTML = `<div class="fsp-filter-head">
        <div><strong>Фильтры подбора</strong><small>${kind === 'candidate'
            ? 'Сузьте вакансии по городу, грейду, формату и Match Score.'
            : 'Сузьте кандидатов по городу, категории, уровню и качеству сигналов.'}</small></div>
        <span data-filter-count></span>
    </div>
    <div class="fsp-filter-grid">
        <label class="search"><span>Поиск</span><input type="search" data-filter="q" placeholder="${kind === 'candidate' ? 'Компания или вакансия' : 'Имя, специализация, навыки'}"></label>
        <label><span>Город</span><select data-filter="city"><option value="">Все города</option>${optionMarkup(cities)}</select></label>
        ${kind === 'employer' ? `<label><span>Категория</span><select data-filter="category"><option value="">Все категории</option>${optionMarkup(categories)}</select></label>` : ''}
        <label><span>Грейд</span><select data-filter="grade"><option value="">Все грейды</option>${optionMarkup(grades, grade)}</select></label>
        <label><span>Формат</span><select data-filter="format"><option value="">Любой формат</option>${optionMarkup(formats, formatName)}</select></label>
        <label><span>Match от</span><select data-filter="score_min">
            <option value="">Любой</option><option value="60">60%</option><option value="70">70%</option><option value="80">80%</option><option value="90">90%</option>
        </select></label>
        ${kind === 'employer' ? `<label><span>Assessment от</span><select data-filter="assessment_min"><option value="">Любой</option><option value="60">60</option><option value="70">70</option><option value="80">80</option><option value="90">90</option></select></label>
        <label><span>Integrity от</span><select data-filter="integrity_min"><option value="">Любой</option><option value="70">70</option><option value="80">80</option><option value="90">90</option></select></label>` : ''}
        <label><span>Сортировка</span><select data-filter="sort">
            ${kind === 'candidate'
                ? '<option value="score_desc">Match Score ↓</option><option value="salary_desc">Зарплата ↓</option><option value="company_asc">Компания А–Я</option><option value="city_asc">Город А–Я</option>'
                : '<option value="score_desc">Match Score ↓</option><option value="assessment_desc">Assessment ↓</option><option value="integrity_desc">Integrity ↓</option><option value="evidence_desc">Evidence ↓</option>'}
        </select></label>
        <button type="button" data-filter-reset>Сбросить</button>
    </div>`;

    panel.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[data-filter]').forEach((control) => {
        const key = control.dataset.filter ?? '';
        const stored = filterState[kind][key];
        if (stored != null) control.value = stored;
        control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', () => {
            filterState[kind][key] = control.value;
            applyFilters(kind, panel);
        });
    });

    panel.querySelector('[data-filter-reset]')?.addEventListener('click', () => {
        filterState[kind] = { sort: 'score_desc' };
        panel.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[data-filter]').forEach((control) => {
            control.value = control.dataset.filter === 'sort' ? 'score_desc' : '';
        });
        applyFilters(kind, panel);
    });

    return panel;
}

function matchesFilter(item: any, kind: FilterKind, state: Record<string, string>): boolean {
    const q = (state.q ?? '').trim().toLocaleLowerCase('ru-RU');
    const haystack = kind === 'candidate'
        ? `${item?.company ?? ''} ${item?.title ?? ''} ${item?.city ?? ''}`
        : `${candidateName(item)} ${item?.headline ?? ''} ${item?.skills ?? ''} ${item?.city ?? ''} ${item?.category ?? ''}`;
    if (q && !haystack.toLocaleLowerCase('ru-RU').includes(q)) return false;
    if (state.city && String(item?.city ?? '') !== state.city) return false;
    if (state.grade && String(kind === 'candidate' ? item?.grade : item?.confirmedGrade ?? '') !== state.grade) return false;
    if (state.format && String(item?.workFormat ?? '') !== state.format) return false;
    if (kind === 'employer' && state.category && String(item?.category ?? '') !== state.category) return false;
    if (state.score_min && Number(item?.score ?? 0) < Number(state.score_min)) return false;
    if (kind === 'employer' && state.assessment_min && Number(item?.assessmentScore ?? 0) < Number(state.assessment_min)) return false;
    if (kind === 'employer' && state.integrity_min && Number(item?.integrityScore ?? 0) < Number(state.integrity_min)) return false;
    return true;
}

function sortValue(item: any, sort: string): number | string {
    switch (sort) {
        case 'salary_desc':
            return Number(item?.salaryMax ?? 0);
        case 'assessment_desc':
            return Number(item?.assessmentScore ?? 0);
        case 'integrity_desc':
            return Number(item?.integrityScore ?? 0);
        case 'evidence_desc':
            return Number(item?.confirmedSkills ?? 0);
        case 'company_asc':
            return String(item?.company ?? '');
        case 'city_asc':
            return String(item?.city ?? '');
        default:
            return Number(item?.score ?? 0);
    }
}

function applyFilters(kind: FilterKind, panel?: HTMLElement): void {
    const cards = kind === 'candidate' ? candidateCards() : employerCards();
    const items = kind === 'candidate' ? (workspace()?.matches ?? []) : (workspace()?.candidateMatches ?? []);
    if (!cards.length) return;

    const currentPanel = panel ?? document.querySelector<HTMLElement>(`.fsp-filter-panel[data-filter-kind="${kind}"]`) ?? undefined;
    const state = filterState[kind];
    const sort = state.sort || 'score_desc';

    const entries = cards
        .map((card) => ({ card, item: itemForCard(card, kind) }))
        .filter((entry) => entry.item);

    entries.sort((a, b) => {
        const av = sortValue(a.item, sort);
        const bv = sortValue(b.item, sort);
        if (typeof av === 'number' && typeof bv === 'number') {
            return sort.endsWith('_asc') ? av - bv : bv - av;
        }
        const result = String(av).localeCompare(String(bv), 'ru');
        return sort.endsWith('_desc') ? -result : result;
    });

    const host = cards[0]?.parentElement;
    if (host) entries.forEach(({ card }) => host.appendChild(card));

    let visible = 0;
    entries.forEach(({ card, item }) => {
        const show = matchesFilter(item, kind, state);
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    if (currentPanel) {
        const counter = currentPanel.querySelector<HTMLElement>('[data-filter-count]');
        if (counter) counter.textContent = `${visible} из ${Math.min(items.length, entries.length)}`;
    }
}

function ensureFilterPanels(): void {
    if (mode() === 'candidate') {
        const cards = candidateCards();
        if (cards.length) {
            const host = cards[0].parentElement;
            const scope = host?.parentElement ?? host;
            if (host && scope && !scope.querySelector('.fsp-filter-panel[data-filter-kind="candidate"]')) {
                const panel = filterPanel('candidate', workspace()?.matches ?? []);
                host.before(panel);
                applyFilters('candidate', panel);
            }
        }
    }

    if (mode() === 'employer') {
        const cards = employerCards();
        if (cards.length) {
            const host = cards[0].parentElement;
            if (host && !host.querySelector('.fsp-filter-panel[data-filter-kind="employer"]')) {
                const panel = filterPanel('employer', workspace()?.candidateMatches ?? []);
                host.insertBefore(panel, cards[0]);
                applyFilters('employer', panel);
            }
        }
    }
}

function updateCompareTray(): void {
    let tray = document.querySelector<HTMLElement>('.fsp-compare-tray');
    const currentMode = mode();
    const count = currentMode === 'candidate'
        ? candidateCompanySelection.size
        : currentMode === 'employer'
            ? employerCandidateSelection.size
            : 0;

    if (count < 2) {
        tray?.remove();
        return;
    }

    if (!tray) {
        tray = document.createElement('div');
        tray.className = 'fsp-compare-tray';
        tray.innerHTML = '<span></span><button type="button">Сравнить</button>';
        tray.querySelector('button')?.addEventListener('click', openComparison);
        document.body.appendChild(tray);
    }

    const label = currentMode === 'candidate'
        ? `${count} компании выбраны`
        : `${count} кандидата выбраны`;
    const span = tray.querySelector('span');
    if (span) span.textContent = label;
}

function queryString(kind: FilterKind): string {
    const params = new URLSearchParams();
    Object.entries(filterState[kind]).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });
    return params.toString();
}

async function fetchJson(url: string): Promise<any[]> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    return data.items ?? [];
}

function comparisonTable(headers: string[], rows: Array<[string, (string | number | null)[]]>): string {
    return `<div class="fsp-compare-scroll"><table class="fsp-compare-table">
        <thead><tr><th>Показатель</th>${headers.map((header) => `<th>${esc(header)}</th>`).join('')}</tr></thead>
        <tbody>${rows.map(([name, values]) => `<tr><th>${esc(name)}</th>${values
            .map((value) => `<td>${esc(value ?? '—')}</td>`)
            .join('')}</tr>`).join('')}</tbody>
    </table></div>`;
}

function companySummaryCard(item: any): string {
    return `<article class="fsp-comparison-card">
        <header><div><span>${esc(item?.industry ?? 'Компания')}</span><h3>${esc(item?.company ?? 'Компания')}</h3><small>${esc(item?.location ?? item?.companyCity ?? '—')}</small></div><b>${esc(item?.score ?? '—')}%</b></header>
        ${metric('Покрытие стека', percentNumber(item?.stackCoverage))}
        ${metric('Соответствие грейда', percentNumber(item?.gradeFit))}
        ${metric('Зарплата', item?.salaryOverlap ? 100 : 0)}
        ${metric('Формат работы', item?.formatMatch ? 100 : 0)}
        ${metric('Hiring quality', Number(item?.quality ?? 0))}
        <footer><button type="button" data-company-profile="${esc(item?.companyId)}">Профиль компании</button><button type="button" data-company-fit="${esc(item?.companyId)}">Разбор совпадений</button></footer>
    </article>`;
}

function candidateSummaryCard(item: any): string {
    return `<article class="fsp-comparison-card">
        <header><div><span>${esc(item?.headline ?? item?.category ?? 'Кандидат')}</span><h3>${esc(candidateName(item))}</h3><small>${esc(item?.city ?? '—')} · ${esc(grade(item?.confirmedGrade))}</small></div><b>${esc(item?.score ?? '—')}%</b></header>
        ${metric('Покрытие стека', percentNumber(item?.stackCoverage))}
        ${metric('Соответствие грейда', percentNumber(item?.gradeFit))}
        ${metric('Assessment', Number(item?.assessmentScore ?? 0))}
        ${metric('Integrity', Number(item?.integrityScore ?? 0))}
        ${metric('Evidence', Math.min(100, Number(item?.confirmedSkills ?? 0) * 10), `${esc(item?.confirmedSkills ?? 0)} узлов`)}
        <footer><button type="button" data-candidate-profile="${esc(item?.candidateId)}">Профиль кандидата</button><button type="button" data-candidate-fit="${esc(item?.candidateId)}">Разбор совпадений</button></footer>
    </article>`;
}

function bindComparisonCardActions(root: HTMLElement, items: any[], kind: FilterKind): void {
    if (kind === 'candidate') {
        root.querySelectorAll<HTMLElement>('[data-company-profile]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = Number(button.dataset.companyProfile);
                const item = items.find((row) => Number(row.companyId) === id);
                if (item) openCompanyProfile(item);
            });
        });
        root.querySelectorAll<HTMLElement>('[data-company-fit]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = Number(button.dataset.companyFit);
                const item = items.find((row) => Number(row.companyId) === id);
                if (item) modal(`${item.company} · ${item.job}`, matchBreakdown(item, 'candidate'), true);
            });
        });
    } else {
        root.querySelectorAll<HTMLElement>('[data-candidate-profile]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = Number(button.dataset.candidateProfile);
                const item = items.find((row) => Number(row.candidateId) === id);
                if (item) openCandidateProfile(item);
            });
        });
        root.querySelectorAll<HTMLElement>('[data-candidate-fit]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = Number(button.dataset.candidateFit);
                const item = items.find((row) => Number(row.candidateId) === id);
                if (item) modal(`${candidateName(item)} · ${item.job}`, matchBreakdown(item, 'employer'), true);
            });
        });
    }
}

function renderCompanyComparison(root: HTMLElement, items: any[]): void {
    const body = root.querySelector('.fsp-modal-body');
    if (!body) return;
    if (items.length < 2) {
        body.innerHTML = '<div class="fsp-modal-error">Для выбранных компаний не найдено двух доступных сравнений.</div>';
        return;
    }

    body.innerHTML = `<div class="fsp-comparison-summary">${items.map(companySummaryCard).join('')}</div>
    ${comparisonTable(items.map((item) => item.company), [
        ['Лучшая вакансия', items.map((item) => item.job)],
        ['Город', items.map((item) => item.location ?? item.companyCity)],
        ['Маршрут', items.map((item) => routeName(item.route))],
        ['Match score', items.map((item) => `${item.score}%`)],
        ['Покрытие стека', items.map((item) => percent(item.stackCoverage))],
        ['Грейд fit', items.map((item) => percent(item.gradeFit))],
        ['Зарплата', items.map((item) => item.salaryOverlap ? '100%' : '0%')],
        ['Формат', items.map((item) => item.formatMatch ? '100%' : '0%')],
        ['Hiring quality', items.map((item) => item.quality ?? '—')],
        ['Ответ компании', items.map((item) => item.responseHours != null ? `${item.responseHours} ч` : '—')],
        ['Acceptance rate', items.map((item) => item.acceptanceRate != null ? `${item.acceptanceRate}%` : '—')],
        ['Пробелы', items.map((item) => (item.gaps ?? []).join(', ') || 'нет критичных')],
    ])}`;

    bindComparisonCardActions(root, items, 'candidate');
}

function renderCandidateComparison(root: HTMLElement, items: any[]): void {
    const body = root.querySelector('.fsp-modal-body');
    if (!body) return;
    if (items.length < 2) {
        body.innerHTML = '<div class="fsp-modal-error">Для выбранных кандидатов не найдено двух доступных сравнений.</div>';
        return;
    }

    body.innerHTML = `<div class="fsp-comparison-summary">${items.map(candidateSummaryCard).join('')}</div>
    ${comparisonTable(items.map((item) => candidateName(item)), [
        ['Подходящая вакансия', items.map((item) => item.job)],
        ['Город', items.map((item) => item.city)],
        ['Категория', items.map((item) => item.category)],
        ['Подтверждённый грейд', items.map((item) => grade(item.confirmedGrade))],
        ['Assessment', items.map((item) => item.assessmentScore ?? '—')],
        ['Integrity', items.map((item) => item.integrityScore ?? '—')],
        ['Match score', items.map((item) => `${item.score}%`)],
        ['Покрытие стека', items.map((item) => percent(item.stackCoverage))],
        ['Грейд fit', items.map((item) => percent(item.gradeFit))],
        ['Evidence узлов', items.map((item) => item.confirmedSkills)],
        ['FSP evidence', items.map((item) => item.fspEvidence)],
        ['Ожидание', items.map((item) => money(item.salaryExpected))],
        ['Формат', items.map((item) => formatName(item.workFormat))],
        ['Пробелы', items.map((item) => (item.gaps ?? []).join(', ') || 'нет критичных')],
    ])}`;

    bindComparisonCardActions(root, items, 'employer');
}

async function openComparison(): Promise<void> {
    const currentMode = mode();
    const root = modal(
        currentMode === 'candidate' ? 'Сравнение компаний' : 'Сравнение кандидатов',
        '<div class="fsp-modal-loading">Загружаем сравнение…</div>',
        true,
    );

    try {
        if (currentMode === 'candidate') {
            const query = queryString('candidate');
            companyCache = await fetchJson(`/compare/candidate/companies${query ? `?${query}` : ''}`);
            const rows = companyCache.filter((item) => candidateCompanySelection.has(companyKey(item)));
            renderCompanyComparison(root, rows);
        } else if (currentMode === 'employer') {
            const query = queryString('employer');
            candidateCache = await fetchJson(`/compare/employer/candidates${query ? `?${query}` : ''}`);
            const rows = candidateCache.filter((item) => employerCandidateSelection.has(Number(item.candidateId)));
            renderCandidateComparison(root, rows);
        }
    } catch {
        const body = root.querySelector('.fsp-modal-body');
        if (body) body.innerHTML = '<div class="fsp-modal-error">Не удалось загрузить сравнение. Обновите страницу и попробуйте снова.</div>';
    }
}

function messageDialog(kind: 'apply' | 'invite' | 'accept', title: string, submit: (message: string) => void): void {
    const heading = kind === 'apply' ? 'Отклик на вакансию' : kind === 'invite' ? 'Адресное приглашение' : 'Принять отклик';
    const label = kind === 'apply' ? 'Сопроводительное сообщение работодателю' : kind === 'invite' ? 'Комментарий кандидату' : 'Ответ кандидату';
    const send = kind === 'apply' ? 'Отправить отклик' : kind === 'invite' ? 'Отправить приглашение' : 'Принять и ответить';

    const root = modal(heading, `<div class="fsp-feedback-form">
        <p>${esc(title)}</p>
        <label>${label}<textarea maxlength="1500" rows="6" placeholder="Коротко: следующий шаг, важные условия, что хотите обсудить…"></textarea></label>
        <div class="fsp-modal-actions"><button type="button" data-cancel>Отмена</button><button type="button" class="primary" data-send>${send}</button></div>
    </div>`);

    root.querySelector('[data-cancel]')?.addEventListener('click', closeModal);
    root.querySelector('[data-send]')?.addEventListener('click', () => {
        const text = root.querySelector<HTMLTextAreaElement>('textarea')?.value.trim() ?? '';
        closeModal();
        submit(text);
    });
}

function feedbackDialog(caseId: number, title: string): void {
    const root = modal('Обратная связь по найму', `<div class="fsp-feedback-form">
        <p>${esc(title)}</p>
        <label>Оценка<select><option value="5">5 — отлично</option><option value="4">4 — хорошо</option><option value="3">3 — нормально</option><option value="2">2 — есть проблемы</option><option value="1">1 — плохо</option></select></label>
        <label>Комментарий<textarea maxlength="2000" rows="6" placeholder="Что было удобно, что можно улучшить, что важно второй стороне?"></textarea></label>
        <label class="check"><input type="checkbox" checked> Готов рекомендовать взаимодействие</label>
        <div class="fsp-modal-actions"><button type="button" data-cancel>Отмена</button><button type="button" class="primary" data-send>Сохранить отзыв</button></div>
    </div>`);

    root.querySelector('[data-cancel]')?.addEventListener('click', closeModal);
    root.querySelector('[data-send]')?.addEventListener('click', () => {
        const rating = Number(root.querySelector<HTMLSelectElement>('select')?.value ?? 5);
        const body = root.querySelector<HTMLTextAreaElement>('textarea')?.value.trim() ?? '';
        const recommend = root.querySelector<HTMLInputElement>('.check input')?.checked ?? false;
        closeModal();
        router.post(`/workspace/cases/${caseId}/feedback`, { context: 'hire', rating, body, recommend }, { preserveScroll: true });
    });
}

function injectHireFeedback(): void {
    const cases = workspace()?.cases ?? [];
    document.querySelectorAll<HTMLElement>('.fsp-shell .case').forEach((card, index) => {
        if (card.querySelector('.fsp-hire-feedback')) return;
        const item = cases[index];
        if (!item || item.status !== 'hired') return;

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'fsp-hire-feedback';
        button.textContent = 'Оставить обратную связь по найму';
        button.addEventListener('click', () => {
            feedbackDialog(Number(item.id), `${item.candidate} ↔ ${item.company} · ${item.job ?? 'процесс'}`);
        });
        card.appendChild(button);
    });
}

function bindActionCapture(): void {
    if (captureBound) return;
    captureBound = true;

    document.addEventListener('click', (event) => {
        const target = event.target as HTMLElement;
        const button = target.closest<HTMLButtonElement>('.fsp-market-action.apply,.fsp-market-action.invite');
        if (!button) return;

        const currentMode = mode();

        if (currentMode === 'candidate' && button.classList.contains('apply')) {
            const card = button.closest<HTMLElement>('.match-card,.matches > article');
            if (!card) return;
            event.preventDefault();
            event.stopImmediatePropagation();

            const item = itemForCard(card, 'candidate');
            if (!item?.jobId) return;
            messageDialog('apply', `${item.company} · ${item.title}`, (message) => {
                router.post(`/interactions/jobs/${item.jobId}/apply`, { message }, { preserveScroll: true });
            });
            return;
        }

        if (currentMode === 'employer' && button.classList.contains('invite')) {
            const candidateCard = button.closest<HTMLElement>('.candidate');
            if (candidateCard) {
                event.preventDefault();
                event.stopImmediatePropagation();

                const item = itemForCard(candidateCard, 'employer');
                if (!item?.candidateId || !item?.jobId) return;
                messageDialog('invite', `${candidateName(item)} · ${item.jobTitle ?? 'вакансия'}`, (message) => {
                    router.post(`/interactions/jobs/${item.jobId}/candidates/${item.candidateId}/invite`, { message }, { preserveScroll: true });
                });
                return;
            }

            const decision = button.closest('.fsp-application-decision');
            const caseCard = button.closest('.case');
            if (decision && caseCard) {
                event.preventDefault();
                event.stopImmediatePropagation();

                const allCases = Array.from(document.querySelectorAll('.fsp-shell .case'));
                const index = allCases.indexOf(caseCard);
                const item = (workspace()?.cases ?? [])[index];
                if (!item?.id) return;

                messageDialog('accept', `${item.candidate} · ${item.job ?? 'отклик'}`, (message) => {
                    router.post(`/interactions/cases/${item.id}/accept-application`, { message }, { preserveScroll: true });
                });
            }
        }
    }, true);
}

function run(): void {
    stampCards();
    addCardTools();
    ensureFilterPanels();
    injectHireFeedback();
    updateCompareTray();
    bindActionCapture();
}

export function installFspCompareFeedback(): void {
    if (typeof window === 'undefined') return;

    run();

    if (observer) return;
    observer = new MutationObserver(() => requestAnimationFrame(run));
    observer.observe(document.documentElement, { childList: true, subtree: true });

    document.addEventListener('inertia:finish', () => {
        companyCache = null;
        candidateCache = null;
        candidateCompanySelection.clear();
        employerCandidateSelection.clear();
        document.querySelector('.fsp-compare-tray')?.remove();
        requestAnimationFrame(run);
    });
}
