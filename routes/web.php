<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\Request;

Route::get('/', function () {
    // Получаем вакансии
    $jobs = DB::table('jobs')
        ->select(
            'job_id',
            'company',
            'title',
            'description',
            'location',
            'salary_min',
            'salary_max',
            'skills'
        )
        ->orderByDesc('job_id')
        ->get();

    // Получаем резюме с именами
    $resumes = DB::table('resumes')
        ->join('users', 'users.user_id', '=', 'resumes.user_id')
        ->select(
            'resumes.resume_id',
            'users.username',
            'resumes.title',
            'resumes.description',
            'resumes.location',
            'resumes.salary_expected',
            'resumes.skills',
            'resumes.experience_years'
        )
        ->orderByDesc('resumes.resume_id')
        ->get();

    // Передаём данные странице
    return Inertia::render('Welcome', [
        'jobs' => $jobs,
        'resumes' => $resumes,
    ]);
})->name('home');

// Кабинет доступен после входа
Route::middleware('auth')->group(function () {
    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');

    // Сохраняем собственный профиль
    Route::patch('/dashboard/profile', function (Request $request) {
        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'skills' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:100'],
        ], [
            'headline.max' => 'Максимум 150 символов.',
            'city.max' => 'Максимум 100 символов.',
            'bio.max' => 'Максимум 3000 символов.',
            'skills.max' => 'Максимум 255 символов.',
            'company.max' => 'Максимум 100 символов.',
        ]);

        // Компания доступна работодателю
        if ($request->user()->role !== 'employer') {
            $data['company'] = null;
        }

        $request->user()->update($data);

        return to_route('dashboard');
    })->name('dashboard.profile');
});

require __DIR__.'/settings.php';
