<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Game\Mappers;

use App\Infrastructure\Persistence\Game\Mappers\GameDataMapper;
use App\Domain\Model\Game;
use App\Domain\Model\ValueObjects\GameIdentifier;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Timestamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GameDataMapperTest extends TestCase
{
    private GameDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GameDataMapper();
    }

    private function fullDbRow(): array
    {
        return [
            'id' => 12345,
            'slug' => 'the-witcher-3',
            'title' => 'The Witcher 3',
            'release_date' => '2015-05-19',
            'developer' => 'CD Projekt Red',
            'publisher' => 'CD Projekt',
            'coverUrl' => 'https://example.com/cover.jpg',
            'backgroundUrl' => 'https://example.com/bg.jpg',
            'rating' => 4.5,
            'user_rating' => 5.0,
            'description' => 'Open world RPG',
            'genres' => json_encode(['RPG', 'Adventure']),
            'platforms' => json_encode(['PC', 'PS4', 'Xbox One']),
            'esrb_rating' => 'M',
            'playtime' => 100,
            'metacritic_score' => 92,
            'hours_played' => 65.5,
            'platform_played' => 'PC',
            'date_started' => '2024-01-01',
            'date_finished' => '2024-03-15',
            'personal_notes' => 'Amazing game',
            'user_statuses' => 'completed, owned',
            'user_added_at' => '2024-01-01 10:00:00',
        ];
    }

    // ── toDomain ──

    #[Test]
    public function to_domain_maps_all_fields(): void
    {
        $game = $this->mapper->toDomain($this->fullDbRow());

        $this->assertInstanceOf(Game::class, $game);
        $this->assertSame(12345, $game->getId()->toInt());
        $this->assertEquals('the-witcher-3', $game->getSlug());
        $this->assertEquals('The Witcher 3', $game->getTitle());
        $this->assertEquals('2015-05-19', $game->getReleaseDate());
        $this->assertEquals('CD Projekt Red', $game->getDeveloper());
        $this->assertEquals('CD Projekt', $game->getPublisher());
        $this->assertEquals('https://example.com/cover.jpg', $game->getCoverUrl());
        $this->assertEquals('https://example.com/bg.jpg', $game->getBackgroundUrl());
        $this->assertNotNull($game->getRating());
        $this->assertSame(4.5, $game->getRating()->toFloat());
        $this->assertNotNull($game->getUserRating());
        $this->assertSame(5.0, $game->getUserRating()->toFloat());
        $this->assertEquals('Open world RPG', $game->getDescription());
        $this->assertEquals('M', $game->getEsrbRating());
        $this->assertSame(100, $game->getPlaytime());
        $this->assertSame(92, $game->getMetacriticScore());
        $this->assertSame(65.5, $game->getHoursPlayed());
        $this->assertEquals('PC', $game->getPlatformPlayed());
        $this->assertEquals('2024-01-01', $game->getDateStarted());
        $this->assertEquals('2024-03-15', $game->getDateFinished());
        $this->assertEquals('Amazing game', $game->getPersonalNotes());
    }

    #[Test]
    public function to_domain_parses_user_statuses_from_comma_string(): void
    {
        $game = $this->mapper->toDomain($this->fullDbRow());

        $this->assertIsArray($game->getUserStatuses());
        $this->assertContains('completed', $game->getUserStatuses());
        $this->assertContains('owned', $game->getUserStatuses());
    }

    #[Test]
    public function to_domain_parses_user_statuses_from_array(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = ['playing', 'wishlist'];

        $game = $this->mapper->toDomain($row);

        $this->assertEquals(['playing', 'wishlist'], $game->getUserStatuses());
    }

    #[Test]
    public function to_domain_empty_user_statuses(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = '';

        $game = $this->mapper->toDomain($row);
        $this->assertEmpty($game->getUserStatuses());
    }

    #[Test]
    public function to_domain_null_user_statuses(): void
    {
        $row = $this->fullDbRow();
        $row['user_statuses'] = null;

        $game = $this->mapper->toDomain($row);
        $this->assertEmpty($game->getUserStatuses());
    }

    #[Test]
    public function to_domain_parses_genres_from_json(): void
    {
        $game = $this->mapper->toDomain($this->fullDbRow());

        $genres = $game->getGenres();
        $this->assertNotNull($genres);
        $this->assertCount(2, $genres);
        $this->assertInstanceOf(Genre::class, $genres[0]);
    }

    #[Test]
    public function to_domain_parses_platforms(): void
    {
        $game = $this->mapper->toDomain($this->fullDbRow());

        $platforms = $game->getPlatforms();
        $this->assertNotNull($platforms);
        $this->assertCount(3, $platforms);
        $this->assertContains('PC', $platforms);
    }

    #[Test]
    public function to_domain_null_optional_fields(): void
    {
        $row = [
            'id' => 1,
            'slug' => 'test-game',
            'title' => 'Test',
            'release_date' => null,
            'developer' => null,
            'publisher' => null,
            'coverUrl' => null,
            'backgroundUrl' => null,
            'description' => null,
            'esrb_rating' => null,
            'playtime' => null,
            'metacritic_score' => null,
            'hours_played' => null,
            'platform_played' => null,
            'date_started' => null,
            'date_finished' => null,
            'personal_notes' => null,
        ];

        $game = $this->mapper->toDomain($row);

        $this->assertNull($game->getReleaseDate());
        $this->assertNull($game->getDeveloper());
        $this->assertNull($game->getPublisher());
        $this->assertNull($game->getCoverUrl());
        $this->assertNull($game->getDescription());
        $this->assertNull($game->getEsrbRating());
        $this->assertNull($game->getPlaytime());
        $this->assertNull($game->getMetacriticScore());
        $this->assertNull($game->getPlatformPlayed());
        $this->assertNull($game->getDateStarted());
        $this->assertNull($game->getDateFinished());
    }

    #[Test]
    public function to_domain_timestamp_from_user_added_at(): void
    {
        $game = $this->mapper->toDomain($this->fullDbRow());
        $this->assertNotNull($game->getAddedTimestamp());
    }

    #[Test]
    public function to_domain_timestamp_defaults_to_now_when_missing(): void
    {
        $row = [
            'id' => 1,
            'slug' => 'test',
            'title' => 'Test',
        ];

        $game = $this->mapper->toDomain($row);
        $this->assertNotNull($game->getAddedTimestamp());
    }

    #[Test]
    public function to_domain_without_ratings(): void
    {
        $row = [
            'id' => 1,
            'slug' => 'test',
            'title' => 'Test',
        ];

        $game = $this->mapper->toDomain($row);
        $this->assertNull($game->getRating());
        $this->assertNull($game->getUserRating());
    }

    // ── toPersistence ──

    #[Test]
    public function to_persistence_maps_core_fields(): void
    {
        $game = $this->mapper->toDomain($this->fullDbRow());
        $data = $this->mapper->toPersistence($game);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('slug', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertEquals('the-witcher-3', $data['slug']);
        $this->assertEquals('The Witcher 3', $data['title']);
        $this->assertArrayHasKey('addedTimestamp', $data);
    }

    #[Test]
    public function to_persistence_null_optional_fields(): void
    {
        $row = [
            'id' => 1,
            'slug' => 'test',
            'title' => 'Test',
        ];

        $game = $this->mapper->toDomain($row);
        $data = $this->mapper->toPersistence($game);

        $this->assertNull($data['release_date']);
        $this->assertNull($data['developer']);
        $this->assertNull($data['publisher']);
        $this->assertNull($data['coverUrl']);
        $this->assertNull($data['description']);
        $this->assertNull($data['esrb_rating']);
        $this->assertNull($data['playtime']);
        $this->assertNull($data['metacritic_score']);
    }

    // ── toDomainCollection ──

    #[Test]
    public function to_domain_collection_maps_multiple_rows(): void
    {
        $rows = [
            array_merge($this->fullDbRow(), ['id' => 1, 'slug' => 'g1', 'title' => 'Game 1']),
            array_merge($this->fullDbRow(), ['id' => 2, 'slug' => 'g2', 'title' => 'Game 2']),
        ];

        $games = $this->mapper->toDomainCollection($rows);

        $this->assertCount(2, $games);
        $this->assertInstanceOf(Game::class, $games[0]);
        $this->assertInstanceOf(Game::class, $games[1]);
        $this->assertEquals('Game 1', $games[0]->getTitle());
        $this->assertEquals('Game 2', $games[1]->getTitle());
    }

    #[Test]
    public function to_domain_collection_empty_array(): void
    {
        $games = $this->mapper->toDomainCollection([]);
        $this->assertEmpty($games);
    }
}
