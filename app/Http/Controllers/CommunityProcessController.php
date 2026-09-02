<?php

namespace App\Http\Controllers;

use App\Models\CommunityProcess;
use App\Models\CommunityProcessApplication;
use App\Support\CommunityArea;
use App\Support\CommunityForumCategory;
use App\Services\CommunitySubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommunityProcessController extends Controller
{
    public function update(Request $request, CommunityProcess $process, CommunitySubscriptionService $subscriptions): RedirectResponse
    {
        $this->authorizePersonal($request);
        $process->loadMissing('post');
        $this->authorizeManage($request, $process);

        $validated = $request->validate([
            'applications_enabled' => ['nullable', 'boolean'],
            'applications_start_at' => ['nullable', 'date'],
            'applications_end_at' => ['nullable', 'date', 'after:applications_start_at'],
            'allow_application_edit' => ['nullable', 'boolean'],
            'allow_application_withdraw' => ['nullable', 'boolean'],
            'max_winners' => ['nullable', 'integer', 'min:1', 'max:20'],
            'eligible_statuses' => ['nullable', 'array', 'max:3'],
            'eligible_statuses.*' => [Rule::in(['ACTIVO', 'RESERVA', 'RECLUTA'])],
        ]);

        $applicationsEnabled = $process->type === CommunityProcess::TYPE_CALL
            && $request->boolean('applications_enabled');

        $process->update([
            'applications_enabled' => $applicationsEnabled,
            'applications_start_at' => $validated['applications_start_at'] ?? null,
            'applications_end_at' => $validated['applications_end_at'] ?? null,
            'allow_application_edit' => $request->boolean('allow_application_edit'),
            'allow_application_withdraw' => $request->boolean('allow_application_withdraw'),
            'max_winners' => $validated['max_winners'] ?? null,
            'eligible_statuses' => $validated['eligible_statuses'] ?? ['ACTIVO'],
            'status' => CommunityProcess::statusForNewProcess(
                $applicationsEnabled,
                $validated['applications_start_at'] ?? null,
                $validated['applications_end_at'] ?? null,
            ),
        ]);

        $process->post?->touch();
        if ($process->post) {
            $subscriptions->notifyPost($process->post, $request->user(), 'process_updated');
        }

        return back()->with('status', 'process-updated');
    }

    public function apply(Request $request, CommunityProcess $process, CommunitySubscriptionService $subscriptions): RedirectResponse
    {
        $this->authorizePersonal($request);
        $process->loadMissing('poll', 'post');

        abort_unless($process->canApply($request->user()), 403, 'Las postulaciones no están abiertas para tu estado actual.');

        $validated = $request->validate([
            'application_body' => ['required', 'string', 'min:20', 'max:15000'],
        ]);

        $application = CommunityProcessApplication::query()
            ->where('community_process_id', $process->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($application && ! $process->allow_application_edit && ! $application->isWithdrawn()) {
            return back()->with('status', 'application-locked');
        }

        CommunityProcessApplication::updateOrCreate(
            [
                'community_process_id' => $process->id,
                'user_id' => $request->user()->id,
            ],
            [
                'body' => $validated['application_body'],
                'withdrawn_at' => null,
            ],
        );

        $process->post?->touch();
        if ($process->post) {
            $subscriptions->notifyPost($process->post, $request->user(), 'application_saved');
        }

        return back()->with('status', 'application-saved');
    }

    public function withdraw(Request $request, CommunityProcess $process, CommunitySubscriptionService $subscriptions): RedirectResponse
    {
        $this->authorizePersonal($request);
        abort_unless($process->allow_application_withdraw && $process->applicationsAreOpen(), 403);

        $application = CommunityProcessApplication::query()
            ->where('community_process_id', $process->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $application->update(['withdrawn_at' => now()]);
        $process->post?->touch();
        if ($process->post) {
            $subscriptions->notifyPost($process->post, $request->user(), 'application_withdrawn');
        }

        return back()->with('status', 'application-withdrawn');
    }

    private function authorizeManage(Request $request, CommunityProcess $process): void
    {
        $post = $process->post;

        $categoryKey = $post
            ? CommunityForumCategory::keyForPost($post)
            : CommunityForumCategory::CALL;

        abort_unless(
            $request->user()->hasRole('admin')
            || CommunityForumCategory::can($request->user(), $categoryKey, 'moderate')
            || $process->created_by === $request->user()->id
            || $post?->user_id === $request->user()->id,
            403,
        );
    }

    private function authorizePersonal(Request $request): void
    {
        abort_unless(
            CommunityArea::can($request->user(), CommunityArea::FORUM),
            403,
            'No tienes acceso al Foro.'
        );
    }
}
