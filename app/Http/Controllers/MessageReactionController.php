<?php

namespace App\Http\Controllers;

use App\Events\MessageReactionUpdated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\User;
use App\Support\BestEffortBroadcaster;
use App\Support\MessagePayload;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageReactionController extends Controller
{
    public function __construct(
        private BestEffortBroadcaster $broadcaster,
        private MessagePayload $payload,
    ) {}

    public function store(
        Request $request,
        Server $server,
        Channel $channel,
        Message $message,
    ): JsonResponse {
        $this->ensureMessageBelongsToChannel($server, $channel, $message);
        Gate::authorize('react', $message);

        $validated = $this->validateEmoji($request);

        /** @var User $user */
        $user = $request->user();

        $message->reactions()->firstOrCreate([
            'user_id' => $user->id,
            'emoji' => $validated['emoji'],
        ]);

        return $this->respondWithUpdatedMessage($message);
    }

    public function destroy(
        Request $request,
        Server $server,
        Channel $channel,
        Message $message,
    ): JsonResponse {
        $this->ensureMessageBelongsToChannel($server, $channel, $message);
        Gate::authorize('react', $message);

        $validated = $this->validateEmoji($request);

        /** @var User $user */
        $user = $request->user();

        $message->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $validated['emoji'])
            ->delete();

        return $this->respondWithUpdatedMessage($message);
    }

    /** @return array{emoji: string} */
    private function validateEmoji(Request $request): array
    {
        return $request->validate([
            'emoji' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! $this->isValidReaction($value)) {
                        $fail('絵文字または4文字以内の文字ハンコを指定してください。');
                    }
                },
            ],
        ]);
    }

    private function isValidReaction(string $value): bool
    {
        $isEmoji = preg_match('/^\X$/u', $value) === 1
            && preg_match('/(?:\p{Extended_Pictographic}|\p{Regional_Indicator}|\x{20E3})/u', $value) === 1;

        if ($isEmoji) {
            return true;
        }

        if (preg_match('/^stamp:v1:[0-9a-f]{6}:(?:none|[0-9a-f]{6}):(.+)$/u', $value, $matches) === 1) {
            $stampText = $matches[1];
        } elseif (str_starts_with($value, 'stamp:')) {
            $stampText = substr($value, strlen('stamp:'));
        } else {
            return false;
        }

        return trim($stampText) === $stampText
            && preg_match('/^(?!.*[\p{C}\r\n])(?:\X){1,4}$/u', $stampText) === 1;
    }

    private function respondWithUpdatedMessage(Message $message): JsonResponse
    {
        $message->unsetRelation('reactions');
        $message->load('reactions.user:id,name');
        $this->broadcaster->broadcastToOthers(new MessageReactionUpdated($message));

        return response()->json(['message' => $this->payload->make($message)]);
    }

    private function ensureMessageBelongsToChannel(
        Server $server,
        Channel $channel,
        Message $message,
    ): void {
        abort_unless(
            $channel->server_id === $server->id
                && $message->server_id === $server->id
                && $message->channel_id === $channel->id,
            404,
        );
    }
}
