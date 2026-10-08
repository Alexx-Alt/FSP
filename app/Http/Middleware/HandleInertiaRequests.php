<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'accountStats' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return null;
                }

                if ($user->role === 'admin') {
                    return [
                        'publications' => DB::table('jobs')->count() + DB::table('resumes')->count(),
                    ];
                }

                $isEmployer = $user->role === 'employer';

                return [
                    'publications' => DB::table($isEmployer ? 'jobs' : 'resumes')
                        ->where($isEmployer ? 'employer_id' : 'user_id', $user->getKey())
                        ->count(),
                ];
            },
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
