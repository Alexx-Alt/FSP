<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{ auth: { user: any }; workspace: any }>();
const mode = computed(() => props.workspace.mode as 'candidate'|'employer'|'admin');
const tab = ref('overview');
const comments = reactive<Record<number,string>>({});
const internal = reactive<Record<number,boolean>>({});
const taskDrafts = reactive<Record<number,{title:string;description:string;due_at:string}>>({});

const profile = useForm({
  headline: props.auth.user.headline ?? '', city: props.auth.user.city ?? '', bio: props.auth.user.bio ?? '',
  skills: props.auth.user.skills ?? '', company: props.auth.user.company ?? '', primary_category: props.auth.user.primary_category ?? 'Backend',
  desired_grade: props.auth.user.desired_grade ?? 'middle', salary_expected: props.auth.user.salary_expected ?? null,
  work_format: props.auth.user.work_format ?? 'any',
});

const tabs = computed(() => mode.value === 'candidate'
 ? [['overview','Главная'],['tree','Древо знаний'],['analytics','3-срезная аналитика'],['processes','Процессы'],['profile','Профиль']]
 : mode.value === 'employer'
 ? [['overview','Подбор'],['analytics','3-срезная аналитика'],['processes','Процессы'],['profile','Компания']]
 : [['overview','Система'],['analytics','3-срезная аналитика'],['processes','Процессы'],['quality','Assessment']]);

const gradeNames:any={intern:'Intern',junior:'Junior',middle:'Middle',senior:'Senior',lead:'Lead'};
const statusNames:any={new:'Новый',screening:'Скрининг',task:'Задача',interview:'Интервью',offer:'Оффер',hired:'Нанят',rejected:'Отклонён работодателем',declined:'Отклонено кандидатом',paused:'Пауза',reopened:'Переоткрыт',archived:'Архив'};
const routeNames:any={invite:'Пригласить',clarify:'Уточнить',perspective:'Перспектива',observe:'Наблюдать'};
const stateNames:any={confirmed:'Подтверждено',partial:'Частично',claimed:'Заявлено',unknown:'Не проверено'};
const grade=(v:any)=>v ? gradeNames[v] ?? v : '—';
const money=(v:any)=>v ? new Intl.NumberFormat('ru-RU').format(v)+' ₽' : '—';
const date=(v:any)=>{ if(!v)return '—'; const d=new Date(String(v).replace(' ','T')); return Number.isNaN(d.getTime())?v:d.toLocaleString('ru-RU',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}); };
const flatTree=computed(()=>{const out:any[]=[];const walk=(nodes:any[],depth=0)=>{for(const n of nodes??[]){out.push({...n,depth});walk(n.children,depth+1)}};walk(props.workspace.knowledgeTree);return out});
const taskDraft=(id:number)=>taskDrafts[id]??=( {title:'',description:'',due_at:''} );

function logout(){router.post('/logout')}
function saveProfile(){profile.patch('/dashboard/profile',{preserveScroll:true})}
function addComment(id:number){if(!comments[id]?.trim())return;router.post(`/workspace/cases/${id}/comments`,{body:comments[id],internal:internal[id]??false},{preserveScroll:true,onSuccess:()=>comments[id]=''});}
function addTask(id:number){const d=taskDraft(id);if(!d.title.trim())return;router.post(`/workspace/cases/${id}/tasks`,d,{preserveScroll:true,onSuccess:()=>taskDrafts[id]={title:'',description:'',due_at:''}})}
function taskStatus(caseId:number,taskId:number,status:string){router.patch(`/workspace/cases/${caseId}/tasks/${taskId}`,{status},{preserveScroll:true})}
function caseStatus(id:number,e:Event){router.patch(`/workspace/cases/${id}/status`,{status:(e.target as HTMLSelectElement).value},{preserveScroll:true})}
function respond(id:number,decision:'accept'|'decline'){router.post(`/workspace/cases/${id}/respond`,{decision},{preserveScroll:true})}
</script>

<template>
<Head title="ИТ-MATCH PRO · Workspace" />
<div class="fsp-shell">
  <header class="topbar"><div class="topbar-inner">
    <Link href="/" class="brand"><b>F</b><span><strong>ИТ-MATCH PRO</strong><small>Федерация спортивного программирования</small></span></Link>
    <nav><button v-for="t in tabs" :key="t[0]" :class="{active:tab===t[0]}" @click="tab=t[0]">{{t[1]}}</button></nav>
    <span class="role">{{mode==='candidate'?'Кандидат':mode==='employer'?'Работодатель':'Администратор'}}</span><button class="icon" @click="logout">↗</button>
  </div></header>

  <main class="container">
    <section class="hero"><div><div class="eyebrow">{{mode==='candidate'?'Кабинет кандидата':mode==='employer'?'Кабинет работодателя':'Администрирование ФСП'}}</div>
      <h1>{{mode==='candidate'?`Здравствуйте, ${auth.user.username}`:mode==='employer'?(workspace.summary.companyName||auth.user.company):'Состояние платформы целиком'}}</h1>
      <p>{{mode==='candidate'?'Доказательная карта навыков, сравнение с рынком и история взаимодействий.':mode==='employer'?'Объяснимый подбор и сетевой процесс с задачами, ЛПР, алертами и историей.':'Кандидаты, компании, города, assessment и процессы в одном контуре.'}}</p></div>
      <div class="chips"><span>Explainable Match</span><span>Evidence Tree</span><span>History first</span></div>
    </section>

    <section v-if="workspace.alerts?.length" class="alerts"><b>Требуют внимания</b><button v-for="a in workspace.alerts.slice(0,4)" :key="a.id" @click="tab='processes'">{{a.title}}</button></section>

    <template v-if="tab==='overview' && mode==='candidate'">
      <section class="kpis"><div><span>Желаемый грейд</span><b>{{grade(workspace.summary.desiredGrade)}}</b><small>выбран вами</small></div><div><span>Подтверждённый</span><b>{{grade(workspace.summary.confirmedGrade)}}</b><small>только assessment</small></div><div><span>Evidence</span><b>{{workspace.summary.confirmedSkills}}</b><small>узлов подтверждено</small></div><div><span>Активные процессы</span><b>{{workspace.summary.activeCases}}</b><small>незакрытых</small></div></section>
      <div class="grid main-grid"><section class="card"><div class="head"><div><div class="eyebrow">Профессиональная карта</div><h2>Что система может доказать</h2></div><button @click="tab='tree'">Древо знаний →</button></div>
        <div class="proof"><div><span>Assessment</span><b>{{workspace.summary.assessmentScore??'—'}}<small>/100</small></b></div><div><span>FSP ID</span><b>{{workspace.summary.fspEvidence}}</b></div><div><span>Peer rank</span><b>#{{workspace.summary.peerRank??'—'}}</b></div></div>
        <div class="section-title"><div class="eyebrow">Explainable matching</div><h3>Подходящие вакансии</h3></div>
        <div class="matches"><article v-for="m in workspace.matches" :key="m.jobId"><div><span class="route" :data-route="m.route">{{routeNames[m.route]}}</span><h3>{{m.title}}</h3><p>{{m.company}} · {{m.city}} · {{grade(m.grade)}}</p></div><b class="score">{{m.score}}</b><div class="axes"><span>Стек <b>{{Math.round(m.stackCoverage*100)}}%</b></span><span>Грейд <b>{{Math.round(m.gradeFit*100)}}%</b></span><span>Зарплата <b>{{m.salaryOverlap?'OK':'gap'}}</b></span><span>Формат <b>{{m.formatMatch?'OK':'gap'}}</b></span></div><small v-if="m.missingSkills?.length">Проверить: {{m.missingSkills.join(', ')}}</small></article></div>
      </section><aside class="stack"><section class="card"><div class="eyebrow">Ваш путь</div><h2>Незакрытые действия</h2><button class="timeline" v-for="c in workspace.cases.slice(0,5)" :key="c.id" @click="tab='processes'"><i :data-priority="c.priority"></i><span><b>{{c.company}}</b><small>{{c.job}} · {{statusNames[c.status]}}</small></span></button></section>
      <section class="card"><div class="eyebrow">FSP ID</div><h2>Подтверждённые достижения</h2><div class="evidence" v-for="x in workspace.fspAchievements" :key="x.title"><span :class="x.verified?'good':''">{{x.verified?'Подтверждено':'Проверка'}}</span><b>{{x.title}}</b><small>{{x.discipline}} · релевантность {{x.relevance}}%</small></div></section></aside></div>
    </template>

    <template v-if="tab==='overview' && mode==='employer'">
      <section class="kpis"><div><span>Вакансии</span><b>{{workspace.summary.openJobs}}</b><small>открыты</small></div><div><span>Процессы</span><b>{{workspace.summary.activeCases}}</b><small>активны</small></div><div><span>Ответ</span><b>{{workspace.summary.avgResponseHours??'—'}} ч</b><small>средний</small></div><div><span>Hiring quality</span><b>{{workspace.summary.hiringQualityScore??'—'}}</b><small>benchmark</small></div></section>
      <div class="grid main-grid"><section class="card"><div class="eyebrow">Explainable Match</div><h2>Кого стоит пригласить</h2><div class="candidate" v-for="c in workspace.candidateMatches" :key="c.candidateId"><div class="avatar">{{c.candidateName?.[0]}}</div><div><span class="route" :data-route="c.route">{{routeNames[c.route]}}</span><h3>{{c.candidateName}}</h3><p>{{c.headline}} · {{c.city}} · {{grade(c.confirmedGrade)}}</p><details><summary>Почему</summary><ul><li v-for="r in c.reasons" :key="r">{{r}}</li></ul><small v-if="c.missingSkills?.length">Gap: {{c.missingSkills.join(', ')}}</small></details></div><b class="score">{{c.score}}</b></div></section>
      <aside class="stack"><section class="card"><div class="eyebrow">Process Center</div><h2>Незакрытые кейсы</h2><button class="queue" v-for="c in workspace.cases.slice(0,6)" :key="c.id" @click="tab='processes'"><i :data-priority="c.priority"></i><span><b>{{c.candidate}}</b><small>{{c.job}} · {{statusNames[c.status]}}</small></span><strong>{{c.progress}}%</strong></button></section><section class="card"><div class="eyebrow">Команда</div><h2>Сотрудники между собой</h2><div class="bar-row" v-for="p in workspace.teamBenchmark" :key="p.id"><span>{{p.name}}</span><i><b :style="{width:`${p.assessmentScore}%`}"></b></i><strong>{{Math.round(p.assessmentScore)}}</strong></div><button @click="tab='analytics'">Открыть аналитику →</button></section></aside></div>
    </template>

    <template v-if="tab==='overview' && mode==='admin'">
      <section class="kpis"><div><span>Кандидаты</span><b>{{workspace.summary.candidates}}</b></div><div><span>Компании</span><b>{{workspace.summary.companies}}</b></div><div><span>Процессы</span><b>{{workspace.summary.activeCases}}</b></div><div><span>Test-retest</span><b>{{workspace.summary.gradeStability??'—'}}%</b><small>стабильность грейда</small></div></section>
      <div class="grid two"><section class="card"><div class="eyebrow">Pipeline</div><h2>Состояния процессов</h2><div class="bar-row" v-for="x in workspace.pipeline" :key="x.status"><span>{{statusNames[x.status]??x.status}}</span><i><b :style="{width:`${Math.min(100,x.total*14)}%`}"></b></i><strong>{{x.total}}</strong></div></section><section class="card"><div class="eyebrow">Audit</div><h2>Последние события</h2><div class="audit" v-for="e in workspace.audit" :key="e.createdAt+e.title"><small>{{date(e.createdAt)}}</small><b>{{e.title}}</b><p>{{e.details}}</p></div></section></div>
    </template>

    <template v-if="tab==='tree' && mode==='candidate'">
      <section class="card"><div class="head"><div><div class="eyebrow">Evidence graph</div><h2>Древо знаний</h2><p>Глубина важнее плоского списка: PostgreSQL → SQL → индексы → транзакции → оптимизация.</p></div><div class="legend"><span class="good">● confirmed</span><span class="warn">● partial</span><span>● claimed / unknown</span></div></div>
        <div class="tree"><article v-for="n in flatTree" :key="n.id" :class="n.state" :style="{marginLeft:`${Math.min(n.depth,4)*26}px`}"><i></i><div><b>{{n.name}}</b><small>{{n.code}}</small></div><div><span>{{stateNames[n.state]}}</span><strong v-if="n.score!=null">{{Math.round(n.score)}}/100</strong><small>{{n.sourceLabel}}</small></div></article></div>
      </section>
    </template>

    <template v-if="tab==='analytics'">
      <section class="intro"><div class="eyebrow">3-срезная аналитика</div><h2>Люди · компании · география</h2><p>Не один непрозрачный рейтинг: три самостоятельных слоя, которые можно объяснить.</p></section>
      <div class="analytics"><section class="card"><span class="cut">01</span><div class="eyebrow">Люди / команда</div><h2>{{mode==='candidate'?'Я среди кандидатов':mode==='employer'?'Сотрудники между собой':'Кандидаты системы'}}</h2>
        <table v-if="mode==='candidate'"><thead><tr><th>#</th><th>Кандидат</th><th>Город</th><th>Грейд</th><th>Evidence</th><th>Score</th></tr></thead><tbody><tr v-for="p in workspace.peerBenchmark" :key="p.id" :class="{current:p.isCurrent}"><td>{{p.rank}}</td><td>{{p.name}}</td><td>{{p.city}}</td><td>{{grade(p.grade)}}</td><td>{{p.confirmedSkills}}</td><td><b>{{p.score}}</b></td></tr></tbody></table>
        <div v-else-if="mode==='employer'" class="compare" v-for="p in workspace.teamBenchmark" :key="p.id"><span><b>{{p.name}}</b><small>{{p.position}} · {{grade(p.grade)}}</small></span><i><b :style="{width:`${p.assessmentScore}%`}"></b></i><strong>{{Math.round(p.assessmentScore)}}</strong><small>{{p.confirmedSkills}} evidence</small></div>
        <table v-else><thead><tr><th>Кандидат</th><th>Город</th><th>Категория</th><th>Грейд</th><th>Evidence</th><th>FSP</th></tr></thead><tbody><tr v-for="p in workspace.candidateBenchmark" :key="p.id"><td>{{p.name}}</td><td>{{p.city}}</td><td>{{p.category}}</td><td>{{grade(p.grade)}}</td><td>{{p.confirmedSkills}}</td><td>{{p.fspEvidence}}</td></tr></tbody></table>
      </section>
      <section class="card"><span class="cut">02</span><div class="eyebrow">Компании</div><h2>Company benchmark</h2><table><thead><tr><th>#</th><th>Компания</th><th>Город</th><th>Quality</th><th>Ответ</th><th>Accept</th></tr></thead><tbody><tr v-for="c in workspace.companyBenchmark" :key="c.id" :class="{current:c.isCurrent}"><td>{{c.rank}}</td><td><b>{{c.name}}</b><small>{{c.industry}}</small></td><td>{{c.city}}</td><td>{{c.quality}}</td><td>{{c.responseHours}} ч</td><td>{{c.acceptanceRate}}%</td></tr></tbody></table></section>
      <section class="card cities"><span class="cut">03</span><div class="eyebrow">География</div><h2>Рынок по городам России</h2><p>Pressure = вакансии / кандидаты в текущем датасете.</p><div class="city-grid"><article v-for="c in workspace.cityBenchmark" :key="c.city"><header><b>{{c.city}}</b><span>{{c.pressure}}× pressure</span></header><div><span>Кандидаты <b>{{c.candidates}}</b></span><span>Вакансии <b>{{c.jobs}}</b></span><span>Компании <b>{{c.companies}}</b></span><span>Подтв. грейд <b>{{c.confirmedShare}}%</b></span></div><small>Ожидание {{money(c.avgExpectedSalary)}} · предложение {{money(c.avgJobSalary)}}</small></article></div></section>
      </div>
    </template>

    <template v-if="tab==='processes'">
      <section class="intro"><div class="eyebrow">Process Center</div><h2>Найм — сеть незакрытых задач, а не карточка профиля</h2><p>Несколько ЛПР, прогресс, адресные задачи, комментарии, алерты, повторы и история прошлых решений. Старый отказ не стирает контекст.</p></section>
      <div class="cases"><article class="card case" v-for="c in workspace.cases" :key="c.id"><header><div><span>CASE-{{c.id}}</span><h3>{{c.candidate}} ↔ {{c.company}}</h3><p>{{c.job||'Без вакансии'}} · owner: {{c.owner||'не назначен'}}</p></div><div><b class="status">{{statusNames[c.status]}}</b><select v-if="mode!=='candidate'" :value="c.status" @change="caseStatus(c.id,$event)"><option v-for="(label,value) in statusNames" :key="value" :value="value">{{label}}</option></select></div></header>
        <div v-if="mode==='candidate'&&c.status==='new'&&c.source==='match_invite'" class="invite"><span><b>Адресное приглашение</b><small>После принятия контакты открываются обеим сторонам.</small></span><button class="primary" @click="respond(c.id,'accept')">Принять</button><button @click="respond(c.id,'decline')">Отклонить</button></div>
        <div class="progress"><i><b :style="{width:`${c.progress}%`}"></b></i><strong>{{c.progress}}%</strong><span>следующее: {{date(c.nextActionAt)}}</span></div>
        <div class="flags"><span>priority: {{c.priority}}</span><span>{{c.contactsUnlockedAt?'контакты открыты':'контакты закрыты'}}</span><span v-if="c.duplicateGroup" class="warn">◎ повтор: {{c.duplicateGroup}}</span><span v-if="c.reopenedFrom" class="good">↻ из CASE-{{c.reopenedFrom}}</span><span v-if="c.participants?.length">ЛПР: {{c.participants.filter((p:any)=>p.canDecide).map((p:any)=>p.name).join(', ')}}</span></div>
        <div class="case-grid"><section><h4>Задачи</h4><article class="task" v-for="t in c.tasks" :key="t.id"><span>{{t.status}}</span><div><b>{{t.title}}</b><small>до {{date(t.dueAt)}} <template v-if="t.resultScore">· {{t.resultScore}}/100</template></small></div><div v-if="mode==='candidate'&&t.status!=='done'&&t.status!=='cancelled'"><button @click="taskStatus(c.id,t.id,'in_progress')">В работу</button><button @click="taskStatus(c.id,t.id,'done')">Готово</button></div></article><details v-if="mode!=='candidate'"><summary>+ адресная задача</summary><input v-model="taskDraft(c.id).title" placeholder="Название"><textarea v-model="taskDraft(c.id).description" placeholder="Что проверить"></textarea><input v-model="taskDraft(c.id).due_at" type="datetime-local"><button class="primary" @click="addTask(c.id)">Добавить</button></details></section>
        <section><h4>Комментарии</h4><article class="comment" v-for="x in c.comments" :key="x.id"><header><b>{{x.author||'Система'}}</b><span v-if="x.internal">internal</span><small>{{date(x.createdAt)}}</small></header><p>{{x.body}}</p></article><textarea v-model="comments[c.id]" placeholder="Добавить контекст или вопрос…"></textarea><label v-if="mode!=='candidate'"><input v-model="internal[c.id]" type="checkbox"> только команда</label><button @click="addComment(c.id)">Отправить</button></section>
        <section><h4>История</h4><article class="event" v-for="e in c.events" :key="e.createdAt+e.title"><i></i><div><b>{{e.title}}</b><p>{{e.details}}</p><small>{{date(e.createdAt)}}</small></div></article></section></div>
      </article></div>
    </template>

    <template v-if="tab==='profile'&&mode!=='admin'"><div class="grid two"><section class="card"><div class="eyebrow">Профиль</div><h2>Данные и контекст</h2><form @submit.prevent="saveProfile"><label>Специализация<input v-model="profile.headline"></label><label>Город<input v-model="profile.city"></label><label v-if="mode==='employer'">Компания<input v-model="profile.company"></label><label>О себе<textarea v-model="profile.bio"></textarea></label><label>Технологии<input v-model="profile.skills"></label><template v-if="mode==='candidate'"><div class="form-row"><label>Категория<select v-model="profile.primary_category"><option>Backend</option><option>Frontend</option></select></label><label>Желаемый грейд<select v-model="profile.desired_grade"><option v-for="(g,k) in gradeNames" :key="k" :value="k">{{g}}</option></select></label></div><div class="form-row"><label>Зарплата<input v-model="profile.salary_expected" type="number"></label><label>Формат<select v-model="profile.work_format"><option value="any">Любой</option><option value="remote">Удалённо</option><option value="hybrid">Гибрид</option><option value="office">Офис</option></select></label></div></template><button class="primary" :disabled="profile.processing">Сохранить</button></form></section><aside class="card principles"><div class="eyebrow">Не смешиваем</div><h2>Три слоя профиля</h2><div><b>Самоописание</b><p>Контекст пользователя.</p></div><div><b>Грейд</b><p>Только результат category assessment.</p></div><div><b>Evidence</b><p>FSP ID и задачи ранжируют внутри уровня, но не назначают его.</p></div></aside></div></template>

    <template v-if="tab==='quality'&&mode==='admin'"><div class="grid two"><section class="card"><div class="eyebrow">Validation</div><h2>Стабильность assessment</h2><div class="quality"><b>{{workspace.summary.gradeStability??'—'}}%</b><span>test-retest совпадение грейда</span></div><div class="quality-grid"><div v-for="x in workspace.assessment.grades" :key="x.grade"><span>{{grade(x.grade)}}</span><b>{{x.avgScore}}</b><small>{{x.total}} прохожд. · integrity {{x.integrity}}</small></div></div></section><section class="card"><div class="eyebrow">Банк заданий</div><h2>Вариативность</h2><div class="variant" v-for="x in workspace.assessment.variants" :key="x.variant"><span><b>{{x.variant}}</b><small>avg {{x.avgScore}}</small></span><strong>{{x.usages}}×</strong></div><div class="notice"><b>AI — помощник, не судья</b><p>LLM может объяснять и готовить подсказки, но не назначает грейд и не блокирует кандидата.</p></div></section></div></template>
  </main>
  <footer>FSP · ИТ-MATCH PRO <span>Assessment → Evidence Tree → Explainable Match → Interaction Process → Analytics</span></footer>
</div>
</template>
<style src="../../css/fsp-workspace.css"></style>
