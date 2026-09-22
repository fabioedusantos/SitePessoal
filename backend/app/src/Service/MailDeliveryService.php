<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Env;
use App\Infrastructure\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Controla a quota da própria aplicação, NÃO a utilização total da conta Gmail.
 * Toda reserva ocupa um slot, mesmo que o processo falhe, por precaução contra
 * SMTP aceito sem que a aplicação tenha recebido a confirmação.
 */
final class MailDeliveryService
{
    public function __construct(private readonly Database $database, private readonly SmtpMailer $mailer)
    {
    }

    public function limit(): int
    {
        return max(1, min(450, (int) Env::get('MAIL_DAILY_LIMIT', 450)));
    }

    public function timezone(): DateTimeZone
    {
        try {
            return new DateTimeZone((string) Env::get('MAIL_QUOTA_TIMEZONE', 'America/Sao_Paulo'));
        } catch (\Throwable) {
            return new DateTimeZone('America/Sao_Paulo');
        }
    }

    public function today(): string
    {
        return (new DateTimeImmutable('now', $this->timezone()))->format('Y-m-d');
    }

    /**
     * @param list<string> $kinds
     * @return list<int>|null IDs de reservas, ou null se exceder a quota
     */
    public function reserve(int $contactId, array $kinds): ?array
    {
        if ($kinds === []) {
            return [];
        }

        $pdo = $this->database->pdo();
        $count = count($kinds);
        $utcNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $cutoff = $utcNow->modify('-24 hours')->format('Y-m-d H:i:s');
        $today = $utcNow->setTimezone($this->timezone())->format('Y-m-d');
        // Inicializa a trava sob demanda: as migrations não precisam semear dados.
        // A chave primária torna a criação simultânea idempotente entre processos.
        // Deve ocorrer ANTES da transação, para que SELECT ... FOR UPDATE
        // sempre encontre uma linha e serialize as reservas concorrentes.
        $pdo->exec('INSERT IGNORE INTO mail_quota_guard (id) VALUES (1)');
        $pdo->beginTransaction();
        try {
            // Lock único serializa também reservas em dois servidores / workers distintos.
            $pdo->query('SELECT id FROM mail_quota_guard WHERE id = 1 FOR UPDATE')->fetchColumn();
            $dayStmt = $pdo->prepare('SELECT COUNT(*) FROM mail_deliveries WHERE quota_date = ?');
            $dayStmt->execute([$today]);
            $dayUsed = (int) $dayStmt->fetchColumn();
            $rollingStmt = $pdo->prepare('SELECT COUNT(*) FROM mail_deliveries WHERE reserved_at_utc >= ?');
            $rollingStmt->execute([$cutoff]);
            $rollingUsed = (int) $rollingStmt->fetchColumn();
            if ($dayUsed + $count > $this->limit() || $rollingUsed + $count > $this->limit()) {
                $pdo->rollBack();
                return null;
            }

            $statement = $pdo->prepare(
                "INSERT INTO mail_deliveries (contact_message_id, kind, status, quota_date, reserved_at_utc) VALUES (?, ?, 'reserved', ?, ?)"
            );
            $ids = [];
            foreach ($kinds as $kind) {
                if (!in_array($kind, ['contact_owner', 'contact_confirmation'], true)) {
                    throw new RuntimeException('Tipo de e-mail inválido.');
                }
                $statement->execute([$contactId, $kind, $today, $utcNow->format('Y-m-d H:i:s')]);
                $ids[] = (int) $pdo->lastInsertId();
            }
            $pdo->commit();
            return $ids;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array{email:string,name?:string}|null $replyTo */
    public function sendReserved(int $deliveryId, string $to, string $name, string $subject, string $html, ?array $replyTo = null): void
    {
        try {
            $this->mailer->send($to, $name, $subject, $html, $replyTo);
            $this->setStatus($deliveryId, 'sent');
        } catch (\Throwable $exception) {
            // Mesmo se SMTP aceitou a mensagem antes do erro, o slot não será devolvido.
            $this->setStatus($deliveryId, 'failed');
            throw $exception;
        }
    }

    public function skip(int $deliveryId): void
    {
        $this->setStatus($deliveryId, 'skipped');
    }

    private function setStatus(int $id, string $status): void
    {
        $stmt = $this->database->pdo()->prepare(
            "UPDATE mail_deliveries SET status = ?, updated_at_utc = ?, sent_at_utc = IF(? = 'sent', ?, NULL) WHERE id = ? AND status = 'reserved'"
        );
        $now = gmdate('Y-m-d H:i:s');
        $stmt->execute([$status, $now, $status, $now, $id]);
    }
}
