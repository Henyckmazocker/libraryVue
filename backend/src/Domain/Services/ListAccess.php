<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\Model\MediaList;
use App\Domain\Repository\MediaList\MediaListCollaboratorRepositoryInterface;

/**
 * Quién puede ver y quién puede editar una lista. La regla existe AQUÍ y en
 * ningún otro sitio.
 *
 * Once use cases —crear, renombrar, borrar, añadir ítem, quitar ítem, listar
 * mías, ver una, listar las de otro, invitar, aceptar, expulsar— necesitan
 * responder a las mismas dos preguntas. Escrita once veces, una copia acabaría
 * mal, y la que estuviera mal sería la que enseñara una lista privada a quien
 * no debe.
 *
 * La tabla de verdad completa (3 visibilidades × 4 roles × 2 operaciones):
 *
 * | visibilidad     | dueño            | colaborador      | amigo            | desconocido      |
 * |-----------------|------------------|------------------|------------------|------------------|
 * | `private`       | ver ✅ · editar ✅ | ver ✅ · editar ✅ | ver ❌ · editar ❌ | ver ❌ · editar ❌ |
 * | `public`        | ver ✅ · editar ✅ | ver ✅ · editar ✅ | ver ✅ · editar ❌ | ver ✅ · editar ❌ |
 * | `collaborative` | ver ✅ · editar ✅ | ver ✅ · editar ✅ | ver ❌ · editar ❌ | ver ❌ · editar ❌ |
 *
 * Dos lecturas que se hacen mal:
 *
 * - **`public` la ve cualquier usuario registrado**, no solo tus amigos. Es lo
 *   que «pública» dice, y es coherente con `/user/:username`, que ya es un
 *   perfil público. Por eso la amistad NO entra en esta clase: ser amigo no da
 *   ningún permiso que un desconocido no tenga.
 * - **`collaborative` NO es pública.** La ven sus colaboradores y nadie más. Si
 *   se quiere que todos la vean y unos pocos la editen, se hace `public` y se
 *   le añaden colaboradores: por eso la tabla de colaboradores se consulta en
 *   las tres visibilidades y no solo en `collaborative`.
 *
 * De ahí que la regla real sea más corta que su tabla: **ve** el dueño, el
 * colaborador, o cualquiera si es pública; **edita** el dueño o el colaborador.
 */
final class ListAccess
{
    public function __construct(
        private readonly MediaListCollaboratorRepositoryInterface $collaborators
    ) {
    }

    public function canView(MediaList $list, ?int $viewerId): bool
    {
        if ($viewerId === null) {
            return false;
        }
        if ($list->isOwnedBy($viewerId)) {
            return true;
        }
        if ($list->isPublic()) {
            return true;
        }

        return $this->isCollaborator($list, $viewerId);
    }

    public function canEdit(MediaList $list, ?int $viewerId): bool
    {
        if ($viewerId === null) {
            return false;
        }
        if ($list->isOwnedBy($viewerId)) {
            return true;
        }

        // Que sea pública no da permiso de edición: `public` solo abre la
        // lectura. Se cae directo a la tabla de colaboradores.
        return $this->isCollaborator($list, $viewerId);
    }

    /**
     * El único punto que consulta la base, y solo se llega aquí cuando ni ser
     * dueño ni la visibilidad han resuelto ya la pregunta. Es lo que evita que
     * `get_my_lists` con N listas dispare N consultas.
     *
     * Una lista sin `id` no está persistida, así que no puede tener
     * colaboradores: preguntarlo sería consultar por `NULL`.
     */
    private function isCollaborator(MediaList $list, int $viewerId): bool
    {
        $listId = $list->getId();
        if ($listId === null) {
            return false;
        }

        return $this->collaborators->isCollaborator($listId, $viewerId);
    }
}
