<?php

namespace App\Notifications;

use App\Models\ServerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ServerInvitation $invitation,
        public readonly string $plainToken,
        public readonly bool $hasAccount,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->invitation->loadMissing(['server:id,name', 'inviter:id,name']);

        $inviterName = $this->invitation->invited_by === null
            ? 'プロジェクト管理者'
            : $this->invitation->inviter->name;
        $url = $this->hasAccount
            ? route('servers.index')
            : route('register', ['invitation' => $this->plainToken]);

        return (new MailMessage)
            ->subject("「{$this->invitation->server->name}」への招待")
            ->greeting('プロジェクトへの招待が届きました')
            ->line("{$inviterName}さんから「{$this->invitation->server->name}」へ招待されています。")
            ->line($this->hasAccount
                ? 'ログイン後、プロジェクト一覧で参加または辞退を選択してください。'
                : 'アカウントを作成すると、プロジェクト一覧で参加または辞退を選択できます。')
            ->action($this->hasAccount ? '招待を確認する' : 'アカウントを作成する', $url)
            ->line('心当たりがない場合は、このメールを無視してください。');
    }
}
