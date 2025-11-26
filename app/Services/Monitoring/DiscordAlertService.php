<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordAlertService
{
    public function send(string $message): void
    {
        if (! config('services.discord.alert_enabled')) return;
        
        try {
            Http::post(config('services.discord.alert_webhook'), ['content' => $message]);
        } catch (\Throwable $e) {
            Log::error('Discord alert failed', ['error' => $e->getMessage()]);
        }
    }
}
