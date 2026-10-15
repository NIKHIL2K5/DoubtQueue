<?php

declare(strict_types=1);

namespace DoubtQueue\Repository;

use PDO;

class DoubtRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(
        int $sessionId,
        string $studentName,
        string $studentEmail,
        string $topic,
        string $question,
        string $createdAt
    ): array {
        $this->pdo->prepare(
            'INSERT INTO doubts (session_id, student_name, student_email, topic, question, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$sessionId, $studentName, $studentEmail, $topic, $question, 'WAITING', $createdAt]);

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM doubts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->format($row);
    }

    public function findWaiting(
        int $sessionId,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $sql = "SELECT * FROM doubts
            WHERE session_id = ? AND status = 'WAITING'
            ORDER BY created_at ASC, id ASC";

        $params = [$sessionId];

        if ($limit !== null) {
            $sql .= ' LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;
        } elseif ($offset > 0) {
            $sql .= ' LIMIT -1 OFFSET ?';
            $params[] = $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'format'], $stmt->fetchAll());
    }

    public function findOldestWaiting(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM doubts WHERE session_id = ? AND status = 'WAITING'
             ORDER BY created_at ASC, id ASC LIMIT 1"
        );
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->format($row);
    }

    public function position(array $doubt): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM doubts
             WHERE session_id = ? AND status = 'WAITING'
               AND (created_at < ? OR (created_at = ? AND id < ?))"
        );
        $stmt->execute([$doubt['session_id'], $doubt['created_at'], $doubt['created_at'], $doubt['id']]);

        return (int) $stmt->fetchColumn() + 1;
    }

    public function markStarted(int $id, string $startedAt): void
    {
        $this->pdo->prepare('UPDATE doubts SET status = ?, started_at = ? WHERE id = ?')
            ->execute(['IN_PROGRESS', $startedAt, $id]);
    }

    public function markFinished(int $id, string $status, string $finishedAt): void
    {
        $this->pdo->prepare('UPDATE doubts SET status = ?, finished_at = ? WHERE id = ?')
            ->execute([$status, $finishedAt, $id]);
    }

    public function countByStatus(int $sessionId): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) AS total FROM doubts WHERE session_id = ? GROUP BY status');
        $stmt->execute([$sessionId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'total', 'status'));
    }

    private function format(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['session_id'] = (int) $row['session_id'];

        return $row;
    }
}
