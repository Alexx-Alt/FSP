<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

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

// Данные от Laravel
defineProps<{
    jobs: Job[];
    resumes: Resume[];
}>();

// Выбранный режим просмотра
const page = usePage<{
    auth: {
        user: {
            role: 'applicant' | 'employer';
        } | null;
    };
}>();

// Начальный список зависит от аккаунта
const role = ref<'applicant' | 'employer'>(
    page.props.auth.user?.role ?? 'applicant',
);

// Форматирование суммы
function money(value: number): string {
    return new Intl.NumberFormat('ru-RU').format(value);
}

// Зарплата в вакансии
function salary(min: number | null, max: number | null): string {
    if (min !== null && max !== null) {
        return `${money(min)} — ${money(max)} ₽`;
    }

    if (min !== null) return `от ${money(min)} ₽`;
    if (max !== null) return `до ${money(max)} ₽`;

    return 'По договорённости';
}

// Разделение навыков на теги
function skillTags(skills: string | null): string[] {
    return [...new Set(
        (skills ?? '')
            .split(',')
            .map((skill) => skill.trim())
            .filter(Boolean),
    )];
}
</script>

<template>
    <!-- Название вкладки -->
    <Head title="Главная" />

    <div class="main-page fsp-background">
        <!-- Верхняя панель -->
        <header class="header">
            <Link href="/" class="logo">
                <span class="logo-icon">ФСП</span>

                <span class="logo-text">
                    <strong>
                        ИТ-MATCH <span class="logo-pro">PRO</span>
                    </strong>

                    <span class="logo-description">
                        Реестр спортивного программирования
                    </span>
                </span>
            </Link>

            <!-- Основное меню -->
            <nav class="navigation" aria-label="Основное меню">
                <Link href="/" class="navigation-link">
                    Главная
                </Link>

                <button type="button" class="navigation-link">
                    Кадровый реестр
                </button>

                <button type="button" class="navigation-link">
                    Сервисы ФСП
                </button>

                <button type="button" class="navigation-link">
                    Кадровый поиск
                </button>
            </nav>

            <!-- Переход на страницу входа -->
            <Link href="/dashboard" class="account-button">
                    Личный кабинет
            </Link>
        </header>
            <!-- Основной блок -->
<main class="main-content">
    <section class="hero">
        <h1 class="hero-title">
            Единый реестр
            <span>IT-специалистов</span>
        </h1>

        <p class="hero-description">
            Интегрированный кадровый поиск спортивного программирования
            с алгоритмической валидацией навыков, шифрованием 152-ФЗ
            и прямой связью с аккредитованными работодателями.
        </p>

<!-- Выбор роли и профориентация -->
<div class="hero-actions">
    <div class="role-switch">
        <button
            type="button"
            class="role-button"
            :class="{ 'role-active': role === 'applicant' }"
            :aria-pressed="role === 'applicant'"
            @click="role = 'applicant'"
        >
            Я Соискатель
        </button>

        <button
            type="button"
            class="role-button"
            :class="{ 'role-active': role === 'employer' }"
            :aria-pressed="role === 'employer'"
            @click="role = 'employer'"
        >
            Я Работодатель
        </button>
    </div>

    <button type="button" class="orientation-button">
        <span aria-hidden="true">✦</span>
        Пройти профориентацию
    </button>
</div>

<!-- Поиск позиций -->
        <form class="search-panel" @submit.prevent>
            <label for="position-search" class="search-label">
                Поиск позиций
            </label>

            <svg
                class="search-icon"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                aria-hidden="true"
            >
                <circle cx="10.5" cy="10.5" r="6.5" />
                <path d="m16 16 5 5" />
            </svg>

            <input
                id="position-search"
                type="search"
                placeholder="Поиск по стеку, грейду или компании..."
            />

            <button type="submit" class="search-button">
                Найти позиции
            </button>
        </form>

        <!-- Популярные технологии -->
        <div class="popular-stack">
            <span>Популярный стек:</span>
            <button type="button">Laravel 11</button>
            <button type="button">Vue 3</button>
            <button type="button">Python AI</button>
            <button type="button">Golang</button>
        </div>
    </section>
    <!-- Реестр из базы данных -->
<section class="registry">
    <div class="registry-heading">
        <div>
            <span class="registry-caption">
                {{ role === 'applicant' ? 'Открытые позиции' : 'Соискатели' }}
            </span>

            <h2>
                {{
                    role === 'applicant'
                        ? 'Актуальный реестр вакансий'
                        : 'Актуальный реестр резюме'
                }}
            </h2>
        </div>

        <span class="registry-count">
            {{
                role === 'applicant'
                    ? jobs.length
                    : resumes.length
            }} записей
        </span>
    </div>

    <!-- Вакансии для соискателя -->
    <template v-if="role === 'applicant'">
        <div v-if="jobs.length" class="registry-grid">
            <article
                v-for="job in jobs"
                :key="job.job_id"
                class="registry-card"
            >
                <div class="card-top">
                    <span class="card-owner">{{ job.company }}</span>

                    <span class="card-salary">
                        {{ salary(job.salary_min, job.salary_max) }}
                    </span>
                </div>

                <h3>{{ job.title }}</h3>
                <p class="card-description">{{ job.description }}</p>

                <div class="card-skills">
                    <span
                        v-for="skill in skillTags(job.skills)"
                        :key="skill"
                    >
                        {{ skill }}
                    </span>
                </div>

                <div class="card-footer">
                    <span>{{ job.location ?? 'Город не указан' }}</span>
                </div>
            </article>
        </div>

        <p v-else class="registry-empty">
            Вакансий пока нет.
        </p>
    </template>

    <!-- Резюме для работодателя -->
    <template v-else>
        <div v-if="resumes.length" class="registry-grid">
            <article
                v-for="resume in resumes"
                :key="resume.resume_id"
                class="registry-card"
            >
                <div class="card-top">
                    <span class="card-owner">{{ resume.username }}</span>

                    <span class="card-salary">
                        {{
                            resume.salary_expected !== null
                                ? `от ${money(resume.salary_expected)} ₽`
                                : 'По договорённости'
                        }}
                    </span>
                </div>

                <h3>{{ resume.title }}</h3>
                <p class="card-description">{{ resume.description }}</p>

                <div class="card-skills">
                    <span
                        v-for="skill in skillTags(resume.skills)"
                        :key="skill"
                    >
                        {{ skill }}
                    </span>
                </div>

                <div class="card-footer">
                    <span>{{ resume.location ?? 'Город не указан' }}</span>
                    <span>
                        Опыт: {{ resume.experience_years ?? 0 }} г.
                    </span>
                </div>
            </article>
        </div>

        <p v-else class="registry-empty">
            Резюме пока нет.
        </p>
    </template>
</section>
</main>
    </div>
</template>
<style scoped src="../../css/welcome.css"></style>