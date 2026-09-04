<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Command;

use c975L\BookBundle\Command\BookReleaseAlertSend;
use c975L\BookBundle\Service\BookReleaseAlertServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

// The one cron line the waiting lists live by: it sends, it purges and it says what is left, the three being what empties this table (see BookReleaseAlertSend)
class BookReleaseAlertSendTest extends TestCase
{
    // What a run prints, the number still waiting included: a queue that stops going down is how a site finds out its mailer is refusing, where a bare success line would hide it
    public function testARunSendsPurgesAndSaysWhatIsLeft(): void
    {
        $service = $this->createMock(BookReleaseAlertServiceInterface::class);
        $service->expects($this->once())->method('notifyReleased')->with(50)->willReturn(7);
        $service->expects($this->once())->method('purgeStale')->willReturn(2);
        $service->expects($this->once())->method('countPending')->willReturn(13);

        $tester = new CommandTester(new BookReleaseAlertSend($service));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));

        // Read with the wrapping SymfonyStyle lays on a success block collapsed away
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('7 release alert(s) sent, 13 still waiting, 2 expired subscription(s) deleted.', $display);
    }

    // A long-awaited book carries thousands of subscriptions, so the run walks the queue rather than holding the mailer for as long as it takes
    public function testTheLimitSaysHowManyOneRunSends(): void
    {
        $service = $this->createMock(BookReleaseAlertServiceInterface::class);
        $service->expects($this->once())->method('notifyReleased')->with(5)->willReturn(5);

        $tester = new CommandTester(new BookReleaseAlertSend($service));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--limit' => '5']));
    }

    // Nothing is sent on a limit that asks for nothing, and the run says so rather than reporting a success it never had
    public function testALimitBelowOneIsRefused(): void
    {
        $service = $this->createMock(BookReleaseAlertServiceInterface::class);
        $service->expects($this->never())->method('notifyReleased');
        $service->expects($this->never())->method('purgeStale');

        $tester = new CommandTester(new BookReleaseAlertSend($service));

        $this->assertSame(Command::INVALID, $tester->execute(['--limit' => '0']));
        $this->assertStringContainsString('at least 1', $tester->getDisplay());
    }
}
