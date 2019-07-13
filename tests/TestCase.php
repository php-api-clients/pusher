<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher;

use ApiClients\Tools\TestUtilities\TestCase as TestUtilitiesTestCase;
use Rx\Scheduler;
use Rx\Testing\ColdObservable;
use Rx\Testing\HotObservable;
use Rx\Testing\Recorded;
use Rx\Testing\TestScheduler;

/**
 * @internal
 */
class TestCase extends TestUtilitiesTestCase
{
    /**
     * @var TestScheduler
     */
    protected $scheduler;

    protected function setup(): void
    {
        parent::setup();

        $this->scheduler = $this->createTestScheduler();

        self::resetScheduler();

        Scheduler::setDefaultFactory(function () {
            return $this->scheduler;
        });
    }

    public static function resetScheduler(): void
    {
        $ref = new \ReflectionClass(Scheduler::class);
        $props = $ref->getProperties();

        foreach ($props as $prop) {
            $prop->setAccessible(true);
            $prop->setValue(null);
            $prop->setAccessible(false);
        }
    }

    /**
     * @param Recorded[] $expected
     * @param Recorded[] $recorded
     */
    public function assertMessages(array $expected, array $recorded): void
    {
        if (\count($expected) !== \count($recorded)) {
            $this->fail(\sprintf('Expected message count %d does not match actual count %d.', \count($expected), \count($recorded)));
        }

        for ($i = 0, $count = \count($expected); $i < $count; $i++) {
            if (!$expected[$i]->equals($recorded[$i])) {
                $this->fail($expected[$i] . ' does not equal ' . $recorded[$i]);
            }
        }

        $this->assertTrue(true); // success
    }

    public function assertSubscriptions(array $expected, array $recorded): void
    {
        if (\count($expected) !== \count($recorded)) {
            $this->fail(\sprintf('Expected subscription count %d does not match actual count %d.', \count($expected), \count($recorded)));
        }

        for ($i = 0, $count = \count($expected); $i < $count; $i++) {
            if (!$expected[$i]->equals($recorded[$i])) {
                $this->fail($expected[$i] . ' does not equal ' . $recorded[$i]);
            }
        }

        $this->assertTrue(true); // success
    }

    protected function createTestScheduler()
    {
        return new TestScheduler();
    }

    protected function createHotObservable(array $events): HotObservable
    {
        return new HotObservable($this->scheduler, $events);
    }

    protected function createColdObservable(array $events)
    {
        return new ColdObservable($this->scheduler, $events);
    }
}
