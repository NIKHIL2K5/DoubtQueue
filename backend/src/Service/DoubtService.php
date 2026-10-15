<?php

declare(strict_types=1);

namespace DoubtQueue\Service;

use DoubtQueue\Exception\NotFoundException;
use DoubtQueue\Exception\SessionClosedException;
use DoubtQueue\Exception\ValidationException;
use DoubtQueue\Model\DoubtStatus;
use DoubtQueue\Model\SessionStatus;
use DoubtQueue\Repository\DoubtRepository;
use DoubtQueue\Repository\SessionRepository;

class DoubtService
{
    public function __construct(private DoubtRepository $doubts, private SessionRepository $sessions) {}

    public function join(int $sessionId, array $data): array
    {
        $session = $this->findSession($sessionId);

        if ($session['status'] !== SessionStatus::Open->value) {
            throw new SessionClosedException("Session with id $sessionId is closed");
        }

        $email = $this->requiredText($data, 'student_email', 255);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('student_email must be a valid email address');
        }

        $doubt = $this->doubts->save(
            $sessionId,
            $this->requiredText($data, 'student_name', 255),
            $email,
            $this->requiredText($data, 'topic', 255),
            $this->requiredText($data, 'question', 2000),
            gmdate('Y-m-d H:i:s')
        );

        return $this->withPosition($doubt);
    }

    public function get(int $id): array
    {
        return $this->withPosition($this->findDoubt($id));
    }

    public function queue(
        int $sessionId,
        mixed $limit = null,
        mixed $offset = null
    ): array {
        $this->findSession($sessionId);

        $validatedLimit = $this->validatePaginationValue(
            $limit,
            'limit',
            1,
            100
        );

        $validatedOffset = $this->validatePaginationValue(
            $offset,
            'offset',
            0,
            PHP_INT_MAX
        );

        return $this->doubts->findWaiting(
            $sessionId,
            $validatedLimit,
            $validatedOffset ?? 0
        );
    }

    public function next(int $sessionId): array
    {
        $this->findSession($sessionId);
        $doubt = $this->doubts->findOldestWaiting($sessionId);

        if ($doubt === null) {
            throw new NotFoundException("Session with id $sessionId has no waiting doubts");
        }

        $this->doubts->markStarted($doubt['id'], gmdate('Y-m-d H:i:s'));

        return $this->get($doubt['id']);
    }

    public function markSolved(int $id): array
    {
        return $this->finish($id, DoubtStatus::Solved);
    }

    public function markSkipped(int $id): array
    {
        return $this->finish($id, DoubtStatus::Skipped);
    }

    public function stats(int $sessionId): array
    {
        $this->findSession($sessionId);
        $counts = $this->doubts->countByStatus($sessionId);
        $stats = [];

        foreach (DoubtStatus::cases() as $status) {
            $stats[$status->value] = $counts[$status->value] ?? 0;
        }

        return $stats;
    }

    private function finish(int $id, DoubtStatus $status): array
    {
        $doubt = $this->findDoubt($id);

        if ($doubt['status'] !== DoubtStatus::InProgress->value) {
            throw new ValidationException('Only a doubt that is in progress can be finished');
        }

        $this->doubts->markFinished($id, $status->value, gmdate('Y-m-d H:i:s'));

        return $this->get($id);
    }

    private function findSession(int $id): array
    {
        $session = $this->sessions->find($id);

        if ($session === null) {
            throw new NotFoundException("Session with id $id not found");
        }

        return $session;
    }

    private function findDoubt(int $id): array
    {
        $doubt = $this->doubts->find($id);

        if ($doubt === null) {
            throw new NotFoundException("Doubt with id $id not found");
        }

        return $doubt;
    }

    private function withPosition(array $doubt): array
    {
        $isWaiting = $doubt['status'] === DoubtStatus::Waiting->value;
        $doubt['position'] = $isWaiting ? $this->doubts->position($doubt) : null;

        return $doubt;
    }

    private function requiredText(array $data, string $field, int $maxLength): string
    {
        $value = $data[$field] ?? null;
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            throw new ValidationException("$field is required");
        }

        if (mb_strlen($value) > $maxLength) {
            throw new ValidationException("$field must be $maxLength characters or fewer");
        }

        return $value;
    }

    private function validatePaginationValue(
        mixed $value,
        string $name,
        int $minimum,
        int $maximum
    ): ?int {
        if ($value === null) {
            return null;
        }

        if (
            !is_string($value)
            || preg_match('/^-?\d+$/D', $value) !== 1
        ) {
            throw new ValidationException(
                "$name must be a whole number"
            );
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT);

        if (
            $validated === false
            || $validated < $minimum
            || $validated > $maximum
        ) {
            throw new ValidationException(
                "$name must be between $minimum and $maximum"
            );
        }

        return $validated;
    }
}
