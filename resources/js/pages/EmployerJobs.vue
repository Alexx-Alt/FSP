<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    auth:{user:any};
    company:{id:number|null;name:string;city:string|null;quality:number|null};
    jobs:Array<any>;
    categories:string[];
    grades:string[];
}>();

const editing = ref<any|null>(null);
const query = ref('');
const statusFilter = ref('all');
const gradeNames:Record<string,string>={intern:'Intern',junior:'Junior',middle:'Middle',senior:'Senior',lead:'Lead'};
const formatNames:Record<string,string>={office:'Офис',hybrid:'Гибрид',remote:'Удалённо',any:'Любой'};
const statusNames:Record<string,string>={draft:'Черновик',open:'Открыта',paused:'Пауза',closed:'Закрыта'};
const money=(v:number)=>new Intl.NumberFormat('ru-RU').format(v||0)+' ₽';

const form=useForm({title:'',description:'',location:props.company.city??'Москва',salary_min:150000,salary_max:250000,skills:'PHP, Laravel, PostgreSQL',category:'Backend',grade_required:'middle',work_format:'hybrid',status:'open'});
const filtered=computed(()=>props.jobs.filter(job=>{
    const q=query.value.trim().toLocaleLowerCase('ru-RU');
    const searchable=`${job.title} ${job.location} ${job.skills} ${job.category}`.toLocaleLowerCase('ru-RU');
    return (!q||searchable.includes(q))&&(statusFilter.value==='all'||job.status===statusFilter.value);
}));
const openCount=computed(()=>props.jobs.filter(x=>x.status==='open').length);
const responseCount=computed(()=>props.jobs.reduce((sum,x)=>sum+Number(x.applications??0),0));
const inviteCount=computed(()=>props.jobs.reduce((sum,x)=>sum+Number(x.invites??0),0));

function reset(){editing.value=null;form.reset();form.location=props.company.city??'Москва';form.salary_min=150000;form.salary_max=250000;form.skills='PHP, Laravel, PostgreSQL';form.category='Backend';form.grade_required='middle';form.work_format='hybrid';form.status='open';form.clearErrors();}
function edit(job:any){editing.value=job;form.title=job.title;form.description=job.description;form.location=job.location??'';form.salary_min=job.salaryMin;form.salary_max=job.salaryMax;form.skills=job.skills??'';form.category=job.category;form.grade_required=job.grade;form.work_format=job.format;form.status=job.status;window.scrollTo({top:0,behavior:'smooth'});}
function submit(){if(editing.value){form.patch(`/employer/jobs/${editing.value.id}`,{preserveScroll:true,onSuccess:reset});}else{form.post('/employer/jobs',{preserveScroll:true,onSuccess:reset});}}
function setStatus(job:any,status:string){router.patch(`/employer/jobs/${job.id}/status`,{status},{preserveScroll:true});}
function remove(job:any){if(!window.confirm(`Удалить/закрыть вакансию «${job.title}»?`))return;router.delete(`/employer/jobs/${job.id}`,{preserveScroll:true});}
function logout(){router.post('/logout');}
</script>

<template>
<Head title="Вакансии работодателя" />
<div class="employer-jobs-shell">
<header class="employer-jobs-top"><div class="employer-jobs-top-inner">
    <Link href="/" class="candidate-brand"><span>F</span><div><strong>ИТ-MATCH PRO</strong><small>Employer Studio</small></div></Link>
    <nav><Link href="/workspace">Подбор</Link><Link href="/employer/jobs" class="active">Вакансии</Link><Link href="/workspace">Процессы</Link><Link href="/workspace">Аналитика</Link></nav>
    <div class="employer-jobs-actions"><span>{{company.name}}</span><button @click="logout">Выйти</button></div>
</div></header>

<main class="employer-jobs-container">
    <section class="employer-jobs-hero">
        <div><span class="eyebrow">Описание потребности → подбор → приглашение</span><h1>Управляйте вакансиями как <em>входом в Match Engine</em>.</h1><p>Стек, категория, грейд, зарплата и формат используются в объяснимом подборе. Зарплатный диапазон обязателен и виден кандидату до контакта.</p></div>
        <div class="employer-jobs-kpis"><article><span>Открыто</span><b>{{openCount}}</b></article><article><span>Отклики</span><b>{{responseCount}}</b></article><article><span>Приглашения</span><b>{{inviteCount}}</b></article><article><span>Hiring quality</span><b>{{company.quality??'—'}}</b></article></div>
    </section>

    <div class="employer-jobs-layout">
        <section class="employer-job-editor">
            <div class="employer-job-editor-head"><div><span class="eyebrow">{{editing?'Редактирование':'Новая потребность'}}</span><h2>{{editing?editing.title:'Создать вакансию'}}</h2></div><button v-if="editing" @click="reset">Отмена</button></div>
            <form @submit.prevent="submit">
                <label class="wide"><span>Название роли</span><input v-model="form.title" placeholder="Senior Backend Developer"/><small v-if="form.errors.title">{{form.errors.title}}</small></label>
                <label class="wide"><span>Что предстоит делать</span><textarea v-model="form.description" rows="5" placeholder="Команда, продукт, зона ответственности, ожидаемый результат…"></textarea><small v-if="form.errors.description">{{form.errors.description}}</small></label>
                <label><span>Категория</span><select v-model="form.category"><option v-for="x in categories" :key="x">{{x}}</option></select></label>
                <label><span>Грейд</span><select v-model="form.grade_required"><option v-for="x in grades" :key="x" :value="x">{{gradeNames[x]??x}}</option></select></label>
                <label><span>Город</span><input v-model="form.location" placeholder="Москва"/></label>
                <label><span>Формат</span><select v-model="form.work_format"><option value="office">Офис</option><option value="hybrid">Гибрид</option><option value="remote">Удалённо</option><option value="any">Любой</option></select></label>
                <label><span>Зарплата от, ₽/мес.</span><input v-model.number="form.salary_min" type="number" min="1" step="10000"/></label>
                <label><span>Зарплата до, ₽/мес.</span><input v-model.number="form.salary_max" type="number" min="1" step="10000"/></label>
                <label class="wide"><span>Обязательный стек</span><input v-model="form.skills" placeholder="PHP, Laravel, PostgreSQL, Redis"/><small>Через запятую. Именно этот стек участвует в покрытии Match.</small></label>
                <label><span>Публикация</span><select v-model="form.status"><option value="open">Открыть сразу</option><option value="draft">Черновик</option><option value="paused">Пауза</option><option value="closed">Закрыта</option></select></label>
                <div class="employer-job-form-note"><b>Проверка перед публикацией</b><span>✓ зарплата указана</span><span>✓ категория и грейд заданы</span><span>✓ стек участвует в explainable match</span></div>
                <button class="employer-job-submit" :disabled="form.processing">{{form.processing?'Сохраняем…':editing?'Сохранить изменения':'Создать вакансию'}}</button>
            </form>
        </section>

        <section class="employer-job-list">
            <div class="employer-job-list-head"><div><span class="eyebrow">Ваши вакансии</span><h2>{{filtered.length}} из {{jobs.length}}</h2></div><div><input v-model="query" type="search" placeholder="Поиск…"/><select v-model="statusFilter"><option value="all">Все статусы</option><option value="open">Открытые</option><option value="draft">Черновики</option><option value="paused">Пауза</option><option value="closed">Закрытые</option></select></div></div>
            <article v-for="job in filtered" :key="job.id" class="employer-job-card" :data-status="job.status">
                <header><div><span class="job-status">{{statusNames[job.status]}}</span><h3>{{job.title}}</h3><p>{{job.category}} · {{gradeNames[job.grade]??job.grade}} · {{job.location}} · {{formatNames[job.format]}}</p></div><strong>{{money(job.salaryMin)}} — {{money(job.salaryMax)}}</strong></header>
                <p class="job-description">{{job.description}}</p>
                <div class="job-skills"><span v-for="skill in String(job.skills??'').split(',').filter(Boolean)" :key="skill">{{skill.trim()}}</span></div>
                <div class="job-signals"><span><b>{{job.applications}}</b> откликов</span><span><b>{{job.invites}}</b> приглашений</span><span>создана {{new Date(job.createdAt).toLocaleDateString('ru-RU')}}</span></div>
                <footer><button @click="edit(job)">Редактировать</button><button v-if="job.status!=='open'" @click="setStatus(job,'open')">Открыть</button><button v-if="job.status==='open'" @click="setStatus(job,'paused')">Пауза</button><Link href="/workspace">Посмотреть подбор →</Link><button class="danger" @click="remove(job)">{{job.applications||job.invites?'Закрыть':'Удалить'}}</button></footer>
            </article>
            <div v-if="!filtered.length" class="employer-job-empty"><b>Ничего не найдено</b><p>Сбросьте фильтр или создайте новую потребность слева.</p></div>
        </section>
    </div>
</main>
</div>
</template>
