<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Services;

use App\Domain\Model\FeedEvent;
use App\Domain\Repository\Social\FeedEventRepositoryInterface;
use App\Domain\Services\FeedEventService;
use App\Domain\UseCases\Social\CreateFeedEventUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * El primer test de `FeedEventService`.
 *
 * Hasta el 2026-08-25 esta clase solo se cubría **como mock** desde los tests de
 * los use cases que la usan, así que nadie comprobaba lo que ella misma
 * construye. Y tiene dos comportamientos que conviene fijar:
 *
 *  1. **Lo que mete en `metadata`.** Es la columna que lee la tarjeta del feed;
 *     una clave mal escrita no rompe nada, simplemente no se ve.
 *  2. **Que se traga sus propios errores.** Es por diseño —un fallo del feed no
 *     puede tumbar el guardado de una nota o de un libro— y es exactamente lo
 *     que hizo que los eventos de vídeo fallaran en absoluto silencio hasta el
 *     plan del feed de vídeos. Que sea deliberado no quiere decir que no haya
 *     que probarlo.
 */
class FeedEventServiceTest extends TestCase
{
    /** @var list<FeedEvent> */
    private array $despachados = [];

    /**
     * El servicio con su use case **real** y solo el repositorio mockeado.
     *
     * `AbstractUseCase::execute()` es `final` (`:32`), así que
     * `CreateFeedEventUseCase` no se puede mockear — y mejor: así el test
     * recorre el camino de verdad y lo que se inspecciona es el `FeedEvent`
     * que llegaría a la base de datos, no un comando intermedio.
     */
    private function service(bool $revienta = false): FeedEventService
    {
        $repo = $this->createMock(FeedEventRepositoryInterface::class);
        $repo->method('save')->willReturnCallback(
            function (FeedEvent $event) use ($revienta): FeedEvent {
                if ($revienta) {
                    throw new RuntimeException('la base de datos se cayó');
                }

                $this->despachados[] = $event;

                return $event;
            }
        );

        return new FeedEventService(
            new CreateFeedEventUseCase($repo, new NullLogger()),
            new NullLogger()
        );
    }

    #[Test]
    public function a_note_event_carries_the_whole_text_in_metadata(): void
    {
        $texto = str_repeat('Una nota bastante larga que no se trunca. ', 20);

        $this->service()->recordNotesUpdated(
            userId: 7,
            entityType: 'album',
            entityId: '1f25d940-89e2-4813-a86f-955b0e99c391',
            title: 'Prequelle',
            cover: null,
            noteText: $texto,
            noteType: 'quote'
        );

        $this->assertCount(1, $this->despachados);
        $evento = $this->despachados[0];

        $this->assertSame(FeedEvent::TYPE_NOTES_UPDATED, $evento->getEventType());
        $this->assertSame('album', $evento->getEntityType());
        $this->assertSame(['note_text' => $texto, 'note_type' => 'quote'], $evento->getMetadata());

        // Lo importante del contrato: **entero**, sin truncar. La tarjeta lo
        // recorta con CSS, así que cortarlo aquí impediría desplegarlo.
        $this->assertSame($texto, $evento->getMetadata()['note_text']);
    }

    #[Test]
    public function the_note_type_is_optional_and_travels_as_null(): void
    {
        // No todas las entidades mandan tipo, y no se normaliza: en libros es un
        // ENUM y en las otras cuatro un VARCHAR.
        $this->service()->recordNotesUpdated(1, 'book', '9780000000019', 'Un libro', null, 'Sin tipo');

        $this->assertNull($this->despachados[0]->getMetadata()['note_type']);
        $this->assertArrayHasKey('note_type', $this->despachados[0]->getMetadata());
    }

    #[Test]
    public function a_failure_in_the_feed_never_reaches_the_caller(): void
    {
        // Si esto lanzara, un fallo del feed tumbaría el guardado de la nota.
        $this->service(revienta: true)->recordNotesUpdated(1, 'book', 'x', 'T', null, 'texto');

        $this->assertSame([], $this->despachados, 'No llegó a despacharse, y aun así no lanzó');
    }

    #[Test]
    public function the_other_event_types_keep_their_own_metadata(): void
    {
        // Que `recordNotesUpdated` haya crecido no puede haber tocado a los
        // demás: `status_changed` e `item_rated` ya usaban `metadata`.
        $service = $this->service();

        $service->recordItemAdded(1, 'movie', 'tt0068646', 'El Padrino', null);
        $service->recordItemRated(1, 'movie', 'tt0068646', 'El Padrino', null, 5.0);

        $this->assertNull($this->despachados[0]->getMetadata(), 'item_added no lleva metadata');
        $this->assertSame(['rating' => 5.0], $this->despachados[1]->getMetadata());
    }
}
