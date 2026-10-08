<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

type ChoiceQuestion = { kind:'choice'; id:number; skill:string; prompt:string; options:string[]; difficulty:number };
type CodeQuestion = { kind:'code'; id:number; skill:string; title:string; prompt:string; language:string; starterCode?:string|null; difficulty:number };
type Question = ChoiceQuestion | CodeQuestion;
type StoredAnswer = { kind:'choice'|'code'; question_id:number; selected_index?:number; code?:string; response_ms:number };

const props = defineProps<{ session:{ sessionId:number; category:string; desiredGrade:string; variant:string; durationSeconds:number; questions:Question[]; rules:string[] } }>();
const active = ref(false);
const finished = ref(false);
const index = ref(0);
const seconds = ref(props.session.durationSeconds);
const risk = ref(0);
const startedQuestionAt = ref(Date.now());
const answers = reactive<Record<number, StoredAnswer>>({});
const codeDraft = ref('');
const lastCodeInputAt = ref(0);
const lastCodeLength = ref(0);
let timer:ReturnType<typeof setInterval>|null = null;
let bound = false;

const question = computed(() => props.session.questions[index.value]);
const progress = computed(() => Math.round(((index.value + (active.value ? 1 : 0)) / props.session.questions.length) * 100));
const timeLabel = computed(() => `${String(Math.floor(Math.max(0,seconds.value)/60)).padStart(2,'0')}:${String(Math.max(0,seconds.value)%60).padStart(2,'0')}`);
const answered = computed(() => Object.keys(answers).length);
const gradeName = computed(() => ({intern:'Intern',junior:'Junior',middle:'Middle',senior:'Senior',lead:'Lead'} as Record<string,string>)[props.session.desiredGrade] ?? props.session.desiredGrade);

function cookie(name:string){const m=document.cookie.match(new RegExp('(?:^|; )'+name.replace(/[.$?*|{}()\[\]\\/+^]/g,'\\$&')+'=([^;]*)'));return m?decodeURIComponent(m[1]):''}
function headers(){const meta=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content; const h:Record<string,string>={'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'}; if(meta)h['X-CSRF-TOKEN']=meta; const xsrf=cookie('XSRF-TOKEN'); if(xsrf)h['X-XSRF-TOKEN']=xsrf; return h;}
async function signal(type:string,weight=1,metadata:Record<string,unknown>={}){
    risk.value=Math.min(100,risk.value+weight);
    try{await fetch(`/candidate/assessment/live/${props.session.sessionId}/event`,{method:'POST',credentials:'same-origin',keepalive:true,headers:headers(),body:JSON.stringify({type,weight,metadata})});}catch{}
}
function signalBeacon(type:string,weight=1,metadata:Record<string,unknown>={}){
    const token=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if(!token){void signal(type,weight,metadata);return;}
    const form=new FormData();form.append('_token',token);form.append('type',type);form.append('weight',String(weight));form.append('metadata',JSON.stringify(metadata));
    navigator.sendBeacon(`/candidate/assessment/live/${props.session.sessionId}/event`,form);
}

const onVisibility=()=>{if(active.value&&document.hidden)void signal('visibility',4,{hidden:true,index:index.value})};
const onBlur=()=>{if(active.value)void signal('blur',3,{index:index.value})};
const onCopy=(e:ClipboardEvent)=>{if(active.value)void signal('copy',6,{index:index.value,selection:String(window.getSelection()??'').length})};
const onCut=()=>{if(active.value)void signal('cut',6,{index:index.value})};
const onPaste=(e:ClipboardEvent)=>{if(!active.value)return;const text=e.clipboardData?.getData('text')??'';void signal(text.length>40?'paste_large':'paste',text.length>40?8:6,{index:index.value,length:text.length,kind:question.value?.kind});};
const onContext=(e:MouseEvent)=>{if(active.value){e.preventDefault();void signal('contextmenu',2,{index:index.value})}};
const onKey=(e:KeyboardEvent)=>{if(!active.value)return;const k=e.key.toLowerCase();if((e.ctrlKey||e.metaKey)&&['c','v','x','a'].includes(k))void signal('shortcut',5,{key:k,index:index.value});if(e.key==='F12'||((e.ctrlKey||e.metaKey)&&e.shiftKey&&['i','j','c'].includes(k))){e.preventDefault();void signal('devtools_shortcut',8,{key:e.key})}};
const onFullscreen=()=>{if(active.value&&!document.fullscreenElement)void signal('fullscreen_exit',7,{index:index.value})};
const onUnload=()=>{if(active.value&&!finished.value)signalBeacon('page_unload',8,{index:index.value,answered:answered.value})};

function bind(){if(bound)return;bound=true;document.addEventListener('visibilitychange',onVisibility);window.addEventListener('blur',onBlur);document.addEventListener('copy',onCopy);document.addEventListener('cut',onCut);document.addEventListener('paste',onPaste);document.addEventListener('contextmenu',onContext);document.addEventListener('keydown',onKey,true);document.addEventListener('fullscreenchange',onFullscreen);window.addEventListener('beforeunload',onUnload)}
function unbind(){if(!bound)return;bound=false;document.removeEventListener('visibilitychange',onVisibility);window.removeEventListener('blur',onBlur);document.removeEventListener('copy',onCopy);document.removeEventListener('cut',onCut);document.removeEventListener('paste',onPaste);document.removeEventListener('contextmenu',onContext);document.removeEventListener('keydown',onKey,true);document.removeEventListener('fullscreenchange',onFullscreen);window.removeEventListener('beforeunload',onUnload)}

async function start(){
    active.value=true;startedQuestionAt.value=Date.now();bind();void signal('session_started',1,{variant:props.session.variant});
    try{await document.documentElement.requestFullscreen();}catch{void signal('fullscreen_denied',4)}
    timer=setInterval(()=>{seconds.value--;if(seconds.value<=0)void finish(true)},1000);
    prepareQuestion();
}
function prepareQuestion(){startedQuestionAt.value=Date.now();const q=question.value;if(q?.kind==='code'){codeDraft.value=answers[q.id]?.code ?? q.starterCode ?? '';lastCodeLength.value=codeDraft.value.length;lastCodeInputAt.value=Date.now();}}
function selectChoice(i:number){const q=question.value;if(!q||q.kind!=='choice')return;answers[q.id]={kind:'choice',question_id:q.id,selected_index:i,response_ms:Date.now()-startedQuestionAt.value};}
function codeInput(){const now=Date.now();const delta=codeDraft.value.length-lastCodeLength.value;const dt=now-lastCodeInputAt.value;if(delta>70&&dt<700)void signal('rapid_insert',8,{delta,ms:dt,index:index.value});lastCodeLength.value=codeDraft.value.length;lastCodeInputAt.value=now;}
function storeCurrent(){const q=question.value;if(!q)return;const ms=Date.now()-startedQuestionAt.value;if(ms<1200)void signal('rapid_answer',3,{ms,index:index.value});if(q.kind==='choice'){if(!answers[q.id])answers[q.id]={kind:'choice',question_id:q.id,response_ms:ms};else answers[q.id].response_ms=ms;}else{answers[q.id]={kind:'code',question_id:q.id,code:codeDraft.value,response_ms:ms};}}
function next(){storeCurrent();if(index.value>=props.session.questions.length-1){void finish(false);return;}index.value++;prepareQuestion();}
function previous(){storeCurrent();if(index.value<=0)return;index.value--;prepareQuestion();}
async function finish(timeout:boolean){
    if(finished.value)return;storeCurrent();
    if(timeout){for(const q of props.session.questions){if(!answers[q.id])answers[q.id]=q.kind==='choice'?{kind:'choice',question_id:q.id,response_ms:0}:{kind:'code',question_id:q.id,code:'',response_ms:0}}void signal('time_expired',2,{answered:answered.value});}
    finished.value=true;active.value=false;if(timer){clearInterval(timer);timer=null}unbind();
    if(document.fullscreenElement){try{await document.exitFullscreen();}catch{}}
    router.post(`/candidate/assessment/live/${props.session.sessionId}/submit`,{answers:Object.values(answers)},{preserveScroll:false});
}
function leave(){if(active.value&&!confirm('Выход будет зафиксирован как прерывание сессии. Выйти?'))return;if(active.value)signalBeacon('manual_exit',8,{index:index.value});router.visit('/candidate');}
onBeforeUnmount(()=>{if(timer)clearInterval(timer);unbind()});
</script>

<template>
<Head title="Проверка уровня · live" />
<div class="live-shell">
  <header class="live-topbar"><div class="live-topbar-inner"><div class="live-brand"><b>F</b><span><strong>ИТ-MATCH PRO</strong><small>live assessment</small></span></div><div class="live-session"><span>{{session.category}} · {{gradeName}}</span><strong v-if="active">{{timeLabel}}</strong><button @click="leave">Выйти</button></div></div></header>
  <main class="live-container">
    <section v-if="!active&&!finished" class="live-intro card-live"><span class="eyebrow">Проверка уровня · защищённая сессия</span><h1>Один вопрос за раз.<br><em>Сигналы — отдельно от результата.</em></h1><p>Тест подтверждает заявленный грейд. Технические сигналы не считаются автоматическим доказательством списывания: при повышенном риске попытка уходит на ручную проверку.</p><div class="rule-grid-live"><span v-for="r in session.rules" :key="r">{{r}}</span></div><div class="live-start-meta"><span>15 минут</span><span>{{session.questions.length}} заданий</span><span>1 практическая задача</span><span>fullscreen + integrity log</span></div><button class="start-live" @click="start">Начать и перейти в полноэкранный режим</button></section>

    <template v-else-if="active">
      <section class="live-status"><div><span>Задание {{index+1}} / {{session.questions.length}}</span><i><b :style="{width:`${progress}%`}"></b></i></div><div class="risk-chip" :class="{warn:risk>20}"><small>integrity risk</small><strong>{{risk}}</strong></div></section>
      <div class="live-layout">
        <section class="card-live live-question">
          <div class="question-meta-live"><span>{{question.skill}}</span><span>сложность {{question.difficulty}}/5</span><span>{{question.kind==='code'?'практика':'теория'}}</span></div>
          <h2>{{question.prompt}}</h2>
          <div v-if="question.kind==='choice'" class="live-options"><button v-for="(o,i) in question.options" :key="o" :class="{selected:answers[question.id]?.selected_index===i}" @click="selectChoice(i)"><i>{{String.fromCharCode(65+i)}}</i><span>{{o}}</span></button></div>
          <div v-else class="code-task"><div class="code-head"><span>{{question.language}}</span><small>Вставка и резкие большие изменения текста фиксируются как integrity-сигнал.</small></div><textarea v-model="codeDraft" spellcheck="false" @input="codeInput" @paste="codeInput"></textarea><div class="code-note">Решение не исполняется на сервере произвольно: demo проверяет структуру ответа, обязательные элементы и подозрительное совпадение с прошлыми решениями.</div></div>
          <footer class="question-actions"><button class="secondary-live" :disabled="index===0" @click="previous">← Назад</button><span>{{answered}} сохранено</span><button class="primary-live" @click="next">{{index===session.questions.length-1?'Завершить':'Следующий вопрос →'}}</button></footer>
        </section>
        <aside class="card-live integrity-panel"><span class="eyebrow">Live integrity</span><h3>Что фиксируется</h3><div class="integrity-list"><span><b>tab</b> смена/скрытие вкладки</span><span><b>focus</b> потеря фокуса окна</span><span><b>copy/paste</b> буфер обмена</span><span><b>fullscreen</b> выход из режима</span><span><b>rapid</b> слишком быстрый ответ/вставка</span><span><b>similarity</b> совпадение практического ответа</span></div><p>Risk выше 20 не обнуляет тест автоматически — грейд не меняется до ручной проверки.</p></aside>
      </div>
    </template>
    <section v-else class="card-live live-intro"><h1>Сохраняем результат…</h1></section>
  </main>
</div>
</template>

<style scoped src="../../css/assessment-session.css"></style>
