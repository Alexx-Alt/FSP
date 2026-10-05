<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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
});

require __DIR__.'/settings.php';
