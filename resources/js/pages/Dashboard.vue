<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface User {
    user_id: number;
    username: string;
    email: string;
    role: 'applicant' | 'employer';
}

defineProps<{
    auth: {
        user: User;
    };
}>();

const exiting = ref(false);

// Завершаем сессию на сервере
function logout() {
    if (exiting.value) return;

    exiting.value = true;

    router.post('/logout', {}, {
        onFinish: () => {
            exiting.value = false;
        },
    });
}
</script>

<template>
    <Head title="Личный кабинет" />

    <main class="main-page fsp-background access-page">
        <section class="access-card">
            <Link href="/" class="access-logo">ФСП · ИТ-MATCH PRO</Link>

            <h1>Привет, {{ auth.user.username }}!</h1>

            <p class="access-description">Это твой личный кабинет.</p>

            <dl class="account-details">
                <div>
                    <dt>Имя пользователя</dt>
                    <dd>{{ auth.user.username }}</dd>
                </div>

                <div>
                    <dt>Почта</dt>
                    <dd>{{ auth.user.email }}</dd>
                </div>

                <div>
                    <dt>Роль</dt>
                    <dd>
                        {{ auth.user.role === 'employer' ? 'Работодатель' : 'Соискатель' }}
                    </dd>
                </div>
            </dl>

            <Link href="/" class="access-submit access-link">
                {{
                    auth.user.role === 'employer'
                        ? 'Перейти к поиску специалистов'
                        : 'Перейти к поиску вакансий'
                }}
            </Link>

            <button
                class="access-exit"
                type="button"
                :disabled="exiting"
                @click="logout"
            >
                {{ exiting ? 'Выходим…' : 'Выйти из аккаунта' }}
            </button>
        </section>
    </main>
</template>

<style scoped src="../../css/welcome.css"></style>
<style scoped src="../../css/access.css"></style>