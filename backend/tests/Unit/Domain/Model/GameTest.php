<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\Game;
use App\Domain\Model\ValueObjects\GameIdentifier;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Rating;
use App\Domain\Model\ValueObjects\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GameTest extends TestCase
{
    private function makeGame(array $overrides = []): Game
    {
        $defaults = [
            'id' => GameIdentifier::fromInt(1),
            'slug' => 'test-game',
            'title' => 'Test Game',
            'releaseDate' => '2024-01-15',
            'developer' => 'Dev Studio',
            'publisher' => 'Pub Inc',
            'coverUrl' => 'https://img.test/cover.jpg',
            'backgroundUrl' => 'https://img.test/bg.jpg',
            'rating' => Rating::fromFloat(4.0),
            'userRating' => Rating::fromFloat(3.5),
            'description' => 'A test game',
            'userStatuses' => ['playing'],
            'addedTimestamp' => Timestamp::fromString('2024-06-15 10:00:00'),
            'allowedStatuses' => ['playing', 'completed'],
        ];
        $d = array_merge($defaults, $overrides);
        return new Game(
            $d['id'], $d['slug'], $d['title'], $d['releaseDate'],
            $d['developer'], $d['publisher'], $d['coverUrl'], $d['backgroundUrl'],
            $d['rating'], $d['userRating'], $d['description'],
            $d['userStatuses'], $d['addedTimestamp'], $d['allowedStatuses'],
            $d['tags'] ?? null, $d['allowedTags'] ?? null,
            $d['genres'] ?? null, $d['platforms'] ?? null,
            $d['esrbRating'] ?? null, $d['playtime'] ?? null,
            $d['metacriticScore'] ?? null, $d['hoursPlayed'] ?? null,
            $d['platformPlayed'] ?? null, $d['dateStarted'] ?? null,
            $d['dateFinished'] ?? null, $d['personalNotes'] ?? null
        );
    }

    // ── Constructor validation ──

    #[Test]
    public function creates_game_with_required_fields(): void
    {
        $game = $this->makeGame();
        $this->assertEquals('Test Game', $game->getTitle());
        $this->assertEquals('test-game', $game->getSlug());
        $this->assertSame(1, $game->getId()->toInt());
    }

    #[Test]
    public function throws_on_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Title cannot be empty');
        $this->makeGame(['title' => '']);
    }

    #[Test]
    public function throws_on_empty_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slug cannot be empty');
        $this->makeGame(['slug' => '']);
    }

    #[Test]
    public function throws_on_negative_playtime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Playtime must be a non-negative');
        $this->makeGame(['playtime' => -1]);
    }

    #[Test]
    public function throws_on_metacritic_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Metacritic score must be between 0 and 100');
        $this->makeGame(['metacriticScore' => 101]);
    }

    #[Test]
    public function throws_on_negative_hours_played(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hours played must be non-negative');
        $this->makeGame(['hoursPlayed' => -1.0]);
    }

    #[Test]
    public function accepts_zero_playtime_and_metacritic(): void
    {
        $game = $this->makeGame(['playtime' => 0, 'metacriticScore' => 0, 'hoursPlayed' => 0.0]);
        $this->assertSame(0, $game->getPlaytime());
        $this->assertSame(0, $game->getMetacriticScore());
        $this->assertSame(0.0, $game->getHoursPlayed());
    }

    // ── Getters ──

    #[Test]
    public function getters_return_all_fields(): void
    {
        $game = $this->makeGame([
            'genres' => [Genre::fromString('RPG'), Genre::fromString('Action')],
            'platforms' => ['PC', 'PS5'],
            'esrbRating' => 'T',
            'playtime' => 40,
            'metacriticScore' => 85,
            'hoursPlayed' => 15.5,
            'platformPlayed' => 'PC',
            'dateStarted' => '2024-01-01',
            'dateFinished' => '2024-02-15',
            'personalNotes' => 'Great game',
        ]);

        $this->assertEquals('2024-01-15', $game->getReleaseDate());
        $this->assertEquals('Dev Studio', $game->getDeveloper());
        $this->assertEquals('Pub Inc', $game->getPublisher());
        $this->assertNotNull($game->getCoverUrl());
        $this->assertNotNull($game->getBackgroundUrl());
        $this->assertSame(4.0, $game->getRating()->toFloat());
        $this->assertSame(3.5, $game->getUserRating()->toFloat());
        $this->assertEquals('A test game', $game->getDescription());
        $this->assertEquals(['playing'], $game->getUserStatuses());
        $this->assertEquals(['playing', 'completed'], $game->getAllowedStatuses());
        $this->assertCount(2, $game->getGenres());
        $this->assertEquals(['PC', 'PS5'], $game->getPlatforms());
        $this->assertEquals('T', $game->getEsrbRating());
        $this->assertSame(40, $game->getPlaytime());
        $this->assertSame(85, $game->getMetacriticScore());
        $this->assertSame(15.5, $game->getHoursPlayed());
        $this->assertEquals('PC', $game->getPlatformPlayed());
        $this->assertEquals('2024-01-01', $game->getDateStarted());
        $this->assertEquals('2024-02-15', $game->getDateFinished());
        $this->assertEquals('Great game', $game->getPersonalNotes());
    }

    // ── Utility methods ──

    #[Test]
    public function has_status_checks_correctly(): void
    {
        $game = $this->makeGame(['userStatuses' => ['playing', 'owned']]);
        $this->assertTrue($game->hasStatus('playing'));
        $this->assertTrue($game->hasStatus('owned'));
        $this->assertFalse($game->hasStatus('completed'));
    }

    #[Test]
    public function is_completed_checks_both_statuses(): void
    {
        $game1 = $this->makeGame(['userStatuses' => ['completed']]);
        $this->assertTrue($game1->isCompleted());

        $game2 = $this->makeGame(['userStatuses' => ['100-completed']]);
        $this->assertTrue($game2->isCompleted());

        $game3 = $this->makeGame(['userStatuses' => ['playing']]);
        $this->assertFalse($game3->isCompleted());
    }

    #[Test]
    public function is_playing_works(): void
    {
        $game = $this->makeGame(['userStatuses' => ['playing']]);
        $this->assertTrue($game->isPlaying());
    }

    #[Test]
    public function is_in_wishlist_works(): void
    {
        $game = $this->makeGame(['userStatuses' => ['in-wishlist']]);
        $this->assertTrue($game->isInWishlist());
    }

    #[Test]
    public function has_genre_case_insensitive(): void
    {
        $game = $this->makeGame(['genres' => [Genre::fromString('RPG'), Genre::fromString('Action')]]);
        $this->assertTrue($game->hasGenre('rpg'));
        $this->assertTrue($game->hasGenre('RPG'));
        $this->assertFalse($game->hasGenre('Strategy'));
    }

    #[Test]
    public function has_genre_returns_false_when_null(): void
    {
        $game = $this->makeGame(['genres' => null]);
        $this->assertFalse($game->hasGenre('RPG'));
    }

    #[Test]
    public function has_platform_case_insensitive(): void
    {
        $game = $this->makeGame(['platforms' => ['PC', 'PS5']]);
        $this->assertTrue($game->hasPlatform('pc'));
        $this->assertTrue($game->hasPlatform('PS5'));
        $this->assertFalse($game->hasPlatform('Xbox'));
    }

    #[Test]
    public function has_platform_returns_false_when_null(): void
    {
        $game = $this->makeGame(['platforms' => null]);
        $this->assertFalse($game->hasPlatform('PC'));
    }

    // ── toArray ──

    #[Test]
    public function to_array_contains_all_fields_with_aliases(): void
    {
        $game = $this->makeGame([
            'genres' => [Genre::fromString('RPG')],
            'platforms' => ['PC'],
            'esrbRating' => 'M',
            'playtime' => 50,
            'metacriticScore' => 90,
            'hoursPlayed' => 25.0,
            'platformPlayed' => 'PC',
            'dateStarted' => '2024-01-01',
            'dateFinished' => '2024-02-01',
            'personalNotes' => 'Notes here',
        ]);

        $arr = $game->toArray();

        $this->assertSame(1, $arr['id']);
        $this->assertEquals('test-game', $arr['slug']);
        $this->assertEquals('Test Game', $arr['title']);
        $this->assertEquals('Test Game', $arr['name']); // alias
        $this->assertEquals('2024-01-15', $arr['release_date']);
        $this->assertEquals('2024-01-15', $arr['releaseDate']); // camelCase alias
        $this->assertEquals('2024-01-15', $arr['released']); // another alias
        $this->assertSame(4.0, $arr['rating']);
        $this->assertSame(3.5, $arr['user_rating']);
        $this->assertEquals(['RPG'], $arr['genres']);
        $this->assertEquals('M', $arr['esrb_rating']);
        $this->assertEquals('M', $arr['esrbRating']);
        $this->assertSame(90, $arr['metacritic_score']);
        $this->assertSame(90, $arr['metacriticScore']);
        $this->assertSame(90, $arr['metacritic']); // alias
        $this->assertSame(25.0, $arr['hours_played']);
        $this->assertSame(25.0, $arr['hoursPlayed']);
        $this->assertEquals('PC', $arr['platform_played']);
        $this->assertEquals('PC', $arr['platformPlayed']);
        $this->assertEquals('2024-01-01', $arr['date_started']);
        $this->assertEquals('2024-01-01', $arr['dateStarted']);
        $this->assertEquals('2024-02-01', $arr['date_finished']);
        $this->assertEquals('2024-02-01', $arr['dateFinished']);
        $this->assertEquals('Notes here', $arr['personal_notes']);
        $this->assertEquals('Notes here', $arr['personalNotes']);
        $this->assertEquals('Notes here', $arr['notes']); // alias
    }

    // ── fromArray ──

    #[Test]
    public function from_array_creates_game(): void
    {
        $data = [
            'id' => 42,
            'slug' => 'elden-ring',
            'title' => 'Elden Ring',
            'release_date' => '2022-02-25',
            'developer' => 'FromSoftware',
            'publisher' => 'Bandai Namco',
            'coverUrl' => 'https://img/cover.jpg',
            'rating' => 4.5,
            'user_rating' => 5.0,
            'description' => 'An action RPG',
            'userStatuses' => ['completed'],
            'genres' => ['RPG', 'Action'],
            'platforms' => ['PC', 'PS5'],
            'metacritic_score' => 96,
            'hours_played' => 120.5,
        ];

        $game = Game::fromArray($data);

        $this->assertSame(42, $game->getId()->toInt());
        $this->assertEquals('elden-ring', $game->getSlug());
        $this->assertEquals('Elden Ring', $game->getTitle());
        $this->assertSame(4.5, $game->getRating()->toFloat());
        $this->assertSame(5.0, $game->getUserRating()->toFloat());
        $this->assertCount(2, $game->getGenres());
        $this->assertEquals(['PC', 'PS5'], $game->getPlatforms());
        $this->assertSame(96, $game->getMetacriticScore());
        $this->assertSame(120.5, $game->getHoursPlayed());
    }

    #[Test]
    public function from_array_throws_on_missing_required(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ID, slug and title are required');
        Game::fromArray(['id' => 1, 'slug' => 'test']); // missing title
    }

    #[Test]
    public function from_array_throws_on_missing_statuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User statuses are required');
        Game::fromArray(['id' => 1, 'slug' => 'test', 'title' => 'Test']);
    }

    // ── Round-trip ──

    #[Test]
    public function from_array_to_array_preserves_data(): void
    {
        $data = [
            'id' => 99,
            'slug' => 'round-trip',
            'title' => 'Round Trip Game',
            'release_date' => '2024-03-01',
            'developer' => 'Studio',
            'publisher' => 'Publisher',
            'coverUrl' => 'https://cover.jpg',
            'backgroundUrl' => 'https://bg.jpg',
            'rating' => 3.0,
            'user_rating' => 4.0,
            'description' => 'Description',
            'userStatuses' => ['playing'],
            'genres' => ['Strategy'],
            'platforms' => ['PC'],
            'esrb_rating' => 'E',
            'playtime' => 20,
            'metacritic_score' => 75,
            'hours_played' => 10.0,
            'platform_played' => 'PC',
            'date_started' => '2024-03-01',
            'date_finished' => '2024-04-15',
            'personal_notes' => 'Fun game',
        ];

        $game = Game::fromArray($data);
        $arr = $game->toArray();

        $this->assertSame(99, $arr['id']);
        $this->assertEquals('round-trip', $arr['slug']);
        $this->assertEquals('Round Trip Game', $arr['title']);
        $this->assertSame(3.0, $arr['rating']);
        $this->assertSame(4.0, $arr['user_rating']);
        $this->assertEquals(['Strategy'], $arr['genres']);
        $this->assertEquals('E', $arr['esrb_rating']);
        $this->assertSame(75, $arr['metacritic_score']);
        $this->assertSame(10.0, $arr['hours_played']);
        $this->assertEquals('Fun game', $arr['personal_notes']);
    }
}
