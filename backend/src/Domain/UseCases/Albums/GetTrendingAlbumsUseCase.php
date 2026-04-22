<?php

declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\Repository\Album\UserAlbumRepositoryInterface;
use App\Domain\UseCases\AbstractUseCase;
use App\Domain\DTO\Queries\GetTrendingAlbumsQuery;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

class GetTrendingAlbumsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserAlbumRepositoryInterface $userAlbumRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function doExecute($command): array
    {
        if (!$command instanceof GetTrendingAlbumsQuery) {
            throw new InvalidArgumentException('Command must be an instance of GetTrendingAlbumsQuery');
        }

        $this->logger->info('Getting trending albums', [
            'limit'      => $command->limit,
            'daysWindow' => $command->daysWindow,
        ]);

        $trendingAlbums = $this->userAlbumRepository->getTrendingAlbums(
            $command->limit,
            $command->daysWindow,
            $command->userId
        );

        $this->logger->info('Trending albums retrieved', [
            'count' => count($trendingAlbums),
        ]);

        return $trendingAlbums;
    }

    protected function getLogContext(): string
    {
        return 'GetTrendingAlbumsUseCase';
    }

    protected function getSuccessMessage(): string
    {
        return 'Trending albums retrieved successfully';
    }

    protected function getErrorMessage(): string
    {
        return 'Failed to retrieve trending albums';
    }
}
