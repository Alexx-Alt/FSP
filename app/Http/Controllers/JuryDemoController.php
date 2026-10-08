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

        $routeDistribution = [];
        if ($has('jobs') && $has('users')) {
            $routeDistribution = [
                ['route' => 'invite', 'label' => 'Высокое соответствие', 'note' => 'Совпали категория, грейд и большая часть обязательного стека.'],
                ['route' => 'clarify', 'label' => 'Нужно уточнить', 'note' => 'Сильная база, но есть один существенный gap или неполный evidence.'],
                ['route' => 'perspective', 'label' => 'Перспектива', 'note' => 'Есть потенциал, однако нужен дополнительный шаг проверки.'],
                ['route' => 'observe', 'label' => 'Наблюдать', 'note' => 'Есть критичное несовпадение по категории, грейду или обязательному стеку.'],
            ];
        }

        $criteria = [
            [
                'key' => 'qualification',
                'title' => 'Квалификационный тест и грейд',
                'status' => $has('assessment_runs') && $has('assessment_questions'),
                'weight' => 18,
                'proof' => 'Грейд подтверждается только assessment; FSP evidence не назначает грейд.',
            ],
            [
                'key' => 'matching',
                'title' => 'Объяснимый подбор',
                'status' => $has('jobs') && $has('candidate_skill_evidence'),
                'weight' => 22,
                'proof' => 'Match раскладывается на категорию, грейд, стек, зарплату, формат и gaps.',
            ],
            [
                'key' => 'invite',
                'title' => 'Адресное приглашение и отклик',
                'status' => $has('interaction_cases') && $has('case_events'),
                'weight' => 14,
                'proof' => 'Самостоятельный отклик и адресное приглашение сходятся в единый Process Center.',
            ],
            [
                'key' => 'evidence',
                'title' => 'Древо знаний и evidence',
                'status' => $has('skill_nodes') && $has('candidate_skill_evidence'),
                'weight' => 12,
                'proof' => 'Поддерживаются confirmed / partial / claimed / unknown и граф компетенций.',
            ],
            [
                'key' => 'analytics',
                'title' => '3-срезная аналитика',
                'status' => $companyCount > 0 && $cityCount > 0,
                'weight' => 12,
                'proof' => 'Люди, компании и география доступны кандидату, работодателю и администратору.',
            ],
            [
                'key' => 'validation',
                'title' => 'Валидация и test-retest',
                'status' => $has('assessment_runs') && (int) ($retests?->pairs ?? 0) > 0,
                'weight' => 10,
                'proof' => 'Сохраняются варианты теста, retest_of, score delta и стабильность результата.',
            ],
            [
                'key' => 'fsp',
                'title' => 'FSP ID / Keycloak ready',
                'status' => $has('fsp_achievements'),
                'weight' => 6,
                'proof' => 'Есть mock-слой достижений и скелет интеграции без зависимости основного алгоритма.',
            ],
            [
                'key' => 'cpu',
                'title' => 'Работа без GPU',
                'status' => true,
                'weight' => 6,
                'proof' => 'Критический путь реализован детерминированно на PHP/MySQL; LLM не обязателен.',
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
                    'steps' => ['Выбирает категорию и желаемый грейд', 'Проходит live assessment', 'Integrity-сигналы учитываются отдельно', 'Грейд меняется только результатом assessment'],
                ],
                [
                    'title' => 'Работодатель ищет Backend Senior',
                    'steps' => ['Система ранжирует кандидатов', 'Показывает причины и gaps', 'Работодатель сравнивает shortlist', 'Адресное приглашение создаёт кейс найма'],
                ],
                [
                    'title' => 'Взаимный шаг и процесс',
                    'steps' => ['Кандидат принимает приглашение', 'Контакты открываются после принятия', 'Несколько ЛПР ведут задачи и комментарии', 'Вся история остаётся в case_events'],
                ],
                [
                    'title' => 'Проверка качества системы',
                    'steps' => ['Админ видит города и компании', 'Смотрит test-retest стабильность', 'Проверяет integrity-review', 'Валидация воспроизводится на синтетических профилях'],
                ],
            ],
        ]);
    }
}
