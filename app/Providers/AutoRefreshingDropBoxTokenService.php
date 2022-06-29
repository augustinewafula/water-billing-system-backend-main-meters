<?php

namespace App\Providers;

use GuzzleHttp\Exception\ClientException;
use Http;
use JsonException;
use Spatie\Dropbox\RefreshableTokenProvider;

class AutoRefreshingDropBoxTokenService implements RefreshableTokenProvider
{

    /**
     * @var string
     */
    private string $app_key;
    /**
     * @var string
     */
    private string $app_secret;
    /**
     * @var string
     */
    private string $refresh_token;
    /**
     * @var string
     */
    private string $access_token;

    public function __construct(string $app_key, string $app_secret, string $refresh_token, string $access_token)
    {
        $this->app_key = $app_key;
        $this->app_secret = $app_secret;
        $this->refresh_token = $refresh_token;
        $this->access_token = $access_token;
    }


    /**
     * @throws JsonException
     */
    public function refresh(ClientException $exception): bool
    {
        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->app_key . ':' . $this->app_secret),
            ])->post('https://api.dropbox.com/oauth2/token', [
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token'
            ]);
        \Log::info($response->body());

        if ($response->status() !== 200) {
            return false; // wrong response
        }

        $response_body = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        $this->access_token = $response_body['access_token']; // the new one

        return true; // it was refreshed
    }

    public function getToken(): string
    {
        return $this->access_token;
    }
}
