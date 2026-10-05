<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    mode: 'login' | 'register';
}>();

const form = useForm({
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'applicant',
    remember: false,
});

// Отправляем данные в Fortify
function submit() {
    form.post(props.mode === 'register' ? '/register' : '/login', {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
}
</script>

<template>
    <Head :title="mode === 'register' ? 'Регистрация' : 'Вход'" />

    <main class="main-page fsp-background access-page">
        <section class="access-card">
            <Link href="/" class="access-logo">ФСП · ИТ-MATCH PRO</Link>

            <h1>{{ mode === 'register' ? 'Создать аккаунт' : 'С возвращением' }}</h1>

            <p class="access-description">
                {{
                    mode === 'register'
                        ? 'Выбери роль и присоединяйся к платформе.'
                        : 'Войди в свой личный кабинет.'
                }}
            </p>

            <form class="access-form" @submit.prevent="submit">
                <label v-if="mode === 'register'" class="access-field">
                    Имя пользователя
                    <input
                        v-model="form.username"
                        name="username"
                        autocomplete="username"
                        maxlength="50"
                        required
                    />
                    <span class="access-error">{{ form.errors.username }}</span>
                </label>

                <label class="access-field">
                    Электронная почта
                    <input
                        v-model="form.email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        maxlength="100"
                        required
                    />
                    <span class="access-error">{{ form.errors.email }}</span>
                </label>

                <label v-if="mode === 'register'" class="access-field">
                    Твоя роль
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
                        :autocomplete="
                            mode === 'register' ? 'new-password' : 'current-password'
                        "
                        :minlength="mode === 'register' ? 8 : undefined"
                        :maxlength="mode === 'register' ? 72 : undefined"
                        required
                    />
                    <span class="access-error">{{ form.errors.password }}</span>
                </label>

                <label v-if="mode === 'register'" class="access-field">
                    Повтори пароль
                    <input
                        v-model="form.password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                    <span class="access-error">
                        {{ form.errors.password_confirmation }}
                    </span>
                </label>

                <label v-if="mode === 'login'" class="access-checkbox">
                    <input v-model="form.remember" type="checkbox" />
                    Запомнить меня
                </label>

                <button class="access-submit" type="submit" :disabled="form.processing">
                    {{
                        form.processing
                            ? 'Подождите…'
                            : mode === 'register'
                              ? 'Зарегистрироваться'
                              : 'Войти'
                    }}
                </button>
            </form>

            <p class="access-bottom">
                {{ mode === 'register' ? 'Уже есть аккаунт?' : 'Нет аккаунта?' }}
                <Link :href="mode === 'register' ? '/login' : '/register'">
                    {{ mode === 'register' ? 'Войти' : 'Регистрация' }}
                </Link>
            </p>
        </section>
    </main>
</template>

<style scoped src="../../css/welcome.css"></style>
<style scoped src="../../css/access.css"></style>