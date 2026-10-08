let navObserver: MutationObserver | null = null;

function pageProps(): any {
    const state = window.history.state as any;
    if (state?.page?.props) return state.page.props;
    const app = document.getElementById('app');
    if (app?.dataset.page) {
        try { return JSON.parse(app.dataset.page).props ?? {}; } catch { return {}; }
    }
    return {};
}

function role(): string | null {
    return pageProps()?.auth?.user?.role ?? null;
}

function addLink(host: HTMLElement, href: string, text: string, key: string): void {
    if (host.querySelector(`[data-fsp-nav="${key}"]`) || host.querySelector(`a[href="${href}"]`)) return;
    const a = document.createElement('a');
    a.href = href;
    a.dataset.fspNav = key;
    a.textContent = text;
    a.className = 'fsp-role-nav-link';
    if (window.location.pathname === href) a.classList.add('active');
    host.appendChild(a);
}

function enhance(): void {
    const currentRole = role();
    if (!currentRole) return;
    const navs = document.querySelectorAll<HTMLElement>('.candidate-topbar nav,.topbar nav,.employer-jobs-top nav');
    navs.forEach((nav) => {
        if (currentRole === 'applicant') addLink(nav, '/candidate/settings', 'Приватность', 'candidate-settings');
        if (currentRole === 'employer') addLink(nav, '/employer/jobs', 'Вакансии', 'employer-jobs');
        if (currentRole === 'admin') addLink(nav, '/jury-demo', 'Jury Demo', 'jury-demo');
    });
}

export function installFspRoleNav(): void {
    if (typeof window === 'undefined') return;
    enhance();
    if (navObserver) return;
    navObserver = new MutationObserver(() => requestAnimationFrame(enhance));
    navObserver.observe(document.body, { childList: true, subtree: true });
    document.addEventListener('inertia:finish', enhance);
}
