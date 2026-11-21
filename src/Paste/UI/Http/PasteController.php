<?php

declare(strict_types=1);

namespace App\Paste\UI\Http;

use App\Paste\Application\Create\CreatedPaste;
use App\Paste\Application\Create\CreatePasteCommand;
use App\Paste\Application\Delete\DeletePasteCommand;
use App\Paste\Application\Query\GetPasteAccessInfo;
use App\Paste\Application\Read\ReadPasteCommand;
use App\Paste\Domain\EncryptedPayload;
use App\Paste\Domain\Exception\InvalidPasteData;
use App\Paste\Domain\Exception\PasteNotFound;
use App\Paste\Domain\ValueObject\PasteId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Paste\Application\Query\ListOwnPastes;
use App\Paste\Application\Query\OwnPasteSummary;

#[AsController]
final class PasteController
{
    use HandleTrait;

    private const NO_STORE = ['Cache-Control' => 'no-store'];
    private const ID = '[A-Za-z0-9_-]{22}';

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly GetPasteAccessInfo $accessInfo,
        private readonly ListOwnPastes $listOwn,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/pastes', name: 'paste_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidPasteData::because('JSON inválido.');
        }
        if (!is_array($data)) {
            throw InvalidPasteData::because('Cuerpo de la petición inválido.');
        }

        $ciphertext = $data['ciphertext'] ?? null;
        $iv = $data['iv'] ?? null;
        $ttl = $data['ttl'] ?? null;
        $burn = $data['burnAfterRead'] ?? false;
        $maxViews = $data['maxViews'] ?? null;
        $kdf = $data['kdf'] ?? null;

        if (
            !is_string($ciphertext) || !is_string($iv)
            || !(null === $ttl || is_int($ttl))
            || !is_bool($burn)
            || !(null === $maxViews || is_int($maxViews))
            || !(null === $kdf || (is_array($kdf) && is_string($kdf['salt'] ?? null) && is_int($kdf['iterations'] ?? null)))
        ) {
            throw InvalidPasteData::because('Cuerpo de la petición inválido.');
        }

        $created = $this->handle(new CreatePasteCommand(
            $ciphertext,
            $iv,
            $ttl,
            $burn,
            $maxViews,
            $kdf['salt'] ?? null,
            $kdf['iterations'] ?? null,
        ));

        return new JsonResponse(['id' => $created->id, 'deleteToken' => $created->deleteToken], 201, self::NO_STORE);
    }

    #[Route('/pastes/{id}/meta', name: 'paste_meta', requirements: ['id' => self::ID], methods: ['GET'])]
    public function meta(string $id): JsonResponse
    {
        $info = ($this->accessInfo)($id) ?? throw PasteNotFound::withId(PasteId::fromString($id));

        return new JsonResponse([
            'hasPassword' => $info->hasPassword(),
            'kdf' => $info->hasPassword() ? ['salt' => $info->kdfSalt, 'iterations' => $info->kdfIterations] : null,
        ], 200, self::NO_STORE);
    }

    #[Route('/pastes/{id}', name: 'paste_read', requirements: ['id' => self::ID], methods: ['GET'])]
    public function read(string $id): JsonResponse
    {
        $payload = $this->handle(new ReadPasteCommand($id));
        if (null === $payload) {
            throw PasteNotFound::withId(PasteId::fromString($id));
        }

        return new JsonResponse([
            'ciphertext' => $payload->ciphertext->value,
            'iv' => $payload->iv->value,
            'kdf' => null === $payload->keyDerivation
                ? null
                : ['salt' => $payload->keyDerivation->salt, 'iterations' => $payload->keyDerivation->iterations],
        ], 200, self::NO_STORE);
    }

    #[Route('/pastes/{id}', name: 'paste_delete', requirements: ['id' => self::ID], methods: ['DELETE'])]
    public function delete(string $id, Request $request): Response
    {
        $this->handle(new DeletePasteCommand($id, $request->headers->get('X-Delete-Token')));

        return new Response(null, 204, self::NO_STORE);
    }

    #[Route('/pastes/mine', name: 'paste_mine', methods: ['GET'])]
    public function mine(): JsonResponse
    {
        $page = ($this->listOwn)();

        return new JsonResponse([
            'pastes' => array_map(static fn(OwnPasteSummary $p): array => [
                'id' => $p->id,
                'createdAt' => $p->createdAt,
                'expiresAt' => $p->expiresAt,
                'views' => $p->views,
                'maxViews' => $p->maxViews,
                'burnAfterRead' => $p->burnAfterRead,
                'hasPassword' => $p->hasPassword,
            ], $page->pastes),
            'truncated' => $page->truncated,
        ], 200, self::NO_STORE);
    }

}