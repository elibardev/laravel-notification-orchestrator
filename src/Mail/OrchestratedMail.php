<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Mail;

use Elibardev\NotificationOrchestrator\NotificationPayload;
use Illuminate\Mail\Mailable;

final class OrchestratedMail extends Mailable
{
    public function __construct(public readonly NotificationPayload $payload) {}

    public function build(): static
    {
        $html = '<h1>'.e($this->payload->title).'</h1><p>'.nl2br(e($this->payload->message)).'</p>';
        foreach ($this->payload->actions as $action) {
            if ($action->type === 'navigate' && $action->url !== null) {
                $html .= '<p><a href="'.e($action->url).'">'.e($action->label).'</a></p>';
            }
        }

        return $this->subject($this->payload->title)->html($html);
    }
}
