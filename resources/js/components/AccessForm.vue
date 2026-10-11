<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ mode: 'login' | 'register' }>();

const form = useForm({
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'applicant',
    remember: false,
});

function submit() {
    form.post(props.mode === 'register' ? '/register' : '/login', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="mode === 'register' ? 'Регистрация · ИТ-МЕТЧ' : 'Вход · ИТ-МЕТЧ'" />

    <div class="fsp-public access-shell">
        <header class="public-topbar">
            <div class="public-topbar-inner">
                <Link href="/" class="public-brand platform-brand" aria-label="ИТ-МЕТЧ"><img src="/brand/fsp-mark.svg" alt="ФСП" /><strong>ИТ-МЕТЧ</strong></Link>\n                <Link href="/" class="btn btn-soft">На главную</Link>
            </div>
        </header>

        <main class="access-main">
            <section class="access-copy">
                <div class="eyebrow">Единая платформа ИТ-МЕТЧ</div>
                <h1>{{ mode === 'register' ? 'Создайте профиль и начните путь.' : 'Продолжите работу в ИТ-МЕТЧ.' }}</h1>
                <p>
                    Assessment подтверждает грейд, FSP ID добавляет проверяемые достижения,
                    а объяснимый Match связывает кандидата и работодателя в одном процессе.
                </p>
                <div class="access-points">
                    <span>✓ единый профиль и кабинет</span>
                    <span>✓ проверяемая квалификация</span>
                    <span>✓ адресные приглашения</span>
                </div>
            </section>

            <section class="access-card">
                <div class="eyebrow">{{ mode === 'register' ? 'Новый аккаунт' : 'Вход в платформу' }}</div>
                <h2>{{ mode === 'register' ? 'Регистрация' : 'С возвращением' }}</h2>
                <p class="access-description">
                    {{ mode === 'register' ? 'Заполните данные — это займёт меньше минуты.' : 'Используйте почту и пароль своего профиля.' }}
                </p>

                <form class="access-form" @submit.prevent="submit">
                    <label v-if="mode === 'register'" class="access-field">
                        Имя пользователя
                        <input v-model="form.username" name="username" autocomplete="username" maxlength="50" required />
                        <span class="access-error">{{ form.errors.username }}</span>
                    </label>

                    <label class="access-field">
                        Электронная почта
                        <input v-model="form.email" name="email" type="email" autocomplete="email" maxlength="100" required />
                        <span class="access-error">{{ form.errors.email }}</span>
                    </label>

                    <label v-if="mode === 'register'" class="access-field">
                        Роль
                        <select v-model="form.role" name="role">
                            <option value="applicant">Соискатель</option>
                            <option value="employer">Работодатель</option>
                        </select>
                        <span class="access-error">{{ form.errors.role }}</span>
                    </label>

                    <label class="access-field">
                        Пароль
                        <input
                            v-model="form.password"
                            name="password"
                            type="password"
                            :autocomplete="mode === 'register' ? 'new-password' : 'current-password'"
                            :minlength="mode === 'register' ? 8 : undefined"
                            maxlength="72"
                            required
                        />
                        <span class="access-error">{{ form.errors.password }}</span>
                    </label>

                    <label v-if="mode === 'register'" class="access-field">
                        Повторите пароль
                        <input v-model="form.password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required />
                        <span class="access-error">{{ form.errors.password_confirmation }}</span>
                    </label>

                    <label v-if="mode === 'login'" class="access-checkbox">
                        <input v-model="form.remember" type="checkbox" />
                        Запомнить меня
                    </label>

                    <button class="access-submit" type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Подождите…' : mode === 'register' ? 'Создать профиль' : 'Войти' }}
                    </button>
                </form>

                <p class="access-bottom">
                    {{ mode === 'register' ? 'Уже есть аккаунт?' : 'Ещё нет аккаунта?' }}
                    <Link :href="mode === 'register' ? '/login' : '/register'">
                        {{ mode === 'register' ? 'Войти' : 'Зарегистрироваться' }}
                    </Link>
                </p>
            </section>
        </main>
    </div>
</template>

<style scoped src="../../css/fsp-public.css"></style>
<style scoped src="../../css/access.css"></style>
