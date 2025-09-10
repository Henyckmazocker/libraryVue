<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Movies;

use App\Domain\Repository\MovieRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

class EditUserMovieUseCase
{
    private MovieRepositoryInterface $movieRepository;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        MovieRepositoryInterface $movieRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->movieRepository = $movieRepository;
        $this->userRepository = $userRepository;
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
        // Verificar si la película existe en la biblioteca del usuario
        if (!$this->userRepository->hasUserMovie($userId, $movieIsbn)) {
            // Si no existe, añadirla primero
            $this->userRepository->addUserMovie(
                $userId,
                $movieIsbn,
                $data['personalRating'] ?? null,
                $data['personalNotes'] ?? null,
                $data['consumedAt'] ?? null
            );
        } else {
            // Si existe, editar datos principales
            $this->movieRepository->editUserMovie(
                $userId,
                $movieIsbn,
                $data['personalRating'] ?? null,
                $data['personalNotes'] ?? null,
                $data['consumedAt'] ?? null
            );
        }

        // Actualizar estados de la película si se pasan
        if (isset($data['statuses']) && is_array($data['statuses'])) {
            $this->movieRepository->updateUserMovieStatuses($userId, $movieIsbn, $data['statuses']);
        }

        // Eliminar todos los tags previamente asignados
        $this->movieRepository->removeAllUserMovieTags($userId, $movieIsbn);

        // Añadir tags y asignarlos
        foreach ($tags as $tag) {
            // Si $tag es un ID numérico, simplemente asignarlo
            if (is_numeric($tag)) {
                $tagId = (int)$tag;
                $this->movieRepository->assignUserMovieTag($userId, $movieIsbn, $tagId);
            } 
            // Si $tag es un array con name, crear nuevo tag
            elseif (is_array($tag) && isset($tag['name'])) {
                $tagId = $this->movieRepository->addUserMovieTag(
                    $userId,
                    $tag['name'],
                    $tag['color'] ?? '#007bff'
                );
                $this->movieRepository->assignUserMovieTag($userId, $movieIsbn, $tagId);
            }
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
