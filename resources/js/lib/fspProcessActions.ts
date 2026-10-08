import { router } from '@inertiajs/vue3';

let observer: MutationObserver | null = null;

function props(): any {
    const state = window.history.state as any;
    if (state?.page?.props) return state.page.props;
    const app = document.getElementById('app');
    if (app?.dataset.page) {
        try { return JSON.parse(app.dataset.page).props ?? {}; } catch { return {}; }
    }
    return {};
}

function enhance(): void {
    const workspace = props()?.workspace;
    if (workspace?.mode !== 'employer') return;
    const cases = workspace?.cases ?? [];
    document.querySelectorAll<HTMLElement>('.fsp-shell .cases > .case').forEach((card, index) => {
        if (card.querySelector('.fsp-application-decision')) return;
        const item = cases[index];
        if (!item || !['self_application', 'reopened'].includes(item.source) || !['new', 'reopened'].includes(item.status)) return;
        const box = document.createElement('div');
        box.className = 'fsp-application-decision';
        box.innerHTML = `<span><b>Входящий отклик кандидата</b><small>Примите отклик, чтобы перейти к скринингу и открыть контакты после взаимного шага.</small></span>`;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'fsp-market-action invite';
        button.textContent = 'Принять отклик';
        button.addEventListener('click', () => {
            button.disabled = true;
            button.textContent = 'Принимаем…';
            router.post(`/interactions/cases/${item.id}/accept-application`, {}, { preserveScroll: true });
        });
        box.appendChild(button);
        const progress = card.querySelector('.progress');
        if (progress) progress.before(box); else card.appendChild(box);
    });
}

export function installFspProcessActions(): void {
    if (typeof window === 'undefined') return;
    enhance();
    observer?.disconnect();
    observer = new MutationObserver(() => enhance());
    observer.observe(document.body, { childList: true, subtree: true });
    document.addEventListener('inertia:navigate', () => window.setTimeout(enhance, 0));
}
