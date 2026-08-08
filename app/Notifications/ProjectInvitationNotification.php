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

        $projectName = $this->invitation->server->name;
        $inviterName = $this->invitation->invited_by === null
            ? __('Project administrator')
            : $this->invitation->inviter->name;
        $url = $this->hasAccount
            ? route('servers.index')
            : route('register', ['invitation' => $this->plainToken]);

        return (new MailMessage)
            ->subject(__('Project invitation for :project', ['project' => $projectName]))
            ->greeting(__('You have received a project invitation'))
            ->line(__(':inviter invited you to join :project.', [
                'inviter' => $inviterName,
                'project' => $projectName,
            ]))
            ->line($this->hasAccount
                ? __('After logging in, choose whether to join or decline the invitation in the project list.')
                : __('After creating your account, choose whether to join or decline the invitation in the project list.'))
            ->action($this->hasAccount ? __('Review invitation') : __('Create account'), $url)
            ->line(__('If you did not expect this invitation, you can ignore this email.'));
    }
}
