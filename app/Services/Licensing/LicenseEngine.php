<?php

namespace App\Services\Licensing;

use App\Models\License;
use App\Models\LicenseLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use App\Services\Monitoring\DiscordAlertService;

class LicenseEngine
{
    const STATUS_VALID = 'VALID';
    const STATUS_INVALID = 'INVALID';
    const STATUS_EXPIRED = 'EXPIRED';
    const STATUS_REVOKED = 'REVOKED';
    const STATUS_VIOLATION = 'VIOLATION';
    const STATUS_KILL_SWITCH = 'KILL_SWITCH';

    public function generateKey(int $productId, int $userId): string
    {
        return strtoupper(sprintf('CARL-%d-%d-%s', $productId, $userId, Str::random(16)));
    }

    public function createLicense(User $user, Product $product, string $type = 'monthly'): License
    {
        $expiresAt = match ($type) {
            'monthly'  => now()->addDays(30),
            'yearly'   => now()->addDays(365),
            'lifetime' => null,
            default    => now()->addDays(30),
        };

        return License::create([
            'user_id'      => $user->id,
            'product_id'   => $product->id,
            'license_key'  => $this->generateKey($product->id, $user->id),
            'type'         => $type,
            'status'       => 'active',
            'activated_at' => now(),
            'expires_at'   => $expiresAt,
        ]);
    }

    public function validate(string $key, ?string $fingerprint, ?string $ip): array
    {
        if (config('settings.kill_switch_enabled')) {
            return ['status' => self::STATUS_KILL_SWITCH, 'license' => null];
        }

        $license = License::where('license_key', $key)->first();

        if (! $license) {
            return ['status' => self::STATUS_INVALID, 'license' => null];
        }

        if ($license->status === 'revoked') {
            $this->log($license, $ip, $fingerprint, self::STATUS_REVOKED);
            return ['status' => self::STATUS_REVOKED, 'license' => $license];
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            $license->update(['status' => 'expired']);
            $this->log($license, $ip, $fingerprint, self::STATUS_EXPIRED);
            return ['status' => self::STATUS_EXPIRED, 'license' => $license];
        }

        if ($license->fingerprint && $license->fingerprint !== $fingerprint) {
            $license->update(['status' => 'revoked']);
            $this->log($license, $ip, $fingerprint, self::STATUS_VIOLATION, 'Fingerprint mismatch');
            return ['status' => self::STATUS_VIOLATION, 'license' => $license];
        }

        if (! $license->fingerprint && $fingerprint) {
            $license->update(['fingerprint' => $fingerprint]);
        }

        $license->update([
            'last_validated_at' => now(),
            'active_sessions' => $license->active_sessions + 1
        ]);

        $this->log($license, $ip, $fingerprint, self::STATUS_VALID);

        return ['status' => self::STATUS_VALID, 'license' => $license];
    }

    protected function log(License $license, ?string $ip, ?string $fingerprint, string $status, ?string $message = null): void
    {
        LicenseLog::create([
            'license_id'  => $license->id,
            'ip'          => $ip,
            'fingerprint' => $fingerprint,
            'status'      => $status,
            'message'     => $message,
        ]);

        if ($status === self::STATUS_VIOLATION) {
             // Discord alert logic here
             app(DiscordAlertService::class)->send('Violation detected for license ' . $license->id);
        }
    }
}
