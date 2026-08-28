<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Persistence;

use Elibardev\NotificationOrchestrator\Configuration\TableNameResolver;
use Illuminate\Database\Schema\Blueprint;

final class PackageSchema
{
    public function __construct(private Storage $storage, private TableNameResolver $names) {}

    public function create(string $name): void
    {
        $tableName = $this->names->for($name);
        $this->storage->connection()->getSchemaBuilder()->create($tableName, function (Blueprint $table) use ($name) {
            if ($name === 'notifications') {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->string('notifiable_type');
                $table->string('notifiable_id');
                $table->text('data');
                $table->timestamp('read_at', 6)->nullable();
                $table->timestamps(6);
                $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notify_inbox_owner_read');
                $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notify_inbox_owner_created');
                $table->index('created_at', 'notify_inbox_created');
            } elseif ($name === 'preferences') {
                $table->string('id', 64)->primary();
                $table->string('notifiable_type');
                $table->string('notifiable_id');
                $table->string('notification_type')->nullable();
                $table->string('channel');
                $table->boolean('enabled');
                $table->timestamps(6);
                $table->index(['notifiable_type', 'notifiable_id'], 'notify_preferences_owner');
            } elseif ($name === 'devices') {
                $table->uuid('id')->primary();
                $table->string('notifiable_type');
                $table->string('notifiable_id');
                $table->string('driver', 64);
                $table->string('platform', 32);
                $table->uuid('device_identifier')->nullable();
                $table->text('token');
                $table->string('token_hash', 64);
                $table->string('name')->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamp('last_used_at', 6)->nullable();
                $table->timestamp('invalidated_at', 6)->nullable();
                $table->text('metadata')->nullable();
                $table->timestamps(6);
                $table->unique(['driver', 'token_hash'], 'notify_device_token');
                $table->unique(['driver', 'device_identifier'], 'notify_device_installation');
                $table->index(['notifiable_type', 'notifiable_id'], 'notify_device_owner');
                $table->index('invalidated_at', 'notify_device_invalidated');
            } elseif ($name === 'deliveries') {
                $table->string('id', 64)->primary();
                $table->uuid('notification_id')->index('notify_delivery_notification');
                $table->uuid('correlation_id')->index('notify_delivery_correlation');
                $table->string('notifiable_type');
                $table->string('notifiable_id');
                $table->string('channel');
                $table->string('driver');
                $table->string('provider')->nullable();
                $table->string('destination_hash', 64);
                $table->string('destination_label')->nullable();
                $table->string('status', 20);
                $table->string('skip_reason', 32)->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedInteger('max_attempts')->nullable();
                $table->string('provider_reference')->nullable();
                foreach (['planned_at', 'queued_at', 'processing_at', 'sent_at', 'delivered_at', 'failed_at'] as $column) {
                    $table->timestamp($column, 6)->nullable();
                }
                $table->string('last_error_code')->nullable();
                $table->string('last_error_message')->nullable();
                $table->text('metadata')->nullable();
                $table->timestamps(6);
                $table->index(['notifiable_type', 'notifiable_id'], 'notify_delivery_owner');
                $table->index(['channel', 'status'], 'notify_delivery_channel_status');
                $table->index(['status', 'created_at'], 'notify_delivery_status_created');
                $table->index('provider_reference', 'notify_delivery_reference');
            } else {
                throw new \InvalidArgumentException('Unsupported package migration.');
            }
        });
    }

    public function drop(string $name): void
    {
        $this->storage->connection()->getSchemaBuilder()->dropIfExists($this->names->for($name));
    }
}
