<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Controller;
use App\Http\Resources\Lead\LeadFollowupResource;
use App\Models\LeadUserDevice;
use App\Services\Lead\LeadFollowupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadFollowupController extends Controller
{
    public function __construct(protected LeadFollowupService $followups)
    {
    }

    /** GET /lead/followups?state=due|overdue|today|upcoming|done|all  (&executive_id for admins) */
    public function index(Request $request)
    {
        /** @var \App\Models\LeadUser $user */
        $user  = $request->user();
        $state = $request->input('state', 'due');
        $today = today()->toDateString();

        $q = $this->followups->queryFor($user);

        if ($user->role === 'super_admin' && $request->filled('executive_id')) {
            $q->whereHas('customer', fn ($c) => $c->where('executive_id', $request->input('executive_id')));
        }

        match ($state) {
            'overdue'  => $q->where('status', 'pending')->whereDate('scheduled_for', '<', $today),
            'today'    => $q->where('status', 'pending')->whereDate('scheduled_for', $today),
            'upcoming' => $q->where('status', 'pending')->whereDate('scheduled_for', '>', $today),
            'done'     => $q->where('status', 'done'),
            'all'      => null,
            default    => $q->where('status', 'pending')->whereDate('scheduled_for', '<=', $today), // due = overdue + today
        };

        $q->orderBy('scheduled_for', $state === 'done' ? 'desc' : 'asc');

        return LeadFollowupResource::collection($q->paginate((int) $request->input('per_page', 50)));
    }

    /** GET /lead/followups/summary */
    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->followups->summary($request->user()));
    }

    /** POST /lead/followups/{id}/complete */
    public function complete(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'outcome'            => 'nullable|string|max:2000',
            'status'             => 'nullable|string|in:new,contacted,follow-up,interested,purchased,closed',
            'next_followup_date' => 'nullable|date_format:Y-m-d|after_or_equal:today',
        ]);

        $followup = $this->followups->complete($request->user(), $id, $data);

        return response()->json(new LeadFollowupResource($followup));
    }

    /** POST /lead/device-token — register this phone for push reminders. */
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => 'required|string|max:255',
            'platform' => 'nullable|string|max:20',
        ]);

        // a token belongs to whoever logged in last on that phone
        LeadUserDevice::updateOrCreate(
            ['expo_token' => $data['token']],
            ['lead_user_id' => $request->user()->id, 'platform' => $data['platform'] ?? null]
        );

        return response()->json(['message' => 'Device registered.']);
    }

    /** DELETE /lead/device-token — stop reminders on this phone (called on logout). */
    public function unregisterDevice(Request $request): JsonResponse
    {
        $token = $request->input('token');
        if ($token) {
            LeadUserDevice::where('expo_token', $token)->where('lead_user_id', $request->user()->id)->delete();
        }

        return response()->json(['message' => 'Device removed.']);
    }
}
