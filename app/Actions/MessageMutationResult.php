<?php

namespace App\Actions;

use App\Models\Message;
use App\Models\MessageMention;
use Illuminate\Database\Eloquent\Collection;

final readonly class MessageMutationResult
{
    /**
     * @param  Collection<int, MessageMention>  $newMentions
     */
    public function __construct(
        public Message $message,
        public Collection $newMentions,
    ) {}
}
