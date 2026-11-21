<?php

declare(strict_types=1);

namespace App\Organization\UI\Http;

use App\Organization\Application\Attach\AttachPasteToTagCommand;
use App\Organization\Application\Create\CreatedTag;
use App\Organization\Application\Create\CreateTagCommand;
use App\Organization\Application\Delete\DeleteTagCommand;
use App\Organization\Application\Detach\DetachPasteFromTagCommand;
use App\Organization\Application\Query\ListMyTags;
use App\Organization\Application\Query\ListTaggedPastes;
use App\Organization\Domain\Exception\InvalidTagData;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class TagController
{
    use HandleTrait;

    private const NO_STORE = ['Cache-Control' => 'no-store'];
    private const TAG_ID = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';
    private const PASTE_ID = '[A-Za-z0-9_-]{22}';

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly ListMyTags $listTags,
        private readonly ListTaggedPastes $listTaggedPastes,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/tags', name: 'tag_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidTagData::because('JSON inválido.');
        }
        if (!is_array($data) || !is_string($data['name'] ?? null)) {
            throw InvalidTagData::because('Se requiere "name".');
        }

        $created = $this->handle(new CreateTagCommand($data['name']));

        return new JsonResponse(['id' => $created->id, 'name' => $created->name], 201, self::NO_STORE);
    }

    #[Route('/tags', name: 'tag_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $tags = array_map(
            static fn ($t): array => ['id' => $t->id, 'name' => $t->name, 'pasteCount' => $t->pasteCount],
            ($this->listTags)(),
        );

        return new JsonResponse(['tags' => $tags], 200, self::NO_STORE);
    }

    #[Route('/tags/{id}', name: 'tag_delete', requirements: ['id' => self::TAG_ID], methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        $this->handle(new DeleteTagCommand($id));

        return new Response(null, 204, self::NO_STORE);
    }

    #[Route('/tags/{id}/pastes', name: 'tag_pastes', requirements: ['id' => self::TAG_ID], methods: ['GET'])]
    public function pastes(string $id): JsonResponse
    {
        $pastes = array_map(
            static fn ($p): array => ['id' => $p->id, 'createdAt' => $p->createdAt, 'expiresAt' => $p->expiresAt, 'views' => $p->views],
            ($this->listTaggedPastes)($id),
        );

        return new JsonResponse(['pastes' => $pastes], 200, self::NO_STORE);
    }

    #[Route('/tags/{id}/pastes/{pasteId}', name: 'tag_attach', requirements: ['id' => self::TAG_ID, 'pasteId' => self::PASTE_ID], methods: ['PUT'])]
    public function attach(string $id, string $pasteId): Response
    {
        $this->handle(new AttachPasteToTagCommand($id, $pasteId));

        return new Response(null, 204, self::NO_STORE);
    }

    #[Route('/tags/{id}/pastes/{pasteId}', name: 'tag_detach', requirements: ['id' => self::TAG_ID, 'pasteId' => self::PASTE_ID], methods: ['DELETE'])]
    public function detach(string $id, string $pasteId): Response
    {
        $this->handle(new DetachPasteFromTagCommand($id, $pasteId));

        return new Response(null, 204, self::NO_STORE);
    }
}