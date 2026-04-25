<?php

declare(strict_types=1);

namespace App\Domain\UseCases;

use App\Domain\DTO\Queries\GetOwnershipFormatsQuery;
use App\Domain\Repository\OwnedFormatRepositoryInterface;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

/**
 * Returns the list of ownership formats available for a given entity type.
 */
class GetOwnershipFormatsUseCase extends AbstractUseCase
{
    private const VALID_ENTITY_TYPES = ['book', 'movie', 'game', 'album'];

    public function __construct(
        private readonly OwnedFormatRepositoryInterface $formatRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string
    {
        return 'GetOwnershipFormats';
    }

    protected function doExecute(mixed ...$args): array
    {
        $query = $args[0] ?? null;

        if (!$query instanceof GetOwnershipFormatsQuery) {
            throw new InvalidArgumentException('Argument must be an instance of GetOwnershipFormatsQuery');
        }

        $entityType = $query->entityType;

        if ($entityType === '' || !in_array($entityType, self::VALID_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid entity type '{$entityType}'. Must be one of: " . implode(', ', self::VALID_ENTITY_TYPES)
            );
        }

        return $this->formatRepository->findByEntityType($entityType);
    }
}
