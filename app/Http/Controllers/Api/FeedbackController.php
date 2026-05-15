<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Receives in-app feedback / support requests and emails them to the
 * support inbox. No DB row — keep it simple; the inbox is the source of
 * truth.
 */
class FeedbackController extends Controller
{
    /**
     * POST /api/feedback
     *
     * Body:
     *   - type:        "support" | "audit"
     *   - message:     non-empty text (required)
     *   - rating:      1..5 (optional, used for audit feedback)
     *   - analysis_id: int (optional, links audit feedback to a specific run)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'        => ['required', 'string', Rule::in(['support', 'audit'])],
            'message'     => ['required', 'string', 'min:3', 'max:5000'],
            'rating'      => ['nullable', 'integer', 'min:1', 'max:5'],
            'analysis_id' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid request.', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $type = (string) $request->string('type');
        $message = (string) $request->string('message');
        $rating = $request->filled('rating') ? (int) $request->integer('rating') : null;
        $analysisId = $request->filled('analysis_id') ? (int) $request->integer('analysis_id') : null;

        $supportEmail = config('codereview.support_email', 'hello@qodeshark.com');
        $brand        = config('codereview.brand_name', 'QodeShark');

        $subject = $type === 'audit'
            ? "[$brand] Audit feedback".($rating !== null ? " — {$rating}/5" : '')
            : "[$brand] Support request from {$user->email}";

        $body = $this->renderBody($user, $type, $message, $rating, $analysisId);

        try {
            Mail::raw($body, function ($mail) use ($supportEmail, $subject, $user) {
                $mail->to($supportEmail)
                     ->replyTo($user->email, $user->name)
                     ->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Feedback email failed', [
                'user_id' => $user->id,
                'type'    => $type,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'message' => "Couldn't send right now. Email us at {$supportEmail} instead.",
            ], 500);
        }

        return response()->json(['ok' => true]);
    }

    private function renderBody($user, string $type, string $message, ?int $rating, ?int $analysisId): string
    {
        $lines = [];
        $lines[] = 'From: '.$user->name.' <'.$user->email.'>';
        $lines[] = 'User ID: #'.$user->id;
        $lines[] = 'Type: '.$type;
        if ($rating !== null) {
            $lines[] = 'Rating: '.$rating.'/5';
        }
        if ($analysisId !== null) {
            $lines[] = 'Analysis: #'.$analysisId;
        }
        $lines[] = 'Submitted: '.now()->toDayDateTimeString().' UTC';
        $lines[] = str_repeat('—', 40);
        $lines[] = '';
        $lines[] = $message;

        return implode("\n", $lines);
    }
}
