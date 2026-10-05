<script setup lang="ts">
import { Building2, Compass, MapPin, Search, User } from "@lucide/vue";
import { Head, Link } from "@inertiajs/vue3";
</script>
<template>
<Head title="Единый кадровый реестр" />
<div class="fsp-site"><div class="ambient-glow glow-1"></div><div class="ambient-glow glow-2"></div>
<header class="fsp-header">
<Link href="/" class="fsp-logo"><span class="fsp-mark">ФСП</span><span><strong>ИТ-MATCH <small>PRO</small></strong><em>Реестр спортивного программирования</em></span></Link>
<nav aria-label="Основное меню"><a href="/">Главная</a><a href="#vacancies-section">Кадровый реестр</a><button type="button">Сервисы ФСП</button><button type="button">Кадровый поиск</button></nav>
<div class="fsp-account"><button type="button" class="fsp-bell" aria-label="Уведомления">♧</button><Link href="/login" class="fsp-primary">Личный кабинет ↗</Link></div>
</header><main id="page-landing" class="w-full px-4 sm:px-8 lg:px-12 py-10 space-y-20 flex-1 relative z-10">
        
        
        <section class="pt-4">
            <div class="space-y-8 text-left max-w-5xl">
                
                <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-full glass-panel text-violet-300 text-xs font-bold shadow-lg">
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-violet-500"></span>
                    </span>
                    <span>Платформа подбора кадров по стандартам ФСП</span>
                </div>

                <h1 class="text-4xl sm:text-6xl xl:text-7xl font-black text-white tracking-tight leading-[1.1]">
                    Единый реестр <br />
                    <span class="fsp-gradient-text">
                        IT-специалистов
                    </span>
                </h1>

                <p class="text-slate-300 text-base sm:text-lg font-normal leading-relaxed max-w-3xl">
                    Интегрированный кадровый поиск спортивного программирования с алгоритмической валидацией навыков, шифрованием 152-ФЗ и прямой связью с аккредитованными работодателями.
                </p>

                
                <div class="pt-2 flex flex-col sm:flex-row gap-4">
                    <div class="inline-flex p-1.5 glass-panel rounded-2xl w-full sm:w-auto">
                        <button id="role-hero-worker" class="flex-1 sm:flex-none px-7 py-3.5 rounded-xl text-xs font-extrabold transition-all bg-gradient-to-r from-brand-600 via-violet-600 to-accent-pink text-white shadow-lg">
                            <User class="inline-block size-4 mr-2" aria-hidden="true" />Я Соискатель
                        </button>
                        <button id="role-hero-employer" class="flex-1 sm:flex-none px-7 py-3.5 rounded-xl text-xs font-extrabold transition-all text-slate-400 hover:text-white">
                            <Building2 class="inline-block size-4 mr-2" aria-hidden="true" />Я Работодатель
                        </button>
                    </div>

                    <button class="px-7 py-3.5 rounded-2xl glass-panel glass-panel-hover text-xs font-extrabold text-white transition-all flex items-center justify-center gap-2.5">
                        <Compass class="inline-block size-4 text-violet-400" aria-hidden="true" />
                        <span>Пройти профориентацию</span>
                    </button>
                </div>

                
                <div class="pt-2 space-y-3">
                    <div class="glass-panel p-2.5 rounded-3xl shadow-2xl flex flex-col sm:flex-row gap-2.5 border-white/10">
                        <div class="relative flex-1 flex items-center">
                            <Search class="inline-block size-4 absolute left-4 text-slate-400 text-base" aria-hidden="true" />
                            <input type="text" id="hero-search-input" placeholder="Поиск по стеку (Laravel, Vue 3, Python, Go) или названию компании..." class="w-full bg-transparent pl-12 pr-4 py-4 text-xs sm:text-sm text-white placeholder-slate-400 focus:outline-none" />
                        </div>
                        <button class="bg-gradient-to-r from-brand-600 via-violet-600 to-accent-pink text-white text-xs font-extrabold px-9 py-4 rounded-2xl transition-all shrink-0 shadow-lg shadow-violet-600/30 hover:opacity-95">
                            Найти позиции
                        </button>
                    </div>

                    
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400 pt-1">
                        <span class="font-bold text-slate-500">Популярный стек:</span>
                        <button class="px-3 py-1 rounded-xl glass-panel glass-panel-hover text-slate-300 text-xs">Laravel 11</button>
                        <button class="px-3 py-1 rounded-xl glass-panel glass-panel-hover text-slate-300 text-xs">Vue 3</button>
                        <button class="px-3 py-1 rounded-xl glass-panel glass-panel-hover text-slate-300 text-xs">Python AI</button>
                        <button class="px-3 py-1 rounded-xl glass-panel glass-panel-hover text-slate-300 text-xs">Golang</button>
                    </div>
                </div>
            </div>

            </section>

        
        <section id="vacancies-section" class="space-y-8 pt-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end border-b border-white/10 pb-5 gap-4">
                <div>
                    <span class="text-xs font-extrabold text-violet-400 uppercase tracking-widest">Проверенные позиции</span>
                    <h2 class="text-3xl font-black text-white mt-1">Актуальный реестр вакансий</h2>
                </div>
                
                <div class="flex items-center space-x-2 text-xs font-bold">
                    <button class="px-4 py-2 rounded-xl glass-panel text-white bg-violet-600/30 border-violet-500">Все позиции</button>
                    <button class="px-4 py-2 rounded-xl glass-panel text-slate-300 hover:text-white">Backend</button>
                    <button class="px-4 py-2 rounded-xl glass-panel text-slate-300 hover:text-white">Frontend</button>
                    <button class="px-4 py-2 rounded-xl glass-panel text-slate-300 hover:text-white">DevOps</button>
                </div>
            </div>

            
            <div id="vacancies-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-8">
                
                
                <div class="glass-panel glass-panel-hover rounded-3xl p-7 space-y-5 flex flex-col justify-between transition-all">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-slate-300 bg-slate-950/80 px-3.5 py-1.5 rounded-xl border border-white/10">
                                <Building2 class="inline-block size-4 mr-1.5 text-violet-400" aria-hidden="true" />ООО «Яндекс»
                            </span>
                            <span class="text-xs font-mono font-bold text-emerald-400 bg-emerald-950/50 px-3 py-1.5 rounded-xl border border-emerald-500/20">
                                280,000 — 350,000 ₽
                            </span>
                        </div>
                        <h3 class="font-extrabold text-white text-lg">Senior Fullstack Developer (Laravel + Vue 3)</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Проектирование сервисов высокой нагрузки. Разработка архитектуры базами данных, микросервисов и высоконагруженных API.
                        </p>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <span class="text-[11px] bg-white/5 border border-white/10 text-slate-300 px-2.5 py-1 rounded-lg">Laravel 11</span>
                            <span class="text-[11px] bg-white/5 border border-white/10 text-slate-300 px-2.5 py-1 rounded-lg">Vue 3</span>
                            <span class="text-[11px] bg-white/5 border border-white/10 text-slate-300 px-2.5 py-1 rounded-lg">PostgreSQL</span>
                        </div>
                    </div>

                    <div class="pt-5 border-t border-white/10 flex justify-between items-center text-xs">
                        <span class="text-slate-400 font-semibold"><MapPin class="inline-block size-4 mr-1 text-violet-400" aria-hidden="true" /> Москва / Удаленно</span>
                        <button class="bg-gradient-to-r from-brand-600 via-violet-600 to-accent-pink hover:opacity-90 text-white font-extrabold px-5 py-2.5 rounded-xl transition-all shadow-md">
                            Откликнуться
                        </button>
                    </div>
                </div>

                
                <div class="glass-panel glass-panel-hover rounded-3xl p-7 space-y-5 flex flex-col justify-between transition-all">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-slate-300 bg-slate-950/80 px-3.5 py-1.5 rounded-xl border border-white/10">
                                <Building2 class="inline-block size-4 mr-1.5 text-violet-400" aria-hidden="true" />АО «СберТех»
                            </span>
                            <span class="text-xs font-mono font-bold text-emerald-400 bg-emerald-950/50 px-3 py-1.5 rounded-xl border border-emerald-500/20">
                                220,000 — 280,000 ₽
                            </span>
                        </div>
                        <h3 class="font-extrabold text-white text-lg">Middle+ Python / AI Engineer</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Интеграция ИИ-моделей и алгоритмов анализа спортивных соревнований. Оптимизация пайплайнов обработки данных.
                        </p>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <span class="text-[11px] bg-white/5 border border-white/10 text-slate-300 px-2.5 py-1 rounded-lg">Python</span>
                            <span class="text-[11px] bg-white/5 border border-white/10 text-slate-300 px-2.5 py-1 rounded-lg">FastAPI</span>
                            <span class="text-[11px] bg-white/5 border border-white/10 text-slate-300 px-2.5 py-1 rounded-lg">PyTorch</span>
                        </div>
                    </div>

                    <div class="pt-5 border-t border-white/10 flex justify-between items-center text-xs">
                        <span class="text-slate-400 font-semibold"><MapPin class="inline-block size-4 mr-1 text-violet-400" aria-hidden="true" /> Санкт-Петербург</span>
                        <button class="bg-gradient-to-r from-brand-600 via-violet-600 to-accent-pink hover:opacity-90 text-white font-extrabold px-5 py-2.5 rounded-xl transition-all shadow-md">
                            Откликнуться
                        </button>
                    </div>
                </div>

                
                <div class="glass-panel glass-panel-hover rounded-3xl p-7 space-y-5 flex flex-col justify-between transition-all">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-slate-300 bg-slate-950/80 px-3.5 py-1.5 rounded-xl border border-white/10">
                                <Building2 class="inline-block size-4 mr-1.5 text-violet-400" aria-hidden="true" />ВК Технологии
                            </span>
                            <span class="text-xs font-mono font-bold text-emerald-400 bg-emerald-950/50 px-3 py-1.5 rounded-xl border border-emerald-500/20">
                                250,000 — 320,000 ₽
                            </span>
                        </div>
                        <h3 class="font-extrabold text-white text-lg">DevOps & Infrastructure Architect</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Управление высоконагруженными кластерами Kubernetes, поддержка отказоустойчивых сервисов и CI/CD автоматизация.
                        </p>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <span class="text-[11px] bg-white/5 border border-white/10 text-slate-300 px-2.5 py-1 rounded-lg">Kubernetes</span>
                            <span class="text-[11px] bg-white/5 border border-white/10 text-slate-300 px-2.5 py-1 rounded-lg">Docker</span>
                            <span class="text-[11px] bg-white/5 border border-white/10 text-slate-300 px-2.5 py-1 rounded-lg">Terraform</span>
                        </div>
                    </div>

                    <div class="pt-5 border-t border-white/10 flex justify-between items-center text-xs">
                        <span class="text-slate-400 font-semibold"><MapPin class="inline-block size-4 mr-1 text-violet-400" aria-hidden="true" /> Удаленно</span>
                        <button class="bg-gradient-to-r from-brand-600 via-violet-600 to-accent-pink hover:opacity-90 text-white font-extrabold px-5 py-2.5 rounded-xl transition-all shadow-md">
                            Откликнуться
                        </button>
                    </div>
                </div>

            </div>
        </section>

    </main>
</div>
</template>
