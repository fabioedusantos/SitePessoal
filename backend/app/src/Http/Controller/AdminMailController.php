<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\JsonResponse;
use App\Infrastructure\Database;
use App\Service\MailDeliveryService;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminMailController
{
    public function __construct(
        private readonly Database $database,
        private readonly MailDeliveryService $mail,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $today = $this->mail->today();
        $month = trim((string) (($request->getQueryParams()['month'] ?? '') ?: substr($today, 0, 7)));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month < '2020-01' || $month > substr($today, 0, 7)) {
            return JsonResponse::write($response, ['message' => 'Selecione um mês válido, até o mês atual.'], 422);
        }

        $start = $month . '-01';
        $end = (new DateTimeImmutable($start))->modify('first day of next month')->format('Y-m-d');
        $pdo = $this->database->pdo();
        $query = $pdo->prepare(
            "SELECT quota_date AS day, COUNT(*) AS reserved,
                SUM(status = 'sent') AS sent, SUM(status = 'failed') AS failed,
                SUM(status = 'skipped') AS skipped, SUM(status = 'reserved') AS pending,
                SUM(kind = 'contact_owner' AND status = 'sent') AS owner_sent,
                SUM(kind = 'contact_confirmation' AND status = 'sent') AS confirmation_sent
             FROM mail_deliveries WHERE quota_date >= ? AND quota_date < ?
             GROUP BY quota_date ORDER BY quota_date"
        );
        $query->execute([$start, $end]);
        $rows = $query->fetchAll();
        $days = [];
        $totals = ['reserved' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'pending' => 0, 'owner_sent' => 0, 'confirmation_sent' => 0];
        foreach ($rows as $row) {
            $day = ['date' => $row['day']];
            foreach ($totals as $key => $_) {
                $day[$key] = (int) $row[$key];
                $totals[$key] += $day[$key];
            }
            $days[] = $day;
        }

        $todayStmt = $pdo->prepare('SELECT COUNT(*) FROM mail_deliveries WHERE quota_date = ?');
        $todayStmt->execute([$today]);
        $todayUsed = (int) $todayStmt->fetchColumn();
        $rollingStmt = $pdo->prepare('SELECT COUNT(*) FROM mail_deliveries WHERE reserved_at_utc >= ?');
        $rollingStmt->execute([gmdate('Y-m-d H:i:s', time() - 86400)]);
        $rollingUsed = (int) $rollingStmt->fetchColumn();
        $pendingMessages = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'pending'")->fetchColumn();
        $attentionCount = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status IN ('pending', 'failed')")->fetchColumn();
        // Restrito a administradores (rota sob AdminAuthMiddleware); nunca publicado na API pública.
        $attention = $pdo->query(
            "SELECT id, name, email, subject, message, status, created_at
             FROM contact_messages WHERE status IN ('pending', 'failed') ORDER BY id DESC LIMIT 30"
        )->fetchAll();
        $limit = $this->mail->limit();
        $usage = max($todayUsed, $rollingUsed);

        return JsonResponse::write($response, [
            'month' => $month,
            'current_month' => substr($today, 0, 7),
            'today' => $today,
            'timezone' => $this->mail->timezone()->getName(),
            'limit' => $limit,
            'today_reserved' => $todayUsed,
            'rolling_reserved' => $rollingUsed,
            'available' => max(0, $limit - $usage),
            'pending_contacts' => $pendingMessages,
            'attention_count' => $attentionCount,
            'attention' => $attention,
            'totals' => $totals,
            'daily' => $days,
            'note' => 'Estatísticas dos e-mails gerados por este site. Envios de outros aplicativos ou do próprio Gmail não são incluídos.',
        ]);
    }
}
