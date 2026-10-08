<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployerJobsController extends Controller
{
    private const CATEGORIES = ['Backend', 'Frontend', 'Data', 'ML/AI', 'DevOps', 'Full-stack'];
    private const GRADES = ['intern', 'junior', 'middle', 'senior', 'lead'];

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'employer', 403);

        $company = $this->company($user);
        $jobs = DB::table('jobs')
            ->where('employer_id', $user->getKey())
            ->orderByRaw("FIELD(status, 'open', 'draft', 'paused', 'closed')")
            ->orderByDesc('job_id')
            ->get()
            ->map(fn ($job) => [
                'id' => (int) $job->job_id,
                'title' => $job->title,
                'description' => $job->description,
                'location' => $job->location,
                'salaryMin' => (int) ($job->salary_min ?? 0),
                'salaryMax' => (int) ($job->salary_max ?? 0),
                'skills' => $job->skills,
                'category' => $job->category,
                'grade' => $job->grade_required,
                'format' => $job->work_format,
                'status' => $job->status,
                'createdAt' => $job->created_at,
                'applications' => DB::table('interaction_cases')->where('job_id', $job->job_id)->where('source', 'self_application')->count(),
                'invites' => DB::table('interaction_cases')->where('job_id', $job->job_id)->where('source', 'match_invite')->count(),
            ])
            ->all();

        return Inertia::render('EmployerJobs', [
            'company' => $company,
            'jobs' => $jobs,
            'categories' => self::CATEGORIES,
            'grades' => self::GRADES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'employer', 403);
        $data = $this->validated($request);
        $company = $this->company($user);

        DB::table('jobs')->insert([
            'employer_id' => $user->getKey(),
            'company_id' => $company['id'],
            'company' => $company['name'],
            'title' => $data['title'],
            'description' => $data['description'],
            'location' => $data['location'],
            'salary_min' => $data['salary_min'],
            'salary_max' => $data['salary_max'],
            'skills' => $data['skills'],
            'category' => $data['category'],
            'grade_required' => $data['grade_required'],
            'work_format' => $data['work_format'],
            'status' => $data['status'],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Вакансия сохранена. Подбор можно проверять сразу.');
    }

    public function update(Request $request, int $job): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'employer', 403);
        $this->ownedJob($user, $job);
        $data = $this->validated($request);

        DB::table('jobs')->where('job_id', $job)->update([
            'title' => $data['title'],
            'description' => $data['description'],
            'location' => $data['location'],
            'salary_min' => $data['salary_min'],
            'salary_max' => $data['salary_max'],
            'skills' => $data['skills'],
            'category' => $data['category'],
            'grade_required' => $data['grade_required'],
            'work_format' => $data['work_format'],
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Вакансия обновлена. Подбор пересчитается на актуальных данных.');
    }

    public function status(Request $request, int $job): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'employer', 403);
        $this->ownedJob($user, $job);
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'open', 'paused', 'closed'])]]);
        DB::table('jobs')->where('job_id', $job)->update(['status' => $data['status']]);
        return back()->with('success', 'Статус вакансии изменён.');
    }

    public function destroy(Request $request, int $job): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'employer', 403);
        $row = $this->ownedJob($user, $job);
        $hasCases = DB::table('interaction_cases')->where('job_id', $job)->exists();
        if ($hasCases) {
            DB::table('jobs')->where('job_id', $job)->update(['status' => 'closed']);
            return back()->with('success', 'У вакансии есть история взаимодействий, поэтому она закрыта, а не удалена.');
        }
        DB::table('jobs')->where('job_id', $row->job_id)->delete();
        return back()->with('success', 'Черновик вакансии удалён.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'location' => ['required', 'string', 'max:100'],
            'salary_min' => ['required', 'integer', 'min:1', 'max:3000000'],
            'salary_max' => ['required', 'integer', 'gte:salary_min', 'max:3000000'],
            'skills' => ['required', 'string', 'min:2', 'max:600'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'grade_required' => ['required', Rule::in(self::GRADES)],
            'work_format' => ['required', Rule::in(['office', 'hybrid', 'remote', 'any'])],
            'status' => ['required', Rule::in(['draft', 'open', 'paused', 'closed'])],
        ]);
    }

    /** @return array{id:?int,name:string,city:?string,quality:?float} */
    private function company(User $user): array
    {
        $company = DB::table('company_members as m')
            ->join('companies as c', 'c.company_id', '=', 'm.company_id')
            ->where('m.user_id', $user->getKey())
            ->where('m.active', true)
            ->select('c.*')
            ->first();

        return [
            'id' => $company ? (int) $company->company_id : null,
            'name' => $company?->name ?? $user->company ?? 'Компания',
            'city' => $company?->city,
            'quality' => $company?->hiring_quality_score === null ? null : (float) $company->hiring_quality_score,
        ];
    }

    private function ownedJob(User $user, int $job): object
    {
        $row = DB::table('jobs')->where('job_id', $job)->where('employer_id', $user->getKey())->first();
        abort_unless($row, 404);
        return $row;
    }
}
