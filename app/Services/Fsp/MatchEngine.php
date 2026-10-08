<?php

namespace App\Services\Fsp;

use Illuminate\Support\Facades\DB;

class MatchEngine
{
    private const GRADE_WEIGHT = [
        'intern' => 1,
        'junior' => 2,
        'middle' => 3,
        'senior' => 4,
        'lead' => 5,
    ];

    /** @return array<string, mixed> */
    public function compare(int $candidateId, int $jobId): array
    {
        $candidate = DB::table('users')->where('user_id', $candidateId)->first();
        $job = DB::table('jobs')->where('job_id', $jobId)->first();

        if (! $candidate || ! $job) {
            return $this->emptyResult();
        }

        $categoryMatch = $this->normalize($candidate->primary_category) === $this->normalize($job->category);
        $confirmedGrade = $candidate->grade_status === 'confirmed' ? $candidate->confirmed_grade : null;
        $gradeFit = $this->gradeFit($confirmedGrade, $job->grade_required, $candidate->grade_status);

        $confirmedSkills = DB::table('candidate_skill_evidence')
            ->join('skill_nodes', 'skill_nodes.skill_node_id', '=', 'candidate_skill_evidence.skill_node_id')
            ->where('candidate_skill_evidence.candidate_id', $candidateId)
            ->whereIn('candidate_skill_evidence.state', ['confirmed', 'partial'])
            ->pluck('skill_nodes.name')
            ->map(fn ($value) => $this->normalize($value))
            ->all();

        $requiredSkills = $this->csv($job->skills);
        $matchedSkills = array_values(array_filter(
            $requiredSkills,
            fn ($skill) => in_array($this->normalize($skill), $confirmedSkills, true)
        ));
        $missingSkills = array_values(array_diff($requiredSkills, $matchedSkills));
        $stackCoverage = count($requiredSkills) === 0 ? 1.0 : count($matchedSkills) / count($requiredSkills);

        $salaryOverlap = $this->salaryOverlap(
            (int) ($candidate->salary_expected ?? 0),
            (int) ($job->salary_min ?? 0),
            (int) ($job->salary_max ?? 0)
        );

        $formatMatch = empty($candidate->work_format)
            || empty($job->work_format)
            || $candidate->work_format === $job->work_format
            || $candidate->work_format === 'any';

        $fspBoost = (float) DB::table('fsp_achievements')
            ->where('candidate_id', $candidateId)
            ->where('verified', true)
            ->avg('relevance_score');
        $fspBoost = min(0.05, max(0, $fspBoost / 1000));

        $score = 0;
        $score += $categoryMatch ? 30 : 0;
        $score += 25 * $gradeFit;
        $score += 30 * $stackCoverage;
        $score += $salaryOverlap ? 8 : 0;
        $score += $formatMatch ? 7 : 0;
        $score = min(100, round($score * (1 + $fspBoost)));

        $route = 'observe';
        if ($categoryMatch && $gradeFit >= 0.85 && $stackCoverage >= 0.70 && $salaryOverlap && $formatMatch) {
            $route = 'invite';
        } elseif ($categoryMatch && $gradeFit >= 0.65 && $stackCoverage >= 0.50) {
            $route = 'clarify';
        } elseif ($categoryMatch && $stackCoverage >= 0.35) {
            $route = 'perspective';
        }

        $reasons = [
            $categoryMatch ? 'Категория совпадает' : 'Категория отличается',
            $candidate->grade_status === 'confirmed'
                ? 'Грейд подтверждён assessment'
                : 'Грейд не подтверждён — профиль понижен в ранжировании',
            'Покрытие обязательного стека: '.round($stackCoverage * 100).'%',
            $salaryOverlap ? 'Зарплатные ожидания пересекаются' : 'Есть разрыв по зарплате',
            $formatMatch ? 'Формат работы совместим' : 'Формат работы не совпадает',
        ];

        return [
            'candidateId' => $candidateId,
            'jobId' => $jobId,
            'score' => $score,
            'route' => $route,
            'categoryMatch' => $categoryMatch,
            'gradeFit' => round($gradeFit, 2),
            'stackCoverage' => round($stackCoverage, 2),
            'salaryOverlap' => $salaryOverlap,
            'formatMatch' => $formatMatch,
            'fspBoost' => round($fspBoost, 3),
            'matchedSkills' => $matchedSkills,
            'missingSkills' => $missingSkills,
            'reasons' => $reasons,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function topJobsForCandidate(int $candidateId, int $limit = 5): array
    {
        return DB::table('jobs')
            ->where('status', 'open')
            ->pluck('job_id')
            ->map(fn ($jobId) => $this->enrichWithJob($this->compare($candidateId, (int) $jobId)))
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function topCandidatesForEmployer(int $employerId, int $limit = 8): array
    {
        $jobIds = DB::table('jobs')->where('employer_id', $employerId)->where('status', 'open')->pluck('job_id');
        if ($jobIds->isEmpty()) {
            return [];
        }

        $candidateIds = DB::table('users')->where('role', 'applicant')->pluck('user_id');
        $rows = collect();
        foreach ($candidateIds as $candidateId) {
            foreach ($jobIds as $jobId) {
                $rows->push($this->enrichWithCandidate($this->compare((int) $candidateId, (int) $jobId)));
            }
        }

        return $rows->sortByDesc('score')->unique(fn ($row) => $row['candidateId'])->take($limit)->values()->all();
    }

    /** @param array<string, mixed> $result */
    private function enrichWithJob(array $result): array
    {
        $job = DB::table('jobs')
            ->leftJoin('companies', 'companies.company_id', '=', 'jobs.company_id')
            ->where('jobs.job_id', $result['jobId'])
            ->select('jobs.*', 'companies.name as company_name')
            ->first();

        return [
            ...$result,
            'title' => $job?->title,
            'company' => $job?->company_name ?? $job?->company,
            'city' => $job?->location,
            'grade' => $job?->grade_required,
            'salaryMin' => $job?->salary_min,
            'salaryMax' => $job?->salary_max,
        ];
    }

    /** @param array<string, mixed> $result */
    private function enrichWithCandidate(array $result): array
    {
        $candidate = DB::table('users')->where('user_id', $result['candidateId'])->first();
        $job = DB::table('jobs')->where('job_id', $result['jobId'])->first();

        return [
            ...$result,
            'candidateName' => $candidate?->username,
            'headline' => $candidate?->headline,
            'city' => $candidate?->city,
            'confirmedGrade' => $candidate?->confirmed_grade,
            'gradeStatus' => $candidate?->grade_status,
            'jobTitle' => $job?->title,
        ];
    }

    private function gradeFit(?string $candidateGrade, ?string $requiredGrade, ?string $status): float
    {
        if ($status !== 'confirmed' || ! $candidateGrade) {
            return 0.55;
        }
        $candidate = self::GRADE_WEIGHT[$this->normalize($candidateGrade)] ?? 0;
        $required = self::GRADE_WEIGHT[$this->normalize($requiredGrade)] ?? 0;
        if ($candidate === 0 || $required === 0) {
            return 0.6;
        }
        if ($candidate >= $required) {
            return 1.0;
        }
        return max(0.35, 1 - (($required - $candidate) * 0.25));
    }

    private function salaryOverlap(int $expected, int $min, int $max): bool
    {
        if ($expected <= 0 || ($min <= 0 && $max <= 0)) {
            return true;
        }
        if ($max <= 0) {
            return $expected >= $min * 0.8;
        }
        return $expected <= $max * 1.15 && $expected >= $min * 0.75;
    }

    /** @return array<int, string> */
    private function csv(?string $value): array
    {
        return $value ? array_values(array_filter(array_map('trim', explode(',', $value)))) : [];
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /** @return array<string, mixed> */
    private function emptyResult(): array
    {
        return [
            'candidateId' => null, 'jobId' => null, 'score' => 0, 'route' => 'observe',
            'categoryMatch' => false, 'gradeFit' => 0, 'stackCoverage' => 0,
            'salaryOverlap' => false, 'formatMatch' => false, 'fspBoost' => 0,
            'matchedSkills' => [], 'missingSkills' => [], 'reasons' => ['Недостаточно данных для расчёта'],
        ];
    }
}
