<?php

declare(strict_types=1);

namespace App\Paste\Infrastructure\Persistence\Doctrine;

use App\Paste\Domain\Exception\PasteNotFound;
use App\Paste\Domain\Paste;
use App\Paste\Domain\PasteRepository;
use App\Paste\Domain\ValueObject\PasteId;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePasteRepository implements PasteRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Paste $paste): void
    {
        $this->em->persist($paste);
        $this->em->flush();
    }

    public function get(PasteId $id): Paste
    {
        return $this->em->find(Paste::class, $id) ?? throw PasteNotFound::withId($id);
    }

    public function getForUpdate(PasteId $id): Paste
    {
        return $this->em->find(Paste::class, $id, LockMode::PESSIMISTIC_WRITE) ?? throw PasteNotFound::withId($id);
    }

    public function remove(Paste $paste): void
    {
        $this->em->remove($paste);
        $this->em->flush();
    }
}