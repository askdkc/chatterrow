<?php

namespace App\Console\Commands;

use App\Events\ReminderCreated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Todo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendDueDateReminders extends Command
{
    protected $signature = 'reminders:send-due {--days-ahead=0 : Send reminders for due dates N days from today}';

    protected $description = 'Send due-date reminders as chat messages for channels and todos';

    public function handle(): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $targetDate = Carbon::today()->addDays($daysAhead);
        $sent = 0;

        $sent += $this->remindChannels($targetDate);
        $sent += $this->remindTodos($targetDate);

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
            $this->postReminder(
                $channel,
                "⏰ チャンネル「{$channel->name}」の終了期限（".$targetDate->format('Y-m-d').'）です。',
            );

            $channel->update(['reminded_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    private function remindTodos(Carbon $targetDate): int
    {
        $todos = Todo::query()
            ->with(['channel.server', 'assignee'])
            ->whereDate('due_on', $targetDate->toDateString())
            ->whereNull('completed_at')
            ->whereNull('reminded_at')
            ->get();

        $sent = 0;

        foreach ($todos as $todo) {
            $assignee = $todo->assignee?->name !== null ? "（担当: {$todo->assignee->name}）" : '';
            $this->postReminder(
                $todo->channel,
                "⏰ タスク期限: 「{$todo->title}」".$assignee."（".$targetDate->format('Y-m-d').'）',
            );

            $todo->update(['reminded_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    private function postReminder(Channel $channel, string $body): void
    {
        $message = Message::create([
            'server_id' => $channel->server_id,
            'channel_id' => $channel->id,
            'user_id' => null,
            'parent_id' => null,
            'body' => $body,
            'is_reminder' => true,
        ]);

        broadcast(new ReminderCreated($message));
    }
}
