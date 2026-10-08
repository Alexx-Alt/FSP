<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Job {
    job_id: number;
    company: string;
    title: string;
    description: string;
    location: string | null;
    salary_min: number | null;
    salary_max: number | null;
    skills: string | null;
}

interface Resume {
    resume_id: number;
    username: string;
    title: string;
    description: string;
    location: string | null;
    salary_expected: number | null;
    skills: string | null;
    experience_years: number | null;
}

const props = defineProps<{ jobs: Job[]; resumes: Resume[] }>();
const page = usePage<{ auth: { user: { role: 'applicant' | 'employer' | 'admin'; username?: string } | null } }>();

const isLoggedIn = computed(() => Boolean(page.props.auth.user));
const guestRole = ref<'applicant' | 'employer'>('applicant');
const search = ref('');

const role = computed<'applicant' | 'employer'>({
    get: () => page.props.auth.user?.role === 'employer' ? 'employer' : guestRole.value,
    set: (value) => {
        if (!isLoggedIn.value) guestRole.value = value;
    },
});

function money(value: number): string {
    return new Intl.NumberFormat('ru-RU').format(value);
}

function salary(min: number | null, max: number | null): string {
    if (min !== null && max !== null) return `${money(min)} — ${money(max)} ₽`;
    if (min !== null) return `от ${money(min)} ₽`;
    if (max !== null) return `до ${money(max)} ₽`;
    return 'По договорённости';
}

function skillTags(skills: string | null): string[] {
    return [...new Set((skills ?? '').split(',').map((x) => x.trim()).filter(Boolean))].slice(0, 6);
}

const normalizedSearch = computed(() => search.value.trim().toLowerCase());
const filteredJobs = computed(() => {
    if (!normalizedSearch.value) return props.jobs;
    return props.jobs.filter((job) => [job.title, job.company, job.location, job.skills].filter(Boolean).join(' ').toLowerCase().includes(normalizedSearch.value));
});
const filteredResumes = computed(() => {
    if (!normalizedSearch.value) return props.resumes;
    return props.resumes.filter((resume) => [resume.username, resume.title, resume.location, resume.skills].filter(Boolean).join(' ').toLowerCase().includes(normalizedSearch.value));
});
</script>

<template>
    <Head title="FSP Match" />

    <div class="fsp-public">
        <header class="public-topbar">
            <div class="public-topbar-inner">
                <Link href="/" class="public-brand">
                    <span class="public-mark">F</span>
                    <span>
                        <strong>ИТ-MATCH PRO</strong>
                        <small>Федерация спортивного программирования</small>
                    </span>
                </Link>

                <nav class="public-nav" aria-label="Основная навигация">
                    <a href="#about">Как работает</a>
                    <a href="#journey">Кандидату</a>
                    <a href="#registry">Рынок</a>
                    <Link v-if="isLoggedIn" href="/workspace">Рабочее пространство</Link>
                </nav>

                <div class="public-actions">
                    <Link v-if="!isLoggedIn" href="/login" class="btn btn-soft">Войти</Link>
                    <Link :href="isLoggedIn ? '/workspace' : '/register'" class="btn btn-primary">
                        {{ isLoggedIn ? 'Открыть кабинет' : 'Создать профиль' }}
                    </Link>
                </div>
            </div>
        </header>

        <main class="public-main">
            <div class="public-container">
                <section class="hero-calm">
                    <div class="hero-copy">
                        <div class="eyebrow">FSP Match · прямой IT-подбор</div>
                        <h1>Найм по проверяемым<br><em>сигналам.</em></h1>
                        <p class="lead">
                            Кандидат выбирает направление и заявляет грейд. Assessment его подтверждает.
                            FSP ID добавляет доказательства достижений, а работодатель получает объяснимый
                            подбор с понятным следующим действием.
                        </p>
                        <div class="hero-buttons">
                            <Link :href="isLoggedIn ? '/workspace' : '/register'" class="btn btn-primary">
                                {{ isLoggedIn ? 'Перейти в workspace' : 'Создать профиль' }}
                            </Link>
                            <a href="#about" class="btn btn-soft">Как это работает</a>
                        </div>
                        <div class="hero-trust">
                            <span>✓ грейд — только assessment</span>
                            <span>✓ FSP — evidence внутри категории</span>
                            <span>✓ контакт — после взаимного шага</span>
                        </div>
                    </div>

                    <aside class="preview-card">
                        <div class="eyebrow">Один экран работодателя</div>
                        <div class="preview-title">
                            <strong>Backend · Senior</strong>
                            <span class="badge">3 кандидата</span>
                        </div>
                        <div class="preview-row">
                            <div><b>Анна Петрова</b><span>можно приглашать</span></div><strong>92</strong>
                        </div>
                        <div class="preview-row">
                            <div><b>Даниил Власов</b><span>сначала уточнить</span></div><strong>77</strong>
                        </div>
                        <div class="preview-row">
                            <div><b>Илья Морозов</b><span>другая категория</span></div><strong>—</strong>
                        </div>
                        <div class="preview-note">
                            Не «кто лучший вообще», а кто подходит под конкретную потребность и почему.
                        </div>
                    </aside>
                </section>

                <section id="about" class="section section-tight">
                    <div class="section-head">
                        <div>
                            <div class="eyebrow">В чём разница</div>
                            <h2>Не витрина вакансий, а рабочая связка.</h2>
                            <p>Профиль, assessment, evidence, объяснимый fit и история взаимодействий соединены в один процесс.</p>
                        </div>
                    </div>
                    <div class="comparison">
                        <div class="comparison-row"><div></div><div>FSP Match</div><div>Обычный job board</div></div>
                        <div class="comparison-row"><div>Квалификация</div><div>assessment + статус грейда</div><div>резюме и самоописание</div></div>
                        <div class="comparison-row"><div>Подбор</div><div>категория → fit → evidence → действие</div><div>поиск и фильтры</div></div>
                        <div class="comparison-row"><div>Контакт</div><div>работодатель приглашает первым</div><div>кандидат начинает с отклика</div></div>
                        <div class="comparison-row"><div>FSP ID</div><div>подтверждённый слой evidence</div><div>обычно не часть модели</div></div>
                    </div>
                </section>

                <section id="journey" class="section">
                    <div class="section-head"><div><div class="eyebrow">Путь кандидата</div><h2>Четыре понятных шага.</h2></div></div>
                    <div class="journey-grid">
                        <article class="journey-card"><div class="journey-num">01</div><div class="eyebrow">Ориентация</div><h3>Career Compass</h3><p>Понять направление, в котором хочется развиваться.</p><span class="text-link">Категория и профиль →</span></article>
                        <article class="journey-card"><div class="journey-num">02</div><div class="eyebrow">Квалификация</div><h3>Assessment</h3><p>Самостоятельно выбрать грейд и подтвердить его тестом.</p><span class="text-link">Проверка уровня →</span></article>
                        <article class="journey-card"><div class="journey-num">03</div><div class="eyebrow">Доказательства</div><h3>FSP ID</h3><p>Подключить участия, места, дисциплины и командные результаты.</p><span class="text-link">Evidence layer →</span></article>
                        <article class="journey-card"><div class="journey-num">04</div><div class="eyebrow">Контакт</div><h3>Адресное приглашение</h3><p>Работодатель приглашает, кандидат принимает — после этого открываются контакты.</p><span class="text-link">Process Center →</span></article>
                    </div>
                </section>

                <section id="registry" class="section">
                    <div class="section-head">
                        <div>
                            <div class="eyebrow">Прямой рынок</div>
                            <h2>{{ role === 'applicant' ? 'Вакансии внутри FSP Match.' : 'Кандидаты внутри FSP Match.' }}</h2>
                            <p>Собственный контур платформы без импорта чужих вакансий.</p>
                        </div>
                        <span class="badge">{{ role === 'applicant' ? filteredJobs.length : filteredResumes.length }} записей</span>
                    </div>

                    <div class="registry-tools">
                        <div v-if="!isLoggedIn" class="role-switch">
                            <button :class="{ active: role === 'applicant' }" @click="role='applicant'">Я кандидат</button>
                            <button :class="{ active: role === 'employer' }" @click="role='employer'">Я работодатель</button>
                        </div>
                        <label class="search"><input v-model="search" type="search" placeholder="Поиск по стеку, грейду, компании или городу..." /></label>
                    </div>

                    <div v-if="role === 'applicant' && filteredJobs.length" class="job-grid">
                        <article v-for="job in filteredJobs" :key="job.job_id" class="job-card">
                            <div class="job-card-top"><span class="company-pill">{{ job.company }}</span><span class="salary">{{ salary(job.salary_min, job.salary_max) }}</span></div>
                            <h3>{{ job.title }}</h3><p>{{ job.description }}</p>
                            <div class="skills"><span v-for="skill in skillTags(job.skills)" :key="skill">{{ skill }}</span></div>
                            <div class="job-footer"><span>{{ job.location ?? 'Город не указан' }}</span><span>Explainable fit →</span></div>
                        </article>
                    </div>
                    <div v-else-if="role === 'employer' && filteredResumes.length" class="job-grid">
                        <article v-for="resume in filteredResumes" :key="resume.resume_id" class="job-card">
                            <div class="job-card-top"><span class="company-pill">{{ resume.username }}</span><span class="salary">{{ resume.salary_expected ? `от ${money(resume.salary_expected)} ₽` : 'По договорённости' }}</span></div>
                            <h3>{{ resume.title }}</h3><p>{{ resume.description }}</p>
                            <div class="skills"><span v-for="skill in skillTags(resume.skills)" :key="skill">{{ skill }}</span></div>
                            <div class="job-footer"><span>{{ resume.location ?? 'Город не указан' }}</span><span>Опыт {{ resume.experience_years ?? 0 }} г.</span></div>
                        </article>
                    </div>
                    <div v-else class="empty">По этому запросу пока ничего не найдено.</div>
                </section>

                <section class="section">
                    <div class="cta-panel">
                        <div><div class="eyebrow">Demo</div><h2>От профиля до контакта — одним процессом.</h2><p>Категория → assessment → evidence → Match → сравнение → приглашение → задачи, комментарии, прогресс и история.</p></div>
                        <Link :href="isLoggedIn ? '/workspace' : '/login'" class="btn btn-primary">Запустить demo →</Link>
                    </div>
                </section>
            </div>
        </main>

        <footer class="public-footer"><div class="public-container footer-inner"><span>FSP · ИТ-MATCH PRO</span><div class="footer-links"><span>Explainable Match</span><span>Evidence Tree</span><span>3-срезная аналитика</span></div></div></footer>
    </div>
</template>

<style scoped src="../../css/fsp-public.css"></style>
