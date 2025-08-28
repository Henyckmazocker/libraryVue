<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\MovieRepositoryInterface;

class EditUserMovieUseCase
{
    private MovieRepositoryInterface $movieRepository;

    public function __construct(MovieRepositoryInterface $movieRepository)
    {
        $this->movieRepository = $movieRepository;
    }

    /**
     * Modifica todos los aspectos de un user_movie: datos principales, tags y notas.
     * Los parámetros que sean null no se modifican.
     *
     * @param int $userId
     * @param string $movieIsbn
     * @param array $data ['personalRating', 'personalNotes', 'consumedAt']
     * @param array $tags [['name' => string, 'color' => string]]
     * @param array $notes [['noteText' => string, 'noteType' => string, 'isPrivate' => bool]]
     */
    public function execute(
        int $userId,
        string $movieIsbn,
        array $data = [],
        array $tags = [],
        array $notes = []
    ): void {
        // Editar datos principales
        $this->movieRepository->editUserMovie(
            $userId,
            $movieIsbn,
            $data['personalRating'] ?? null,
            $data['personalNotes'] ?? null,
            $data['consumedAt'] ?? null
        );

        // Actualizar estados de la película si se pasan
        if (isset($data['statuses']) && is_array($data['statuses'])) {
            $this->movieRepository->updateUserMovieStatuses($userId, $movieIsbn, $data['statuses']);
        }

        // Añadir tags y asignarlos
        foreach ($tags as $tag) {
            $tagId = $this->movieRepository->addUserMovieTag(
                $userId,
                $tag['name'],
                $tag['color'] ?? '#007bff'
            );
            $this->movieRepository->assignUserMovieTag($userId, $movieIsbn, $tagId);
        }

        // Añadir notas
        foreach ($notes as $note) {
            $this->movieRepository->addUserMovieNote(
                $userId,
                $movieIsbn,
                $note['noteText'],
                $note['noteType'] ?? 'note',
                $note['isPrivate'] ?? true
            );
        }
    }
}
