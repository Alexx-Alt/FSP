<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Fsp\MatchEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComparisonController extends Controller
{
    public function companies(Request $request, MatchEngine $engine): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);

        $jobs = DB::table('jobs as j')
            ->leftJoin('companies as c', 'c.company_id', '=', 'j.company_id')
            ->where('j.status', 'open')
            ->select(
                'j.job_id', 'j.company_id', 'j.title', 'j.location', 'j.grade_required', 'j.salary_min', 'j.salary_max', 'j.work_format',
                'c.name as company_name', 'c.city as company_city', 'c.industry', 'c.size_band', 'c.hiring_quality_score', 'c.avg_response_hours', 'c.acceptance_rate'
            )
            ->get();

        $best = [];
        foreach ($jobs as $job) {
            $match = $engine->compare((int) $user->getKey(), (int) $job->job_id);
            $companyId = (int) ($job->company_id ?? 0);
            if ($companyId <= 0) {
                continue;
            }
            if (isset($best[$companyId]) && ($best[$companyId]['score'] ?? 0) >= ($match['score'] ?? 0)) {
                continue;
            }

            $best[$companyId] = [
                'companyId' => $companyId,
                'company' => $job->company_name ?? 'Компания',
                'companyCity' => $job->company_city,
                'industry' => $job->industry,
                'size' => $job->size_band,
                'quality' => $job->hiring_quality_score === null ? null : (float) $job->hiring_quality_score,
                'responseHours' => $job->avg_response_hours === null ? null : (float) $job->avg_response_hours,
                'acceptanceRate' => $job->acceptance_rate === null ? null : (float) $job->acceptance_rate,
                'jobId' => (int) $job->job_id,
                'job' => $job->title,
                'location' => $job->location,
                'grade' => $job->grade_required,
                'salaryMin' => $job->salary_min === null ? null : (int) $job->salary_min,
                'salaryMax' => $job->salary_max === null ? null : (int) $job->salary_max,
                'workFormat' => $job->work_format,
                ...$this->matchPayload($match),
            ];
        }

        $items = collect(array_values($best))->sortByDesc('score')->values()->all();
        return response()->json(['items' => $items]);
    }

    public function candidates(Request $request, MatchEngine $engine): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'employer', 403);

        $companyId = DB::table('company_members')
            ->where('user_id', $user->getKey())
            ->where('active', true)
            ->value('company_id');
        abort_unless($companyId, 422, 'Работодатель не привязан к компании.');

        $jobs = DB::table('jobs')
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->get();
        abort_if($jobs->isEmpty(), 422, 'Сначала создайте открытую вакансию.');

        $candidates = DB::table('users')->where('role', 'applicant')->get();
        $items = [];

        foreach ($candidates as $candidate) {
            $best = null;
            foreach ($jobs as $job) {
                $match = $engine->compare((int) $candidate->user_id, (int) $job->job_id);
                if ($best === null || ($match['score'] ?? 0) > ($best['score'] ?? 0)) {
                    $best = [
                        'jobId' => (int) $job->job_id,
                        'job' => $job->title,
                        'jobGrade' => $job->grade_required,
                        'jobLocation' => $job->location,
                        ...$this->matchPayload($match),
                    ];
                }
            }
            if ($best === null) {
                continue;
            }

            $assessment = DB::table('assessment_runs')
                ->where('candidate_id', $candidate->user_id)
                ->orderByDesc('completed_at')
                ->first();
            $confirmedSkills = DB::table('candidate_skill_evidence')
                ->where('candidate_id', $candidate->user_id)
                ->where('state', 'confirmed')
                ->count();
            $fspEvidence = DB::table('fsp_achievements')
                ->where('candidate_id', $candidate->user_id)
                ->where('verified', true)
                ->count();

            $items[] = [
                'candidateId' => (int) $candidate->user_id,
                'name' => $candidate->username,
                'headline' => $candidate->headline,
                'city' => $candidate->city,
                'category' => $candidate->primary_category,
                'desiredGrade' => $candidate->desired_grade,
                'confirmedGrade' => $candidate->confirmed_grade,
                'gradeStatus' => $candidate->grade_status,
                'salaryExpected' => $candidate->salary_expected === null ? null : (int) $candidate->salary_expected,
                'workFormat' => $candidate->work_format,
                'assessmentScore' => $assessment?->score === null ? null : (float) $assessment->score,
                'integrityScore' => $assessment?->integrity_score === null ? null : (float) $assessment->integrity_score,
                'confirmedSkills' => $confirmedSkills,
                'fspEvidence' => $fspEvidence,
                ...$best,
            ];
        }

        usort($items, fn (array $a, array $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        return response()->json(['items' => array_slice($items, 0, 30)]);
    }

    /** @param array<string,mixed> $match */
    private function matchPayload(array $match): array
    {
        $gaps = $match['missingSkills'] ?? [];
        if (! ($match['salaryOverlap'] ?? false)) {
            $gaps[] = 'зарплатные ожидания';
        }
        if (! ($match['formatMatch'] ?? false)) {
            $gaps[] = 'формат работы';
        }
        if (($match['gradeFit'] ?? 0) < .85) {
            $gaps[] = 'подтверждение уровня';
        }

        return [
            'score' => (int) ($match['score'] ?? 0),
            'route' => $match['route'] ?? 'observe',
            'categoryMatch' => (bool) ($match['categoryMatch'] ?? false),
            'gradeFit' => (float) ($match['gradeFit'] ?? 0),
            'stackCoverage' => (float) ($match['stackCoverage'] ?? 0),
            'salaryOverlap' => (bool) ($match['salaryOverlap'] ?? false),
            'formatMatch' => (bool) ($match['formatMatch'] ?? false),
            'matchedSkills' => array_values($match['matchedSkills'] ?? []),
            'missingSkills' => array_values($match['missingSkills'] ?? []),
            'gaps' => array_values(array_unique($gaps)),
            'reasons' => array_values($match['reasons'] ?? []),
        ];
    }
}
