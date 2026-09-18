<?php

declare(strict_types=1);

namespace App\Security;

use App\Config\Env;
use Psr\Http\Message\ServerRequestInterface;

final class ClientIp
{
    public static function fromRequest(ServerRequestInterface $request): ?string
    {
        if (Env::bool('TRUST_PROXY_HEADERS', false)) {
            foreach (['CF-Connecting-IP', 'X-Real-IP'] as $header) {
                $value = trim(explode(',', $request->getHeaderLine($header))[0] ?? '');
                if ($value !== '' && filter_var($value, FILTER_VALIDATE_IP)) {
                    return $value;
                }
            }
        }

        $server = $request->getServerParams();
        $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        return $remote !== '' && filter_var($remote, FILTER_VALIDATE_IP) ? $remote : null;
    }
}
