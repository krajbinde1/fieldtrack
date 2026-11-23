<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FCM HTTP v1 sender. In-app notifications are stored even when this is not configured.
 */
final class FcmHttpClient
{
    public function isConfigured(): bool
    {
        if (! config('firebase.enabled', false)) {
            return false;
        }

        $projectId = (string) config('firebase.project_id');
        $credentials = (string) config('firebase.credentials');

        return $projectId !== '' && is_file($credentials);
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, string>  $notification
     * @param  array<string, string>  $data
     */
    public function sendToTokens(array $tokens, array $notification, array $data = []): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === [] || ! $this->isConfigured()) {
            return;
        }

        try {
            $accessToken = $this->accessToken();
            $projectId = (string) config('firebase.project_id');
            foreach ($tokens as $token) {
                Http::withToken($accessToken)
                    ->acceptJson()
                    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                        'message' => [
                            'token' => $token,
                            'notification' => $notification,
                            'data' => $data,
                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => [
                                    'channel_id' => 'fieldtrack_expenses',
                                ],
                            ],
                        ],
                    ])
                    ->throw();
            }
        } catch (Throwable $exception) {
            Log::warning('Expense push notification was not delivered.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function accessToken(): string
    {
        $credentials = json_decode((string) file_get_contents((string) config('firebase.credentials')), true);
        $now = time();
        $header = $this->base64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = $this->base64(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = $header.'.'.$claim;
        openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = $unsigned.'.'.$this->base64($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ])->throw();

        return (string) $response->json('access_token');
    }

    private function base64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
