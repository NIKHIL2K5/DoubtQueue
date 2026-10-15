<?php

declare(strict_types=1);

namespace DoubtQueue\Tests\Service;

use DoubtQueue\Database;
use DoubtQueue\DatabaseSetup;
use DoubtQueue\Exception\NotFoundException;
use DoubtQueue\Exception\SessionClosedException;
use DoubtQueue\Exception\ValidationException;
use DoubtQueue\Repository\DoubtRepository;
use DoubtQueue\Repository\SessionRepository;
use DoubtQueue\Service\DoubtService;
use DoubtQueue\Service\SessionService;
use PHPUnit\Framework\TestCase;

class DoubtServiceTest extends TestCase
{
    private DoubtService $doubts;
    private SessionService $sessions;

    protected function setUp(): void
    {
        $pdo = Database::memory();
        (new DatabaseSetup($pdo))->createTables();

        $sessionRepository = new SessionRepository($pdo);
        $this->sessions = new SessionService($sessionRepository);
        $this->doubts = new DoubtService(new DoubtRepository($pdo), $sessionRepository);
    }

    public function testJoiningOpenSessionWorks(): void
    {
        $session = $this->sessions->create(['title' => 'PHP', 'mentor_name' => 'Priya']);

        $doubt = $this->doubts->join($session['id'], $this->validData());

        $this->assertSame('WAITING', $doubt['status']);
        $this->assertSame(1, $doubt['position']);
        $this->assertSame('Asha', $doubt['student_name']);
    }

    public function testJoiningClosedSessionThrows(): void
    {
        $session = $this->sessions->create(['title' => 'PHP', 'mentor_name' => 'Priya']);
        $this->sessions->close($session['id']);

        $this->expectException(SessionClosedException::class);

        $this->doubts->join($session['id'], $this->validData());
    }

    public function testMissingQuestionThrows(): void
    {
        $session = $this->sessions->create(['title' => 'PHP', 'mentor_name' => 'Priya']);
        $data = $this->validData();
        unset($data['question']);

        $this->expectException(ValidationException::class);

        $this->doubts->join($session['id'], $data);
    }

    public function testInvalidEmailThrows(): void
    {
        $session = $this->sessions->create(['title' => 'PHP', 'mentor_name' => 'Priya']);

        $this->expectException(ValidationException::class);

        $this->doubts->join($session['id'], ['student_email' => 'not-an-email'] + $this->validData());
    }

    public function testNextTakesOldestWaitingDoubt(): void
    {
        $session = $this->sessions->create(['title' => 'PHP', 'mentor_name' => 'Priya']);
        $first = $this->doubts->join($session['id'], $this->validData());
        $second = $this->doubts->join($session['id'], ['student_name' => 'Ben'] + $this->validData());

        $taken = $this->doubts->next($session['id']);

        $this->assertSame($first['id'], $taken['id']);
        $this->assertSame('IN_PROGRESS', $taken['status']);
        $this->assertNotNull($taken['started_at']);
        $this->assertNull($taken['position']);
        $this->assertSame(1, $this->doubts->get($second['id'])['position']);
    }

    public function testSolvedAndSkippedSetFinishedAt(): void
    {
        $session = $this->sessions->create(['title' => 'PHP', 'mentor_name' => 'Priya']);
        $this->doubts->join($session['id'], $this->validData());
        $this->doubts->join($session['id'], $this->validData());

        $solved = $this->doubts->markSolved($this->doubts->next($session['id'])['id']);
        $skipped = $this->doubts->markSkipped($this->doubts->next($session['id'])['id']);

        $this->assertSame('SOLVED', $solved['status']);
        $this->assertNotNull($solved['finished_at']);
        $this->assertSame('SKIPPED', $skipped['status']);
        $this->assertSame(1, $this->doubts->stats($session['id'])['SOLVED']);
    }

    public function testFinishingDoubtThatIsNotInProgressThrows(): void
    {
        $session = $this->sessions->create(['title' => 'PHP', 'mentor_name' => 'Priya']);
        $doubt = $this->doubts->join($session['id'], $this->validData());

        $this->expectException(ValidationException::class);

        $this->doubts->markSolved($doubt['id']);
    }

    public function testMissingDoubtThrows(): void
    {
        $this->expectException(NotFoundException::class);

        $this->doubts->get(999);
    }

    private function validData(): array
    {
        return [
            'student_name' => 'Asha',
            'student_email' => 'asha@example.com',
            'topic' => 'Arrays',
            'question' => 'How do I loop over an array?',
        ];
    }


    public function testQueueWithInvalidLimitThrows(): void
    {
        $session = $this->sessions->create([
                'title' => 'PHP',
                'mentor_name' => 'Priya',
        ]);

        foreach (['0', '-1', 'abc', '101', '1.5'] as $limit) {
            try {
                $this->doubts->queue($session['id'], $limit);
                $this->fail("Expected ValidationException for limit $limit");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testQueueWithInvalidOffsetThrows(): void
    {
        $session = $this->sessions->create([
            'title' => 'PHP',
            'mentor_name' => 'Priya',
        ]);

        foreach (['-1', 'abc', '1.5'] as $offset) {
            try {
                $this->doubts->queue($session['id'], null, $offset);
                $this->fail("Expected ValidationException for offset $offset");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testQueueWithOffsetOnlyReturnsRemainingDoubts(): void
    {
        $session = $this->sessions->create([
        'title' => 'PHP',
        'mentor_name' => 'Priya',
    ]);

        $first = $this->doubts->join($session['id'], $this->validData());
        $second = $this->doubts->join(
            $session['id'],
            ['student_name' => 'Ben'] + $this->validData()
        );

        $queue = $this->doubts->queue($session['id'], null, '1');

        $this->assertSame(
            [$second['id']],
            array_column($queue, 'id')
        );
    }

    public function testQueueForUnknownSessionThrowsNotFound(): void
    {
    $this->expectException(NotFoundException::class);

    $this->doubts->queue(999999);
    }
}
