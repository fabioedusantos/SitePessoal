<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Env;
use RuntimeException;

final class SmtpMailer
{
    /** @param array{email:string,name?:string}|null $replyTo */
    public function send(string $toEmail, string $toName, string $subject, string $html, ?array $replyTo = null): void
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $toEmail)) {
            throw new RuntimeException('Destinatário de e-mail inválido.');
        }
        $host = trim((string) Env::get('MAIL_HOST', 'smtp.gmail.com'));
        $port = (int) Env::get('MAIL_PORT', 587);
        $username = trim((string) Env::get('MAIL_USERNAME', ''));
        $password = (string) Env::get('MAIL_PASSWORD', '');
        $fromEmail = trim((string) Env::get('MAIL_FROM_EMAIL', $username));
        $fromName = trim((string) Env::get('MAIL_FROM_NAME', 'Fábio Santos'));

        if ($host === '' || $port <= 0 || $username === '' || $password === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configuração SMTP incompleta.');
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
            ],
        ]);

        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errorNumber,
            $errorMessage,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!is_resource($socket)) {
            throw new RuntimeException('Não foi possível conectar ao servidor SMTP.');
        }

        stream_set_timeout($socket, 15);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO fbsantos.com.br', [250]);
            $this->command($socket, 'STARTTLS', [220]);

            if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                throw new RuntimeException('Falha ao iniciar TLS no servidor SMTP.');
            }

            $this->command($socket, 'EHLO fbsantos.com.br', [250]);
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($password), [235]);
            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $this->mailbox($fromEmail, $fromName),
                'To: ' . $this->mailbox($toEmail, $toName),
                'Subject: ' . $this->encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@fbsantos.com.br>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];

            if ($replyTo && filter_var($replyTo['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $headers[] = 'Reply-To: ' . $this->mailbox(
                    (string) $replyTo['email'],
                    (string) ($replyTo['name'] ?? '')
                );
            }

            $body = str_replace(["\r\n", "\r"], "\n", $html);
            $body = preg_replace('/^\./m', '..', $body) ?? $body;
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body) . "\r\n.";
            $this->command($socket, $payload, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function command($socket, string $command, array $expectedCodes): string
    {
        if (fwrite($socket, $command . "\r\n") === false) {
            throw new RuntimeException('Falha ao comunicar com o servidor SMTP.');
        }
        return $this->expect($socket, $expectedCodes);
    }

    /** @param resource $socket */
    private function expect($socket, array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($socket, 8192)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Servidor SMTP recusou a operação.');
        }

        return $response;
    }

    private function mailbox(string $email, string $name): string
    {
        $safeName = trim(str_replace(["\r", "\n"], '', $name));
        return $safeName === '' ? "<{$email}>" : $this->encodeHeader($safeName) . " <{$email}>";
    }

    private function encodeHeader(string $value): string
    {
        $safe = trim(str_replace(["\r", "\n"], '', $value));
        return '=?UTF-8?B?' . base64_encode($safe) . '?=';
    }
}
