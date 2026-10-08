import { router } from '@inertiajs/vue3';

let observer: MutationObserver | null = null;
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
function isEmployer(): boolean { return workspace()?.mode === 'employer'; }

function selectedItems(): Array<{ candidate_id:number; job_id:number; name:string; job:string }> {
    const data = workspace()?.candidateMatches ?? [];
    const rows: Array<{ candidate_id:number; job_id:number; name:string; job:string }> = [];
    document.querySelectorAll<HTMLElement>('.fsp-shell .candidate').forEach((card, domIndex) => {
        const selected = Array.from(card.querySelectorAll<HTMLButtonElement>('.fsp-card-secondary-actions button'))
            .some((button) => button.classList.contains('selected') && (button.textContent ?? '').includes('сравнен'));
        if (!selected) return;
        const index = Number(card.dataset.fspSourceIndex ?? card.dataset.fspIndex ?? domIndex);
        const item = data[index];
        if (!item?.candidateId || !item?.jobId) return;
        rows.push({
            candidate_id: Number(item.candidateId),
            job_id: Number(item.jobId),
            name: String(item.candidateName ?? item.name ?? 'Кандидат'),
            job: String(item.jobTitle ?? 'вакансия'),
        });
    });
    return rows;
}

function close(): void { document.querySelector('.fsp-bulk-invite-backdrop')?.remove(); }

function openDialog(): void {
    const items = selectedItems();
    if (items.length < 2) return;
    close();
    const back = document.createElement('div');
    back.className = 'fsp-bulk-invite-backdrop';
    back.innerHTML = `<section class="fsp-bulk-invite-modal">
        <header><div><span>АДРЕСНАЯ КАМПАНИЯ</span><h2>Пригласить ${items.length} кандидатов</h2></div><button type="button" data-close>×</button></header>
        <p>Каждому кандидату создаётся отдельный кейс по его наиболее подходящей вакансии. Активные дубли автоматически пропускаются.</p>
        <div class="fsp-bulk-list">${items.map((item) => `<article><b>${escapeHtml(item.name)}</b><span>${escapeHtml(item.job)}</span></article>`).join('')}</div>
        <label><span>Общее сообщение</span><textarea maxlength="1500" rows="5" placeholder="Почему вы приглашаете кандидатов и какой следующий шаг предлагаете?"></textarea></label>
        <div class="fsp-bulk-note"><b>Контакты останутся закрыты</b><span>Они откроются только после принятия приглашения кандидатом.</span></div>
        <footer><button type="button" data-cancel>Отмена</button><button type="button" class="primary" data-send>Отправить ${items.length} приглашений</button></footer>
    </section>`;
    const doClose = () => close();
    back.addEventListener('click', (event) => { if (event.target === back) doClose(); });
    back.querySelector('[data-close]')?.addEventListener('click', doClose);
    back.querySelector('[data-cancel]')?.addEventListener('click', doClose);
    back.querySelector('[data-send]')?.addEventListener('click', () => {
        const message = back.querySelector<HTMLTextAreaElement>('textarea')?.value.trim() ?? '';
        doClose();
        router.post('/interactions/invitations/bulk', {
            items: items.map(({ candidate_id, job_id }) => ({ candidate_id, job_id })),
            message,
        }, { preserveScroll: true });
    });
    document.body.appendChild(back);
}

function escapeHtml(value:string): string {
    return value.replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c] ?? c));
}

function enhance(): void {
    scheduled = false;
    if (!isEmployer()) return;
    const tray = document.querySelector<HTMLElement>('.fsp-compare-tray');
    if (!tray || selectedItems().length < 2) return;
    if (tray.querySelector('[data-bulk-invite]')) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.bulkInvite = '1';
    button.className = 'fsp-bulk-invite-launch';
    button.textContent = 'Пригласить выбранных';
    button.addEventListener('click', openDialog);
    tray.appendChild(button);
}

function schedule(): void {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(enhance);
}

export function installFspBulkInvites(): void {
    if (typeof window === 'undefined') return;
    schedule();
    if (observer) return;
    observer = new MutationObserver(schedule);
    observer.observe(document.body, { childList:true, subtree:true, attributes:true, attributeFilter:['class'] });
    document.addEventListener('inertia:finish', schedule);
}
