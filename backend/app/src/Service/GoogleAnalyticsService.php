<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Env;
use RuntimeException;

final class GoogleAnalyticsService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const DATA_API_BASE = 'https://analyticsdata.googleapis.com/v1beta';
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private ?string $accessToken = null;

    public function isConfigured(): bool
    {
        return $this->propertyId() !== ''
            && $this->serviceAccountEmail() !== ''
            && $this->privateKey() !== '';
    }

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        if (!$this->isConfigured()) {
            return [
                'configured' => false,
                'message' => 'Google Analytics ainda não foi configurado no backend.',
            ];
        }

        $cached = $this->readCache();
        if ($cached !== null) {
            return $cached;
        }

        $current = $this->metricSummary('27daysAgo', 'today');
        $previous = $this->metricSummary('55daysAgo', '28daysAgo');

        $payload = [
            'configured' => true,
            'period' => [
                'label' => 'Últimos 28 dias',
                'start' => '28daysAgo',
                'end' => 'today',
            ],
            'summary' => $this->summaryWithChanges($current, $previous),
            'daily' => $this->tableReport(
                ['date'],
                ['activeUsers', 'sessions', 'screenPageViews'],
                35,
                [['dimension' => ['dimensionName' => 'date'], 'desc' => false]],
            ),
            'pages' => $this->tableReport(
                ['pagePath', 'pageTitle'],
                ['screenPageViews', 'activeUsers'],
                15,
                [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            ),
            'channels' => $this->tableReport(
                ['sessionDefaultChannelGroup'],
                ['sessions', 'activeUsers', 'engagedSessions'],
                12,
                [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
            ),
            'countries' => $this->tableReport(
                ['country'],
                ['activeUsers', 'sessions'],
                12,
                [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
            ),
            'devices' => $this->tableReport(
                ['deviceCategory'],
                ['activeUsers', 'sessions'],
                10,
                [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
            ),
            'generated_at' => gmdate('c'),
        ];

        $this->writeCache($payload);
        return $payload;
    }

    /** @return array<string,float|int> */
    private function metricSummary(string $startDate, string $endDate): array
    {
        $metrics = [
            'activeUsers',
            'newUsers',
            'sessions',
            'screenPageViews',
            'engagedSessions',
            'engagementRate',
            'averageSessionDuration',
        ];
        $report = $this->runReport([], $metrics, 1, [], $startDate, $endDate);
        $row = $report['rows'][0]['metricValues'] ?? [];
        $result = [];
        foreach ($metrics as $index => $name) {
            $raw = (string) ($row[$index]['value'] ?? '0');
            $result[$name] = in_array($name, ['engagementRate', 'averageSessionDuration'], true)
                ? (float) $raw
                : (int) round((float) $raw);
        }
        return $result;
    }

    /** @param array<string,float|int> $current @param array<string,float|int> $previous */
    private function summaryWithChanges(array $current, array $previous): array
    {
        $result = [];
        foreach ($current as $key => $value) {
            $previousValue = (float) ($previous[$key] ?? 0);
            $currentValue = (float) $value;
            $change = $previousValue == 0.0
                ? ($currentValue == 0.0 ? 0.0 : null)
                : (($currentValue - $previousValue) / $previousValue) * 100;
            $result[$key] = [
                'value' => $value,
                'previous' => $previous[$key] ?? 0,
                'change_percent' => $change === null ? null : round($change, 1),
            ];
        }
        return $result;
    }

    /**
     * @param list<string> $dimensions
     * @param list<string> $metrics
     * @param list<array<string,mixed>> $orderBys
     * @return array{headers:array{dimensions:list<string>,metrics:list<string>},rows:list<array<string,mixed>>,row_count:int}
     */
    private function tableReport(array $dimensions, array $metrics, int $limit, array $orderBys = []): array
    {
        $report = $this->runReport($dimensions, $metrics, $limit, $orderBys);
        $rows = [];
        foreach ($report['rows'] ?? [] as $row) {
            $dimensionValues = [];
            foreach ($dimensions as $index => $name) {
                $dimensionValues[$name] = (string) ($row['dimensionValues'][$index]['value'] ?? '');
            }
            $metricValues = [];
            foreach ($metrics as $index => $name) {
                $raw = (string) ($row['metricValues'][$index]['value'] ?? '0');
                $metricValues[$name] = is_numeric($raw) ? (float) $raw : 0.0;
            }
            $rows[] = ['dimensions' => $dimensionValues, 'metrics' => $metricValues];
        }

        return [
            'headers' => ['dimensions' => $dimensions, 'metrics' => $metrics],
            'rows' => $rows,
            'row_count' => (int) ($report['rowCount'] ?? count($rows)),
        ];
    }

    /** @param list<string> $dimensions @param list<string> $metrics @param list<array<string,mixed>> $orderBys */
    private function runReport(
        array $dimensions,
        array $metrics,
        int $limit = 10,
        array $orderBys = [],
        string $startDate = '27daysAgo',
        string $endDate = 'today',
    ): array {
        $body = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => array_map(static fn (string $name): array => ['name' => $name], $metrics),
            'limit' => (string) max(1, min(1000, $limit)),
            'keepEmptyRows' => false,
        ];
        if ($dimensions) {
            $body['dimensions'] = array_map(static fn (string $name): array => ['name' => $name], $dimensions);
        }
        if ($orderBys) {
            $body['orderBys'] = $orderBys;
        }

        $url = self::DATA_API_BASE . '/properties/' . rawurlencode($this->propertyId()) . ':runReport';
        return $this->requestJson($url, 'POST', $body, [
            'Authorization: Bearer ' . $this->accessToken(),
        ]);
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode([
            'iss' => $this->serviceAccountEmail(),
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $unsigned = $header . '.' . $claims;

        $signature = '';
        $key = openssl_pkey_get_private($this->privateKey());
        if ($key === false || !openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Não foi possível assinar a credencial do Google Analytics.');
        }

        $assertion = $unsigned . '.' . $this->base64Url($signature);
        $result = $this->requestForm(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);
        $token = trim((string) ($result['access_token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('O Google não retornou token de acesso para a conta de serviço.');
        }
        $this->accessToken = $token;
        return $token;
    }

    private function propertyId(): string
    {
        return preg_replace('/\D+/', '', (string) Env::get('GA_PROPERTY_ID', '')) ?: '';
    }

    private function serviceAccountEmail(): string
    {
        $email = trim((string) Env::get('GA_SERVICE_ACCOUNT_EMAIL', ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function privateKey(): string
    {
        $encoded = trim((string) Env::get('GA_SERVICE_ACCOUNT_PRIVATE_KEY_B64', ''));
        if ($encoded === '') {
            return '';
        }
        $decoded = base64_decode($encoded, true);
        return is_string($decoded) && str_contains($decoded, 'PRIVATE KEY') ? $decoded : '';
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @param array<string,mixed> $body @param list<string> $headers */
    private function requestJson(string $url, string $method, array $body, array $headers = []): array
    {
        $headers[] = 'Accept: application/json';
        $headers[] = 'Content-Type: application/json';
        return $this->httpRequest($url, $method, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $headers);
    }

    /** @param array<string,string> $fields */
    private function requestForm(string $url, array $fields): array
    {
        return $this->httpRequest(
            $url,
            'POST',
            http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        );
    }

    /** @param list<string> $headers */
    private function httpRequest(string $url, string $method, string $body, array $headers): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => 12,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }
        if ($response === false) {
            throw new RuntimeException('Falha ao consultar o Google Analytics.');
        }

        try {
            $decoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Resposta inválida recebida do Google Analytics.');
        }

        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['error']['message'] ?? 'Falha ao consultar o Google Analytics.');
            throw new RuntimeException($message);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function cachePath(): string
    {
        return (string) Env::get('GA_CACHE_PATH', sys_get_temp_dir() . '/fbsantos/ga4-dashboard.json');
    }

    private function readCache(): ?array
    {
        $path = $this->cachePath();
        $ttl = max(60, min(3600, (int) Env::get('GA_CACHE_SECONDS', 300)));
        if (!is_file($path) || (time() - (int) filemtime($path)) > $ttl) {
            return null;
        }
        $contents = @file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return null;
        }
        $decoded = json_decode($contents, true);
        return is_array($decoded) && ($decoded['configured'] ?? false) === true ? $decoded : null;
    }

    /** @param array<string,mixed> $payload */
    private function writeCache(array $payload): void
    {
        $path = $this->cachePath();
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }
        $temporary = $path . '.tmp.' . bin2hex(random_bytes(4));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (@file_put_contents($temporary, $json, LOCK_EX) !== false) {
            @chmod($temporary, 0660);
            @rename($temporary, $path);
        }
    }
}
