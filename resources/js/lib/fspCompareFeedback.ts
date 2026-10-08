import { router } from '@inertiajs/vue3';

let observer: MutationObserver | null = null;
let captureBound = false;
const candidateCompanySelection = new Set<string>();
const employerCandidateSelection = new Set<number>();
let companyCache: any[] | null = null;
let candidateCache: any[] | null = null;

function pageProps(): any {
    const state = window.history.state as any;
    if (state?.page?.props) return state.page.props;
    const app = document.getElementById('app');
    if (app?.dataset.page) {
        try { return JSON.parse(app.dataset.page).props ?? {}; } catch { /* noop */ }
    }
    return {};
}

function workspace(): any { return pageProps()?.workspace ?? {}; }
function mode(): 'candidate'|'employer'|'admin'|null {
    const value = workspace()?.mode;
    return value === 'candidate' || value === 'employer' || value === 'admin' ? value : null;
}
function esc(value: unknown): string {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c] ?? c));
}
function percent(value: unknown): string { return `${Math.round(Number(value ?? 0) * 100)}%`; }
function money(value: unknown): string { const n=Number(value ?? 0); return n ? `${new Intl.NumberFormat('ru-RU').format(n)} ₽` : '—'; }
function grade(value: unknown): string { return ({intern:'Intern',junior:'Junior',middle:'Middle',senior:'Senior',lead:'Lead'} as Record<string,string>)[String(value ?? '')] ?? String(value ?? '—'); }
function routeName(value: unknown): string { return ({invite:'Высокое соответствие',clarify:'Нужно уточнить',perspective:'Перспектива',observe:'Есть критичные пробелы'} as Record<string,string>)[String(value ?? '')] ?? String(value ?? '—'); }

function closeModal(): void { document.querySelector('.fsp-modal-backdrop')?.remove(); }
function modal(title: string, body: string, wide = false): HTMLElement {
    closeModal();
    const back = document.createElement('div');
    back.className = 'fsp-modal-backdrop';
    back.innerHTML = `<section class="fsp-modal${wide?' wide':''}"><header><div><span>FSP MATCH</span><h2>${esc(title)}</h2></div><button type="button" data-close>×</button></header><div class="fsp-modal-body">${body}</div></section>`;
    back.addEventListener('click', e => { if (e.target === back) closeModal(); });
    back.querySelector('[data-close]')?.addEventListener('click', closeModal);
    document.body.appendChild(back);
    return back;
}

function matchBreakdown(item: any, perspective: 'candidate'|'employer'): string {
    const matched = (item?.matchedSkills ?? []).map((s:string)=>`<span>${esc(s)}</span>`).join('') || '<small>Подтверждённых совпадений по стеку пока нет.</small>';
    const gaps = (item?.gaps ?? item?.missingSkills ?? []).map((s:string)=>`<span>${esc(s)}</span>`).join('') || '<small>Критичных пробелов не найдено.</small>';
    return `<div class="fsp-fit-summary"><div><strong>${esc(routeName(item?.route))}</strong><small>${perspective==='candidate'?'Как вакансия совпадает с вашим профилем':'Как кандидат совпадает с потребностью вакансии'}</small></div><b>${esc(item?.score ?? '—')}</b></div>
    <div class="fsp-factor-grid">
      <div><span>Категория</span><b>${item?.categoryMatch?'совпадает':'не совпадает'}</b></div>
      <div><span>Грейд</span><b>${percent(item?.gradeFit)}</b></div>
      <div><span>Стек</span><b>${percent(item?.stackCoverage)}</b></div>
      <div><span>Зарплата</span><b>${item?.salaryOverlap?'совпадает':'разрыв'}</b></div>
      <div><span>Формат</span><b>${item?.formatMatch?'совпадает':'разрыв'}</b></div>
    </div>
    <div class="fsp-fit-columns"><section><h3>Совпадает</h3><div class="fsp-tags good">${matched}</div></section><section><h3>Нужно закрыть / уточнить</h3><div class="fsp-tags gap">${gaps}</div></section></div>
    ${(item?.reasons ?? []).length ? `<div class="fsp-reasons"><h3>Почему система так решила</h3>${item.reasons.map((r:string)=>`<p>${esc(r)}</p>`).join('')}</div>` : ''}`;
}

function addCardTools(): void {
    const m = mode();
    if (m === 'candidate') {
        const items = workspace()?.matches ?? [];
        const selectors = ['.candidate-shell .match-card', '.fsp-shell .matches > article'];
        selectors.forEach(selector => {
            document.querySelectorAll<HTMLElement>(selector).forEach((card, index) => {
                if (card.querySelector('.fsp-compare-tools')) return;
                const item = items[index]; if (!item) return;
                const tools = document.createElement('div'); tools.className='fsp-compare-tools';
                const detail = document.createElement('button'); detail.type='button'; detail.textContent='Разбор совпадений'; detail.addEventListener('click',()=>modal(`${item.company} · ${item.title}`,matchBreakdown(item,'candidate'),true));
                const compare = document.createElement('button'); compare.type='button'; compare.textContent='＋ Сравнить компанию';
                compare.addEventListener('click',()=>{
                    const key=String(item.company ?? ''); if(!key)return;
                    candidateCompanySelection.has(key)?candidateCompanySelection.delete(key):candidateCompanySelection.add(key);
                    compare.classList.toggle('selected',candidateCompanySelection.has(key)); compare.textContent=candidateCompanySelection.has(key)?'✓ В сравнении':'＋ Сравнить компанию'; updateCompareTray();
                });
                tools.append(detail,compare); card.appendChild(tools);
            });
        });
    }
    if (m === 'employer') {
        const items = workspace()?.candidateMatches ?? [];
        document.querySelectorAll<HTMLElement>('.fsp-shell .candidate').forEach((card,index)=>{
            if(card.querySelector('.fsp-compare-tools'))return;
            const item=items[index];if(!item)return;
            const tools=document.createElement('div');tools.className='fsp-compare-tools';
            const detail=document.createElement('button');detail.type='button';detail.textContent='Разбор кандидата';detail.addEventListener('click',()=>modal(`${item.candidateName} · ${item.jobTitle??'вакансия'}`,matchBreakdown(item,'employer'),true));
            const compare=document.createElement('button');compare.type='button';compare.textContent='＋ Сравнить кандидата';
            compare.addEventListener('click',()=>{const id=Number(item.candidateId);if(!id)return;employerCandidateSelection.has(id)?employerCandidateSelection.delete(id):employerCandidateSelection.add(id);compare.classList.toggle('selected',employerCandidateSelection.has(id));compare.textContent=employerCandidateSelection.has(id)?'✓ В сравнении':'＋ Сравнить кандидата';updateCompareTray();});
            tools.append(detail,compare);card.appendChild(tools);
        });
    }
}

function updateCompareTray(): void {
    let tray=document.querySelector<HTMLElement>('.fsp-compare-tray');
    const m=mode();
    const count=m==='candidate'?candidateCompanySelection.size:m==='employer'?employerCandidateSelection.size:0;
    if(count<2){tray?.remove();return;}
    if(!tray){tray=document.createElement('div');tray.className='fsp-compare-tray';tray.innerHTML='<span></span><button type="button">Сравнить</button>';tray.querySelector('button')?.addEventListener('click',openComparison);document.body.appendChild(tray);}
    const label=m==='candidate'?`${count} компании выбраны`:`${count} кандидата выбраны`;
    const span=tray.querySelector('span');if(span)span.textContent=label;
}

async function fetchJson(url:string): Promise<any[]> {
    const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
    if(!response.ok)throw new Error(`HTTP ${response.status}`);
    const data=await response.json();return data.items??[];
}

async function openComparison(): Promise<void> {
    const m=mode();
    const loading=modal(m==='candidate'?'Сравнение компаний':'Сравнение кандидатов','<div class="fsp-modal-loading">Загружаем сравнение…</div>',true);
    try{
        if(m==='candidate'){
            companyCache??=await fetchJson('/compare/candidate/companies');
            const rows=companyCache.filter(x=>candidateCompanySelection.has(String(x.company)));
            renderCompanyComparison(loading,rows);
        }else if(m==='employer'){
            candidateCache??=await fetchJson('/compare/employer/candidates');
            const rows=candidateCache.filter(x=>employerCandidateSelection.has(Number(x.candidateId)));
            renderCandidateComparison(loading,rows);
        }
    }catch(error){const body=loading.querySelector('.fsp-modal-body');if(body)body.innerHTML='<div class="fsp-modal-error">Не удалось загрузить сравнение. Обновите страницу и попробуйте снова.</div>';}
}

function comparisonTable(headers:string[], rows:Array<[string,(string|number|null)[]]>):string{
    return `<div class="fsp-compare-scroll"><table class="fsp-compare-table"><thead><tr><th>Показатель</th>${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(([name,values])=>`<tr><th>${esc(name)}</th>${values.map(v=>`<td>${esc(v??'—')}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}
function renderCompanyComparison(root:HTMLElement,items:any[]):void{
    const body=root.querySelector('.fsp-modal-body');if(!body)return;
    if(items.length<2){body.innerHTML='<div class="fsp-modal-error">Для выбранных компаний не найдено двух доступных сравнений.</div>';return;}
    body.innerHTML=comparisonTable(items.map(x=>x.company),[
        ['Лучшая вакансия',items.map(x=>x.job)],['Город',items.map(x=>x.location??x.companyCity)],['Маршрут',items.map(x=>routeName(x.route))],['Match score',items.map(x=>x.score)],['Покрытие стека',items.map(x=>percent(x.stackCoverage))],['Грейд fit',items.map(x=>percent(x.gradeFit))],['Зарплата',items.map(x=>x.salaryOverlap?'совпадает':'разрыв')],['Формат',items.map(x=>x.formatMatch?'совпадает':'разрыв')],['Hiring quality',items.map(x=>x.quality??'—')],['Ответ компании',items.map(x=>x.responseHours!=null?`${x.responseHours} ч`:'—')],['Acceptance rate',items.map(x=>x.acceptanceRate!=null?`${x.acceptanceRate}%`:'—')],['Пробелы',items.map(x=>(x.gaps??[]).join(', ')||'нет критичных')]
    ]);
}
function renderCandidateComparison(root:HTMLElement,items:any[]):void{
    const body=root.querySelector('.fsp-modal-body');if(!body)return;
    if(items.length<2){body.innerHTML='<div class="fsp-modal-error">Для выбранных кандидатов не найдено двух доступных сравнений.</div>';return;}
    body.innerHTML=comparisonTable(items.map(x=>x.name),[
        ['Подходящая вакансия',items.map(x=>x.job)],['Город',items.map(x=>x.city)],['Подтверждённый грейд',items.map(x=>grade(x.confirmedGrade))],['Assessment',items.map(x=>x.assessmentScore??'—')],['Integrity',items.map(x=>x.integrityScore??'—')],['Match score',items.map(x=>x.score)],['Покрытие стека',items.map(x=>percent(x.stackCoverage))],['Грейд fit',items.map(x=>percent(x.gradeFit))],['Evidence узлов',items.map(x=>x.confirmedSkills)],['FSP evidence',items.map(x=>x.fspEvidence)],['Ожидание',items.map(x=>money(x.salaryExpected))],['Формат',items.map(x=>x.workFormat??'—')],['Пробелы',items.map(x=>(x.gaps??[]).join(', ')||'нет критичных')]
    ]);
}

function messageDialog(kind:'apply'|'invite', title:string, submit:(message:string)=>void):void{
    const root=modal(kind==='apply'?'Отклик на вакансию':'Адресное приглашение',`<div class="fsp-feedback-form"><p>${esc(title)}</p><label>${kind==='apply'?'Сопроводительное сообщение работодателю':'Комментарий кандидату'}<textarea maxlength="1500" rows="6" placeholder="Коротко: почему это интересно, что хотите обсудить, важные условия…"></textarea></label><div class="fsp-modal-actions"><button type="button" data-cancel>Отмена</button><button type="button" class="primary" data-send>${kind==='apply'?'Отправить отклик':'Отправить приглашение'}</button></div></div>`);
    root.querySelector('[data-cancel]')?.addEventListener('click',closeModal);
    root.querySelector('[data-send]')?.addEventListener('click',()=>{const text=root.querySelector<HTMLTextAreaElement>('textarea')?.value.trim()??'';closeModal();submit(text);});
}

function feedbackDialog(caseId:number, title:string):void{
    const root=modal('Обратная связь по найму',`<div class="fsp-feedback-form"><p>${esc(title)}</p><label>Оценка<select><option value="5">5 — отлично</option><option value="4">4 — хорошо</option><option value="3">3 — нормально</option><option value="2">2 — есть проблемы</option><option value="1">1 — плохо</option></select></label><label>Комментарий<textarea maxlength="2000" rows="6" placeholder="Что было удобно, что можно улучшить, что важно второй стороне?"></textarea></label><label class="check"><input type="checkbox" checked> Готов рекомендовать взаимодействие</label><div class="fsp-modal-actions"><button type="button" data-cancel>Отмена</button><button type="button" class="primary" data-send>Сохранить отзыв</button></div></div>`);
    root.querySelector('[data-cancel]')?.addEventListener('click',closeModal);
    root.querySelector('[data-send]')?.addEventListener('click',()=>{const rating=Number(root.querySelector<HTMLSelectElement>('select')?.value??5);const body=root.querySelector<HTMLTextAreaElement>('textarea')?.value.trim()??'';const recommend=root.querySelector<HTMLInputElement>('.check input')?.checked??false;closeModal();router.post(`/workspace/cases/${caseId}/feedback`,{context:'hire',rating,body,recommend},{preserveScroll:true});});
}

function injectHireFeedback():void{
    const cases=workspace()?.cases??[];
    document.querySelectorAll<HTMLElement>('.fsp-shell .case').forEach((card,index)=>{
        if(card.querySelector('.fsp-hire-feedback'))return;
        const item=cases[index];if(!item||item.status!=='hired')return;
        const button=document.createElement('button');button.type='button';button.className='fsp-hire-feedback';button.textContent='Оставить обратную связь по найму';button.addEventListener('click',()=>feedbackDialog(Number(item.id),`${item.candidate} ↔ ${item.company} · ${item.job??'процесс'}`));
        card.appendChild(button);
    });
}

function bindActionCapture():void{
    if(captureBound)return;captureBound=true;
    document.addEventListener('click',(event)=>{
        const target=event.target as HTMLElement;
        const button=target.closest<HTMLButtonElement>('.fsp-market-action.apply,.fsp-market-action.invite');
        if(!button)return;
        const m=mode();
        if(m==='candidate'&&button.classList.contains('apply')){
            event.preventDefault();event.stopImmediatePropagation();
            const card=button.closest('.match-card,.matches > article');if(!card)return;
            const all=Array.from(document.querySelectorAll('.candidate-shell .match-card')).length?Array.from(document.querySelectorAll('.candidate-shell .match-card')):Array.from(document.querySelectorAll('.fsp-shell .matches > article'));
            const index=all.indexOf(card);const item=(workspace()?.matches??[])[index];if(!item?.jobId)return;
            messageDialog('apply',`${item.company} · ${item.title}`,(message)=>router.post(`/interactions/jobs/${item.jobId}/apply`,{message},{preserveScroll:true}));
        }
        if(m==='employer'&&button.classList.contains('invite')){
            event.preventDefault();event.stopImmediatePropagation();
            const card=button.closest('.candidate');if(!card)return;
            const all=Array.from(document.querySelectorAll('.fsp-shell .candidate'));const index=all.indexOf(card);const item=(workspace()?.candidateMatches??[])[index];if(!item?.candidateId||!item?.jobId)return;
            messageDialog('invite',`${item.candidateName} · ${item.jobTitle??'вакансия'}`,(message)=>router.post(`/interactions/jobs/${item.jobId}/candidates/${item.candidateId}/invite`,{message},{preserveScroll:true}));
        }
    },true);
}

function run():void{addCardTools();injectHireFeedback();updateCompareTray();bindActionCapture();}
export function installFspCompareFeedback():void{
    if(typeof window==='undefined')return;run();if(observer)return;
    observer=new MutationObserver(()=>requestAnimationFrame(run));observer.observe(document.documentElement,{childList:true,subtree:true});
    document.addEventListener('inertia:finish',()=>{companyCache=null;candidateCache=null;requestAnimationFrame(run);});
}
