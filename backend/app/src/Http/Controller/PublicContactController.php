<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Config\Env;
use App\Http\JsonResponse;
use App\Infrastructure\Database;
use App\Security\ClientIp;
use App\Security\ContactThrottle;
use App\Service\HCaptchaVerifier;
use App\Service\MailDeliveryService;
use App\Service\ContactEmailTemplate;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PublicContactController
{
    public function __construct(
        private readonly Database $database,
        private readonly ContactThrottle $throttle,
        private readonly HCaptchaVerifier $captcha,
        private readonly MailDeliveryService $mail,
        private readonly ContactEmailTemplate $templates,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);

        if (trim((string) ($body['website'] ?? '')) !== '') {
            return JsonResponse::write($response, ['message' => 'Mensagem recebida.']);
        }

        $name = trim((string) ($body['name'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $subject = trim((string) ($body['subject'] ?? 'Contato pelo FBSantos.com.br'));
        $message = trim((string) ($body['message'] ?? ''));
        $captchaToken = trim((string) ($body['hcaptcha_token'] ?? ''));

        $validationError = $this->validate($name, $email, $subject, $message);
        if ($validationError !== null) {
            return JsonResponse::write($response, ['message' => $validationError], 422);
        }

        $ip = ClientIp::fromRequest($request);
        $ipAllowed = $this->throttle->consume('ip:' . ($ip ?: 'unknown'));
        $emailAllowed = $this->throttle->consume('email:' . strtolower($email));
        if (!$ipAllowed || !$emailAllowed) {
            return JsonResponse::write(
                $response,
                ['message' => 'Muitas tentativas em pouco tempo. Aguarde alguns minutos e tente novamente.'],
                429
            );
        }

        if (!$this->captcha->verify($captchaToken, $ip)) {
            return JsonResponse::write(
                $response,
                ['message' => 'Não foi possível validar o hCaptcha. Atualize o desafio e tente novamente.'],
                422
            );
        }

        $sourceSecret = (string) Env::get('JWT_SECRET', 'fbsantos-contact');
        $sourceHash = $ip ? hash('sha256', $ip . '|' . $sourceSecret) : null;
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO contact_messages (name, email, subject, message, status, source_hash) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $subject, $message, 'pending', $sourceHash]);
        $messageId = (int) $this->database->pdo()->lastInsertId();

        if (!Env::bool('MAIL_ENABLED', false)) {
            return JsonResponse::write($response, [
                'message' => 'Mensagem validada e registrada. O envio SMTP está desativado neste ambiente.',
            ], 202);
        }

        $sendConfirmation = Env::bool('CONTACT_AUTO_REPLY', true);
        $kinds = $sendConfirmation ? ['contact_owner', 'contact_confirmation'] : ['contact_owner'];
        try {
            // Reserva a solicitação inteira: 1 contato consome 2 slots com autorresposta.
            // O limite é testado em janela móvel de 24 horas e no dia local.
            $deliveries = $this->mail->reserve($messageId, $kinds);
        } catch (\Throwable) {
            return JsonResponse::write($response, [
                'message' => 'Sua mensagem foi registrada, mas a notificação por e-mail está indisponível. Não é necessário reenviar.',
            ], 202);
        }

        if ($deliveries === null) {
            // Mantém a mensagem salva e pendente para conferência no painel.
            return JsonResponse::write($response, [
                'message' => 'Mensagem recebida e registrada. O limite de e-mails foi alcançado, então a notificação automática não será enviada agora.',
            ], 202);
        }

        $ownerEmail = trim((string) Env::get('CONTACT_TO_EMAIL', ''));
        try {
            $this->mail->sendReserved(
                $deliveries[0],
                $ownerEmail,
                'Fábio Santos',
                '[FBSantos.com.br] ' . $subject,
                $this->templates->owner($name, $email, $subject, $message),
                ['email' => $email, 'name' => $name]
            );
        } catch (\Throwable) {
            if ($sendConfirmation) {
                $this->mail->skip($deliveries[1]);
            }
            $this->updateStatus($messageId, 'failed');
            return JsonResponse::write(
                $response,
                ['message' => 'Sua mensagem foi registrada, mas a notificação por e-mail falhou. Tente novamente mais tarde.'],
                503
            );
        }

        if ($sendConfirmation) {
            try {
                $this->mail->sendReserved(
                    $deliveries[1],
                    $email,
                    $name,
                    'Recebi sua mensagem | Fábio Santos',
                    $this->templates->confirmation($name)
                );
            } catch (\Throwable) {
                $this->updateStatus($messageId, 'partial');
                return JsonResponse::write($response, [
                    'message' => 'Sua mensagem foi enviada. A confirmação automática por e-mail não pôde ser entregue.',
                ], 201);
            }
        }

        $this->updateStatus($messageId, 'sent');
        return JsonResponse::write($response, [
            'message' => $sendConfirmation
                ? 'Mensagem enviada. Você também receberá uma confirmação automática por e-mail.'
                : 'Mensagem enviada com sucesso.',
        ], 201);
    }

    private function validate(string $name, string $email, string $subject, string $message): ?string
    {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            return 'Informe seu nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            return 'Informe um e-mail válido.';
        }
        if ($subject === '' || mb_strlen($subject) > 180 || preg_match('/[\r\n]/', $subject)) {
            return 'Informe um assunto válido.';
        }
        if (mb_strlen($message) < 20 || mb_strlen($message) > 5000) {
            return 'A mensagem deve ter entre 20 e 5.000 caracteres.';
        }
        return null;
    }

    private function updateStatus(int $id, string $status): void
    {
        $stmt = $this->database->pdo()->prepare('UPDATE contact_messages SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

}
