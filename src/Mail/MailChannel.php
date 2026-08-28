<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Mail;

use Elibardev\NotificationOrchestrator\Channels\ChannelDelivery;
use Elibardev\NotificationOrchestrator\Channels\ChannelHealth;
use Elibardev\NotificationOrchestrator\Channels\DeliveryResult;
use Elibardev\NotificationOrchestrator\Channels\DeliveryStatus;
use Elibardev\NotificationOrchestrator\Channels\HealthStatus;
use Elibardev\NotificationOrchestrator\Configuration\Configuration;
use Elibardev\NotificationOrchestrator\Contracts\NotificationChannel;
use Elibardev\NotificationOrchestrator\Exceptions\ChannelConfigurationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Mail\Factory;

final class MailChannel implements NotificationChannel
{
    public function __construct(private Factory $mail, private Configuration $config, private Repository $application) {}

    public function name(): string
    {
        return 'mail';
    }

    public function validateConfiguration(): void
    {
        $mailer = $this->config->get('mail.mailer') ?? $this->application->get('mail.default');
        if (! is_string($mailer) || ! is_array($this->application->get('mail.mailers.'.$mailer))) {
            throw new ChannelConfigurationException('A Laravel mailer is required.');
        }
        $this->mail->mailer($mailer);
    }

    public function health(): ChannelHealth
    {
        return new ChannelHealth(HealthStatus::HEALTHY);
    }

    public function send(ChannelDelivery $delivery): DeliveryResult
    {
        foreach ($delivery->channelPlan->destinations as $destination) {
            $this->mail->mailer($this->config->get('mail.mailer'))->to($destination->value)->send(new OrchestratedMail($delivery->recipientPlan->payload));
        }

        return new DeliveryResult('mail', DeliveryStatus::SENT, 'laravel');
    }
}
