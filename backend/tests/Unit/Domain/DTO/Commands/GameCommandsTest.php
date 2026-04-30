<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DTO\Commands;

use App\Domain\DTO\Commands\AddGameCommand;
use App\Domain\DTO\Commands\DeleteGameCommand;
use App\Domain\DTO\Commands\EditUserGameCommand;
use App\Domain\DTO\Commands\UpdateGameRatingCommand;
use App\Domain\DTO\Commands\UpdateGameStatusesCommand;
use App\Domain\Model\ValueObjects\GameIdentifier;
use App\Domain\Model\ValueObjects\Genre;
use App\Domain\Model\ValueObjects\Rating;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GameCommandsTest extends TestCase
{
    // ═══════════════════════════════════════
    // AddGameCommand
    // ═══════════════════════════════════════

    #[Test]
    public function add_game_constructor_sets_all_properties(): void
    {
        $id = GameIdentifier::fromInt(12345);
        $rating = Rating::fromFloat(4.0);

        $cmd = new AddGameCommand(
            id: $id,
            slug: 'test-game',
            title: 'Test Game',
            userId: 1,
            statuses: ['playing'],
            releaseDate: '2024-01-15',
            developer: 'Dev Studio',
            publisher: 'Publisher',
            coverUrl: 'https://cover.jpg',
            backgroundUrl: 'https://bg.jpg',
            rating: $rating,
            description: 'A great game',
            genres: [Genre::fromString('Action')],
            platforms: ['PC', 'PS5'],
            playtime: 20,
            metacriticScore: 85,
            hoursPlayed: 10.5,
            platformPlayed: 'PC',
            dateStarted: '2024-01-20',
            dateFinished: '2024-02-15',
            personalNotes: 'Fun game'
        );

        $this->assertSame($id, $cmd->id);
        $this->assertEquals('test-game', $cmd->slug);
        $this->assertEquals('Test Game', $cmd->title);
        $this->assertSame(1, $cmd->userId);
        $this->assertEquals(['playing'], $cmd->statuses);
        $this->assertEquals('2024-01-15', $cmd->releaseDate);
        $this->assertEquals('Dev Studio', $cmd->developer);
        $this->assertEquals('Publisher', $cmd->publisher);
        $this->assertSame(20, $cmd->playtime);
        $this->assertSame(85, $cmd->metacriticScore);
        $this->assertSame(10.5, $cmd->hoursPlayed);
        $this->assertEquals('PC', $cmd->platformPlayed);
        $this->assertEquals('2024-01-20', $cmd->dateStarted);
        $this->assertEquals('2024-02-15', $cmd->dateFinished);
        $this->assertEquals('Fun game', $cmd->personalNotes);
    }

    #[Test]
    public function add_game_from_array_full(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 12345,
            'slug' => 'test-game',
            'title' => 'Test Game',
            'userStatuses' => ['playing'],
            'release_date' => '2024-01-15',
            'developer' => 'Dev Studio',
            'publisher' => 'Publisher',
            'coverUrl' => 'https://cover.jpg',
            'backgroundUrl' => 'https://bg.jpg',
            'rating' => 4.0,
            'user_rating' => 3.5,
            'description' => 'Desc',
            'genres' => ['Action', 'RPG'],
            'platforms' => ['PC', 'PS5'],
            'esrb_rating' => 'M',
            'playtime' => 20,
            'metacritic_score' => 85,
            'hours_played' => 10.5,
            'platform_played' => 'PC',
            'date_started' => '2024-01-20',
            'date_finished' => '2024-02-15',
            'personal_notes' => 'Fun',
        ], 1);

        $this->assertSame(12345, $cmd->id->toInt());
        $this->assertEquals('test-game', $cmd->slug);
        $this->assertSame(4.0, $cmd->rating->toFloat());
        $this->assertSame(3.5, $cmd->userRating->toFloat());
        $this->assertCount(2, $cmd->genres);
        $this->assertEquals('Action', $cmd->genres[0]->toString());
        $this->assertEquals(['PC', 'PS5'], $cmd->platforms);
        $this->assertEquals('M', $cmd->esrbRating);
        $this->assertSame(85, $cmd->metacriticScore);
        $this->assertSame(10.5, $cmd->hoursPlayed);
        $this->assertEquals('2024-01-20', $cmd->dateStarted);
        $this->assertEquals('Fun', $cmd->personalNotes);
    }

    #[Test]
    public function add_game_from_array_generates_slug_from_title(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'The Witcher 3: Wild Hunt!',
            'userStatuses' => [],
        ], 1);

        $this->assertEquals('the-witcher-3-wild-hunt', $cmd->slug);
    }

    #[Test]
    public function add_game_from_array_string_id(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => '99999',
            'title' => 'Game',
            'userStatuses' => [],
        ], 1);

        $this->assertSame(99999, $cmd->id->toInt());
    }

    #[Test]
    public function add_game_from_array_genres_as_comma_string(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'genres' => 'Action, RPG, Adventure',
        ], 1);

        $this->assertCount(3, $cmd->genres);
        $this->assertEquals('Action', $cmd->genres[0]->toString());
        $this->assertEquals('RPG', $cmd->genres[1]->toString());
        $this->assertEquals('Adventure', $cmd->genres[2]->toString());
    }

    #[Test]
    public function add_game_from_array_platforms_as_json_string(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'platforms' => '["PC","PS5"]',
        ], 1);

        $this->assertEquals(['PC', 'PS5'], $cmd->platforms);
    }

    #[Test]
    public function add_game_from_array_platforms_as_comma_string(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'platforms' => 'PC, PS5, Xbox',
        ], 1);

        $this->assertEquals(['PC', 'PS5', 'Xbox'], $cmd->platforms);
    }

    #[Test]
    public function add_game_from_array_camel_case_date_keys(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'releaseDate' => '2024-01-01',
            'dateStarted' => '2024-02-01',
            'dateFinished' => '2024-03-01',
            'hoursPlayed' => 15.0,
            'platformPlayed' => 'Switch',
        ], 1);

        $this->assertEquals('2024-01-01', $cmd->releaseDate);
        $this->assertEquals('2024-02-01', $cmd->dateStarted);
        $this->assertEquals('2024-03-01', $cmd->dateFinished);
        $this->assertSame(15.0, $cmd->hoursPlayed);
        $this->assertEquals('Switch', $cmd->platformPlayed);
    }

    #[Test]
    public function add_game_from_array_metacritic_key_fallback(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'metacritic' => 92,
        ], 1);

        $this->assertSame(92, $cmd->metacriticScore);
    }

    #[Test]
    public function add_game_from_array_background_image_fallback(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'background_image' => 'https://bg-image.jpg',
        ], 1);

        $this->assertEquals('https://bg-image.jpg', $cmd->coverUrl);
        $this->assertEquals('https://bg-image.jpg', $cmd->backgroundUrl);
    }

    #[Test]
    public function add_game_from_array_notes_key_fallback(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'notes' => 'My notes',
        ], 1);

        $this->assertEquals('My notes', $cmd->personalNotes);
    }

    #[Test]
    public function add_game_from_array_description_raw_fallback(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'description_raw' => 'Raw description',
        ], 1);

        $this->assertEquals('Raw description', $cmd->description);
    }

    #[Test]
    public function add_game_to_array_contains_aliases(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'coverUrl' => 'https://cover.jpg',
            'esrb_rating' => 'T',
            'metacritic_score' => 80,
            'genres' => ['Action'],
        ], 1);

        $arr = $cmd->toArray();
        $this->assertEquals('https://cover.jpg', $arr['coverUrl']);
        $this->assertEquals('https://cover.jpg', $arr['cover_url']);
        $this->assertEquals('T', $arr['esrb_rating']);
        $this->assertEquals('T', $arr['esrbRating']);
        $this->assertSame(80, $arr['metacritic_score']);
        $this->assertSame(80, $arr['metacritic']);
        $this->assertEquals(['Action'], $arr['genres']);
    }

    #[Test]
    public function add_game_from_array_zero_rating_is_null(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 1,
            'title' => 'Game',
            'rating' => 0,
            'user_rating' => 0,
        ], 1);

        $this->assertNull($cmd->rating);
        $this->assertNull($cmd->userRating);
    }

    // ═══════════════════════════════════════
    // DeleteGameCommand
    // ═══════════════════════════════════════

    #[Test]
    public function delete_game_from_array(): void
    {
        $cmd = DeleteGameCommand::fromArray(['gameId' => 42], 5);

        $this->assertSame(5, $cmd->userId);
        $this->assertSame(42, $cmd->gameId);
    }

    #[Test]
    public function delete_game_throws_on_missing_game_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Game ID is required');
        DeleteGameCommand::fromArray([], 1);
    }

    // ═══════════════════════════════════════
    // EditUserGameCommand
    // ═══════════════════════════════════════

    #[Test]
    public function edit_user_game_from_array_nested_data(): void
    {
        $cmd = EditUserGameCommand::fromArray([
            'gameId' => 42,
            'data' => [
                'personalRating' => 4.0,
                'personalNotes' => 'Great',
                'hoursPlayed' => 25.5,
                'platformPlayed' => 'PC',
                'completedAt' => '2024-06-01',
                'dateStarted' => '2024-01-01',
                'dateFinished' => '2024-06-01',
                'statuses' => ['completed'],
            ],
            'tags' => [1, 2],
        ], 1);

        $this->assertSame(42, $cmd->gameId);
        $this->assertSame(1, $cmd->userId);
        $this->assertSame(4.0, $cmd->userRating->toFloat());
        $this->assertEquals('Great', $cmd->personalNotes);
        $this->assertSame(25.5, $cmd->hoursPlayed);
        $this->assertEquals('PC', $cmd->platformPlayed);
        $this->assertEquals('2024-06-01', $cmd->completedAt);
        $this->assertEquals('2024-01-01', $cmd->dateStarted);
        $this->assertEquals('2024-06-01', $cmd->dateFinished);
        $this->assertEquals(['completed'], $cmd->statuses);
        $this->assertEquals([1, 2], $cmd->tags);
    }

    #[Test]
    public function edit_user_game_from_array_snake_case_keys(): void
    {
        $cmd = EditUserGameCommand::fromArray([
            'gameId' => 10,
            'data' => [
                'user_rating' => 3.5,
                'personal_notes' => 'Notes',
                'hours_played' => 10.0,
                'platform_played' => 'PS5',
                'completed_at' => '2024-05-01',
                'date_started' => '2024-04-01',
                'date_finished' => '2024-05-01',
            ],
        ], 2);

        $this->assertSame(3.5, $cmd->userRating->toFloat());
        $this->assertEquals('Notes', $cmd->personalNotes);
        $this->assertSame(10.0, $cmd->hoursPlayed);
        $this->assertEquals('PS5', $cmd->platformPlayed);
        $this->assertEquals('2024-04-01', $cmd->dateStarted);
        $this->assertEquals('2024-05-01', $cmd->dateFinished);
    }

    #[Test]
    public function edit_user_game_throws_on_missing_game_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Game ID is required');
        EditUserGameCommand::fromArray([], 1);
    }

    #[Test]
    public function edit_user_game_to_array_only_includes_non_null(): void
    {
        $cmd = EditUserGameCommand::fromArray([
            'gameId' => 1,
            'data' => [
                'personalRating' => 4.0,
                'personal_notes' => 'Test',
            ],
        ], 1);

        $arr = $cmd->toArray();
        $this->assertSame(4.0, $arr['personal_rating']);
        $this->assertEquals('Test', $arr['personal_notes']);
        $this->assertArrayNotHasKey('hours_played', $arr);
        $this->assertArrayNotHasKey('platform_played', $arr);
        $this->assertArrayNotHasKey('completed_at', $arr);
    }

    // ═══════════════════════════════════════
    // UpdateGameRatingCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_game_rating_from_array(): void
    {
        $cmd = UpdateGameRatingCommand::fromArray([
            'gameId' => 42,
            'rating' => 4.5,
        ], 1);

        $this->assertSame(1, $cmd->userId);
        $this->assertSame(42, $cmd->gameId);
        $this->assertSame(4.5, $cmd->rating->toFloat());
    }

    #[Test]
    public function update_game_rating_throws_on_missing_rating(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Valid rating is required');
        UpdateGameRatingCommand::fromArray(['gameId' => 1], 1);
    }

    #[Test]
    public function update_game_rating_throws_on_missing_game_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Game ID is required');
        UpdateGameRatingCommand::fromArray(['rating' => 4.0], 1);
    }

    // ═══════════════════════════════════════
    // UpdateGameStatusesCommand
    // ═══════════════════════════════════════

    #[Test]
    public function update_game_statuses_from_array(): void
    {
        $cmd = UpdateGameStatusesCommand::fromArray([
            'gameId' => 42,
            'statuses' => ['playing', 'owned'],
        ], 1);

        $this->assertSame(1, $cmd->userId);
        $this->assertSame(42, $cmd->gameId);
        $this->assertEquals(['playing', 'owned'], $cmd->statuses);
    }

    #[Test]
    public function update_game_statuses_throws_on_missing_statuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Statuses array is required');
        UpdateGameStatusesCommand::fromArray(['gameId' => 1], 1);
    }

    #[Test]
    public function update_game_statuses_throws_on_missing_game_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Game ID is required');
        UpdateGameStatusesCommand::fromArray(['statuses' => []], 1);
    }

    // ═══════════════════════════════════════
    // Ownership Format
    // ═══════════════════════════════════════

    #[Test]
    public function add_game_ownership_format_id_from_array(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 42,
            'title' => 'Game',
            'ownership_format_id' => 3,
        ], 1);

        $this->assertSame(3, $cmd->ownershipFormatId);
    }

    #[Test]
    public function add_game_ownership_format_id_defaults_null(): void
    {
        $cmd = AddGameCommand::fromArray([
            'id' => 42,
            'title' => 'Game',
        ], 1);

        $this->assertNull($cmd->ownershipFormatId);
    }

    #[Test]
    public function edit_game_ownership_format_id_camel_case(): void
    {
        $cmd = EditUserGameCommand::fromArray([
            'gameId' => 42,
            'data' => ['ownershipFormatId' => 5],
        ], 1);

        $this->assertSame(5, $cmd->ownershipFormatId);
    }
}
