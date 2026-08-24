<?php

declare(strict_types=1);

namespace App\Domain\Services;

use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * La lista de pistas de un álbum del mirror
 *
 * Los dumps de MusicBrainz traen el conteo de pistas, no la lista, así que la
 * lista se pide a la API por la release canónica —`mb_release_group.
 * canonical_release_gid`— y se guarda en `mb_track`. Quien la consulta es
 * `MySqlAlbumCatalog::tracksFor()`; esta clase solo la trae y la persiste.
 *
 * **Nunca se llama dentro de la petición de un usuario.** La API tarda entre
 * 4 y 45 segundos, así que el disparo va diferido con `PostResponse::defer()`,
 * igual que la descarga de portadas.
 */
class AlbumTrackService
{
    /** Tres intentos y se abandona, como CoverStore: una URL que falla siempre no mejora sola */
    public const MAX_ATTEMPTS = 3;

    private MusicBrainzService $musicBrainz;
    private PDO $mirror;
    private LoggerInterface $logger;

    public function __construct(MusicBrainzService $musicBrainz, PDO $mirror, LoggerInterface $logger)
    {
        $this->musicBrainz = $musicBrainz;
        $this->mirror      = $mirror;
        $this->logger      = $logger;
    }

    // =========================================================================
    // El mapeo — puro, y la parte que de verdad hay que testear
    // =========================================================================

    /**
     * Aplana los medios de una release en una lista de pistas
     *
     * **`position` se renumera de forma continua a través de los medios**, y
     * esto es lo único delicado de todo el fichero: en un álbum doble cada
     * medio empieza otra vez por la pista 1, así que respetar el `position` que
     * viene de la API haría colisionar la clave primaria de `mb_track` y
     * perdería medio disco sin decir nada.
     *
     * El `number` sí se conserva tal cual: es lo impreso en el disco ('A1',
     * 'B3') y es lo que tiene sentido enseñar.
     *
     * @param array<string,mixed> $release JSON de la API, ya decodificado
     * @return array<int,array{position:int,number:string|null,title:string,length_ms:int|null,recording_gid:string|null}>
     */
    public static function flattenTracks(array $release): array
    {
        $out      = [];
        $position = 0;

        foreach ($release['media'] ?? [] as $medium) {
            foreach ($medium['tracks'] ?? [] as $track) {
                $title = $track['title'] ?? null;
                if ($title === null || $title === '') {
                    continue;
                }

                $position++;
                $out[] = [
                    'position'      => $position,
                    'number'        => isset($track['number']) ? (string) $track['number'] : null,
                    'title'         => (string) $title,
                    // `length` viene en milisegundos y puede faltar.
                    'length_ms'     => isset($track['length']) ? (int) $track['length'] : null,
                    'recording_gid' => $track['id'] ?? null,
                ];
            }
        }

        return $out;
    }

    // =========================================================================
    // Traer y persistir
    // =========================================================================

    /**
     * Trae las pistas de un álbum y las guarda, si hace falta
     *
     * @return int Cuántas pistas quedaron guardadas (0 si no se pudo)
     */
    public function fetchFor(string $releaseGroupGid): int
    {
        $canonical = $this->canonicalReleaseOf($releaseGroupGid);

        if ($canonical === null) {
            // Sin release canónica no hay nada que pedir. Se registra igual para
            // que el backfill no lo reintente eternamente.
            $this->recordFailure($releaseGroupGid, 'sin release canónica en el mirror');

            return 0;
        }

        $release = $this->musicBrainz->releaseWithRecordings($canonical);

        if ($release === null) {
            $this->recordFailure($releaseGroupGid, 'la API no devolvió la release');

            return 0;
        }

        $tracks = self::flattenTracks($release);

        if ($tracks === []) {
            // Aquí sí es legítimo: la release existe y no tiene pistas cargadas
            // en MusicBrainz. Se marca como resuelta con cero para no volver.
            $this->recordSuccess($releaseGroupGid, []);

            return 0;
        }

        $this->recordSuccess($releaseGroupGid, $tracks);

        $this->logger->info('musicbrainz: pistas guardadas', [
            'release_group' => $releaseGroupGid,
            'tracks'        => count($tracks),
        ]);

        return count($tracks);
    }

    /** Si este álbum ya se resolvió, o agotó sus intentos */
    public function isSettled(string $releaseGroupGid): bool
    {
        $stmt = $this->mirror->prepare(
            'SELECT fetched_at, attempts FROM mb_track_fetch WHERE release_group_gid = :gid'
        );
        $stmt->execute([':gid' => $releaseGroupGid]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        return $row['fetched_at'] !== null || (int) $row['attempts'] >= self::MAX_ATTEMPTS;
    }

    private function canonicalReleaseOf(string $releaseGroupGid): ?string
    {
        $stmt = $this->mirror->prepare(
            'SELECT canonical_release_gid FROM mb_release_group WHERE gid = :gid'
        );
        $stmt->execute([':gid' => $releaseGroupGid]);

        $gid = $stmt->fetchColumn();

        return $gid === false || $gid === null || $gid === '' ? null : (string) $gid;
    }

    /**
     * @param array<int,array<string,mixed>> $tracks
     */
    private function recordSuccess(string $releaseGroupGid, array $tracks): void
    {
        $this->mirror->beginTransaction();
        try {
            // Se borra antes de insertar: una reimportación puede haber cambiado
            // la release canónica, y mezclar dos listas dejaría pistas fantasma
            // en las posiciones altas de la más corta.
            $del = $this->mirror->prepare('DELETE FROM mb_track WHERE release_group_gid = :gid');
            $del->execute([':gid' => $releaseGroupGid]);

            $ins = $this->mirror->prepare(
                'INSERT INTO mb_track (release_group_gid, position, number, title, length_ms, recording_gid)
                 VALUES (:gid, :position, :number, :title, :length_ms, :recording_gid)'
            );
            foreach ($tracks as $track) {
                $ins->execute([
                    ':gid'           => $releaseGroupGid,
                    ':position'      => $track['position'],
                    ':number'        => $track['number'],
                    ':title'         => mb_substr($track['title'], 0, 512),
                    ':length_ms'     => $track['length_ms'],
                    ':recording_gid' => $track['recording_gid'],
                ]);
            }

            $this->mirror->prepare(
                "INSERT INTO mb_track_fetch (release_group_gid, fetched_at, track_count, attempts)
                 VALUES (:gid, NOW(), :n, 0)
                 ON DUPLICATE KEY UPDATE fetched_at = NOW(), track_count = VALUES(track_count),
                                         attempts = 0, last_error = NULL"
            )->execute([':gid' => $releaseGroupGid, ':n' => count($tracks)]);

            $this->mirror->commit();
        } catch (Throwable $e) {
            $this->mirror->rollBack();
            $this->logger->error('musicbrainz: no se pudieron guardar las pistas', [
                'release_group' => $releaseGroupGid,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    private function recordFailure(string $releaseGroupGid, string $reason): void
    {
        $this->mirror->prepare(
            'INSERT INTO mb_track_fetch (release_group_gid, attempts, last_error)
             VALUES (:gid, 1, :error)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_error = VALUES(last_error)'
        )->execute([':gid' => $releaseGroupGid, ':error' => mb_substr($reason, 0, 255)]);

        $this->logger->info('musicbrainz: pistas no obtenidas', [
            'release_group' => $releaseGroupGid,
            'reason'        => $reason,
        ]);
    }
}
