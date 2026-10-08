<?php

use App\Http\Controllers\ApplicationDecisionController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\LiveAssessmentController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

Route::get('/', function () {
    $jobs = DB::table('jobs')
        ->select('job_id', 'company', 'title', 'description', 'location', 'salary_min', 'salary_max', 'skills')
        ->where('status', 'open')
        ->orderByDesc('job_id')
        ->get();

    $resumes = DB::table('resumes')
        ->join('users', 'users.user_id', '=', 'resumes.user_id')
        ->select('resumes.resume_id', 'users.username', 'resumes.title', 'resumes.description', 'resumes.location', 'resumes.salary_expected', 'resumes.skills', 'resumes.experience_years')
        ->orderByDesc('resumes.resume_id')
        ->get();

    return Inertia::render('Welcome', ['jobs' => $jobs, 'resumes' => $resumes]);
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function (Request $request) {
        return $request->user()->role === 'applicant'
            ? redirect()->route('candidate.center')
            : redirect()->route('workspace');
    })->name('dashboard');

    Route::get('/candidate', [CandidateController::class, 'show'])->name('candidate.center');
    Route::post('/candidate/career', [CandidateController::class, 'saveCareer'])->name('candidate.career');
    Route::post('/candidate/assessment', [CandidateController::class, 'submitAssessment'])->name('candidate.assessment');
    Route::patch('/candidate/profile', [CandidateController::class, 'updateProfile'])->name('candidate.profile');

    Route::get('/candidate/assessment/session', [LiveAssessmentController::class, 'show'])->name('candidate.assessment.live');
    Route::post('/candidate/assessment/live/{session}/event', [LiveAssessmentController::class, 'event'])->whereNumber('session')->name('candidate.assessment.live.event');
    Route::post('/candidate/assessment/live/{session}/submit', [LiveAssessmentController::class, 'submit'])->whereNumber('session')->name('candidate.assessment.live.submit');

    Route::post('/interactions/jobs/{job}/apply', [InteractionController::class, 'apply'])->whereNumber('job')->name('interactions.apply');
    Route::post('/interactions/jobs/{job}/candidates/{candidate}/invite', [InteractionController::class, 'invite'])->whereNumber('job')->whereNumber('candidate')->name('interactions.invite');
    Route::post('/interactions/cases/{case}/accept-application', [ApplicationDecisionController::class, 'accept'])->whereNumber('case')->name('interactions.application.accept');

    Route::get('/workspace', [WorkspaceController::class, 'show'])->name('workspace');

    Route::post('/workspace/cases/{case}/comments', [WorkspaceController::class, 'comment'])->whereNumber('case')->name('workspace.cases.comments');
    Route::post('/workspace/cases/{case}/tasks', [WorkspaceController::class, 'addTask'])->whereNumber('case')->name('workspace.cases.tasks');
    Route::post('/workspace/cases/{case}/respond', [WorkspaceController::class, 'respondInvitation'])->whereNumber('case')->name('workspace.cases.respond');
    Route::patch('/workspace/cases/{case}/tasks/{task}', [WorkspaceController::class, 'updateTask'])->whereNumber('case')->whereNumber('task')->name('workspace.cases.tasks.update');
    Route::patch('/workspace/cases/{case}/status', [WorkspaceController::class, 'updateStatus'])->whereNumber('case')->name('workspace.cases.status');

    Route::patch('/dashboard/profile', function (Request $request) {
        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'skills' => ['nullable', 'string', 'max:600'],
            'company' => ['nullable', 'string', 'max:160'],
            'primary_category' => ['nullable', Rule::in(['Backend', 'Frontend', 'Data', 'ML/AI', 'DevOps', 'Full-stack'])],
            'desired_grade' => ['nullable', Rule::in(['intern', 'junior', 'middle', 'senior', 'lead'])],
            'salary_expected' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'work_format' => ['nullable', Rule::in(['office', 'hybrid', 'remote', 'any'])],
        ]);

        if ($request->user()->role !== 'employer') {
            $data['company'] = null;
        }
        if ($request->user()->role !== 'applicant') {
            unset($data['desired_grade'], $data['salary_expected'], $data['work_format']);
        }

        $request->user()->update($data);
        return $request->user()->role === 'applicant'
            ? to_route('candidate.center')
            : to_route('workspace');
    })->name('dashboard.profile');
});

require __DIR__.'/settings.php';
