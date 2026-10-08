<?php

namespace App\Services\Fsp;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(
        private readonly KnowledgeTreeService $knowledgeTree,
        private readonly MatchEngine $matchEngine,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        return match ($user->role) {
            'admin' => $this->admin($user),
            'employer' => $this->employer($user),
            default => $this->candidate($user),
        };
    }

    /** @return array<string, mixed> */
    private function candidate(User $user): array
    {
        $id = (int) $user->getKey();
        $assessment = DB::table('assessment_runs')->where('candidate_id', $id)->orderByDesc('completed_at')->first();
        $skills = DB::table('candidate_skill_evidence')->where('candidate_id', $id)
            ->selectRaw("SUM(state='confirmed') confirmed, SUM(state='partial') partial, SUM(state='claimed') claimed")
            ->first();

        $peers = DB::table('users as u')
            ->leftJoin('candidate_skill_evidence as e', function ($join) {
                $join->on('e.candidate_id', '=', 'u.user_id')->where('e.state', '=', 'confirmed');
            })
            ->leftJoin('assessment_runs as ar', function ($join) {
                $join->on('ar.candidate_id', '=', 'u.user_id')
                    ->whereRaw('ar.assessment_id = (SELECT MAX(a2.assessment_id) FROM assessment_runs a2 WHERE a2.candidate_id=u.user_id)');
            })
            ->leftJoin('fsp_achievements as fa', function ($join) {
                $join->on('fa.candidate_id', '=', 'u.user_id')->where('fa.verified', '=', 1);
            })
            ->where('u.role', 'applicant')->where('u.primary_category', $user->primary_category)
            ->groupBy('u.user_id', 'u.username', 'u.city', 'u.confirmed_grade', 'u.grade_status', 'ar.score')
            ->select('u.user_id', 'u.username', 'u.city', 'u.confirmed_grade', 'u.grade_status')
            ->selectRaw('COUNT(DISTINCT e.evidence_id) confirmed_skills')
            ->selectRaw('COUNT(DISTINCT fa.achievement_id) fsp_evidence')
            ->selectRaw("LEAST(100, COALESCE(ar.score,0)*.55 + COUNT(DISTINCT e.evidence_id)*5 + COUNT(DISTINCT fa.achievement_id)*2 + CASE WHEN u.grade_status='confirmed' THEN 12 ELSE 0 END) benchmark_score")
            ->orderByDesc('benchmark_score')->limit(12)->get()
            ->map(fn ($row, $index) => [
                'rank' => $index + 1,
                'id' => $row->user_id,
                'name' => $row->username,
                'city' => $row->city,
                'grade' => $row->confirmed_grade,
                'gradeStatus' => $row->grade_status,
                'confirmedSkills' => (int) $row->confirmed_skills,
                'fspEvidence' => (int) $row->fsp_evidence,
                'score' => (int) round($row->benchmark_score),
                'isCurrent' => (int) $row->user_id === $id,
            ])->all();

        return [
            'mode' => 'candidate',
            'summary' => [
                'desiredGrade' => $user->desired_grade,
                'confirmedGrade' => $user->confirmed_grade,
                'gradeStatus' => $user->grade_status,
                'category' => $user->primary_category,
                'assessmentScore' => $assessment?->score === null ? null : (float) $assessment->score,
                'confirmedSkills' => (int) ($skills?->confirmed ?? 0),
                'partialSkills' => (int) ($skills?->partial ?? 0),
                'claimedSkills' => (int) ($skills?->claimed ?? 0),
                'fspEvidence' => DB::table('fsp_achievements')->where('candidate_id', $id)->where('verified', true)->count(),
                'peerRank' => collect($peers)->firstWhere('isCurrent', true)['rank'] ?? null,
                'activeCases' => DB::table('interaction_cases')->where('candidate_id', $id)->whereNotIn('status', ['archived', 'hired', 'declined'])->count(),
            ],
            'knowledgeTree' => $this->knowledgeTree->forCandidate($id),
            'peerBenchmark' => $peers,
            'companyBenchmark' => $this->companyBenchmark(),
            'cityBenchmark' => $this->cityBenchmark(),
            'matches' => $this->matchEngine->topJobsForCandidate($id),
            'cases' => $this->cases($user),
            'alerts' => $this->alerts($user),
            'fspAchievements' => DB::table('fsp_achievements')->where('candidate_id', $id)->orderByDesc('achieved_at')->get()->map(fn ($row) => [
                'title' => $row->title, 'discipline' => $row->discipline, 'type' => $row->achievement_type,
                'place' => $row->place, 'rankName' => $row->rank_name, 'verified' => (bool) $row->verified,
                'relevance' => (int) $row->relevance_score, 'date' => $row->achieved_at,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function employer(User $user): array
    {
        $companyId = $this->companyId($user);
        $company = $companyId ? DB::table('companies')->where('company_id', $companyId)->first() : null;
        $team = $companyId ? DB::table('company_members as cm')
            ->join('users as u', 'u.user_id', '=', 'cm.user_id')
            ->leftJoin('candidate_skill_evidence as e', function ($join) {
                $join->on('e.candidate_id', '=', 'u.user_id')->where('e.state', '=', 'confirmed');
            })
            ->leftJoin('assessment_runs as ar', function ($join) {
                $join->on('ar.candidate_id', '=', 'u.user_id')
                    ->whereRaw('ar.assessment_id=(SELECT MAX(a2.assessment_id) FROM assessment_runs a2 WHERE a2.candidate_id=u.user_id)');
            })
            ->where('cm.company_id', $companyId)->where('cm.active', true)->where('u.role', 'applicant')
            ->groupBy('u.user_id', 'u.username', 'u.headline', 'u.city', 'u.confirmed_grade', 'u.grade_status', 'cm.position_title', 'ar.score')
            ->select('u.user_id', 'u.username', 'u.headline', 'u.city', 'u.confirmed_grade', 'u.grade_status', 'cm.position_title')
            ->selectRaw('COUNT(DISTINCT e.evidence_id) confirmed_skills')->selectRaw('COALESCE(ar.score,0) assessment_score')
            ->orderByDesc('assessment_score')->get()->map(fn ($row) => [
                'id' => $row->user_id, 'name' => $row->username, 'headline' => $row->headline,
                'position' => $row->position_title, 'city' => $row->city, 'grade' => $row->confirmed_grade,
                'gradeStatus' => $row->grade_status, 'confirmedSkills' => (int) $row->confirmed_skills,
                'assessmentScore' => (float) $row->assessment_score,
            ])->all() : [];

        return [
            'mode' => 'employer',
            'summary' => [
                'companyId' => $companyId, 'companyName' => $company?->name ?? $user->company,
                'openJobs' => DB::table('jobs')->where('employer_id', $user->getKey())->where('status', 'open')->count(),
                'activeCases' => $companyId ? DB::table('interaction_cases')->where('company_id', $companyId)->whereNotIn('status', ['archived', 'hired', 'declined'])->count() : 0,
                'teamSize' => count($team), 'avgResponseHours' => $company?->avg_response_hours,
                'acceptanceRate' => $company?->acceptance_rate, 'hiringQualityScore' => $company?->hiring_quality_score,
            ],
            'teamBenchmark' => $team,
            'candidateMatches' => $this->matchEngine->topCandidatesForEmployer((int) $user->getKey()),
            'companyBenchmark' => $this->companyBenchmark($companyId),
            'cityBenchmark' => $this->cityBenchmark(),
            'cases' => $this->cases($user),
            'alerts' => $this->alerts($user),
        ];
    }

    /** @return array<string, mixed> */
    private function admin(User $user): array
    {
        $stability = DB::table('assessment_runs as second')->join('assessment_runs as first', 'second.retest_of', '=', 'first.assessment_id')
            ->selectRaw('AVG(ABS(second.score-first.score)) avg_delta')
            ->selectRaw('SUM(second.result_grade=first.result_grade)/NULLIF(COUNT(*),0)*100 grade_stability')->first();

        return [
            'mode' => 'admin',
            'summary' => [
                'candidates' => DB::table('users')->where('role', 'applicant')->count(),
                'employers' => DB::table('users')->where('role', 'employer')->count(),
                'companies' => DB::table('companies')->count(), 'openJobs' => DB::table('jobs')->where('status', 'open')->count(),
                'activeCases' => DB::table('interaction_cases')->whereNotIn('status', ['archived', 'hired', 'declined'])->count(),
                'alerts' => DB::table('interaction_alerts')->where('is_read', false)->count(),
                'gradeStability' => $stability?->grade_stability === null ? null : round((float) $stability->grade_stability, 1),
                'avgRetestDelta' => $stability?->avg_delta === null ? null : round((float) $stability->avg_delta, 1),
            ],
            'candidateBenchmark' => $this->adminCandidates(),
            'companyBenchmark' => $this->companyBenchmark(),
            'cityBenchmark' => $this->cityBenchmark(),
            'pipeline' => DB::table('interaction_cases')->groupBy('status')->select('status')->selectRaw('COUNT(*) total')->orderByDesc('total')->get()->map(fn ($row) => ['status' => $row->status, 'total' => (int) $row->total])->all(),
            'assessment' => $this->assessmentAnalytics(),
            'cases' => $this->cases($user), 'alerts' => $this->alerts($user),
            'audit' => DB::table('case_events as ce')->leftJoin('users as u', 'u.user_id', '=', 'ce.actor_user_id')->orderByDesc('ce.created_at')->limit(12)
                ->select('ce.event_type', 'ce.title', 'ce.details', 'ce.created_at', 'u.username as actor')->get()->map(fn ($row) => [
                    'type' => $row->event_type, 'title' => $row->title, 'details' => $row->details, 'createdAt' => $row->created_at, 'actor' => $row->actor,
                ])->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function companyBenchmark(?int $current = null): array
    {
        return DB::table('companies as c')
            ->leftJoin('jobs as j', function ($join) { $join->on('j.company_id', '=', 'c.company_id')->where('j.status', '=', 'open'); })
            ->leftJoin('interaction_cases as ic', 'ic.company_id', '=', 'c.company_id')
            ->groupBy('c.company_id', 'c.name', 'c.city', 'c.industry', 'c.size_band', 'c.hiring_quality_score', 'c.avg_response_hours', 'c.acceptance_rate')
            ->select('c.company_id', 'c.name', 'c.city', 'c.industry', 'c.size_band', 'c.hiring_quality_score', 'c.avg_response_hours', 'c.acceptance_rate')
            ->selectRaw('COUNT(DISTINCT j.job_id) open_jobs')->selectRaw('ROUND(AVG((j.salary_min+j.salary_max)/2)) avg_salary')
            ->selectRaw("COUNT(DISTINCT CASE WHEN ic.status='hired' THEN ic.case_id END) hires")->selectRaw('COUNT(DISTINCT ic.case_id) cases_total')
            ->orderByDesc('c.hiring_quality_score')->get()->map(fn ($row, $index) => [
                'rank' => $index + 1, 'id' => $row->company_id, 'name' => $row->name, 'city' => $row->city,
                'industry' => $row->industry, 'size' => $row->size_band, 'quality' => (float) $row->hiring_quality_score,
                'responseHours' => (float) $row->avg_response_hours, 'acceptanceRate' => (float) $row->acceptance_rate,
                'openJobs' => (int) $row->open_jobs, 'avgSalary' => $row->avg_salary === null ? null : (int) $row->avg_salary,
                'hires' => (int) $row->hires, 'cases' => (int) $row->cases_total,
                'isCurrent' => $current !== null && (int) $row->company_id === $current,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function cityBenchmark(): array
    {
        $candidates = DB::table('users')->where('role', 'applicant')->whereNotNull('city')->groupBy('city')->select('city')
            ->selectRaw('COUNT(*) candidates')->selectRaw("SUM(grade_status='confirmed') confirmed_candidates")
            ->selectRaw('ROUND(AVG(NULLIF(salary_expected,0))) avg_expected_salary')->get()->keyBy('city');
        $jobs = DB::table('jobs')->where('status', 'open')->whereNotNull('location')->groupBy('location')->select('location')
            ->selectRaw('COUNT(*) jobs')->selectRaw('ROUND(AVG((salary_min+salary_max)/2)) avg_job_salary')->get()->keyBy('location');
        $companies = DB::table('companies')->whereNotNull('city')->groupBy('city')->select('city')->selectRaw('COUNT(*) companies')->get()->keyBy('city');

        return collect($candidates->keys())->merge($jobs->keys())->merge($companies->keys())->unique()->map(function ($city) use ($candidates, $jobs, $companies) {
            $count = (int) ($candidates[$city]->candidates ?? 0);
            $confirmed = (int) ($candidates[$city]->confirmed_candidates ?? 0);
            $jobCount = (int) ($jobs[$city]->jobs ?? 0);
            return [
                'city' => $city, 'candidates' => $count, 'confirmedShare' => $count ? round($confirmed / $count * 100) : 0,
                'avgExpectedSalary' => isset($candidates[$city]) ? (int) $candidates[$city]->avg_expected_salary : null,
                'jobs' => $jobCount, 'avgJobSalary' => isset($jobs[$city]) ? (int) $jobs[$city]->avg_job_salary : null,
                'companies' => (int) ($companies[$city]->companies ?? 0), 'pressure' => $count ? round($jobCount / $count, 2) : $jobCount,
            ];
        })->sortByDesc('jobs')->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function adminCandidates(): array
    {
        return DB::table('users as u')->leftJoin('candidate_skill_evidence as e', function ($join) { $join->on('e.candidate_id', '=', 'u.user_id')->where('e.state', '=', 'confirmed'); })
            ->leftJoin('fsp_achievements as fa', function ($join) { $join->on('fa.candidate_id', '=', 'u.user_id')->where('fa.verified', '=', 1); })
            ->where('u.role', 'applicant')->groupBy('u.user_id', 'u.username', 'u.city', 'u.primary_category', 'u.confirmed_grade', 'u.grade_status')
            ->select('u.user_id', 'u.username', 'u.city', 'u.primary_category', 'u.confirmed_grade', 'u.grade_status')
            ->selectRaw('COUNT(DISTINCT e.evidence_id) confirmed_skills')->selectRaw('COUNT(DISTINCT fa.achievement_id) fsp_evidence')
            ->orderByDesc('confirmed_skills')->limit(30)->get()->map(fn ($row) => [
                'id' => $row->user_id, 'name' => $row->username, 'city' => $row->city, 'category' => $row->primary_category,
                'grade' => $row->confirmed_grade, 'gradeStatus' => $row->grade_status, 'confirmedSkills' => (int) $row->confirmed_skills,
                'fspEvidence' => (int) $row->fsp_evidence,
            ])->all();
    }

    /** @return array<string, mixed> */
    private function assessmentAnalytics(): array
    {
        return [
            'grades' => DB::table('assessment_runs')->groupBy('result_grade')->select('result_grade')->selectRaw('COUNT(*) total')->selectRaw('ROUND(AVG(score),1) avg_score')->selectRaw('ROUND(AVG(integrity_score),1) integrity')->get()->map(fn ($row) => ['grade' => $row->result_grade, 'total' => (int) $row->total, 'avgScore' => (float) $row->avg_score, 'integrity' => (float) $row->integrity])->all(),
            'variants' => DB::table('assessment_runs')->groupBy('variant_key')->select('variant_key')->selectRaw('COUNT(*) usages')->selectRaw('ROUND(AVG(score),1) avg_score')->orderByDesc('usages')->get()->map(fn ($row) => ['variant' => $row->variant_key, 'usages' => (int) $row->usages, 'avgScore' => (float) $row->avg_score])->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function cases(User $user): array
    {
        $query = DB::table('interaction_cases as ic')->join('users as candidate', 'candidate.user_id', '=', 'ic.candidate_id')
            ->join('companies as company', 'company.company_id', '=', 'ic.company_id')->leftJoin('jobs as job', 'job.job_id', '=', 'ic.job_id')
            ->leftJoin('users as owner', 'owner.user_id', '=', 'ic.owner_user_id')
            ->select('ic.*', 'candidate.username as candidate_name', 'company.name as company_name', 'job.title as job_title', 'owner.username as owner_name');

        if ($user->role === 'applicant') $query->where('ic.candidate_id', $user->getKey());
        if ($user->role === 'employer') $query->where('ic.company_id', $this->companyId($user) ?? -1);

        return $query->orderByRaw("CASE ic.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderBy('ic.next_action_at')->limit($user->role === 'admin' ? 40 : 15)->get()->map(function ($row) use ($user) {
                $comments = DB::table('case_comments as cc')->leftJoin('users as u', 'u.user_id', '=', 'cc.user_id')->where('cc.case_id', $row->case_id);
                if ($user->role === 'applicant') $comments->where('cc.is_internal', false);
                return [
                    'id' => $row->case_id, 'status' => $row->status, 'progress' => (int) $row->progress, 'priority' => $row->priority,
                    'source' => $row->source, 'candidateId' => $row->candidate_id, 'candidate' => $row->candidate_name,
                    'companyId' => $row->company_id, 'company' => $row->company_name, 'job' => $row->job_title, 'owner' => $row->owner_name,
                    'lastInteractionAt' => $row->last_interaction_at, 'nextActionAt' => $row->next_action_at,
                    'contactsUnlockedAt' => $row->contacts_unlocked_at, 'identitySignal' => $row->identity_signal,
                    'duplicateGroup' => $row->duplicate_group, 'reopenedFrom' => $row->reopened_from_case_id,
                    'participants' => DB::table('case_participants as cp')->join('users as u', 'u.user_id', '=', 'cp.user_id')->where('cp.case_id', $row->case_id)->select('u.username', 'cp.role_in_case', 'cp.can_decide')->get()->map(fn ($p) => ['name' => $p->username, 'role' => $p->role_in_case, 'canDecide' => (bool) $p->can_decide])->all(),
                    'tasks' => DB::table('case_tasks')->where('case_id', $row->case_id)->orderBy('due_at')->get()->map(fn ($t) => ['id' => $t->task_id, 'title' => $t->title, 'status' => $t->status, 'dueAt' => $t->due_at, 'resultScore' => $t->result_score])->all(),
                    'comments' => $comments->orderByDesc('cc.created_at')->limit(5)->select('cc.comment_id', 'cc.body', 'cc.is_internal', 'cc.created_at', 'u.username')->get()->map(fn ($c) => ['id' => $c->comment_id, 'body' => $c->body, 'internal' => (bool) $c->is_internal, 'createdAt' => $c->created_at, 'author' => $c->username])->all(),
                    'events' => DB::table('case_events')->where('case_id', $row->case_id)->orderByDesc('created_at')->limit(6)->get()->map(fn ($e) => ['type' => $e->event_type, 'title' => $e->title, 'details' => $e->details, 'createdAt' => $e->created_at])->all(),
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function alerts(User $user): array
    {
        $query = DB::table('interaction_alerts as ia')->orderBy('ia.is_read')->orderByDesc('ia.created_at')
            ->select('ia.alert_id', 'ia.type', 'ia.severity', 'ia.title', 'ia.is_read', 'ia.created_at', 'ia.case_id');
        if ($user->role !== 'admin') {
            $caseIds = DB::table('interaction_cases');
            $user->role === 'applicant' ? $caseIds->where('candidate_id', $user->getKey()) : $caseIds->where('company_id', $this->companyId($user) ?? -1);
            $visible = $caseIds->pluck('case_id');
            $query->where(function ($q) use ($user, $visible) {
                $q->where('ia.user_id', $user->getKey())->orWhere(function ($global) use ($visible) {
                    $global->whereNull('ia.user_id')->whereIn('ia.case_id', $visible);
                });
            });
        }
        return $query->limit(12)->get()->map(fn ($row) => ['id' => $row->alert_id, 'type' => $row->type, 'severity' => $row->severity, 'title' => $row->title, 'read' => (bool) $row->is_read, 'createdAt' => $row->created_at, 'caseId' => $row->case_id])->all();
    }

    private function companyId(User $user): ?int
    {
        $id = DB::table('company_members')->where('user_id', $user->getKey())->where('active', true)->value('company_id');
        return $id === null ? null : (int) $id;
    }
}
