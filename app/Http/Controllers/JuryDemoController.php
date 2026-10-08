<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class JuryDemoController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $has = fn (string $table): bool => Schema::hasTable($table);
        $count = fn (string $table, ?callable $scope = null): int => $has($table)
            ? (int) (($scope ? $scope(DB::table($table)) : DB::table($table))->count())
            : 0;

        $candidateCount = $has('users') ? (int) DB::table('users')->where('role', 'applicant')->count() : 0;
        $employerCount = $has('users') ? (int) DB::table('users')->where('role', 'employer')->count() : 0;
        $companyCount = $count('companies');
        $openJobs = $has('jobs') ? (int) DB::table('jobs')->where('status', 'open')->count() : 0;
        $cityCount = $has('users') ? (int) DB::table('users')->where('role', 'applicant')->whereNotNull('city')->distinct()->count('city') : 0;
        $verifiedFsp = $has('fsp_achievements') ? (int) DB::table('fsp_achievements')->where('verified', true)->count() : 0;
        $activeCases = $has('interaction_cases') ? (int) DB::table('interaction_cases')->whereNotIn('status', ['archived', 'hired', 'declined', 'rejected'])->count() : 0;
        $accepted = $has('interaction_cases') ? (int) DB::table('interaction_cases')->whereNotNull('contacts_unlocked_at')->count() : 0;
        $totalCases = $count('interaction_cases');

        $latestAssessments = $has('assessment_runs')
            ? DB::table('assessment_runs as a')
                ->whereRaw('a.assessment_id = (SELECT MAX(a2.assessment_id) FROM assessment_runs a2 WHERE a2.candidate_id = a.candidate_id)')
                ->get()
            : collect();
        $avgAssessment = $latestAssessments->isNotEmpty() ? round((float) $latestAssessments->avg('score'), 1) : null;
        $avgIntegrity = $latestAssessments->isNotEmpty() ? round((float) $latestAssessments->avg('integrity_score'), 1) : null;

        $retests = $has('assessment_runs')
            ? DB::table('assessment_runs as second')
                ->join('assessment_runs as first', 'second.retest_of', '=', 'first.assessment_id')
                ->selectRaw('COUNT(*) pairs')
                ->selectRaw('AVG(ABS(second.score-first.score)) avg_delta')
                ->selectRaw('AVG(second.result_grade=first.result_grade)*100 grade_stability')
                ->first()
            : null;

        $routeDistribution = $has('jobs') && $has('users') ? [
            ['route' => 'invite', 'label' => 'Высокое соответствие', 'note' => 'Совпали категория, грейд и большая часть обязательного стека.'],
            ['route' => 'clarify', 'label' => 'Нужно уточнить', 'note' => 'Сильная база, но есть один существенный gap или неполный evidence.'],
            ['route' => 'perspective', 'label' => 'Перспектива', 'note' => 'Есть потенциал, однако нужен дополнительный шаг проверки.'],
            ['route' => 'observe', 'label' => 'Наблюдать', 'note' => 'Есть критичное несовпадение по категории, грейду или обязательному стеку.'],
        ] : [];

        // Веса полностью повторяют раздел 6 официального ТЗ ФСП (35/25/10/7/6/7/10).
        $criteria = [
            [
                'key' => 'matching',
                'title' => 'Новая механика подбора',
                'status' => $has('jobs') && $has('candidate_skill_evidence') && $has('interaction_cases'),
                'weight' => 35,
                'proof' => 'Explainable Match ранжирует по подтверждённой категории/грейду, стеку, зарплате и формату, показывает reasons/gaps и ведёт к адресному контакту.',
            ],
            [
                'key' => 'testing',
                'title' => 'Тестирование, валидация и категоризация',
                'status' => $has('assessment_runs') && $has('assessment_questions') && $has('assessment_integrity_events'),
                'weight' => 25,
                'proof' => 'Варианты банка, вопросы по одному, практическая задача, integrity-сигналы, code similarity, retest_of и измерение стабильности грейда.',
            ],
            [
                'key' => 'ux',
                'title' => 'Удобство и интуитивность интерфейса',
                'status' => true,
                'weight' => 10,
                'proof' => 'Ролевые кабинеты, Today/Focus, light/dark, фильтры, сравнения, понятные маршруты Match, адаптивные экраны и контекстная помощь.',
            ],
            [
                'key' => 'technical',
                'title' => 'Техническая проработка',
                'status' => true,
                'weight' => 7,
                'proof' => 'Laravel/Vue/TypeScript/MySQL, разделение UI/business/data, OpenAPI, Docker-ready контур и критический путь без GPU/обязательного LLM.',
            ],
            [
                'key' => 'completeness',
                'title' => 'Полнота реализации и документация',
                'status' => $candidateCount > 0 && $employerCount > 0 && $companyCount > 0,
                'weight' => 6,
                'proof' => 'Оба кабинета, профиль без FSP-истории, FSP ID stub, инструкция запуска, архитектура, тестирование, matching, validation и API описаны в репозитории.',
            ],
            [
                'key' => 'extras',
                'title' => 'Дополнительный функционал',
                'status' => $has('jobs') && $has('interaction_cases') && $has('case_tasks'),
                'weight' => 7,
                'proof' => 'Самостоятельный отклик, CRUD вакансий, gap/case tasks, Process Center, privacy/trust layer, сравнение компаний и кандидатов, мобильная адаптация.',
            ],
            [
                'key' => 'pitch',
                'title' => 'Презентация и выступление',
                'status' => true,
                'weight' => 10,
                'proof' => 'Jury Demo даёт 7–10 минутный сценарий: assessment → categorization → employer search → explainable match → contact → validation/analytics.',
            ],
        ];

        $readinessScore = collect($criteria)->sum(fn (array $row) => $row['status'] ? $row['weight'] : 0);

        return Inertia::render('JuryDemo', [
            'snapshot' => [
                'candidates' => $candidateCount,
                'employers' => $employerCount,
                'companies' => $companyCount,
                'openJobs' => $openJobs,
                'cities' => $cityCount,
                'verifiedFsp' => $verifiedFsp,
                'activeCases' => $activeCases,
                'contactsUnlocked' => $accepted,
                'caseConversion' => $totalCases ? round($accepted / $totalCases * 100, 1) : 0,
                'avgAssessment' => $avgAssessment,
                'avgIntegrity' => $avgIntegrity,
                'retestPairs' => (int) ($retests?->pairs ?? 0),
                'avgRetestDelta' => $retests?->avg_delta === null ? null : round((float) $retests->avg_delta, 1),
                'gradeStability' => $retests?->grade_stability === null ? null : round((float) $retests->grade_stability, 1),
                'readinessScore' => $readinessScore,
            ],
            'criteria' => $criteria,
            'routes' => $routeDistribution,
            'scenarios' => [
                [
                    'title' => 'Кандидат заявляет Senior',
                    'steps' => ['Заполняет профиль и выбирает категорию/желаемый грейд', 'Проходит live assessment по одному вопросу', 'Практическая задача и integrity учитываются отдельно', 'Только assessment подтверждает или не подтверждает грейд'],
                ],
                [
                    'title' => 'Работодатель описывает потребность',
                    'steps' => ['Создаёт вакансию с обязательной зарплатой', 'Указывает категорию, грейд, стек и формат', 'Получает ранжированную подборку', 'Открывает причины и gaps по каждому кандидату'],
                ],
                [
                    'title' => 'Контакт и Process Center',
                    'steps' => ['Работодатель отправляет адресное приглашение или кандидат откликается', 'Взаимный шаг открывает контакты', 'Несколько ЛПР ведут задачи и комментарии', 'Статусы и история сохраняются в case_events'],
                ],
                [
                    'title' => 'Проверка качества системы',
                    'steps' => ['Админ показывает test-retest и integrity', 'Сравнивает людей, компании и города', 'Показывает воспроизводимую логику Match', 'Жюри может повторить путь на синтетических профилях'],
                ],
            ],
        ]);
    }
}
