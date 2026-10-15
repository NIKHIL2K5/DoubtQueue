<?php

declare(strict_types=1);

namespace DoubtQueue\Tests\Repository;

use DoubtQueue\Database;
use DoubtQueue\DatabaseSetup;
use DoubtQueue\Repository\DoubtRepository;
use DoubtQueue\Repository\SessionRepository;
use PHPUnit\Framework\TestCase;

class DoubtRepositoryTest extends TestCase
{
    private DoubtRepository $doubts;
    private int $sessionId;

    protected function setUp(): void
    {
        $pdo = Database::memory();
        (new DatabaseSetup($pdo))->createTables();

        $session = (new SessionRepository($pdo))->create('Test', 'Mentor', 'OPEN', '2026-01-01 10:00:00');
        $this->sessionId = $session['id'];
        $this->doubts = new DoubtRepository($pdo);
    }

    public function testSaveStoresWaitingDoubt(): void
    {
        $doubt = $this->save('Asha', '2026-01-01 10:01:00');

        $this->assertSame('WAITING', $doubt['status']);
        $this->assertSame('Asha', $doubt['student_name']);
        $this->assertNull($doubt['started_at']);
        $this->assertSame($doubt, $this->doubts->find($doubt['id']));
    }

    public function testFindWaitingSkipsOtherStatuses(): void
    {
        $first = $this->save('Asha', '2026-01-01 10:01:00');
        $second = $this->save('Ben', '2026-01-01 10:02:00');
        $this->doubts->markStarted($first['id'], '2026-01-01 10:03:00');

        $waiting = $this->doubts->findWaiting($this->sessionId);

        $this->assertCount(1, $waiting);
        $this->assertSame($second['id'], $waiting[0]['id']);
    }

    public function testFindWaitingReturnsOldestFirst(): void
    {
        $late = $this->save('Late', '2026-01-01 10:05:00');
        $early = $this->save('Early', '2026-01-01 10:01:00');
        $middle = $this->save('Middle', '2026-01-01 10:03:00');

        $ids = array_column($this->doubts->findWaiting($this->sessionId), 'id');

        $this->assertSame([$early['id'], $middle['id'], $late['id']], $ids);
        $this->assertSame($early['id'], $this->doubts->findOldestWaiting($this->sessionId)['id']);
    }

    public function testPositionCountsWaitingDoubtsCreatedBefore(): void
    {
        $first = $this->save('Asha', '2026-01-01 10:01:00');
        $second = $this->save('Ben', '2026-01-01 10:02:00');
        $third = $this->save('Chitra', '2026-01-01 10:03:00');

        $this->assertSame(1, $this->doubts->position($first));
        $this->assertSame(2, $this->doubts->position($second));
        $this->assertSame(3, $this->doubts->position($third));

        $this->doubts->markStarted($first['id'], '2026-01-01 10:04:00');

        $this->assertSame(1, $this->doubts->position($second));
    }

    public function testPositionUsesIdWhenCreatedAtIsEqual(): void
    {
        $first = $this->save('Asha', '2026-01-01 10:01:00');
        $second = $this->save('Ben', '2026-01-01 10:01:00');

        $this->assertSame(1, $this->doubts->position($first));
        $this->assertSame(2, $this->doubts->position($second));
    }

    private function save(string $name, string $createdAt): array
    {
        return $this->doubts->save(
            $this->sessionId,
            $name,
            strtolower($name) . '@example.com',
            'Topic',
            'Question',
            $createdAt
        );
    }

    public function testFindWaitingRespectsLimit(): void
    {
        $first = $this->save('First', '2026-01-01 10:01:00');
        $second = $this->save('Second', '2026-01-01 10:02:00');
        $third = $this->save('Third', '2026-01-01 10:03:00');

        $waiting = $this->doubts->findWaiting($this->sessionId, 2);

        $this->assertCount(2, $waiting);
        $this->assertSame(
        [$first['id'], $second['id']],
        array_column($waiting, 'id')
        );
    }

    public function testFindWaitingRespectsOffset(): void
    {
        $first = $this->save('First', '2026-01-01 10:01:00');
        $second = $this->save('Second', '2026-01-01 10:02:00');
        $third = $this->save('Third', '2026-01-01 10:03:00');

        $waiting = $this->doubts->findWaiting($this->sessionId, 2, 1);

        $this->assertSame(
        [$second['id'], $third['id']],
        array_column($waiting, 'id')
        );
    }

    public function testFindWaitingWithoutPaginationReturnsAllWaitingDoubts(): void
    {
        $first = $this->save('First', '2026-01-01 10:01:00');
        $second = $this->save('Second', '2026-01-01 10:02:00');
        $third = $this->save('Third', '2026-01-01 10:03:00');

        $waiting = $this->doubts->findWaiting($this->sessionId);

        $this->assertSame(
            [$first['id'], $second['id'], $third['id']],
            array_column($waiting, 'id')
        );
    }

}
