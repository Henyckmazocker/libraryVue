<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Model\MediaList;
use App\Domain\Repository\MediaList\MediaListCollaboratorRepositoryInterface;
use App\Domain\Services\ListAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La tabla de verdad de `ListAccess`, entera: 3 visibilidades × 4 roles × 2
 * operaciones = 24 casos, más los bordes que la tabla no cubre.
 *
 * Se escribe antes que la migración a propósito: si al rellenarla apareciera un
 * caso que no se sabe contestar, sería una decisión que falta, y descubrirla
 * aquí cuesta una tarde en vez de un escape de privacidad en producción.
 */
class ListAccessTest extends TestCase
{
    private const OWNER        = 1;
    private const COLLABORATOR = 2;
    private const FRIEND       = 3;
    private const STRANGER     = 4;

    private const LIST_ID = 77;

    /**
     * El doble solo sabe una cosa: quién está en `media_list_collaborator`.
     * La amistad no aparece por ninguna parte, y eso es la afirmación central
     * de esta clase — ser amigo no da ningún permiso.
     */
    private function accessWithCollaborators(int ...$collaboratorIds): ListAccess
    {
        $repo = $this->createStub(MediaListCollaboratorRepositoryInterface::class);
        $repo->method('isCollaborator')
            ->willReturnCallback(
                static fn (int $listId, int $userId): bool => in_array($userId, $collaboratorIds, true)
            );

        return new ListAccess($repo);
    }

    private function listWith(string $visibility, ?int $id = self::LIST_ID): MediaList
    {
        return new MediaList(
            id:         $id,
            ownerId:    self::OWNER,
            name:       'Para el verano',
            visibility: $visibility
        );
    }

    // ------------------------------------------------------------------
    // La tabla de verdad: 12 casos de `canView` y 12 de `canEdit`
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, bool}>
     */
    public static function viewCases(): array
    {
        return [
            // visibilidad `private`: solo dueño y colaborador
            'private · dueño ve'              => [MediaList::VISIBILITY_PRIVATE,       self::OWNER,        true],
            'private · colaborador ve'        => [MediaList::VISIBILITY_PRIVATE,       self::COLLABORATOR, true],
            'private · amigo NO ve'           => [MediaList::VISIBILITY_PRIVATE,       self::FRIEND,       false],
            'private · desconocido NO ve'     => [MediaList::VISIBILITY_PRIVATE,       self::STRANGER,     false],

            // visibilidad `public`: cualquier usuario registrado
            'public · dueño ve'               => [MediaList::VISIBILITY_PUBLIC,        self::OWNER,        true],
            'public · colaborador ve'         => [MediaList::VISIBILITY_PUBLIC,        self::COLLABORATOR, true],
            'public · amigo ve'               => [MediaList::VISIBILITY_PUBLIC,        self::FRIEND,       true],
            'public · desconocido ve'         => [MediaList::VISIBILITY_PUBLIC,        self::STRANGER,     true],

            // visibilidad `collaborative`: NO es pública
            'collaborative · dueño ve'        => [MediaList::VISIBILITY_COLLABORATIVE, self::OWNER,        true],
            'collaborative · colaborador ve'  => [MediaList::VISIBILITY_COLLABORATIVE, self::COLLABORATOR, true],
            'collaborative · amigo NO ve'     => [MediaList::VISIBILITY_COLLABORATIVE, self::FRIEND,       false],
            'collaborative · desconocido NO ve' => [MediaList::VISIBILITY_COLLABORATIVE, self::STRANGER,   false],
        ];
    }

    #[Test]
    #[DataProvider('viewCases')]
    public function view_truth_table(string $visibility, int $viewerId, bool $expected): void
    {
        $access = $this->accessWithCollaborators(self::COLLABORATOR);

        $this->assertSame($expected, $access->canView($this->listWith($visibility), $viewerId));
    }

    /**
     * @return array<string, array{string, int, bool}>
     */
    public static function editCases(): array
    {
        return [
            'private · dueño edita'             => [MediaList::VISIBILITY_PRIVATE,       self::OWNER,        true],
            'private · colaborador edita'       => [MediaList::VISIBILITY_PRIVATE,       self::COLLABORATOR, true],
            'private · amigo NO edita'          => [MediaList::VISIBILITY_PRIVATE,       self::FRIEND,       false],
            'private · desconocido NO edita'    => [MediaList::VISIBILITY_PRIVATE,       self::STRANGER,     false],

            // Pública se LEE, no se escribe: es el par que más se confunde.
            'public · dueño edita'              => [MediaList::VISIBILITY_PUBLIC,        self::OWNER,        true],
            'public · colaborador edita'        => [MediaList::VISIBILITY_PUBLIC,        self::COLLABORATOR, true],
            'public · amigo NO edita'           => [MediaList::VISIBILITY_PUBLIC,        self::FRIEND,       false],
            'public · desconocido NO edita'     => [MediaList::VISIBILITY_PUBLIC,        self::STRANGER,     false],

            'collaborative · dueño edita'       => [MediaList::VISIBILITY_COLLABORATIVE, self::OWNER,        true],
            'collaborative · colaborador edita' => [MediaList::VISIBILITY_COLLABORATIVE, self::COLLABORATOR, true],
            'collaborative · amigo NO edita'    => [MediaList::VISIBILITY_COLLABORATIVE, self::FRIEND,       false],
            'collaborative · desconocido NO edita' => [MediaList::VISIBILITY_COLLABORATIVE, self::STRANGER,  false],
        ];
    }

    #[Test]
    #[DataProvider('editCases')]
    public function edit_truth_table(string $visibility, int $viewerId, bool $expected): void
    {
        $access = $this->accessWithCollaborators(self::COLLABORATOR);

        $this->assertSame($expected, $access->canEdit($this->listWith($visibility), $viewerId));
    }

    // ------------------------------------------------------------------
    // Lo que la tabla no cubre
    // ------------------------------------------------------------------

    /**
     * La firma acepta `?int` aunque las once acciones lleven `Auth` y el id
     * nunca debería llegar nulo. Si algún día una ruta se declara sin `Auth`,
     * esto es lo que impide que el anónimo vea una lista pública.
     */
    #[Test]
    public function an_anonymous_viewer_can_do_nothing_not_even_on_a_public_list(): void
    {
        $access = $this->accessWithCollaborators(self::COLLABORATOR);

        foreach (MediaList::VALID_VISIBILITIES as $visibility) {
            $list = $this->listWith($visibility);
            $this->assertFalse($access->canView($list, null), "canView anónimo en {$visibility}");
            $this->assertFalse($access->canEdit($list, null), "canEdit anónimo en {$visibility}");
        }
    }

    /**
     * La amistad no entra en esta clase. Amigo y desconocido son el mismo caso,
     * y conviene que un test lo diga: es lo que se rompería si alguien
     * «arreglara» la tabla de verdad metiendo `friendships`.
     */
    #[Test]
    public function a_friend_has_exactly_the_permissions_of_a_stranger(): void
    {
        $access = $this->accessWithCollaborators(self::COLLABORATOR);

        foreach (MediaList::VALID_VISIBILITIES as $visibility) {
            $list = $this->listWith($visibility);
            $this->assertSame(
                $access->canView($list, self::STRANGER),
                $access->canView($list, self::FRIEND),
                "canView difiere entre amigo y desconocido en {$visibility}"
            );
            $this->assertSame(
                $access->canEdit($list, self::STRANGER),
                $access->canEdit($list, self::FRIEND),
                "canEdit difiere entre amigo y desconocido en {$visibility}"
            );
        }
    }

    /**
     * El cortocircuito no es cosmético: es lo que evita que `get_my_lists` con
     * N listas propias dispare N consultas a `media_list_collaborator`.
     */
    #[Test]
    public function the_owner_is_resolved_without_asking_the_collaborator_table(): void
    {
        $repo = $this->createMock(MediaListCollaboratorRepositoryInterface::class);
        $repo->expects($this->never())->method('isCollaborator');
        $access = new ListAccess($repo);

        foreach (MediaList::VALID_VISIBILITIES as $visibility) {
            $this->assertTrue($access->canView($this->listWith($visibility), self::OWNER));
            $this->assertTrue($access->canEdit($this->listWith($visibility), self::OWNER));
        }
    }

    #[Test]
    public function a_public_list_is_visible_without_asking_the_collaborator_table(): void
    {
        $repo = $this->createMock(MediaListCollaboratorRepositoryInterface::class);
        $repo->expects($this->never())->method('isCollaborator');
        $access = new ListAccess($repo);

        $this->assertTrue($access->canView($this->listWith(MediaList::VISIBILITY_PUBLIC), self::STRANGER));
    }

    /**
     * Una lista recién construida y aún sin guardar no tiene `id`, así que
     * preguntar por sus colaboradores sería consultar por `NULL` — que en SQL
     * no casa con nada, pero es una consulta igualmente.
     */
    #[Test]
    public function an_unsaved_list_never_reaches_the_collaborator_table(): void
    {
        $repo = $this->createMock(MediaListCollaboratorRepositoryInterface::class);
        $repo->expects($this->never())->method('isCollaborator');
        $access = new ListAccess($repo);

        $unsaved = $this->listWith(MediaList::VISIBILITY_COLLABORATIVE, id: null);

        $this->assertFalse($access->canView($unsaved, self::COLLABORATOR));
        $this->assertFalse($access->canEdit($unsaved, self::COLLABORATOR));
    }
}
