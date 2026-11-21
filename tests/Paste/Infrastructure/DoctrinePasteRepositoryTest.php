<?php

declare(strict_types=1);

namespace App\Tests\Paste\Infrastructure;

use App\Paste\Domain\Exception\PasteNotFound;
use App\Paste\Domain\Paste;
use App\Paste\Domain\ValueObject\Ciphertext;
use App\Paste\Domain\ValueObject\ContentSizeLimit;
use App\Paste\Domain\ValueObject\DeleteToken;
use App\Paste\Domain\ValueObject\ExpirationPolicy;
use App\Paste\Domain\ValueObject\InitializationVector;
use App\Paste\Domain\ValueObject\PasteId;
use App\Paste\Domain\ValueObject\ViewPolicy;
use App\Paste\Infrastructure\Persistence\Doctrine\DoctrinePasteRepository;
use App\Shared\Domain\Base64Url;
use App\Shared\Domain\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Paste\Domain\ValueObject\KeyDerivation;

final class DoctrinePasteRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DoctrinePasteRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->repository = new DoctrinePasteRepository($this->em);
    }

    public function test_anonymous_paste_roundtrip(): void
    {
        $now = new \DateTimeImmutable('2026-10-13 12:00:00');
        $token = DeleteToken::generate();
        $id = PasteId::generate();
        $ciphertext = Base64Url::encode(random_bytes(64));

        $this->repository->save(Paste::createAnonymous(
            $id,
            Ciphertext::fromBase64Url($ciphertext),
            InitializationVector::fromBase64Url(Base64Url::encode(random_bytes(12))),
            ExpirationPolicy::create(60, $now, 3600),
            ViewPolicy::create(false, 2),
            ContentSizeLimit::ofBytes(1_000_000),
            $token->hash(),
            $now,
        ));
        $this->em->clear();

        $paste = $this->repository->get($id);

        self::assertTrue($paste->acceptsDeleteToken($token));
        self::assertFalse($paste->isExpiredAt($now->modify('+30 seconds')));
        self::assertTrue($paste->isExpiredAt($now->modify('+61 seconds')));
        self::assertSame($ciphertext, $paste->view($now)->ciphertext->value);
        $paste->view($now);
        self::assertTrue($paste->isConsumed());
    }

    public function test_owned_permanent_paste_roundtrip_with_null_expiry(): void
    {
        $now = new \DateTimeImmutable('2026-10-13 12:00:00');
        $owner = UserId::generate();
        $id = PasteId::generate();

        $this->repository->save(Paste::createOwned(
            $id,
            Ciphertext::fromBase64Url(Base64Url::encode(random_bytes(32))),
            InitializationVector::fromBase64Url(Base64Url::encode(random_bytes(12))),
            ExpirationPolicy::create(null, $now, null),
            ViewPolicy::create(true, null),
            ContentSizeLimit::ofBytes(1_000_000),
            $owner,
            $now,
        ));
        $this->em->clear();

        $paste = $this->em->wrapInTransaction(fn() => $this->repository->getForUpdate($id));

        self::assertTrue($paste->isOwnedBy($owner));
        self::assertFalse($paste->isExpiredAt($now->modify('+10 years')));
        $paste->view($now);
        self::assertTrue($paste->isConsumed());
    }

    public function test_remove_is_physical(): void
    {
        $now = new \DateTimeImmutable('2026-10-13 12:00:00');
        $id = PasteId::generate();
        $paste = Paste::createAnonymous(
            $id,
            Ciphertext::fromBase64Url(Base64Url::encode(random_bytes(32))),
            InitializationVector::fromBase64Url(Base64Url::encode(random_bytes(12))),
            ExpirationPolicy::create(60, $now, 3600),
            ViewPolicy::create(false, null),
            ContentSizeLimit::ofBytes(1_000_000),
            DeleteToken::generate()->hash(),
            $now,
        );
        $this->repository->save($paste);
        $this->repository->remove($paste);
        $this->em->clear();

        $this->expectException(PasteNotFound::class);
        $this->repository->get($id);
    }

    public function test_kdf_parameters_roundtrip_and_null_means_no_password(): void
    {
        $now = new \DateTimeImmutable('2026-10-13 12:00:00');
        $salt = Base64Url::encode(random_bytes(16));
        $make = fn(?KeyDerivation $kdf): Paste => Paste::createAnonymous(
            PasteId::generate(),
            Ciphertext::fromBase64Url(Base64Url::encode(random_bytes(32))),
            InitializationVector::fromBase64Url(Base64Url::encode(random_bytes(12))),
            ExpirationPolicy::create(60, $now, 3600),
            ViewPolicy::create(false, null),
            ContentSizeLimit::ofBytes(1_000_000),
            DeleteToken::generate()->hash(),
            $now,
            $kdf,
        );
        $protected = $make(KeyDerivation::fromNullable($salt, 750_000));
        $plain = $make(null);
        $this->repository->save($protected);
        $this->repository->save($plain);
        $this->em->clear();

        $loaded = $this->repository->get($protected->id())->view($now)->keyDerivation;
        self::assertSame($salt, $loaded->salt);
        self::assertSame(750_000, $loaded->iterations);

        self::assertNull($this->repository->get($plain->id())->view($now)->keyDerivation);
    }

}