<?php
declare(strict_types=1);

namespace App\Domain\UseCases\Albums;

use App\Domain\DTO\Queries\GetLastFmStatsQuery;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\Services\LastFmService;
use App\Domain\UseCases\AbstractUseCase;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;

/**
 * Fetches Last.fm listening statistics for the authenticated user.
 *
 * The user must have their lastfm_username configured in their profile.
 * Data is fetched from the Last.fm API and cached by LastFmService.
 */
class GetListeningStatsUseCase extends AbstractUseCase
{
    private const ALLOWED_TYPES = [
        'user_info',
        'top_albums',
        'top_artists',
        'top_tracks',
        'recent_tracks',
        'loved_tracks',
        'weekly_album_chart',
        'weekly_artist_chart',
        'album_info',
    ];

    private const ALLOWED_PERIODS = [
        'overall', '7day', '1month', '3month', '6month', '12month',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LastFmService $lastFmService,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string
    {
        return 'GetListeningStats';
    }

    protected function doExecute(mixed ...$args): array
    {
        $query = $args[0] ?? null;

        if (!$query instanceof GetLastFmStatsQuery) {
            throw new InvalidArgumentException('Query must be an instance of GetLastFmStatsQuery');
        }

        // Validate statsType and period
        if (!in_array($query->statsType, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException("Invalid stats_type: {$query->statsType}");
        }

        if (!in_array($query->period, self::ALLOWED_PERIODS, true)) {
            throw new InvalidArgumentException("Invalid period: {$query->period}");
        }

        // Resolve Last.fm username from user record
        $user = $this->userRepository->findById($query->userId);
        if (!$user) {
            throw new InvalidArgumentException("User with ID {$query->userId} not found");
        }

        $lastfmUsername = $user->getLastFmUsername();

        // album_info: lastfm_username is optional (adds personal playcount when present)
        if ($query->statsType === 'album_info') {
            if (empty($query->artist) || empty($query->album)) {
                throw new InvalidArgumentException('artist and album are required for album_info stats_type');
            }
            $stale    = false;
            $cachedAt = null;
            try {
                $sobre    = $this->lastFmService->getAlbumInfoResilient($query->artist, $query->album, $lastfmUsername ?: null);
                $parsed   = $this->lastFmService->parseAlbumInfo($sobre['data']);
                $stale    = $sobre['stale'];
                $cachedAt = $sobre['cached_at'];
            } catch (\RuntimeException $e) {
                // Album not found on Last.fm or API error — return null data gracefully
                $parsed = null;
            }
            return [
                'stats_type'      => 'album_info',
                'period'          => $query->period,
                'lastfm_username' => $lastfmUsername,
                'data'            => $parsed,
                'stale'           => $stale,
                'cached_at'       => $cachedAt ? date('c', $cachedAt) : null,
            ];
        }

        if (empty($lastfmUsername)) {
            throw new InvalidArgumentException(
                'Last.fm username not configured. Please add it in your profile.'
            );
        }

        // Fetch data from Last.fm.
        //
        // Se pide la variante `…Resilient()` porque el dashboard tiene que poder
        // decir que lo que enseña sale de una caché caducada. Es UNA llamada por
        // petición —el statsType lo elige el selector de `ListeningStats.vue`—,
        // así que la frescura de la respuesta es la de esta llamada y no hay
        // nada que agregar.
        $sobre = match ($query->statsType) {
            'user_info'          => $this->lastFmService->getUserInfoResilient($lastfmUsername),
            'top_albums'         => $this->lastFmService->getTopAlbumsResilient($lastfmUsername, $query->period, $query->limit),
            'top_artists'        => $this->lastFmService->getTopArtistsResilient($lastfmUsername, $query->period, $query->limit),
            'top_tracks'         => $this->lastFmService->getTopTracksResilient($lastfmUsername, $query->period, $query->limit),
            'recent_tracks'      => $this->lastFmService->getRecentTracksResilient($lastfmUsername, $query->limit),
            'loved_tracks'       => $this->lastFmService->getLovedTracksResilient($lastfmUsername, $query->limit),
            'weekly_album_chart' => $this->lastFmService->getWeeklyAlbumChartResilient($lastfmUsername),
            'weekly_artist_chart'=> $this->lastFmService->getWeeklyArtistChartResilient($lastfmUsername),
            default              => throw new InvalidArgumentException("Unhandled stats_type: {$query->statsType}")
        };

        $raw = $sobre['data'];

        // Parse raw response into a clean array
        $parsed = match ($query->statsType) {
            'user_info'    => $this->lastFmService->parseUserInfo($raw),
            'top_albums'   => $this->lastFmService->parseTopAlbums($raw),
            'top_artists'  => $this->lastFmService->parseTopArtists($raw),
            'top_tracks',
            'recent_tracks',
            'loved_tracks' => $this->lastFmService->parseRecentTracks($raw),
            default        => $raw
        };

        return [
            'stats_type'      => $query->statsType,
            'period'          => $query->period,
            'lastfm_username' => $lastfmUsername,
            'data'            => $parsed,
            'stale'           => $sobre['stale'],
            'cached_at'       => $sobre['cached_at'] ? date('c', $sobre['cached_at']) : null,
        ];
    }
}
