<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

type Tab = 'overview' | 'matches' | 'passport' | 'skills' | 'career' | 'assessment' | 'fsp' | 'processes';
type Category = 'Backend' | 'Frontend' | 'Data' | 'ML/AI' | 'DevOps' | 'Full-stack';
type Grade = 'intern' | 'junior' | 'middle' | 'senior' | 'lead';

interface Question { id: number | string; skill: string; prompt: string; options: string[]; difficulty: number }
interface AssessmentPayload {
    categories: Category[]; grades: Grade[]; category: Category; desiredGrade: Grade; questionSet: Question[];
    history: Array<{ id:number; category:Category; desiredGrade:Grade; resultGrade:Grade; score:number; integrityScore:number; variant:string; completedAt:string }>;
    cooldownUntil: string | null; gradeChangeLocked: boolean; demoRetestAllowed: boolean; bankSource: 'database' | 'fallback';
}

const props = defineProps<{
    auth: { user: any };
    workspace: any;
    assessment: AssessmentPayload;
    careerHistory: Array<{ category: Category; confidence: number; createdAt: string }>;
}>();

const tab = ref<Tab>('overview');
const returningUser = computed(() => (props.assessment.history?.length ?? 0) > 0 || (props.workspace.summary?.activeCases ?? 0) > 0 || (props.workspace.summary?.confirmedSkills ?? 0) > 0);
const tabs: Array<[Tab,string]> = [
    ['overview','Обзор'],['matches','Подбор'],['passport','Паспорт'],['skills','Карта навыков'],
    ['career','Career Compass'],['assessment','Проверка уровня'],['fsp','FSP ID'],['processes','Процессы'],
];
const gradeNames: Record<string,string> = { intern:'Intern', junior:'Junior', middle:'Middle', senior:'Senior', lead:'Lead' };
const routeNames: Record<string,string> = { invite:'Можно приглашать', clarify:'Сначала уточнить', perspective:'Перспектива', observe:'Наблюдать' };
const stateNames: Record<string,string> = { confirmed:'Подтверждено', partial:'Частично', claimed:'Заявлено', unknown:'Не проверено' };
const statusNames: Record<string,string> = { new:'Новое приглашение', screening:'Скрининг', task:'Задача', interview:'Интервью', offer:'Оффер', hired:'Нанят', rejected:'Отклонён', declined:'Отклонено', paused:'Пауза', reopened:'Переоткрыт', archived:'Архив' };

const grade = (v?: string | null) => v ? (gradeNames[v] ?? v) : '—';
const money = (v?: number | null) => v ? `${new Intl.NumberFormat('ru-RU').format(v)} ₽` : '—';
const date = (v?: string | null) => {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ','T'));
    return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('ru-RU',{day:'2-digit',month:'short',year:'numeric'});
};

const flatTree = computed(() => {
    const out:any[]=[];
    const walk=(nodes:any[],depth=0)=>{ for(const node of nodes ?? []) { out.push({...node,depth}); walk(node.children ?? [],depth+1); } };
    walk(props.workspace.knowledgeTree ?? []); return out;
});

const profile = useForm({
    headline: props.auth.user.headline ?? '', city: props.auth.user.city ?? '', bio: props.auth.user.bio ?? '',
    skills: props.auth.user.skills ?? '', salary_expected: props.auth.user.salary_expected ?? null,
    work_format: props.auth.user.work_format ?? 'any', desired_grade: props.auth.user.desired_grade ?? 'middle',
});
const saveProfile=()=>profile.patch('/candidate/profile',{preserveScroll:true});
const logout=()=>router.post('/logout');
const respond=(id:number,decision:'accept'|'decline')=>router.post(`/workspace/cases/${id}/respond`,{decision},{preserveScroll:true});

// Career Compass — направление, а не источник грейда.
const careerAnswers=reactive<Record<string,Category>>({});
const careerQuestions=[
    {id:'interest',title:'Что интереснее разбирать?',options:[['Серверную логику и API','Backend'],['Интерфейсы и взаимодействие','Frontend'],['Данные и закономерности','Data'],['Модели и интеллектуальные системы','ML/AI'],['Инфраструктуру и надёжность','DevOps'],['Продукт целиком','Full-stack']]},
    {id:'result',title:'Какой результат нравится видеть?',options:[['Надёжный сервис','Backend'],['Удобный экран','Frontend'],['Чистые данные и метрики','Data'],['Работающую модель','ML/AI'],['Стабильный production','DevOps'],['Фичу от БД до UI','Full-stack']]},
    {id:'thinking',title:'Какой стиль мышления ближе?',options:[['Системный и предметный','Backend'],['Визуальный и пользовательский','Frontend'],['Аналитический','Data'],['Исследовательский','ML/AI'],['Операционный и системный','DevOps'],['Комбинированный','Full-stack']]},
    {id:'debug',title:'Какую проблему выбрали бы?',options:[['Медленный API','Backend'],['Дёргающийся интерфейс','Frontend'],['Сломанный ETL','Data'],['Падение качества модели','ML/AI'],['Падение кластера','DevOps'],['Ошибка между несколькими слоями','Full-stack']]},
    {id:'goal',title:'Что хочется уметь через полгода?',options:[['Проектировать backend-сервисы','Backend'],['Строить сложный web UI','Frontend'],['Проектировать data pipelines','Data'],['Доводить ML до production','ML/AI'],['Разворачивать платформы','DevOps'],['Собирать продуктовую фичу целиком','Full-stack']]},
] as Array<{id:string;title:string;options:Array<[string,Category]>}>;
const careerResult=computed(()=>{
    const values=Object.values(careerAnswers); if(values.length<3) return null;
    const counts=new Map<Category,number>(); values.forEach(v=>counts.set(v,(counts.get(v)??0)+1));
    const best=[...counts.entries()].sort((a,b)=>b[1]-a[1])[0];
    return best ? {category:best[0],confidence:Math.min(96,Math.round(55+(best[1]/values.length)*41))} : null;
});
const saveCareer=()=>{ if(careerResult.value) router.post('/candidate/career',{recommended_category:careerResult.value.category,confidence:careerResult.value.confidence,answers:careerAnswers},{preserveScroll:true}); };

// Assessment: правильные ответы сервер не отправляет клиенту.
const assessmentCategory=ref<Category>(props.assessment.category);
const assessmentGrade=ref<Grade>(props.assessment.desiredGrade);
const assessmentActive=ref(false);
const selectedAnswers=reactive<Record<string,number>>({});
const answerStarted=reactive<Record<string,number>>({});
const integrityEvents=ref<Array<{type:string;weight:number;metadata?:Record<string,unknown>}>>([]);
const secondsLeft=ref(15*60);
let timer:ReturnType<typeof setInterval>|null=null;
let integrityBound=false;
const answeredCount=computed(()=>Object.keys(selectedAnswers).length);
const canSubmit=computed(()=>assessmentActive.value && answeredCount.value>=5);
const timeLabel=(sec:number)=>`${String(Math.max(0,Math.floor(sec/60))).padStart(2,'0')}:${String(Math.max(0,sec%60)).padStart(2,'0')}`;
function recordIntegrity(type:string,weight=1,metadata:Record<string,unknown>={}) { if(assessmentActive.value && integrityEvents.value.length<100) integrityEvents.value.push({type,weight,metadata}); }
const onVisibility=()=>{if(document.hidden)recordIntegrity('visibility',2)};
const onBlur=()=>recordIntegrity('blur',2);
const onCopy=()=>recordIntegrity('copy',4);
const onPaste=()=>recordIntegrity('paste',4);
const onContext=()=>recordIntegrity('contextmenu',4);
const onKey=(e:KeyboardEvent)=>{if((e.ctrlKey||e.metaKey)&&['c','v','a','p'].includes(e.key.toLowerCase()))recordIntegrity('shortcut',5,{key:e.key})};
const onFullscreen=()=>{if(!document.fullscreenElement)recordIntegrity('fullscreen_exit',5)};
function bindIntegrity(){ if(integrityBound)return; integrityBound=true; document.addEventListener('visibilitychange',onVisibility); window.addEventListener('blur',onBlur); document.addEventListener('copy',onCopy); document.addEventListener('paste',onPaste); document.addEventListener('contextmenu',onContext); document.addEventListener('keydown',onKey,true); document.addEventListener('fullscreenchange',onFullscreen); }
function unbindIntegrity(){ if(!integrityBound)return; integrityBound=false; document.removeEventListener('visibilitychange',onVisibility); window.removeEventListener('blur',onBlur); document.removeEventListener('copy',onCopy); document.removeEventListener('paste',onPaste); document.removeEventListener('contextmenu',onContext); document.removeEventListener('keydown',onKey,true); document.removeEventListener('fullscreenchange',onFullscreen); }
function startAssessment(){ assessmentActive.value=true; secondsLeft.value=15*60; Object.keys(selectedAnswers).forEach(k=>delete selectedAnswers[k]); Object.keys(answerStarted).forEach(k=>delete answerStarted[k]); props.assessment.questionSet.forEach(q=>answerStarted[String(q.id)]=Date.now()); integrityEvents.value=[]; bindIntegrity(); if(timer)clearInterval(timer); timer=setInterval(()=>{secondsLeft.value--; if(secondsLeft.value<=0)submitAssessment();},1000); }
function submitAssessment(){ if(!assessmentActive.value||answeredCount.value<5)return; assessmentActive.value=false; if(timer){clearInterval(timer);timer=null} unbindIntegrity(); const now=Date.now(); const answers=Object.entries(selectedAnswers).map(([question_id,selected_index])=>({question_id,selected_index,response_ms:Math.max(0,now-(answerStarted[question_id]??now))})); router.post('/candidate/assessment',{category:assessmentCategory.value,desired_grade:assessmentGrade.value,answers,integrity_events:integrityEvents.value.map(event=>({type:event.type,weight:event.weight,metadata:JSON.stringify(event.metadata??{})}))},{preserveScroll:true}); }
onBeforeUnmount(()=>{if(timer)clearInterval(timer);unbindIntegrity()});
</script>

<template>
<Head title="Кабинет кандидата" />
<div class="candidate-shell" :class="{ 'is-returning': returningUser }">
    <header class="candidate-topbar"><div class="candidate-topbar-inner">
        <Link href="/" class="candidate-brand platform-brand" aria-label="ИТ-МЕТЧ"><img src="/brand/fsp-mark.svg" alt="ФСП" /><strong>ИТ-МЕТЧ</strong></Link>
        <nav><button v-for="item in tabs" :key="item[0]" :class="{active:tab===item[0]}" @click="tab=item[0]">{{ item[1] }}</button></nav>
        <div class="candidate-actions"><Link href="/workspace" class="ghost-link">Аналитика</Link><button @click="logout">Выйти</button></div>
    </div></header>

    <main class="candidate-container">
        <section class="candidate-hero"><div><span class="eyebrow">Кабинет кандидата · {{ workspace.summary.category ?? 'без категории' }}</span><h1>{{ auth.user.username }}, профессиональный профиль</h1><p>Паспорт, проверка уровня, дерево навыков, FSP evidence, объяснимый подбор и история найма — в одном контуре.</p></div><div class="hero-status"><span>Заявленный <b>{{ grade(workspace.summary.desiredGrade) }}</b></span><span>Подтверждённый <b>{{ grade(workspace.summary.confirmedGrade) }}</b></span><span>Evidence <b>{{ workspace.summary.confirmedSkills ?? 0 }}</b></span></div></section>

        <template v-if="tab==='overview'">
            <section class="candidate-kpis"><article><span>Assessment</span><strong>{{ workspace.summary.assessmentScore ?? '—' }}</strong><small>из 100</small></article><article><span>Peer rank</span><strong>#{{ workspace.summary.peerRank ?? '—' }}</strong><small>в своей категории</small></article><article><span>FSP evidence</span><strong>{{ workspace.summary.fspEvidence ?? 0 }}</strong><small>подтверждений</small></article><article><span>Процессы</span><strong>{{ workspace.summary.activeCases ?? 0 }}</strong><small>активных</small></article></section>
            <div class="candidate-grid main-grid"><section class="card onboarding-card" v-if="!returningUser"><span class="eyebrow">Маршрут развития</span><h2>Что сделать дальше</h2><div class="action-list"><button @click="tab='assessment'"><i>01</i><span><b>Подтвердить грейд</b><small>адаптивный assessment + test-retest</small></span><em>→</em></button><button @click="tab='skills'"><i>02</i><span><b>Закрыть gaps в навыках</b><small>confirmed / partial / claimed / unknown</small></span><em>→</em></button><button @click="tab='career'"><i>03</i><span><b>Проверить направление</b><small>Career Compass для выбора специализации</small></span><em>→</em></button><button @click="tab='matches'"><i>04</i><span><b>Посмотреть подбор</b><small>score + причины + пробелы</small></span><em>→</em></button></div></section><aside class="stack"><section class="card"><span class="eyebrow">Top Match</span><h2>Ближайшие вакансии</h2><button v-for="m in (workspace.matches??[]).slice(0,4)" :key="m.jobId" class="mini-match" @click="tab='matches'"><span><b>{{m.title}}</b><small>{{m.company}} · {{m.city}}</small></span><strong>{{m.score}}</strong></button></section><section class="card"><span class="eyebrow">Рынок</span><h2>Ваш контекст</h2><div class="stat-row"><span>Категория</span><b>{{workspace.summary.category??'—'}}</b></div><div class="stat-row"><span>Ожидание</span><b>{{money(auth.user.salary_expected)}}</b></div><div class="stat-row"><span>Формат</span><b>{{auth.user.work_format??'any'}}</b></div></section></aside></div>
        </template>

        <template v-else-if="tab==='matches'">
            <section class="section-head"><span class="eyebrow">Explainable Match</span><h2>Вакансии и причины подбора</h2><p>Не один магический рейтинг: стек, грейд, формат, зарплата и evidence показываются отдельно.</p></section>
            <div class="match-grid"><article v-for="m in workspace.matches??[]" :key="m.jobId" class="card match-card"><header><div><span class="route" :data-route="m.route">{{routeNames[m.route]??m.route}}</span><h3>{{m.title}}</h3><p>{{m.company}} · {{m.city}} · {{grade(m.grade)}}</p></div><strong>{{m.score}}</strong></header><div class="axis"><span>Стек <b>{{Math.round((m.stackCoverage??0)*100)}}%</b></span><span>Грейд <b>{{Math.round((m.gradeFit??0)*100)}}%</b></span><span>Зарплата <b>{{m.salaryOverlap?'OK':'gap'}}</b></span><span>Формат <b>{{m.formatMatch?'OK':'gap'}}</b></span></div><div v-if="m.reasons?.length" class="reason"><b>Почему подходит</b><span v-for="r in m.reasons" :key="r">{{r}}</span></div><div v-if="m.missingSkills?.length" class="gap"><b>Проверить</b> {{m.missingSkills.join(', ')}}</div></article></div>
        </template>

        <template v-else-if="tab==='passport'">
            <div class="candidate-grid main-grid"><section class="card"><span class="eyebrow">Профессиональный паспорт</span><h2>Профиль кандидата</h2><form class="passport-form" @submit.prevent="saveProfile"><label>Специализация<input v-model="profile.headline" maxlength="180" /></label><div class="form-two"><label>Город<input v-model="profile.city" maxlength="100" /></label><label>Желаемый грейд<select v-model="profile.desired_grade"><option v-for="g in assessment.grades" :key="g" :value="g">{{grade(g)}}</option></select></label></div><div class="form-two"><label>Зарплатное ожидание<input v-model="profile.salary_expected" type="number" min="0" /></label><label>Формат<select v-model="profile.work_format"><option value="any">Любой</option><option value="remote">Удалённо</option><option value="hybrid">Гибрид</option><option value="office">Офис</option></select></label></div><label>Навыки<input v-model="profile.skills" maxlength="600" placeholder="PHP, Laravel, PostgreSQL..." /></label><label>О себе<textarea v-model="profile.bio" rows="7" maxlength="3000"></textarea></label><button class="primary" :disabled="profile.processing">{{profile.processing?'Сохраняем…':'Сохранить паспорт'}}</button></form></section><aside class="stack"><section class="card"><span class="eyebrow">Доказательность</span><h2>Паспорт ≠ резюме</h2><p>Самоописание хранится отдельно от подтверждённых assessment/FSP/practical evidence.</p></section><section class="card"><span class="eyebrow">Грейд</span><h2>{{grade(workspace.summary.confirmedGrade)}}</h2><p>Официальный грейд меняет только assessment. Достижения ФСП его не назначают.</p></section></aside></div>
        </template>

        <template v-else-if="tab==='skills'">
            <section class="section-head"><span class="eyebrow">Evidence Tree</span><h2>Карта знаний</h2><p>От общей области к конкретным компетенциям. У каждого узла — статус, источник и score.</p></section><section class="card"><div class="legend"><span class="confirmed">● confirmed</span><span class="partial">● partial</span><span class="claimed">● claimed</span><span>● unknown</span></div><div class="skill-tree"><article v-for="n in flatTree" :key="n.id" :class="n.state" :style="{marginLeft:`${Math.min(n.depth,4)*30}px`}"><i></i><div><b>{{n.name}}</b><small>{{n.code}}</small></div><div><span>{{stateNames[n.state]}}</span><strong v-if="n.score!=null">{{Math.round(n.score)}}/100</strong><small>{{n.sourceLabel}}</small></div></article></div></section>
        </template>

        <template v-else-if="tab==='career'">
            <section class="section-head"><span class="eyebrow">Career Compass</span><h2>Выберите профессиональный вектор</h2><p>Compass рекомендует специализацию, но не определяет квалификационный грейд.</p></section><div class="candidate-grid main-grid"><section class="card compass"><article v-for="q in careerQuestions" :key="q.id"><h3>{{q.title}}</h3><div><button v-for="o in q.options" :key="o[0]" :class="{selected:careerAnswers[q.id]===o[1]}" @click="careerAnswers[q.id]=o[1]">{{o[0]}}</button></div></article></section><aside class="stack"><section class="card compass-result"><span class="eyebrow">Рекомендация</span><template v-if="careerResult"><h2>{{careerResult.category}}</h2><strong>{{careerResult.confidence}}%</strong><p>Уверенность по выбранным рабочим ситуациям.</p><button class="primary" @click="saveCareer">Сохранить направление</button></template><template v-else><h2>Нужно 3 ответа</h2><p>Результат рассчитывается сразу.</p></template></section><section v-if="careerHistory.length" class="card"><span class="eyebrow">История</span><div v-for="h in careerHistory" :key="h.createdAt" class="history"><b>{{h.category}}</b><span>{{h.confidence}}% · {{date(h.createdAt)}}</span></div></section></aside></div>
        </template>

        <template v-else-if="tab==='assessment'">
            <section class="section-head"><span class="eyebrow">Assessment</span><h2>Подтверждение заявленного грейда</h2><p>Тест относится к категории. Правильные ответы остаются на сервере, а integrity используется как сигнал для проверки.</p></section><div class="candidate-grid main-grid"><section class="card"><div v-if="!assessmentActive" class="assessment-start"><div class="form-two"><label>Категория<select v-model="assessmentCategory" disabled><option v-for="c in assessment.categories" :key="c" :value="c">{{c}}</option></select><small>Меняется через Career Compass.</small></label><label>Проверяемый грейд<select v-model="assessmentGrade"><option v-for="g in assessment.grades" :key="g" :value="g">{{grade(g)}}</option></select></label></div><div v-if="assessment.gradeChangeLocked" class="notice"><b>Cooldown смены грейда</b><span>До {{date(assessment.cooldownUntil)}} попытка сохраняется как test-retest, но может не менять официальный грейд.</span></div><div class="rule-grid"><span>15 минут</span><span>6 вопросов</span><span>5 ответов минимум</span><span>без GPU/LLM в обязательном пути</span></div><button class="primary large" @click="startAssessment">Начать assessment</button></div><div v-else><div class="assessment-head"><div><span class="eyebrow">{{assessmentCategory}} · {{grade(assessmentGrade)}}</span><h2>Живая сессия</h2></div><strong>{{timeLabel(secondsLeft)}}</strong></div><article v-for="(q,i) in assessment.questionSet" :key="q.id" class="question"><div class="question-meta"><span>Вопрос {{i+1}}</span><span>{{q.skill}}</span><span>сложность {{q.difficulty}}/5</span></div><h3>{{q.prompt}}</h3><div class="options"><button v-for="(o,oi) in q.options" :key="o" :class="{selected:selectedAnswers[String(q.id)]===oi}" @click="selectedAnswers[String(q.id)]=oi"><i>{{String.fromCharCode(65+oi)}}</i>{{o}}</button></div></article><div class="submit-row"><span>Отвечено {{answeredCount}} / {{assessment.questionSet.length}}</span><button class="primary" :disabled="!canSubmit" @click="submitAssessment">Завершить</button></div></div></section><aside class="stack"><section class="card"><span class="eyebrow">Test-retest</span><h2>История попыток</h2><div v-for="r in assessment.history" :key="r.id" class="history score-history"><span><b>{{r.category}} · {{grade(r.desiredGrade)}}</b><small>{{date(r.completedAt)}} · integrity {{Math.round(r.integrityScore)}}</small></span><strong>{{Math.round(r.score)}}</strong></div><p v-if="!assessment.history.length">Попыток ещё нет.</p></section><section class="card"><span class="eyebrow">Банк заданий</span><h2>{{assessment.bankSource==='database'?'DB bank подключён':'Fallback demo'}}</h2><p>На админ-этапе добавим редактор, версии, уникальность вариантов и статистику вопросов.</p></section></aside></div>
        </template>

        <template v-else-if="tab==='fsp'">
            <section class="section-head"><span class="eyebrow">FSP ID</span><h2>Подтверждённые достижения</h2><p>Участия, места, разряды, дисциплины и командные результаты усиливают evidence внутри категории.</p></section><div class="candidate-grid main-grid"><section class="card"><div class="achievements"><article v-for="a in workspace.fspAchievements??[]" :key="a.title+a.date"><div><span :class="a.verified?'ok':'wait'">{{a.verified?'Подтверждено':'Проверка'}}</span><h3>{{a.title}}</h3><p>{{a.discipline}} · {{a.type}}</p></div><strong>{{a.relevance}}%</strong></article><div v-if="!(workspace.fspAchievements??[]).length" class="empty">Достижений пока нет.</div></div></section><aside class="stack"><section class="card"><span class="eyebrow">Правило</span><h2>FSP ID ≠ grade</h2><p>Достижения ранжируют и подтверждают контекст, но официальный грейд определяется только assessment.</p></section><section class="card"><span class="eyebrow">Интеграция</span><h2>OIDC / Keycloak skeleton</h2><p>Оставлена архитектурная точка для будущего FSP ID; demo не требует боевых доступов.</p></section></aside></div>
        </template>

        <template v-else-if="tab==='processes'">
            <section class="section-head"><span class="eyebrow">Process Center</span><h2>Взаимодействия с компаниями</h2><p>Приглашение, несколько ЛПР, задачи, прогресс и история — это один долгий процесс, а не одноразовый отклик.</p></section><div class="process-list"><article v-for="c in workspace.cases??[]" :key="c.id" class="card process"><header><div><span>{{statusNames[c.status]??c.status}}</span><h3>{{c.company}}</h3><p>{{c.job}}</p></div><strong>{{c.progress??0}}%</strong></header><div class="progress"><i :style="{width:`${c.progress??0}%`}"></i></div><div class="process-meta"><span>Ответственный <b>{{c.owner??'—'}}</b></span><span>Следующий шаг <b>{{date(c.nextActionAt)}}</b></span><span>Контакты <b>{{c.contactsUnlockedAt?'открыты':'закрыты'}}</b></span></div><div v-if="c.source==='match_invite'&&c.status==='new'" class="process-actions"><button class="primary" @click="respond(c.id,'accept')">Принять</button><button class="secondary" @click="respond(c.id,'decline')">Отклонить</button></div></article><div v-if="!(workspace.cases??[]).length" class="card empty">Активных процессов пока нет.</div></div>
        </template>
    </main>
</div>
</template>

<style src="../../css/candidate-center.css"></style>
