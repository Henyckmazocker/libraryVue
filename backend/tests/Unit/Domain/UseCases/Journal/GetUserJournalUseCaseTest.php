<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\UseCases\Journal;

use App\Domain\DTO\Queries\GetUserJournalQuery;
use App\Domain\Model\Friendship;
use App\Domain\Model\JournalEntry;
use App\Domain\Model\PrivacySettings;
use App\Domain\Model\User;
use App\Domain\Repository\Journal\JournalRepositoryInterface;
use App\Domain\Repository\Social\FriendshipRepositoryInterface;
use App\Domain\Repository\Social\PrivacySettingsRepositoryInterface;
use App\Domain\Repository\User\UserRepositoryInterface;
use App\Domain\UseCases\Journal\GetUserJournalUseCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Las cuatro combinaciones de (amistad sí/no) × (interruptor sí/no), que es
 * donde vive el riesgo de este plan, más lo que las hace de verdad seguras: que
 * cuando alguna dice que no, **el repositorio del diario ni se consulta**. Un
 * filtro que trae las entradas y las descarta después ya las ha sacado del
 * servidor.
 */
class GetUserJournalUseCaseTest extends TestCase
{
    private const DUENO  = 2;
    private const VISOR  = 1;

    private GetUserJournalUseCase $useCase;
    private UserRepositoryInterface $userRepo;
    private FriendshipRepositoryInterface $friendshipRepo;
    private PrivacySettingsRepositoryInterface $privacyRepo;
    private JournalRepositoryInterface $journalRepo;

    protected function setUp(): void
    {
        $this->userRepo       = $this->createMock(UserRepositoryInterface::class);
        $this->friendshipRepo = $this->createMock(FriendshipRepositoryInterface::class);
        $this->privacyRepo    = $this->createMock(PrivacySettingsRepositoryInterface::class);
        $this->journalRepo    = $this->createMock(JournalRepositoryInterface::class);

        $this->useCase = new GetUserJournalUseCase(
            $this->userRepo,
            $this->friendshipRepo,
            $this->privacyRepo,
            $this->journalRepo,
            new NullLogger()
        );
    }

    private function query(string $username = 'dueno'): GetUserJournalQuery
    {
        return new GetUserJournalQuery(username: $username, viewerUserId: self::VISOR);
    }

    private function elDuenoExiste(): void
    {
        $dueno = $this->createMock(User::class);
        $dueno->method('getId')->willReturn(self::DUENO);
        $this->userRepo->method('findByUsername')->willReturn($dueno);
    }

    private function conAmistad(string $estado = Friendship::STATUS_ACCEPTED): void
    {
        $this->friendshipRepo->method('findByUsers')
            ->willReturn(new Friendship(7, self::VISOR, self::DUENO, $estado));
    }

    private function conInterruptor(bool $encendido): void
    {
        $this->privacyRepo->method('findByUserId')
            ->willReturn(new PrivacySettings(userId: self::DUENO, showJournal: $encendido));
    }

    /** El diario no se toca: ni una entrada sale del servidor. */
    private function elDiarioNoSeConsulta(): void
    {
        $this->journalRepo->expects($this->never())->method('findByUser');
        $this->journalRepo->expects($this->never())->method('countByUser');
    }

    private function assertVacio(array $r): void
    {
        $this->assertSame([], $r['entries']);
        $this->assertSame(0, $r['total']);
        $this->assertFalse($r['hasMore']);
    }

    #[Test]
    public function throws_on_invalid_query(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }

    // ---- Las cuatro combinaciones -------------------------------------------

    #[Test]
    public function without_friendship_and_with_the_switch_off_returns_empty(): void
    {
        $this->elDuenoExiste();
        $this->friendshipRepo->method('findByUsers')->willReturn(null);
        $this->elDiarioNoSeConsulta();

        $this->assertVacio($this->useCase->execute($this->query()));
    }

    #[Test]
    public function without_friendship_the_switch_being_on_changes_nothing(): void
    {
        $this->elDuenoExiste();
        $this->friendshipRepo->method('findByUsers')->willReturn(null);
        $this->conInterruptor(true);
        $this->elDiarioNoSeConsulta();

        $this->assertVacio($this->useCase->execute($this->query()));
    }

    #[Test]
    public function a_friend_with_the_switch_off_gets_nothing(): void
    {
        $this->elDuenoExiste();
        $this->conAmistad();
        $this->conInterruptor(false);
        $this->elDiarioNoSeConsulta();

        $this->assertVacio($this->useCase->execute($this->query()));
    }

    #[Test]
    public function a_friend_with_the_switch_on_sees_the_journal(): void
    {
        $this->elDuenoExiste();
        $this->conAmistad();
        $this->conInterruptor(true);

        $entrada = new JournalEntry(
            id: 5,
            userId: self::DUENO,
            media: 'movie',
            entityId: 'tt0111161',
            entityTitle: 'Cadena perpetua',
            entityCover: null,
            entryDate: '2026-09-01',
            rating: null
        );
        $this->journalRepo->expects($this->once())->method('findByUser')
            ->with(self::DUENO, 20, 0)
            ->willReturn([$entrada]);
        $this->journalRepo->expects($this->once())->method('countByUser')
            ->with(self::DUENO)
            ->willReturn(3);

        $r = $this->useCase->execute($this->query());

        $this->assertCount(1, $r['entries']);
        $this->assertSame('Cadena perpetua', $r['entries'][0]['entity_title']);
        $this->assertSame(3, $r['total']);
        $this->assertTrue($r['hasMore']);
    }

    // ---- Lo que no debe confirmar nada --------------------------------------

    #[Test]
    public function a_pending_request_is_not_a_friendship(): void
    {
        $this->elDuenoExiste();
        $this->conAmistad(Friendship::STATUS_PENDING);
        $this->conInterruptor(true);
        $this->elDiarioNoSeConsulta();

        $this->assertVacio($this->useCase->execute($this->query()));
    }

    #[Test]
    public function an_unknown_username_returns_empty_instead_of_an_error(): void
    {
        // Un 404 aquí sería un oráculo de qué usuarios existen. Y ni la amistad
        // se consulta: sin usuario no hay a quién comprobar.
        $this->userRepo->method('findByUsername')->willReturn(null);
        $this->friendshipRepo->expects($this->never())->method('findByUsers');
        $this->elDiarioNoSeConsulta();

        $this->assertVacio($this->useCase->execute($this->query('nadie')));
    }

    #[Test]
    public function an_empty_username_never_reaches_the_repositories(): void
    {
        $this->userRepo->expects($this->never())->method('findByUsername');
        $this->elDiarioNoSeConsulta();

        $this->assertVacio($this->useCase->execute($this->query('')));
    }
}
