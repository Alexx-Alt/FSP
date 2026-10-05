<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface AccountUser {
    username: string;
    role: 'applicant' | 'employer';
    headline: string | null;
    city: string | null;
    bio: string | null;
    skills: string | null;
    company: string | null;
}

const page = usePage<{
    auth: {
        user: AccountUser | null;
    };
    accountStats: {
        publications: number;
    } | null;
}>();

const user = computed(() => page.props.auth.user);

// Заполненность дополнительных данных
const completion = computed(() => {
    if (!user.value) return 0;

    const fields = [
        user.value.headline,
        user.value.city,
        user.value.bio,
        user.value.skills,
    ];

    if (user.value.role === 'employer') {
        fields.push(user.value.company);
    }

    const filled = fields.filter((value) => value?.trim()).length;

    return Math.round((filled / fields.length) * 100);
});

const tags = computed(() => {
    return [...new Set(
        (user.value?.skills ?? '')
            .split(',')
            .map((skill) => skill.trim())
            .filter(Boolean),
    )];
});
</script>

<template>
    <section v-if="user" class="summary-card">
        <div class="summary-top">
            <span class="summary-caption">Личный профиль</span>

            <span class="summary-role">
                {{ user.role === 'employer' ? 'Работодатель' : 'Соискатель' }}
            </span>
        </div>

        <h2>{{ user.username }}</h2>
        <p class="summary-headline">
            {{ user.headline || 'Укажи свою специализацию в кабинете' }}
        </p>

        <div class="summary-stats">
            <div>
                <span>Профиль заполнен</span>
                <strong>{{ completion }}%</strong>
            </div>

            <div>
                <span>
                    {{ user.role === 'employer' ? 'Мои вакансии' : 'Мои резюме' }}
                </span>
                <strong>{{ page.props.accountStats?.publications ?? 0 }}</strong>
            </div>
        </div>

        <div class="summary-progress">
            <span :style="{ width: `${completion}%` }"></span>
        </div>

        <div class="summary-info">
            <span>{{ user.city || 'Город не указан' }}</span>
            <span v-if="user.role === 'employer' && user.company">
                {{ user.company }}
            </span>
        </div>

        <div v-if="tags.length" class="summary-tags">
            <span v-for="tag in tags" :key="tag">{{ tag }}</span>
        </div>

        <Link href="/dashboard" class="summary-link">
            Открыть личный кабинет →
        </Link>
    </section>
</template>

<style scoped>
/* Карточка профиля */
.summary-card {
    min-width: 0;
    padding: 26px;
    border: 1px solid rgba(167, 139, 250, 0.2);
    border-radius: 22px;
    background: rgba(12, 16, 35, 0.92);
    color: #f3f4f6;
    overflow-wrap: anywhere;
}

.summary-top,
.summary-info {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 12px;
}

.summary-caption {
    color: #a78bfa;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.summary-role {
    color: #6ee7b7;
    font-size: 11px;
}

.summary-card h2 {
    margin: 20px 0 8px;
    font-size: 24px;
    font-weight: 800;
}

.summary-headline {
    margin: 0;
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.6;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 24px;
}

.summary-stats > div {
    padding: 16px;
    border-radius: 12px;
    background: #080d20;
}

.summary-stats span {
    display: block;
    color: #94a3b8;
    font-size: 11px;
}

.summary-stats strong {
    display: block;
    margin-top: 8px;
    color: #c4b5fd;
    font-size: 26px;
}

.summary-progress {
    height: 6px;
    margin: 20px 0;
    overflow: hidden;
    border-radius: 10px;
    background: #20243c;
}

.summary-progress span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899);
}

.summary-info {
    color: #94a3b8;
    font-size: 12px;
}

.summary-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 18px;
}

.summary-tags span {
    padding: 5px 9px;
    border-radius: 7px;
    background: rgba(167, 139, 250, 0.1);
    color: #c4b5fd;
    font-size: 11px;
}

.summary-link {
    display: block;
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    color: #c4b5fd;
    font-size: 12px;
    text-align: center;
    text-decoration: none;
}
</style>