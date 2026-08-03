<?php

namespace App\Actions;

use App\Models\Notification;
use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers a notification to somebody's devices through Expo's push service.
 *
 * Expo rather than talking to FCM and APNs directly: the client is an Expo app,
 * so it already holds an Expo push token, and one HTTP call reaches both
 * platforms. Expo forwards to FCM and APNs using the credentials configured for
 * the project — which is the part that has to be set up outside this code, and
 * without which every send is accepted and quietly delivers nothing.
 */
class SendPushNotification
{
    protected const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send one notification to every live device its recipient has.
     *
     * Failures are logged, never thrown: a push is a courtesy on top of a
     * notification already written to the database, and the request that
     * triggered it must not fail because a device has gone away.
     */
    public function execute(Notification $notification): void
    {
        $tokens = PushToken::query()
            ->where('user_id', $notification->user_id)
            ->live()
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $messages = array_map(fn (string $token): array => [
            'to' => $token,
            'title' => 'welle',
            'body' => $notification->message,
            'sound' => 'default',
            /*
             * What the app reads when the notification is tapped, to decide
             * where to go. The same two fields the in-app list uses, so the
             * routing rule is written once.
             */
            'data' => [
                'notification_id' => $notification->getKey(),
                'type' => $notification->type,
                'post_id' => $notification->post_id,
                'actor_id' => $notification->actor_id,
            ],
            // Android only: the channel decides importance, sound and whether
            // it appears as a heads-up banner.
            'channelId' => 'default',
        ], $tokens);

        try {
            $response = Http::acceptJson()
                ->timeout(8)
                ->post(self::ENDPOINT, $messages);

            $this->retireDeadTokens($response->json('data') ?? [], $tokens);
        } catch (\Throwable $caught) {
            Log::warning('Push delivery failed', [
                'notification' => $notification->getKey(),
                'error' => $caught->getMessage(),
            ]);
        }
    }

    /**
     * Stop sending to devices Expo says are gone.
     *
     * `DeviceNotRegistered` means the app was uninstalled or the token was
     * reissued. Left alone, those tokens are retried forever and eventually get
     * the project rate limited.
     *
     * @param  array<int, array<string, mixed>>  $receipts
     * @param  list<string>  $tokens
     */
    protected function retireDeadTokens(array $receipts, array $tokens): void
    {
        foreach ($receipts as $index => $receipt) {
            $isDead = ($receipt['status'] ?? null) === 'error'
                && ($receipt['details']['error'] ?? null) === 'DeviceNotRegistered';

            if ($isDead && isset($tokens[$index])) {
                PushToken::query()
                    ->where('token', $tokens[$index])
                    ->update(['failed_at' => now()]);
            }
        }
    }
}
