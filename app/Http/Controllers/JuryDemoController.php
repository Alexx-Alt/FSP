<?php

namespace App\Http\Controllers;

use App\Services\Fsp\MatchEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class JuryDemoController extends Controller
{
    public function __construct(private readonly MatchEngine $matchEngine) {}

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

        $validation = $this->matchValidation();
        $routeDistribution = $has('jobs') && $has('users') ? [
            ['route' => 'invite', 'label' => 'Высокое соответствие', 'note' => 'Совпали категория, грейд и большая часть обязательного стека.'],
            ['route' => 'clarify', 'label' => 'Нужно уточнить', 'note' => 'Сильная база, но есть один существенный gap или неполный evidence.'],
            ['route' => 'perspective', 'label' => 'Перспектива', 'note' => 'Есть потенциал, однако нужен дополнительный шаг проверки.'],
            ['route' => 'observe', 'label' => 'Наблюдать', 'note' => 'Есть критичное несовпадение по категории, грейду или обязательному стеку.'],
        ] : [];

        $criteria = [
            [
                'key' => 'matching', 'title' => 'Новая механика подбора',
                'status' => $has('jobs') && $has('candidate_skill_evidence') && $has('interaction_cases') && ($validation['pairs'] ?? 0) > 0,
                'weight' => 35,
                'proof' => 'Explainable Match ранжирует по подтверждённой категории/грейду, стеку, зарплате и формату, показывает reasons/gaps и проверяется на размеченных парах.',
            ],
            [
                'key' => 'testing', 'title' => 'Тестирование, валидация и категоризация',
                'status' => $has('assessment_runs') && $has('assessment_questions') && $has('assessment_integrity_events'),
                'weight' => 25,
                'proof' => 'Варианты банка, вопросы по одному, практическая задача, integrity-сигналы, code similarity, retest_of и измерение стабильности грейда.',
            ],
            [
                'key' => 'ux', 'title' => 'Удобство и интуитивность интерфейса', 'status' => true, 'weight' => 10,
                'proof' => 'Ролевые кабинеты, Today/Focus, light/dark, фильтры, сравнения, понятные маршруты Match, адаптивные экраны и контекстная помощь.',
            ],
            [
                'key' => 'technical', 'title' => 'Техническая проработка', 'status' => true, 'weight' => 7,
                'proof' => 'Laravel/Vue/TypeScript/MySQL, разделение UI/business/data, OpenAPI, Docker-ready контур и критический путь без GPU/обязательного LLM.',
            ],
            [
                'key' => 'completeness', 'title' => 'Полнота реализации и документация',
                'status' => $candidateCount > 0 && $employerCount > 0 && $companyCount > 0, 'weight' => 6,
                'proof' => 'Оба кабинета, профиль без FSP-истории, FSP ID stub, инструкция запуска, архитектура, тестирование, matching, validation и API описаны в репозитории.',
            ],
            [
                'key' => 'extras', 'title' => 'Дополнительный функционал',
                'status' => $has('jobs') && $has('interaction_cases') && $has('case_tasks'), 'weight' => 7,
                'proof' => 'Самостоятельный отклик, CRUD вакансий, gap/case tasks, Process Center, privacy/trust layer, сравнение компаний и кандидатов, мобильная адаптация.',
            ],
            [
                'key' => 'pitch', 'title' => 'Презентация и выступление', 'status' => true, 'weight' => 10,
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
                'matchPairs' => $validation['pairs'] ?? 0,
                'matchPrecision' => $validation['precision'] ?? null,
                'matchRecall' => $validation['recall'] ?? null,
                'matchF1' => $validation['f1'] ?? null,
                'scoreSeparation' => $validation['scoreSeparation'] ?? null,
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
                    'steps' => ['Админ показывает test-retest и integrity', 'Запускает fsp:validate / показывает F1', 'Сравнивает людей, компании и города', 'Жюри может повторить путь на синтетических профилях'],
                ],
            ],
        ]);
    }

    /** @return array<string,int|float|null> */
    private function matchValidation(): array
    {
        $file = base_path('database/fsp_match_v6/validation_pairs.json');
        if (! is_file($file) || ! Schema::hasTable('users') || ! Schema::hasTable('jobs')) {
            return ['pairs' => 0, 'precision' => null, 'recall' => null, 'f1' => null, 'scoreSeparation' => null];
        }

        try {
            $pairs = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['pairs' => 0, 'precision' => null, 'recall' => null, 'f1' => null, 'scoreSeparation' => null];
        }

        $tp = $fp = $fn = 0;
        $relevant = [];
        $irrelevant = [];
        $evaluated = 0;
        foreach ($pairs as $pair) {
            $candidateId = DB::table('users')->where('email', $pair['candidate_email'] ?? '')->value('user_id');
            if (! $candidateId || ! DB::table('jobs')->where('job_id', (int) ($pair['job_id'] ?? 0))->exists()) {
                continue;
            }

            $result = $this->matchEngine->compare((int) $candidateId, (int) $pair['job_id']);
            $expected = (bool) ($pair['relevant'] ?? false);
            $predicted = in_array($result['route'], ['invite', 'clarify'], true);
            $score = (float) $result['score'];

            if ($expected) {
                $relevant[] = $score;
            } else {
                $irrelevant[] = $score;
            }

            if ($expected && $predicted) {
                $tp++;
            } elseif (! $expected && $predicted) {
                $fp++;
            } elseif ($expected && ! $predicted) {
                $fn++;
            }
            $evaluated++;
        }

        $precision = ($tp + $fp) ? $tp / ($tp + $fp) : 0;
        $recall = ($tp + $fn) ? $tp / ($tp + $fn) : 0;
        $f1 = ($precision + $recall) ? 2 * $precision * $recall / ($precision + $recall) : 0;
        $avgRel = $relevant ? array_sum($relevant) / count($relevant) : 0;
        $avgIrr = $irrelevant ? array_sum($irrelevant) / count($irrelevant) : 0;

        return [
            'pairs' => $evaluated,
            'precision' => round($precision * 100, 1),
            'recall' => round($recall * 100, 1),
            'f1' => round($f1 * 100, 1),
            'scoreSeparation' => round($avgRel - $avgIrr, 1),
        ];
    }
}
