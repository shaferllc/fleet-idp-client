<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Console;

use Fleet\IdpClient\FleetIdpOAuth;
use Fleet\IdpClient\Support\EnvFileWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ConfigureFleetIdpCommand extends Command
{
    protected $signature = 'fleet:idp:configure
                            {--url= : Fleet Auth base URL (or config app.url on IdP side is returned; use full IdP root e.g. https://fleet-auth.test)}
                            {--token= : Bearer token matching FLEET_AUTH_CLI_SETUP_TOKEN on Fleet Auth}
                            {--name=Waypost : Integration name (Passport client names in Fleet Auth)}
                            {--redirect=* : OAuth redirect URI(s); default APP_URL + /oauth/fleet-auth/callback}
                            {--client-url= : Client app base URL for TrustedClientSite (default APP_URL)}
                            {--no-rotate : Merge redirect URIs only; do not rotate existing secrets}
                            {--dry-run : Print JSON only; do not write .env}
                            {--env-file=.env : Path to .env relative to project base path}';

    protected $description = 'Call Fleet Auth /api/cli/setup and merge IdP credentials into .env';

    public function handle(): int
    {
        $baseUrl = $this->option('url') ?: $this->ask('Fleet Auth base URL (e.g. https://fleet-auth.test)');
        $baseUrl = is_string($baseUrl) ? rtrim(trim($baseUrl), '/') : '';
        if ($baseUrl === '') {
            $this->error('Fleet Auth URL is required.');

            return self::FAILURE;
        }

        $token = $this->option('token') ?: $this->secret('FLEET_AUTH_CLI_SETUP_TOKEN (from Fleet Auth .env)');
        if (! is_string($token) || $token === '') {
            $this->error('CLI setup token is required.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: 'Waypost');
        $rotate = ! $this->option('no-rotate');

        $appUrl = (string) ($this->option('client-url') ?: config('app.url', ''));
        $appUrl = rtrim(trim($appUrl), '/');

        /** @var array<int, string> $redirects */
        $redirects = $this->option('redirect') ?: [];
        if ($redirects === []) {
            if ($appUrl === '') {
                $this->error('Set APP_URL or pass --client-url / --redirect.');

                return self::FAILURE;
            }
            $redirects = [$appUrl.'/oauth/fleet-auth/callback'];
        }

        $endpoint = $baseUrl.'/api/cli/setup';

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withOptions(FleetIdpOAuth::redirectPreservingPostOptions())
                ->post($endpoint, [
                    'integration_name' => $name,
                    'redirect_uris' => $redirects,
                    'client_base_url' => $appUrl !== '' ? $appUrl : null,
                    'rotate_secrets' => $rotate,
                ]);
        } catch (\Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Fleet Auth responded HTTP '.$response->status());
            $this->line($response->body());

            return self::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        if ($this->option('dry-run')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $idpUrl = (string) ($data['fleet_idp_url'] ?? $baseUrl);

        /** @var array<string, mixed> $oauth */
        $oauth = is_array($data['oauth_redirect'] ?? null) ? $data['oauth_redirect'] : [];
        /** @var array<string, mixed> $password */
        $password = is_array($data['password_grant'] ?? null) ? $data['password_grant'] : [];
        /** @var array<string, mixed> $prov */
        $prov = is_array($data['provisioning'] ?? null) ? $data['provisioning'] : [];

        $merge = [
            'FLEET_IDP_URL' => $idpUrl,
            'FLEET_IDP_CLIENT_ID' => isset($oauth['client_id']) ? (string) $oauth['client_id'] : null,
            'FLEET_IDP_CLIENT_SECRET' => isset($oauth['client_secret']) && is_string($oauth['client_secret']) ? $oauth['client_secret'] : null,
            'FLEET_IDP_PASSWORD_CLIENT_ID' => isset($password['client_id']) ? (string) $password['client_id'] : null,
            'FLEET_IDP_PASSWORD_CLIENT_SECRET' => isset($password['client_secret']) && is_string($password['client_secret']) ? $password['client_secret'] : null,
            'FLEET_AUTH_PROVISIONING_TOKEN' => isset($prov['token']) && is_string($prov['token']) ? $prov['token'] : null,
        ];

        $envPath = base_path((string) $this->option('env-file'));
        EnvFileWriter::mergeIntoFile($envPath, $merge);

        $this->info('Updated '.$envPath);
        $rows = [];
        foreach ($merge as $k => $v) {
            $rows[] = [$k, $v !== null && $v !== '' ? 'yes' : 'unchanged'];
        }
        $this->table(['Key', 'Set'], $rows);

        if (! $rotate) {
            $this->warn('Secrets were not rotated; blank columns mean Fleet Auth kept existing hashes. Run without --no-rotate to issue new secrets.');
        }

        return self::SUCCESS;
    }
}
