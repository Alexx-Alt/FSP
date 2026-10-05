<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ title?: string; description?: string }>();
</script>
<template>
<div class="fsp-site fsp-auth">
<div class="ambient-glow glow-1"></div><div class="ambient-glow glow-2"></div>
<Link href="/" class="fsp-logo relative z-10"><span class="fsp-mark">ФСП</span><span><strong>ИТ-MATCH <small>PRO</small></strong><em>Реестр спортивного программирования</em></span></Link>
<div class="glass-panel fsp-auth-card">
<div class="mb-8 text-center"><h1>{{ title }}</h1><p>{{ description }}</p></div>
<slot />
</div>
<Link href="/" class="fsp-auth-back">← Вернуться на главную</Link>
</div>
</template>
