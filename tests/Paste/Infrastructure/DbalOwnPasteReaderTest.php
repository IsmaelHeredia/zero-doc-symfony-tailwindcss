<?php

declare(strict_types=1);

namespace App\Tests\Paste\Infrastructure;

use App\Paste\Infrastructure\Persistence\Doctrine\DbalOwnPasteReader;
use App\Paste\Infrastructure\Persistence\Doctrine\DoctrinePasteRepository;
use App\Shared\Domain\UserId;
use App\Tests\Paste\Support\PasteFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalOwnPasteReaderTest extends KernelTestCase
{
    public function test_it_reports_truncation_and_never_mixes_owners(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $repository = new DoctrinePasteRepository($em);

        $owner = UserId::generate();
        foreach (range(1, 3) as $_) {
            $repository->save(PasteFactory::owned($owner));
        }
        $repository->save(PasteFactory::owned(UserId::generate()));
        $em->clear();

        $reader = new DbalOwnPasteReader(self::getContainer()->get('doctrine.dbal.default_connection'));
        $now = new \DateTimeImmutable();

        $two = $reader->pastesOf($owner, $now, 2);
        self::assertCount(2, $two->pastes);
        self::assertTrue($two->truncated);

        $three = $reader->pastesOf($owner, $now, 3);
        self::assertCount(3, $three->pastes);
        self::assertFalse($three->truncated);

        self::assertSame([], $reader->pastesOf(UserId::generate(), $now, 3)->pastes);
    }
}