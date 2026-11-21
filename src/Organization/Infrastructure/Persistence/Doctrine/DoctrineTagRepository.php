<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Exception\TagAlreadyExists;
use App\Organization\Domain\Exception\TagNotFound;
use App\Organization\Domain\Tag;
use App\Organization\Domain\TagRepository;
use App\Organization\Domain\ValueObject\TagId;
use App\Organization\Domain\ValueObject\TagName;
use App\Paste\Domain\ValueObject\PasteId;
use App\Shared\Domain\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\AggregateHydrator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTagRepository implements TagRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Tag $tag): void
    {
        try {
            $this->em->persist($tag);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw TagAlreadyExists::withName($tag->name());
        }

        $this->syncPastes($tag);
    }

    public function getOwnedForUpdate(TagId $id, UserId $owner): Tag
    {
        $tag = $this->em
            ->createQuery('SELECT t FROM '.Tag::class.' t WHERE t.id = :id AND t.ownerId = :owner')
            ->setParameter('id', $id->value)
            ->setParameter('owner', $owner->value)
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $this->withPastes($tag) ?? throw TagNotFound::withId($id);
    }

    public function findByOwnerAndName(UserId $owner, TagName $name): ?Tag
    {
        return $this->withPastes($this->em->getRepository(Tag::class)->findOneBy(['ownerId' => $owner, 'name' => $name]));
    }

    public function countByOwner(UserId $owner): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM tag WHERE owner_id = ?', [$owner->value]);
    }

    public function remove(Tag $tag): void
    {
        $this->em->remove($tag);
        $this->em->flush();
    }

    public function forgetPaste(PasteId $pasteId): void
    {
        $this->connection()->executeStatement('DELETE FROM paste_tag WHERE paste_id = ?', [$pasteId->value]);
    }

    private function withPastes(?Tag $tag): ?Tag
    {
        if (null === $tag) {
            return null;
        }

        $ids = $this->connection()->fetchFirstColumn('SELECT paste_id FROM paste_tag WHERE tag_id = ?', [$tag->id()->value]);
        AggregateHydrator::set(Tag::class, $tag, 'pasteIds', array_map(PasteId::fromString(...), array_map('strval', $ids)));

        return $tag;
    }

    private function syncPastes(Tag $tag): void
    {
        $conn = $this->connection();
        $tagId = $tag->id()->value;

        $desired = array_map(static fn (PasteId $p): string => $p->value, $tag->pasteIds());
        $current = array_map('strval', $conn->fetchFirstColumn('SELECT paste_id FROM paste_tag WHERE tag_id = ?', [$tagId]));

        foreach (array_diff($desired, $current) as $pasteId) {
            $conn->insert('paste_tag', ['tag_id' => $tagId, 'paste_id' => $pasteId]);
        }
        foreach (array_diff($current, $desired) as $pasteId) {
            $conn->delete('paste_tag', ['tag_id' => $tagId, 'paste_id' => $pasteId]);
        }
    }

    private function connection(): Connection
    {
        return $this->em->getConnection();
    }
}