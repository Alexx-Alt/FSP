<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    auth: { user: any };
    snapshot: Record<string, any>;
    criteria: Array<{ key:string; title:string; status:boolean; weight:number; proof:string }>;
    routes: Array<{ route:string; label:string; note:string }>;
    scenarios: Array<{ title:string; steps:string[] }>;
}>();

const tab = ref<'overview'|'criteria'|'scenario'|'architecture'>('overview');
const passed = computed(() => props.criteria.filter((x) => x.status).length);
const totalWeight = computed(() => props.criteria.reduce((sum, x) => sum + x.weight, 0));
const readiness = computed(() => Number(props.snapshot.readinessScore ?? 0));
const pct = (value:any) => value === null || value === undefined ? '—' : `${Math.round(Number(value))}%`;
const logout = () => router.post('/logout');
</script>

<template>
<Head title="Jury Demo · ИТ-MATCH PRO" />
<div class="jury-shell">
    <header class="topbar jury-topbar">
        <div class="topbar-inner jury-topbar-inner">
            <Link href="/" class="brand"><b>F</b><span><strong>ИТ-MATCH PRO</strong><small>режим демонстрации для жюри</small></span></Link>
            <nav>
                <button :class="{active:tab==='overview'}" @click="tab='overview'">Сводка</button>
                <button :class="{active:tab==='criteria'}" @click="tab='criteria'">Критерии</button>
                <button :class="{active:tab==='scenario'}" @click="tab='scenario'">Сценарий защиты</button>
                <button :class="{active:tab==='architecture'}" @click="tab='architecture'">Архитектура</button>
            </nav>
            <span class="role">Администратор</span>
            <Link href="/workspace" class="jury-ghost">← Workspace</Link>
            <button class="jury-logout" @click="logout">Выйти</button>
        </div>
    </header>

    <main class="jury-container">
        <section class="jury-hero">
            <div>
                <span class="eyebrow">FSP MATCH · защита решения</span>
                <h1>Один экран, чтобы доказать, что система <em>работает</em>, а не просто красиво выглядит.</h1>
                <p>Здесь собраны механика грейда, explainable matching, адресные приглашения, evidence, test-retest, аналитика и воспроизводимый сценарий проверки.</p>
                <div class="jury-hero-actions">
                    <button @click="tab='scenario'">Запустить сценарий защиты</button>
                    <button class="secondary" @click="tab='criteria'">Показать критерии</button>
                </div>
            </div>
            <aside class="jury-score-card">
                <span>Готовность по официальным критериям</span>
                <strong>{{ readiness }}<small>/{{ totalWeight }}</small></strong>
                <div class="jury-ring" :style="{'--score': `${readiness}%`}"><i></i></div>
                <p>{{ passed }} из {{ criteria.length }} контуров подтверждены работающими механизмами и данными проекта.</p>
            </aside>
        </section>

        <template v-if="tab==='overview'">
            <section class="jury-kpis">
                <article><span>Кандидаты</span><b>{{ snapshot.candidates }}</b><small>синтетические + demo</small></article>
                <article><span>Компании</span><b>{{ snapshot.companies }}</b><small>{{ snapshot.openJobs }} открытых вакансий</small></article>
                <article><span>География</span><b>{{ snapshot.cities }}</b><small>городов в аналитике</small></article>
                <article><span>FSP evidence</span><b>{{ snapshot.verifiedFsp }}</b><small>подтверждённых достижений</small></article>
                <article><span>Match F1</span><b>{{ pct(snapshot.matchF1) }}</b><small>{{ snapshot.matchPairs ?? 0 }} размеченных пар</small></article>
                <article><span>Integrity</span><b>{{ snapshot.avgIntegrity ?? '—' }}</b><small>средний показатель</small></article>
                <article><span>Test-retest</span><b>{{ pct(snapshot.gradeStability) }}</b><small>стабильность грейда</small></article>
                <article><span>Процессы</span><b>{{ snapshot.activeCases }}</b><small>{{ snapshot.contactsUnlocked }} с открытыми контактами</small></article>
            </section>

            <div class="jury-grid main">
                <section class="jury-card">
                    <div class="jury-card-head"><div><span class="eyebrow">Ключевая механика</span><h2>Не рейтинг ради рейтинга</h2></div><span class="jury-badge good">Explainable</span></div>
                    <div class="jury-flow">
                        <article><i>01</i><div><b>Кандидат выбирает грейд</b><p>Выбор фиксирует цель, но не становится подтверждённым уровнем.</p></div></article>
                        <article><i>02</i><div><b>Assessment подтверждает</b><p>Вопросы по одному, практическая задача, integrity и test-retest.</p></div></article>
                        <article><i>03</i><div><b>Evidence уточняет профиль</b><p>FSP и дерево навыков повышают доказательность, но не назначают грейд.</p></div></article>
                        <article><i>04</i><div><b>Match объясняет решение</b><p>Категория, грейд, стек, зарплата, формат и gaps видны отдельно.</p></div></article>
                        <article><i>05</i><div><b>Взаимный шаг запускает процесс</b><p>Отклик или приглашение превращаются в кейс с задачами, ЛПР и историей.</p></div></article>
                    </div>
                </section>

                <aside class="jury-stack">
                    <section class="jury-card">
                        <span class="eyebrow">Маршруты Match</span><h2>Четыре понятных результата</h2>
                        <div class="jury-routes">
                            <article v-for="route in routes" :key="route.route" :data-route="route.route"><span></span><div><b>{{ route.label }}</b><p>{{ route.note }}</p></div></article>
                        </div>
                    </section>
                    <section class="jury-card">
                        <span class="eyebrow">No-GPU path</span><h2>Критический путь детерминирован</h2>
                        <p>PHP + MySQL принимают обязательные решения. LLM можно подключать как помощника оператору, но система не зависит от GPU и внешней модели.</p>
                        <div class="jury-tech"><span>Laravel</span><span>Vue</span><span>TypeScript</span><span>MySQL</span><span>Inertia</span></div>
                    </section>
                </aside>
            </div>
        </template>

        <template v-else-if="tab==='criteria'">
            <section class="jury-section-head"><span class="eyebrow">Матрица оценки ФСП · 100%</span><h2>Что жюри может проверить руками</h2><p>Вес каждого блока соответствует официальному ТЗ: 35 / 25 / 10 / 7 / 6 / 7 / 10. Каждый критерий привязан к работающей части системы.</p></section>
            <div class="jury-criteria">
                <article v-for="item in criteria" :key="item.key" :class="{ready:item.status}">
                    <div class="jury-criteria-status"><span>{{ item.status ? '✓' : '!' }}</span><b>{{ item.weight }}%</b></div>
                    <div><h3>{{ item.title }}</h3><p>{{ item.proof }}</p></div>
                    <strong>{{ item.status ? 'ГОТОВО' : 'ТРЕБУЕТ ДАННЫХ' }}</strong>
                </article>
            </div>
            <section class="jury-card jury-validation">
                <div><span class="eyebrow">Воспроизводимая валидация</span><h2>Match + assessment проверяются числами</h2><p>Match-метрики считаются прямо на размеченном synthetic dataset. Тот же расчёт можно повторить командой <code>php artisan fsp:validate</code>.</p></div>
                <div class="jury-validation-metrics">
                    <article><span>Match pairs</span><b>{{ snapshot.matchPairs ?? 0 }}</b></article>
                    <article><span>Precision</span><b>{{ pct(snapshot.matchPrecision) }}</b></article>
                    <article><span>Recall</span><b>{{ pct(snapshot.matchRecall) }}</b></article>
                    <article class="accent"><span>Match F1</span><b>{{ pct(snapshot.matchF1) }}</b></article>
                    <article><span>Score separation</span><b>{{ snapshot.scoreSeparation ?? '—' }}</b></article>
                    <article><span>Retest pairs</span><b>{{ snapshot.retestPairs }}</b></article>
                    <article><span>Средний Δ score</span><b>{{ snapshot.avgRetestDelta ?? '—' }}</b></article>
                    <article><span>Стабильность грейда</span><b>{{ pct(snapshot.gradeStability) }}</b></article>
                    <article><span>Конверсия взаимного шага</span><b>{{ pct(snapshot.caseConversion) }}</b></article>
                </div>
            </section>
        </template>

        <template v-else-if="tab==='scenario'">
            <section class="jury-section-head"><span class="eyebrow">Сценарий защиты</span><h2>Показываем систему за 7–10 минут</h2><p>Порядок выстроен по весу критериев: сначала новая механика подбора и тестирование, затем процесс, валидация и архитектура.</p></section>
            <div class="jury-scenarios">
                <article v-for="(scenario,index) in scenarios" :key="scenario.title">
                    <span class="jury-step">{{ String(index+1).padStart(2,'0') }}</span>
                    <div><h3>{{ scenario.title }}</h3><ol><li v-for="step in scenario.steps" :key="step">{{ step }}</li></ol></div>
                    <div class="jury-scenario-link">
                        <Link v-if="index===0" href="/candidate">Кабинет кандидата →</Link>
                        <Link v-else-if="index===1" href="/employer/jobs">Vacancy Studio →</Link>
                        <Link v-else-if="index===2" href="/workspace">Process Center →</Link>
                        <button v-else @click="tab='criteria'">Критерии →</button>
                    </div>
                </article>
            </div>
            <section class="jury-card jury-defense-note">
                <span class="eyebrow">Что проговорить</span>
                <h2>Главное отличие от job board</h2>
                <p>Мы не пытаемся назвать «лучшего программиста вообще». Мы доказываем, почему конкретный человек подходит под конкретную потребность, какие сигналы уже подтверждены, чего не хватает и какой следующий шаг должен сделать работодатель или кандидат.</p>
            </section>
        </template>

        <template v-else>
            <section class="jury-section-head"><span class="eyebrow">Архитектура</span><h2>Прозрачный контур без скрытой магии</h2><p>Каждый слой можно тестировать независимо, а критический путь остаётся доступным без GPU.</p></section>
            <div class="jury-architecture">
                <article><span>01</span><h3>Identity / FSP ID</h3><p>Mock-адаптер достижений, privacy/consent и готовая точка расширения для Keycloak/FSP ID.</p><div><b>users</b><b>fsp_achievements</b></div></article>
                <article><span>02</span><h3>Qualification</h3><p>Банк вопросов, live-session, практическое задание, integrity и test-retest.</p><div><b>assessment_*</b><b>evidence</b></div></article>
                <article><span>03</span><h3>Knowledge graph</h3><p>Иерархия компетенций, evidence-состояния, gaps и интерактивный Obsidian-like граф.</p><div><b>skill_nodes</b><b>candidate_skill_evidence</b></div></article>
                <article><span>04</span><h3>Match Engine</h3><p>Детерминированное сравнение категории, грейда, стека, зарплаты и формата + reasons/gaps.</p><div><b>PHP service</b><b>validation_pairs</b></div></article>
                <article><span>05</span><h3>Process Center</h3><p>Отклик, приглашение, ЛПР, задачи, комментарии, SLA, события и возвраты.</p><div><b>interaction_cases</b><b>case_events</b></div></article>
                <article><span>06</span><h3>Analytics / API</h3><p>Люди, компании, география, OpenAPI и воспроизводимый Docker-контур.</p><div><b>benchmarks</b><b>OpenAPI</b><b>Docker</b></div></article>
            </div>
        </template>
    </main>
</div>
</template>
