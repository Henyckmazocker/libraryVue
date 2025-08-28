<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\BookRepositoryInterface;

class EditUserBookUseCase
{
    private BookRepositoryInterface $bookRepository;

    public function __construct(BookRepositoryInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
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
        // Editar datos principales
        $this->bookRepository->editUserBook(
            $userId,
            $isbn,
            $data['currentPage'] ?? null,
            $data['personalRating'] ?? null,
            $data['personalNotes'] ?? null,
            $data['consumedAt'] ?? null
        );

        // Actualizar estados del libro si se pasan
        if (isset($data['statuses']) && is_array($data['statuses'])) {
            $this->bookRepository->updateUserBookStatuses($userId, $isbn, $data['statuses']);
        }

        // Añadir tags y asignarlos
        foreach ($tags as $tag) {
            $tagId = $this->bookRepository->addUserBookTag(
                $userId,
                $tag['name'],
                $tag['color'] ?? '#007bff'
            );
            $this->bookRepository->assignUserBookTag($userId, $isbn, $tagId);
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
