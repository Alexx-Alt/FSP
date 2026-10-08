const THEME_KEY = 'appearance';
const VIEW_KEY = 'fsp.skill.view';
let observer: MutationObserver | null = null;

type State = 'confirmed' | 'partial' | 'claimed' | 'unknown';
type NodeData = { id:string; name:string; code:string; state:State; source:string; score:string; depth:number; x:number; y:number };
type EdgeData = { source:string; target:string };

function esc(v:string){return v.replace(/[&<>'\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'}[c]||c));}
function isDark(){return document.documentElement.classList.contains('dark');}
function applyTheme(theme:'light'|'dark'){
    localStorage.setItem(THEME_KEY,theme);
    document.cookie=`appearance=${theme};path=/;max-age=31536000;SameSite=Lax`;
    document.documentElement.classList.toggle('dark',theme==='dark');
    document.documentElement.dataset.theme=theme==='dark'?'midnight':'light';
    document.documentElement.style.colorScheme=theme;
    document.querySelectorAll<HTMLButtonElement>('.fsp-theme-toggle').forEach(b=>{
        if(b.dataset.theme===theme)return;
        b.dataset.theme=theme;
        const dark=theme==='dark';
        b.title=dark?'Включить светлую тему':'Включить тёмную тему';
        b.setAttribute('aria-label',b.title);
        b.innerHTML=`<span aria-hidden="true">${dark?'☀':'◐'}</span><span class="fsp-theme-label">${dark?'Светлая':'Тёмная'}</span>`;
    });
}
function themeButton(compact=false){const b=document.createElement('button');b.type='button';b.className=`fsp-theme-toggle${compact?' compact':''}`;b.addEventListener('click',()=>applyTheme(isDark()?'light':'dark'));return b;}
function enhanceTheme(){
    const targets=[['.candidate-actions',false],['.public-actions',true],['.topbar-inner',true]] as const;
    for(const [selector,compact] of targets){document.querySelectorAll<HTMLElement>(selector).forEach(host=>{
        if(host.querySelector('.fsp-theme-toggle'))return;
        const b=themeButton(compact);
        if(selector==='.topbar-inner'){const role=host.querySelector('.role');host.insertBefore(b,role??host.lastElementChild);}else host.insertBefore(b,host.firstChild);
    });}
    if(!document.querySelector('.fsp-theme-toggle')&&document.body){const b=themeButton(true);b.classList.add('floating');document.body.appendChild(b);}
    const saved=localStorage.getItem(THEME_KEY);
    applyTheme(saved==='dark'||saved==='light'?saved:(isDark()?'dark':'light'));
}
function stateOf(a:HTMLElement):State{return a.classList.contains('confirmed')?'confirmed':a.classList.contains('partial')?'partial':a.classList.contains('claimed')?'claimed':'unknown';}
function hash(s:string){let h=2166136261;for(const c of s){h^=c.charCodeAt(0);h=Math.imul(h,16777619);}return h>>>0;}
function parseTree(tree:HTMLElement){
    const nodes=Array.from(tree.querySelectorAll<HTMLElement>('article')).map((a,i)=>{
        const name=a.querySelector('div b')?.textContent?.trim()||`Навык ${i+1}`;
        const code=a.querySelector('div small')?.textContent?.trim()||`skill.${i}`;
        const last=a.querySelector('div:last-child');
        const depth=Math.max(0,Math.round(parseFloat(a.style.marginLeft||'0')/30));
        const seed=hash(code),angle=(seed%360)*Math.PI/180,r=55+depth*105+((seed>>>8)%38);
        return {id:code,name,code,state:stateOf(a),source:last?.querySelector('small')?.textContent?.trim()||'',score:last?.querySelector('strong')?.textContent?.trim()||'',depth,x:600+Math.cos(angle)*r,y:340+Math.sin(angle)*r} as NodeData;
    });
    const byCode=new Map(nodes.map(n=>[n.code,n])),stack:NodeData[]=[],edges:EdgeData[]=[];
    for(const n of nodes){const bits=n.code.split('.');let p=bits.length>1?byCode.get(bits.slice(0,-1).join('.')):undefined;if(!p&&n.depth>0)p=stack[n.depth-1];if(p)edges.push({source:p.id,target:n.id});stack[n.depth]=n;stack.length=n.depth+1;}
    return {nodes,edges};
}
function makeGraph(tree:HTMLElement){
    const {nodes,edges}=parseTree(tree),byId=new Map(nodes.map(n=>[n.id,n]));
    const counts=nodes.reduce<Record<State,number>>((a,n)=>(a[n.state]++,a),{confirmed:0,partial:0,claimed:0,unknown:0});
    const root=document.createElement('div');root.className='fsp-knowledge-graph';
    root.innerHTML=`<div class="fsp-graph-head"><div><strong>Граф компетенций</strong><span>${nodes.length} узлов · ${edges.length} связей</span></div><div class="fsp-graph-controls"><label class="fsp-graph-search"><span>⌕</span><input type="search" placeholder="Найти навык..." /></label><button data-ga="minus">−</button><button data-ga="plus">+</button><button data-ga="reset">Центр</button></div></div><div class="fsp-graph-stats"><span class="confirmed">${counts.confirmed} доказано</span><span class="partial">${counts.partial} частично</span><span class="claimed">${counts.claimed} заявлено</span><span>${counts.unknown} не проверено</span></div><div class="fsp-graph-stage"><svg class="fsp-graph-svg" viewBox="0 0 1200 680"><g class="fsp-graph-viewport"><g class="fsp-graph-edges"></g><g class="fsp-graph-nodes"></g></g></svg><div class="fsp-graph-hint">Колесо — масштаб · тяните фон — перемещение · тяните узел — перестроение</div></div><div class="fsp-node-detail"><span>Выберите узел, чтобы увидеть источник доказательства.</span></div>`;
    const svg=root.querySelector<SVGSVGElement>('.fsp-graph-svg')!,vp=root.querySelector<SVGGElement>('.fsp-graph-viewport')!,edgeLayer=root.querySelector<SVGGElement>('.fsp-graph-edges')!,nodeLayer=root.querySelector<SVGGElement>('.fsp-graph-nodes')!,detail=root.querySelector<HTMLElement>('.fsp-node-detail')!,search=root.querySelector<HTMLInputElement>('input[type=search]')!;
    let panX=0,panY=0,zoom=1,drag:NodeData|null=null,panning=false,last={x:0,y:0};
    const viewport=()=>vp.setAttribute('transform',`translate(${panX} ${panY}) scale(${zoom})`);
    const drawEdges=()=>{edgeLayer.innerHTML=edges.map(e=>{const a=byId.get(e.source)!,b=byId.get(e.target)!;return `<line class="fsp-graph-edge" x1="${a.x}" y1="${a.y}" x2="${b.x}" y2="${b.y}"/>`;}).join('');};
    const drawNodes=()=>{nodeLayer.innerHTML=nodes.map(n=>{const r=n.depth===0?18:n.depth===1?14:11,label=n.name.length>23?n.name.slice(0,22)+'…':n.name;return `<g class="fsp-graph-node ${n.state}" data-id="${encodeURIComponent(n.id)}" transform="translate(${n.x} ${n.y})" tabindex="0"><circle r="${r}"/><text x="${r+7}" y="4">${esc(label)}</text></g>`;}).join('');};
    const redraw=()=>{drawEdges();drawNodes();viewport();};redraw();
    const pick=(el:Element|null)=>{const g=el?.closest<SVGGElement>('.fsp-graph-node');return g?byId.get(decodeURIComponent(g.dataset.id||''))||null:null;};
    const select=(n:NodeData)=>{nodeLayer.querySelectorAll('.selected').forEach(x=>x.classList.remove('selected'));nodeLayer.querySelector(`[data-id="${encodeURIComponent(n.id)}"]`)?.classList.add('selected');const labels={confirmed:'Подтверждено',partial:'Частично',claimed:'Заявлено',unknown:'Не проверено'};detail.innerHTML=`<div><span class="fsp-node-dot ${n.state}"></span><div><b>${esc(n.name)}</b><small>${esc(n.code)}</small></div></div><div><strong>${labels[n.state]}${n.score?' · '+esc(n.score):''}</strong><span>${esc(n.source||'Источник доказательства пока отсутствует')}</span></div>`;};
    const point=(e:PointerEvent)=>{const r=svg.getBoundingClientRect(),x=(e.clientX-r.left)/r.width*1200,y=(e.clientY-r.top)/r.height*680;return{x:(x-panX)/zoom,y:(y-panY)/zoom};};
    nodeLayer.addEventListener('pointerdown',e=>{const n=pick(e.target as Element);if(!n)return;e.preventDefault();drag=n;select(n);svg.setPointerCapture(e.pointerId);});
    svg.addEventListener('pointerdown',e=>{if((e.target as Element).closest('.fsp-graph-node'))return;panning=true;last={x:e.clientX,y:e.clientY};svg.setPointerCapture(e.pointerId);});
    svg.addEventListener('pointermove',e=>{if(drag){const p=point(e);drag.x=p.x;drag.y=p.y;redraw();select(drag);return;}if(panning){const r=svg.getBoundingClientRect();panX+=(e.clientX-last.x)/r.width*1200;panY+=(e.clientY-last.y)/r.height*680;last={x:e.clientX,y:e.clientY};viewport();}});
    const stop=(e:PointerEvent)=>{drag=null;panning=false;if(svg.hasPointerCapture(e.pointerId))svg.releasePointerCapture(e.pointerId);};svg.addEventListener('pointerup',stop);svg.addEventListener('pointercancel',stop);
    svg.addEventListener('wheel',e=>{e.preventDefault();zoom=Math.max(.45,Math.min(2.4,zoom*(e.deltaY>0?.9:1.1)));viewport();},{passive:false});
    nodeLayer.addEventListener('click',e=>{const n=pick(e.target as Element);if(n)select(n);});
    root.querySelector('[data-ga=plus]')?.addEventListener('click',()=>{zoom=Math.min(2.4,zoom*1.15);viewport();});root.querySelector('[data-ga=minus]')?.addEventListener('click',()=>{zoom=Math.max(.45,zoom/1.15);viewport();});root.querySelector('[data-ga=reset]')?.addEventListener('click',()=>{panX=panY=0;zoom=1;viewport();});
    search.addEventListener('input',()=>{const q=search.value.trim().toLocaleLowerCase('ru-RU');nodeLayer.querySelectorAll<SVGGElement>('.fsp-graph-node').forEach(g=>{const n=byId.get(decodeURIComponent(g.dataset.id||''));const ok=!q||!!n&&`${n.name} ${n.code} ${n.source}`.toLocaleLowerCase('ru-RU').includes(q);g.classList.toggle('dimmed',!ok);g.classList.toggle('search-hit',!!q&&ok);});});
    return root;
}
function enhanceGraph(){document.querySelectorAll<HTMLElement>('.skill-tree').forEach(tree=>{if(tree.dataset.graphEnhanced==='true')return;tree.dataset.graphEnhanced='true';const parent=tree.parentElement;if(!parent)return;const switcher=document.createElement('div');switcher.className='fsp-knowledge-switch';switcher.innerHTML='<div><button data-view="graph">Граф</button><button data-view="tree">Дерево</button></div><span>Граф показывает связи и пробелы; дерево — строгую иерархию.</span>';const graph=makeGraph(tree);parent.insertBefore(switcher,tree);parent.insertBefore(graph,tree);const show=(v:'graph'|'tree')=>{localStorage.setItem(VIEW_KEY,v);graph.hidden=v!=='graph';tree.hidden=v!=='tree';switcher.querySelectorAll<HTMLButtonElement>('button').forEach(b=>b.classList.toggle('active',b.dataset.view===v));};switcher.querySelector('[data-view=graph]')?.addEventListener('click',()=>show('graph'));switcher.querySelector('[data-view=tree]')?.addEventListener('click',()=>show('tree'));show(localStorage.getItem(VIEW_KEY)==='tree'?'tree':'graph');});}
function run(){enhanceTheme();enhanceGraph();}
export function installFspEnhancements(){if(typeof window==='undefined')return;run();if(observer)return;observer=new MutationObserver(()=>requestAnimationFrame(run));observer.observe(document.documentElement,{childList:true,subtree:true});document.addEventListener('inertia:finish',run);}
