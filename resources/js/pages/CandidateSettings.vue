<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
const props=defineProps<{auth:{user:any};settings:{visibility:string;consentProcessing:boolean;consentPublication:boolean;fspId:string|null;fspLinked:boolean;emailVerified:boolean}}>();
const form=useForm({profile_visibility:props.settings.visibility,consent_processing:props.settings.consentProcessing,consent_publication:props.settings.consentPublication,fsp_id_external:props.settings.fspId??''});
const save=()=>form.patch('/candidate/settings',{preserveScroll:true});
</script>
<template>
<Head title="Приватность и FSP ID" />
<div class="candidate-settings-shell">
<header><div class="candidate-settings-top"><Link href="/candidate" class="candidate-brand platform-brand" aria-label="ИТ-МЕТЧ"><img src="/brand/fsp-mark.svg" alt="ФСП" /><strong>ИТ-МЕТЧ</strong></Link><Link href="/candidate">← Вернуться в кабинет</Link></div></header>
<main>
<section class="candidate-settings-hero"><span>ПРИВАТНОСТЬ · 152-ФЗ · FSP ID</span><h1>Вы контролируете, <em>что видит работодатель</em>.</h1><p>Контакты всё равно остаются закрытыми до взаимного шага. Здесь регулируется публикация профессионального профиля и связь с идентификатором ФСП.</p></section>
<form @submit.prevent="save">
<section class="candidate-settings-card"><div><span class="index">01</span><h2>Видимость профиля</h2><p>Выберите, может ли профиль участвовать в общей выдаче работодателя.</p></div><div class="candidate-setting-options"><label :class="{active:form.profile_visibility==='employers'}"><input v-model="form.profile_visibility" type="radio" value="employers"/><b>Видим работодателям</b><span>Профиль участвует в подборе, но контакты скрыты до принятия приглашения или вашего отклика.</span></label><label :class="{active:form.profile_visibility==='private'}"><input v-model="form.profile_visibility" type="radio" value="private"/><b>Приватный режим</b><span>Профиль не должен появляться в новых подборках. Ваши уже созданные процессы сохраняются.</span></label></div></section>
<section class="candidate-settings-card"><div><span class="index">02</span><h2>Согласия</h2><p>Отдельно фиксируем обработку данных и публикацию профессионального профиля. Любое согласие можно отозвать и сохранить.</p></div><div class="candidate-setting-toggles"><label><input v-model="form.consent_processing" type="checkbox"/><span></span><div><b>Обработка персональных данных</b><small>При отключении профиль исключается из новых подборок работодателей; существующая история взаимодействий сохраняется.</small></div></label><label><input v-model="form.consent_publication" type="checkbox"/><span></span><div><b>Публикация профессионального профиля</b><small>Разрешает использовать профессиональные данные в поиске работодателя и обезличенном benchmark.</small></div></label></div></section>
<section class="candidate-settings-card"><div><span class="index">03</span><h2>Связь с FSP ID</h2><p>В хакатонной версии это mock-интеграция, подготовленная под будущий Keycloak/FSP ID.</p></div><div class="candidate-fsp-link"><label><span>Идентификатор ФСП</span><input v-model="form.fsp_id_external" placeholder="например, FSP-2026-001337"/></label><div :class="['candidate-link-status',{ok:form.fsp_id_external}]"><b>{{form.fsp_id_external?'Связь подготовлена':'Не привязан'}}</b><small>Подтверждённые достижения влияют на evidence и ранжирование внутри категории, но не присваивают грейд.</small></div></div></section>
<section class="candidate-settings-card compact"><div><span class="index">04</span><h2>Проверка e-mail</h2><p>Статус учётной записи для демонстрации требования регистрации по e-mail.</p></div><div class="candidate-email-status"><b :class="{ok:settings.emailVerified}">{{settings.emailVerified?'E-mail подтверждён':'E-mail не подтверждён'}}</b><small>Для production-потока подключается стандартный VerifyEmail Laravel / корпоративный IdP.</small></div></section>
<div class="candidate-settings-save"><div><b>Контакты кандидата</b><span>Не раскрываются новым работодателям до взаимного шага. Отключение согласий можно сохранить в любой момент.</span></div><button :disabled="form.processing">{{form.processing?'Сохраняем…':'Сохранить настройки'}}</button></div>
</form>
</main>
</div>
</template>
