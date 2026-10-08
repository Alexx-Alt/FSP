<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CandidateSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);
        abort_unless(Schema::hasColumn('users', 'profile_visibility'), 422, 'Выполните database/fsp_match_v6/06_mega_update_compliance.sql');

        return Inertia::render('CandidateSettings', [
            'settings' => [
                'visibility' => $user->profile_visibility ?? 'employers',
                'consentProcessing' => (bool) $user->consent_processing,
                'consentPublication' => (bool) $user->consent_publication,
                'fspId' => $user->fsp_id_external,
                'fspLinked' => (bool) $user->fsp_id_linked,
                'emailVerified' => $user->email_verified_at !== null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);
        abort_unless(Schema::hasColumn('users', 'profile_visibility'), 422, 'Выполните database/fsp_match_v6/06_mega_update_compliance.sql');

        $data = $request->validate([
            'profile_visibility' => ['required', Rule::in(['employers', 'private'])],
            'consent_processing' => ['required', 'boolean'],
            'consent_publication' => ['required', 'boolean'],
            'fsp_id_external' => ['nullable', 'string', 'max:120'],
        ]);
        $data['fsp_id_linked'] = filled($data['fsp_id_external'] ?? null);
        $user->update($data);

        return back()->with('success', 'Настройки приватности и FSP ID сохранены.');
    }
}
