<?php

namespace App\Console\Commands;

use App\Events\ReminderCreated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Todo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SendDueDateReminders extends Command
{
    protected $signature = 'reminders:send-due {--days-ahead=0 : Send reminders for due dates N days from today}';

    protected $description = 'Send due-date reminders as chat messages for channels and todos';

    public function handle(): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $nowUtc = Carbon::now('UTC');
        $targetDate = Carbon::today()->addDays($daysAhead);
        $sent = 0;

        $sent += $this->remindChannels($targetDate);
        $sent += $this->remindTodos($nowUtc, $daysAhead);

        $this->info("Sent {$sent} due-date reminder(s).");

        return self::SUCCESS;
    }

    private function remindChannels(Carbon $targetDate): int
    {
        $channels = Channel::query()
            ->with('server')
            ->whereDate('ends_on', $targetDate->toDateString())
            ->whereNull('reminded_at')
            ->get();

        $sent = 0;

        foreach ($channels as $channel) {
            $sent += $this->createReminder(
                $channel,
                $channel,
                "⏰ チャンネル「{$channel->name}」の終了期限（".$targetDate->format('Y-m-d').'）です。',
                $this->channelReminderKey($channel),
            );
        }

        return $sent;
    }

    private function remindTodos(Carbon $nowUtc, int $daysAhead): int
    {
        $candidateStart = $nowUtc->copy()->addDays($daysAhead - 2)->startOfDay();
        $candidateEnd = $nowUtc->copy()->addDays($daysAhead + 2)->endOfDay();
        $todos = Todo::query()
            ->with(['channel.server', 'assignee'])
            ->whereBetween('due_at', [$candidateStart, $candidateEnd])
            ->whereNull('completed_at')
            ->whereNull('reminded_at')
            ->get()
            ->filter(function (Todo $todo) use ($nowUtc, $daysAhead): bool {
                if ($todo->due_at === null) {
                    return false;
                }

                $targetDay = $nowUtc->copy()
                    ->setTimezone($todo->due_timezone)
                    ->addDays($daysAhead)
                    ->toDateString();

                return $todo->due_at->copy()
                    ->setTimezone($todo->due_timezone)
                    ->toDateString() === $targetDay;
            });

        $sent = 0;

        foreach ($todos as $todo) {
            $assignee = $todo->assignee?->name !== null ? "（担当: {$todo->assignee->name}）" : '';
            $dueDay = $todo->due_at->copy()->setTimezone($todo->due_timezone)->toDateString();
            $sent += $this->createReminder(
                $todo,
                $todo->channel,
                "⏰ タスク期限: 「{$todo->title}」".$assignee.'（'.$dueDay.'）',
                $this->todoReminderKey($todo),
            );
        }

        return $sent;
    }

    private function createReminder(Channel|Todo $resource, Channel $channel, string $body, string $reminderKey): int
    {
        $created = false;

        DB::transaction(function () use ($resource, $channel, $body, $reminderKey, &$created): void {
            $claimed = $resource instanceof Channel
                ? Channel::query()
                    ->whereKey($resource->getKey())
                    ->whereNull('reminded_at')
                    ->where('ends_on', $resource->getRawOriginal('ends_on'))
                    ->update(['reminded_at' => now()])
                : Todo::query()
                    ->whereKey($resource->getKey())
                    ->whereNull('reminded_at')
                    ->whereNull('completed_at')
                    ->where('due_at', $resource->getRawOriginal('due_at'))
                    ->where('due_timezone', $resource->getRawOriginal('due_timezone'))
                    ->update(['reminded_at' => now()]);

            if ($claimed !== 1) {
                return;
            }

            $message = Message::query()->createOrFirst(
                ['reminder_key' => $reminderKey],
                [
                    'server_id' => $channel->server_id,
                    'channel_id' => $channel->id,
                    'user_id' => null,
                    'parent_id' => null,
                    'body' => $body,
                    'is_reminder' => true,
                ],
            );

            $created = $message->wasRecentlyCreated;

            if ($created) {
                DB::afterCommit(function () use ($message): void {
                    try {
                        broadcast(new ReminderCreated($message));
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
            }
        });

        return $created ? 1 : 0;
    }

    private function channelReminderKey(Channel $channel): string
    {
        if ($channel->ends_on === null) {
            throw new \LogicException('Cannot create a reminder key without a channel deadline.');
        }

        return sprintf('channel:%d:%s', $channel->id, $channel->ends_on->toDateString());
    }

    private function todoReminderKey(Todo $todo): string
    {
        if ($todo->due_at === null) {
            throw new \LogicException('Cannot create a reminder key without a todo deadline.');
        }

        return sprintf('todo:%d:%s:%s', $todo->id, $todo->due_timezone, $todo->due_at->utc()->format('Y-m-d\\TH:i:s.u\\Z'));
    }
}
