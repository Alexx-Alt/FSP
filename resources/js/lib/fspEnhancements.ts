const THEME_KEY = 'appearance';
const VIEW_KEY = 'fsp.skill.view';
let observer: MutationObserver | null = null;

type State = 'confirmed' | 'partial' | 'claimed' | 'unknown';
type NodeData = { id:string; name:string; code:string; state:State; source:string; score:string; depth:number; x:number; y:number; parent?:string };
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
    const targets=[['.candidate-actions',false],['.public-actions',true],['.topbar-inner',true],['.jury-topbar-inner',true]] as const;
    for(const [selector,compact] of targets){document.querySelectorAll<HTMLElement>(selector).forEach(host=>{
        if(host.querySelector('.fsp-theme-toggle'))return;
        const b=themeButton(compact);
        if(selector.includes('topbar')){const role=host.querySelector('.role');host.insertBefore(b,role??host.lastElementChild);}else host.insertBefore(b,host.firstChild);
    });}
    if(!document.querySelector('.fsp-theme-toggle')&&document.body){const b=themeButton(true);b.classList.add('floating');document.body.appendChild(b);}
    const saved=localStorage.getItem(THEME_KEY);
    applyTheme(saved==='dark'||saved==='light'?saved:(isDark()?'dark':'light'));
}
function stateOf(a:HTMLElement):State{return a.classList.contains('confirmed')?'confirmed':a.classList.contains('partial')?'partial':a.classList.contains('claimed')?'claimed':'unknown';}
function parseTree(tree:HTMLElement){
    const raw=Array.from(tree.querySelectorAll<HTMLElement>('article')).map((a,i)=>{
        const name=a.querySelector('div b')?.textContent?.trim()||`Навык ${i+1}`;
        const code=a.querySelector('div small')?.textContent?.trim()||`skill.${i}`;
        const last=a.querySelector('div:last-child');
        const depth=Math.max(0,Math.round(parseFloat(a.style.marginLeft||'0')/30));
        return {id:code,name,code,state:stateOf(a),source:last?.querySelector('small')?.textContent?.trim()||'',score:last?.querySelector('strong')?.textContent?.trim()||'',depth,x:600,y:340} as NodeData;
    });
    const nodes=[...new Map(raw.map(n=>[n.code,n])).values()];
    const byCode=new Map(nodes.map(n=>[n.code,n])),stack:NodeData[]=[],edges:EdgeData[]=[];
    for(const n of nodes){
        const bits=n.code.split('.');
        let p=bits.length>1?byCode.get(bits.slice(0,-1).join('.')):undefined;
        if(!p&&n.depth>0)p=stack[n.depth-1];
        if(p){n.parent=p.id;edges.push({source:p.id,target:n.id});}
        stack[n.depth]=n;stack.length=n.depth+1;
    }
    layout(nodes,edges);
    return {nodes,edges};
}
function layout(nodes:NodeData[],edges:EdgeData[]){
    const children=new Map<string,NodeData[]>();
    for(const edge of edges){const list=children.get(edge.source)??[];const child=nodes.find(n=>n.id===edge.target);if(child)list.push(child);children.set(edge.source,list);}
    const roots=nodes.filter(n=>!n.parent);
    const place=(node:NodeData,angle:number,spread:number)=>{
        const radius=node.depth===0?0:95+node.depth*112;
        node.x=600+Math.cos(angle)*radius;
        node.y=330+Math.sin(angle)*radius*.72;
        const kids=children.get(node.id)??[];
        kids.forEach((child,index)=>{const offset=(index-(kids.length-1)/2)*(spread/Math.max(1,kids.length));place(child,angle+offset,Math.max(.45,spread*.72));});
    };
    roots.forEach((root,index)=>place(root,roots.length===1?-Math.PI/2:(Math.PI*2*index/roots.length),Math.PI*1.35));
    // Cheap deterministic collision relaxation; keeps labels readable without a heavy graph dependency.
    for(let iteration=0;iteration<55;iteration++){
        for(let i=0;i<nodes.length;i++)for(let j=i+1;j<nodes.length;j++){
            const a=nodes[i],b=nodes[j];let dx=b.x-a.x,dy=b.y-a.y;let d=Math.sqrt(dx*dx+dy*dy)||1;
            const min=58+(a.depth===0||b.depth===0?16:0);
            if(d<min){const force=(min-d)/2;dx/=d;dy/=d;a.x-=dx*force;a.y-=dy*force;b.x+=dx*force;b.y+=dy*force;}
        }
        nodes.forEach(n=>{n.x=Math.max(55,Math.min(1145,n.x));n.y=Math.max(55,Math.min(625,n.y));});
    }
}
function makeGraph(tree:HTMLElement){
    const {nodes,edges}=parseTree(tree),byId=new Map(nodes.map(n=>[n.id,n]));
    const counts=nodes.reduce<Record<State,number>>((a,n)=>(a[n.state]++,a),{confirmed:0,partial:0,claimed:0,unknown:0});
    const root=document.createElement('div');root.className='fsp-knowledge-graph';
    root.innerHTML=`<div class="fsp-graph-head"><div><strong>Граф компетенций</strong><span>${nodes.length} узлов · ${edges.length} связей</span><div class="fsp-graph-filterbar"><button class="active" data-filter="all">Все</button><button data-filter="confirmed">Подтверждено</button><button data-filter="gaps">Gaps</button><button data-filter="claimed">Заявлено</button></div></div><div class="fsp-graph-controls"><label class="fsp-graph-search"><span>⌕</span><input type="search" placeholder="Найти навык..." /></label><button data-ga="minus">−</button><button data-ga="plus">+</button><button data-ga="fit">Вписать</button><button data-ga="reset">Центр</button></div></div><div class="fsp-graph-stats"><span class="confirmed">${counts.confirmed} доказано</span><span class="partial">${counts.partial} частично</span><span class="claimed">${counts.claimed} заявлено</span><span>${counts.unknown} не проверено</span></div><div class="fsp-graph-stage"><svg class="fsp-graph-svg" viewBox="0 0 1200 680"><g class="fsp-graph-viewport"><g class="fsp-graph-edges"></g><g class="fsp-graph-nodes"></g></g></svg><div class="fsp-graph-hint">Колесо — масштаб · фон — перемещение · узел — ручная перестройка · клик — связи</div></div><div class="fsp-node-detail"><span>Выберите узел: покажем evidence и ближайшие связи.</span></div>`;
    const svg=root.querySelector<SVGSVGElement>('.fsp-graph-svg')!,vp=root.querySelector<SVGGElement>('.fsp-graph-viewport')!,edgeLayer=root.querySelector<SVGGElement>('.fsp-graph-edges')!,nodeLayer=root.querySelector<SVGGElement>('.fsp-graph-nodes')!,detail=root.querySelector<HTMLElement>('.fsp-node-detail')!,search=root.querySelector<HTMLInputElement>('input[type=search]')!;
    let panX=0,panY=0,zoom=1,drag:NodeData|null=null,panning=false,last={x:0,y:0},filter='all',query='';
    const allowed=(n:NodeData)=>filter==='all'||filter===n.state||(filter==='gaps'&&(n.state==='partial'||n.state==='unknown'));
    const viewport=()=>vp.setAttribute('transform',`translate(${panX} ${panY}) scale(${zoom})`);
    const drawEdges=()=>{edgeLayer.innerHTML=edges.map(e=>{const a=byId.get(e.source)!,b=byId.get(e.target)!;const hidden=!allowed(a)||!allowed(b);return `<line class="fsp-graph-edge${hidden?' hidden-by-filter':''}" data-source="${encodeURIComponent(a.id)}" data-target="${encodeURIComponent(b.id)}" x1="${a.x}" y1="${a.y}" x2="${b.x}" y2="${b.y}"/>`;}).join('');};
    const drawNodes=()=>{nodeLayer.innerHTML=nodes.map(n=>{const r=n.depth===0?19:n.depth===1?14:11,label=n.name.length>24?n.name.slice(0,23)+'…':n.name;const hidden=!allowed(n);return `<g class="fsp-graph-node ${n.state}${hidden?' hidden-by-filter':''}" data-id="${encodeURIComponent(n.id)}" transform="translate(${n.x} ${n.y})" tabindex="0"><circle r="${r}"/><text x="${r+7}" y="4">${esc(label)}</text></g>`;}).join('');};
    const applySearch=()=>{const q=query.trim().toLocaleLowerCase('ru-RU');nodeLayer.querySelectorAll<SVGGElement>('.fsp-graph-node').forEach(g=>{const n=byId.get(decodeURIComponent(g.dataset.id||''));const ok=!q||!!n&&`${n.name} ${n.code} ${n.source}`.toLocaleLowerCase('ru-RU').includes(q);g.classList.toggle('dimmed',!ok);g.classList.toggle('search-hit',!!q&&ok);});};
    const redraw=()=>{drawEdges();drawNodes();viewport();applySearch();};redraw();
    const pick=(el:Element|null)=>{const g=el?.closest<SVGGElement>('.fsp-graph-node');return g?byId.get(decodeURIComponent(g.dataset.id||''))||null:null;};
    const clearRelations=()=>{nodeLayer.querySelectorAll('.related,.focused').forEach(x=>x.classList.remove('related','focused'));edgeLayer.querySelectorAll('.active-path').forEach(x=>x.classList.remove('active-path'));};
    const select=(n:NodeData)=>{
        clearRelations();nodeLayer.querySelector(`[data-id="${encodeURIComponent(n.id)}"]`)?.classList.add('selected','focused');
        const neighbourIds=new Set<string>();
        edges.forEach(e=>{if(e.source===n.id||e.target===n.id){neighbourIds.add(e.source);neighbourIds.add(e.target);edgeLayer.querySelector(`[data-source="${encodeURIComponent(e.source)}"][data-target="${encodeURIComponent(e.target)}"]`)?.classList.add('active-path');}});
        neighbourIds.forEach(id=>nodeLayer.querySelector(`[data-id="${encodeURIComponent(id)}"]`)?.classList.add('related'));
        const labels={confirmed:'Подтверждено',partial:'Частично',claimed:'Заявлено',unknown:'Не проверено'};
        const related=[...neighbourIds].filter(id=>id!==n.id).map(id=>byId.get(id)?.name).filter(Boolean).slice(0,5).join(', ');
        detail.innerHTML=`<div><span class="fsp-node-dot ${n.state}"></span><div><b>${esc(n.name)}</b><small>${esc(n.code)}</small></div></div><div><strong>${labels[n.state]}${n.score?' · '+esc(n.score):''}</strong><span>${esc(n.source||'Источник доказательства пока отсутствует')}</span>${related?`<small>Связано: ${esc(related)}</small>`:''}</div>`;
    };
    const point=(e:PointerEvent)=>{const r=svg.getBoundingClientRect(),x=(e.clientX-r.left)/r.width*1200,y=(e.clientY-r.top)/r.height*680;return{x:(x-panX)/zoom,y:(y-panY)/zoom};};
    const fit=()=>{const visible=nodes.filter(allowed);if(!visible.length)return;const xs=visible.map(n=>n.x),ys=visible.map(n=>n.y);const minX=Math.min(...xs)-80,maxX=Math.max(...xs)+180,minY=Math.min(...ys)-70,maxY=Math.max(...ys)+70;const w=Math.max(180,maxX-minX),h=Math.max(160,maxY-minY);zoom=Math.max(.55,Math.min(1.55,Math.min(1100/w,600/h)));panX=600-((minX+maxX)/2)*zoom;panY=340-((minY+maxY)/2)*zoom;viewport();};
    nodeLayer.addEventListener('pointerdown',e=>{const n=pick(e.target as Element);if(!n||!allowed(n))return;e.preventDefault();drag=n;select(n);svg.setPointerCapture(e.pointerId);});
    svg.addEventListener('pointerdown',e=>{if((e.target as Element).closest('.fsp-graph-node'))return;panning=true;last={x:e.clientX,y:e.clientY};svg.setPointerCapture(e.pointerId);});
    svg.addEventListener('pointermove',e=>{if(drag){const p=point(e);drag.x=p.x;drag.y=p.y;redraw();select(drag);return;}if(panning){const r=svg.getBoundingClientRect();panX+=(e.clientX-last.x)/r.width*1200;panY+=(e.clientY-last.y)/r.height*680;last={x:e.clientX,y:e.clientY};viewport();}});
    const stop=(e:PointerEvent)=>{drag=null;panning=false;if(svg.hasPointerCapture(e.pointerId))svg.releasePointerCapture(e.pointerId);};svg.addEventListener('pointerup',stop);svg.addEventListener('pointercancel',stop);
    svg.addEventListener('wheel',e=>{e.preventDefault();zoom=Math.max(.42,Math.min(2.5,zoom*(e.deltaY>0?.9:1.1)));viewport();},{passive:false});
    svg.addEventListener('dblclick',()=>fit());
    nodeLayer.addEventListener('click',e=>{const n=pick(e.target as Element);if(n&&allowed(n))select(n);});
    root.querySelector('[data-ga=plus]')?.addEventListener('click',()=>{zoom=Math.min(2.5,zoom*1.15);viewport();});
    root.querySelector('[data-ga=minus]')?.addEventListener('click',()=>{zoom=Math.max(.42,zoom/1.15);viewport();});
    root.querySelector('[data-ga=reset]')?.addEventListener('click',()=>{panX=panY=0;zoom=1;clearRelations();viewport();});
    root.querySelector('[data-ga=fit]')?.addEventListener('click',fit);
    root.querySelectorAll<HTMLButtonElement>('[data-filter]').forEach(button=>button.addEventListener('click',()=>{filter=button.dataset.filter||'all';root.querySelectorAll('[data-filter]').forEach(x=>x.classList.toggle('active',x===button));clearRelations();redraw();fit();}));
    search.addEventListener('input',()=>{query=search.value;applySearch();});
    window.setTimeout(fit,20);
    return root;
}
function enhanceGraph(){document.querySelectorAll<HTMLElement>('.skill-tree').forEach(tree=>{if(tree.dataset.graphEnhanced==='true')return;tree.dataset.graphEnhanced='true';const parent=tree.parentElement;if(!parent)return;const switcher=document.createElement('div');switcher.className='fsp-knowledge-switch';switcher.innerHTML='<div><button data-view="graph">Граф</button><button data-view="tree">Дерево</button></div><span>Граф — связи, evidence и gaps; дерево — строгая иерархия компетенций.</span>';const graph=makeGraph(tree);parent.insertBefore(switcher,tree);parent.insertBefore(graph,tree);const show=(v:'graph'|'tree')=>{localStorage.setItem(VIEW_KEY,v);graph.hidden=v!=='graph';tree.hidden=v!=='tree';switcher.querySelectorAll<HTMLButtonElement>('button').forEach(b=>b.classList.toggle('active',b.dataset.view===v));};switcher.querySelector('[data-view=graph]')?.addEventListener('click',()=>show('graph'));switcher.querySelector('[data-view=tree]')?.addEventListener('click',()=>show('tree'));show(localStorage.getItem(VIEW_KEY)==='tree'?'tree':'graph');});}
function run(){enhanceTheme();enhanceGraph();}
export function installFspEnhancements(){if(typeof window==='undefined')return;run();if(observer)return;observer=new MutationObserver(()=>requestAnimationFrame(run));observer.observe(document.documentElement,{childList:true,subtree:true});document.addEventListener('inertia:finish',run);}
