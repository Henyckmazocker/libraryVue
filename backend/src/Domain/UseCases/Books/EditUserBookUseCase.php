<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

class EditUserBookUseCase
{
    private BookRepositoryInterface $bookRepository;
    private UserRepositoryInterface $userRepository;

    public function __construct(BookRepositoryInterface $bookRepository, UserRepositoryInterface $userRepository)
    {
        $this->bookRepository = $bookRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Modifica todos los aspectos de un user_book: datos principales, tags y notas por página.
     * Los parámetros que sean null no se modifican.
     *
     * @param int $userId
     * @param string $isbn
     * @param array $data ['currentPage', 'personalRating', 'personalNotes', 'consumedAt']
     * @param array $tags [['name' => string, 'color' => string]]
     * @param array $notes [['pageNumber' => int, 'noteText' => string, 'noteType' => string, 'isPrivate' => bool]]
     */
    public function execute(
        int $userId,
        string $isbn,
        array $data = [],
        array $tags = [],
        array $notes = []
    ): void {
        // Verificar si el libro existe en la biblioteca del usuario
        if (!$this->userRepository->hasUserBook($userId, $isbn)) {
            // Si no existe, añadirlo primero
            $this->userRepository->addUserBook(
                $userId,
                $isbn,
                $data['currentPage'] ?? null,
                $data['personalRating'] ?? null,
                $data['personalNotes'] ?? null,
                $data['consumedAt'] ?? null
            );
        } else {
            // Si existe, editar datos principales
            $this->bookRepository->editUserBook(
                $userId,
                $isbn,
                $data['currentPage'] ?? null,
                $data['personalRating'] ?? null,
                $data['personalNotes'] ?? null,
                $data['consumedAt'] ?? null
            );
        }

        // Actualizar estados del libro si se pasan
        if (isset($data['statuses']) && is_array($data['statuses'])) {
            $this->bookRepository->updateUserBookStatuses($userId, $isbn, $data['statuses']);
        }

        // Eliminar todos los tags previamente asignados
        $this->bookRepository->removeAllUserBookTags($userId, $isbn);

        // Añadir tags y asignarlos
        foreach ($tags as $tag) {
            // Si $tag es un ID numérico, simplemente asignarlo
            if (is_numeric($tag)) {
                $tagId = (int)$tag;
                $this->bookRepository->assignUserBookTag($userId, $isbn, $tagId);
            } 
            // Si $tag es un array con name, crear nuevo tag
            elseif (is_array($tag) && isset($tag['name'])) {
                $tagId = $this->bookRepository->addUserBookTag(
                    $userId,
                    $tag['name'],
                    $tag['color'] ?? '#007bff'
                );
                $this->bookRepository->assignUserBookTag($userId, $isbn, $tagId);
            }
        }

        // Añadir notas por página
        foreach ($notes as $note) {
            $this->bookRepository->addUserBookNote(
                $userId,
                $isbn,
                $note['pageNumber'],
                $note['noteText'],
                $note['noteType'] ?? 'note',
                $note['isPrivate'] ?? true
            );
        }
    }
}
