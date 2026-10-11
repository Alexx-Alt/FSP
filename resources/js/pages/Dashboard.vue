<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AccountSummary from '@/components/AccountSummary.vue';

interface User {
    user_id: number;
    username: string;
    email: string;
    role: 'applicant' | 'employer';
    headline: string | null;
    city: string | null;
    bio: string | null;
    skills: string | null;
    company: string | null;
    created_at: string | null;
}

const props = defineProps<{
    auth: {
        user: User;
    };
}>();

const saved = ref(false);
const exiting = ref(false);

const form = useForm({
    headline: props.auth.user.headline ?? '',
    city: props.auth.user.city ?? '',
    bio: props.auth.user.bio ?? '',
    skills: props.auth.user.skills ?? '',
    company: props.auth.user.company ?? '',
});

// Сохраняем дополнительные данные
function saveProfile() {
    saved.value = false;

    form.patch('/dashboard/profile', {
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true;
        },
    });
}

function logout() {
    if (exiting.value) return;

    exiting.value = true;

    router.post('/logout', {}, {
        onFinish: () => {
            exiting.value = false;
        },
    });
}

function registrationDate(value: string | null): string {
    if (!value) return 'Не указана';

    // Поддерживаем дату из MySQL и ISO
    const date = new Date(value.replace(' ', 'T'));

    return Number.isNaN(date.getTime())
        ? 'Не указана'
        : date.toLocaleDateString('ru-RU');
}
</script>

<template>
    <Head title="Личный кабинет" />

    <div class="main-page fsp-background">
        <header class="header">
            <Link href="/" class="logo platform-brand" aria-label="ИТ-МЕТЧ"><img src="/brand/fsp-mark.svg" alt="ФСП" /><strong>ИТ-МЕТЧ</strong></Link>\n        <Link href="/" class="navigation-link">На главную</Link>

            <button
                class="account-button"
                type="button"
                :disabled="exiting"
                @click="logout"
            >
                {{ exiting ? 'Выходим…' : 'Выйти' }}
            </button>
        </header>

        <main class="main-content">
            <div class="cabinet-heading">
                <h1>Личный кабинет</h1>
                <p>
                    {{
                        auth.user.role === 'employer'
                            ? 'Расскажи о себе и своей компании.'
                            : 'Расскажи о своей специализации и навыках.'
                    }}
                </p>
            </div>

            <div class="cabinet-grid">
                <section class="cabinet-panel">
                    <h2>Данные профиля</h2>

                    <dl class="cabinet-account">
                        <div>
                            <dt>Имя пользователя</dt>
                            <dd>{{ auth.user.username }}</dd>
                        </div>

                        <div>
                            <dt>Почта</dt>
                            <dd>{{ auth.user.email }}</dd>
                        </div>

                        <div>
                            <dt>Дата регистрации</dt>
                            <dd>{{ registrationDate(auth.user.created_at) }}</dd>
                        </div>
                    </dl>

                    <form class="access-form" @submit.prevent="saveProfile">
                        <label class="access-field">
                            Специализация или должность
                            <input v-model="form.headline" maxlength="150" />
                            <span class="access-error">{{ form.errors.headline }}</span>
                        </label>

                        <label class="access-field">
                            Город
                            <input v-model="form.city" maxlength="100" />
                            <span class="access-error">{{ form.errors.city }}</span>
                        </label>

                        <label
                            v-if="auth.user.role === 'employer'"
                            class="access-field"
                        >
                            Название компании
                            <input v-model="form.company" maxlength="100" />
                            <span class="access-error">{{ form.errors.company }}</span>
                        </label>

                        <label class="access-field">
                            {{ auth.user.role === 'employer' ? 'О компании и себе' : 'О себе' }}
                            <textarea
                                v-model="form.bio"
                                rows="5"
                                maxlength="3000"
                            ></textarea>
                            <span class="access-error">{{ form.errors.bio }}</span>
                        </label>

                        <label class="access-field">
                            Технологии через запятую
                            <input
                                v-model="form.skills"
                                maxlength="255"
                                placeholder="PHP, Laravel, Vue, MySQL"
                            />
                            <span class="access-error">{{ form.errors.skills }}</span>
                        </label>

                        <button
                            type="submit"
                            class="access-submit"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Сохраняем…' : 'Сохранить профиль' }}
                        </button>

                        <p v-if="saved && !form.isDirty" class="cabinet-success" role="status">
                            Профиль сохранён.
                        </p>
                    </form>
                </section>

                <aside class="cabinet-sidebar">
                    <AccountSummary />

                    <section class="cabinet-panel">
                        <h2>
                            {{ auth.user.role === 'employer' ? 'Мои вакансии' : 'Мои резюме' }}
                        </h2>

                        <p class="cabinet-note">
                            {{
                                auth.user.role === 'employer'
                                    ? 'Здесь будут инструменты создания и управления вакансиями.'
                                    : 'Здесь будут инструменты создания и управления резюме.'
                            }}
                        </p>

                        <Link href="/" class="access-submit">
                            {{
                                auth.user.role === 'employer'
                                    ? 'Найти специалистов'
                                    : 'Найти вакансии'
                            }}
                        </Link>
                    </section>
                </aside>
            </div>
        </main>
    </div>
</template>

<style scoped src="../../css/welcome.css"></style>
<style scoped src="../../css/access.css"></style>

<style scoped>
/* Заголовок кабинета */
.cabinet-heading {
    margin-bottom: 30px;
}

.cabinet-heading h1 {
    margin: 0 0 10px;
    font-size: clamp(28px, 4vw, 42px);
    font-weight: 800;
}

.cabinet-heading p,
.cabinet-note {
    color: #94a3b8;
    font-size: 14px;
    line-height: 1.7;
}

.cabinet-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(320px, 1fr);
    gap: 28px;
    align-items: start;
}

.cabinet-panel {
    min-width: 0;
    padding: 28px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 22px;
    background: rgba(12, 16, 35, 0.92);
}

.cabinet-panel h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
}

.cabinet-account {
    display: grid;
    gap: 16px;
    margin: 24px 0;
}

.cabinet-account dt {
    color: #94a3b8;
    font-size: 12px;
}

.cabinet-account dd {
    margin: 6px 0 0;
    overflow-wrap: anywhere;
}

.cabinet-sidebar {
    display: grid;
    min-width: 0;
    gap: 24px;
}

/* Большое поле описания */
.access-field textarea {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 12px;
    background: #11182c;
    color: white;
    font: inherit;
    line-height: 1.6;
    resize: vertical;
}

.access-field textarea:focus-visible {
    outline: 2px solid #a78bfa;
    outline-offset: 2px;
}

.cabinet-success {
    margin: 0;
    color: #6ee7b7;
    font-size: 13px;
}

.account-button {
    border: none;
    cursor: pointer;
}

@media (max-width: 1000px) {
    .cabinet-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 600px) {
    .cabinet-panel {
        padding: 20px;
    }
}
</style>