<?php

namespace Tests\Unit\Unit;

use PHPUnit\Framework\Attributes\Group;

use App\AI\Tools\ToolProfileSelector;
use App\AI\Tools\ToolRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[Group('legacy-semantic-routing')]
class ConversationalEvaluationTest extends TestCase
{
    /**
     * These fixtures are model-output simulations, not a production parser.
     * They make natural-language variations executable against the same
     * registry and profile contracts used by the real orchestrator.
     */
    #[DataProvider('conversationalVariations')]
    public function test_conversational_variation_keeps_the_expected_canonical_sequence(
        string $message,
        array $activeEntities,
        array $expectedActions,
    ): void {
        $registry = new ToolRegistry;
        $profile = (new ToolProfileSelector)->select(
            ['message' => $message, 'active_entities' => $activeEntities],
            $registry->allMetadata(),
        );

        $availableActions = collect($profile['metadata'])->pluck('key')->all();
        if ($expectedActions === []) {
            $this->assertIsArray($availableActions);
        }
        foreach ($expectedActions as $actionKey) {
            $this->assertContains($actionKey, $availableActions, $message.' must expose '.$actionKey);

            $metadata = $registry->metadata($registry->resolve($actionKey));
            $this->assertNotEmpty($metadata['input_schema'], $actionKey.' must expose an input contract');
            $this->assertNotEmpty($metadata['output_schema']['component'] ?? null, $actionKey.' must expose a result component');
            if (($metadata['mode'] ?? 'read') === 'write') {
                $this->assertTrue($metadata['requires_confirmation'], $actionKey.' must be confirmation-gated');
            }
        }
    }

    /** @return array<string, array{0:string, 1:array<int, array<string, mixed>>, 2:array<int, string>}> */
    public static function conversationalVariations(): array
    {
        return [
            'task creation with person and relative time' => [
                'Crea una tarea para que Jennifer revise el freezer mañana a las 8 AM.',
                [],
                ['members.list', 'tasks.create'],
            ],
            'task creation with continuation wording' => [
                'Ahora crea otra para Santiago a las 9 AM para revisar dry storage.',
                [],
                ['members.list', 'tasks.create'],
            ],
            'task query with partial member reference' => [
                '¿Qué pendientes tiene Jen para mañana?',
                [],
                ['members.list', 'tasks.search'],
            ],
            'contextual time update' => [
                'Cambia la primera para las 10 AM.',
                [['type' => 'task', 'id' => 'task-first', 'title' => 'Revisar freezer']],
                ['tasks.update'],
            ],
            'reassignment with pronoun' => [
                'Asígnale esa tarea a Santiago en vez de Jennifer.',
                [['type' => 'task', 'id' => 'task-first', 'title' => 'Revisar freezer']],
                ['members.list', 'tasks.assign'],
            ],
            'plural completion' => [
                'Marca como terminadas todas las tareas de limpieza.',
                [],
                ['tasks.search', 'tasks.complete'],
            ],
            'bulk deletion with confirmation' => [
                'Elimina las tareas de Santiago de revisar dry storage.',
                [],
                ['members.list', 'tasks.search', 'tasks.delete'],
            ],
            'confirmation cancellation continuation' => [
                'No, cancela la eliminación pendiente.',
                [],
                [],
            ],
            'combined read and update' => [
                'Muéstrame las tareas de Jennifer y cambia la última para las 2 PM.',
                [],
                ['members.list', 'tasks.search', 'tasks.update'],
            ],
            'english task variation' => [
                'Show Santiago\'s pending tasks for tomorrow.',
                [],
                ['members.list', 'tasks.search'],
            ],
            'event detail variation' => [
                'Enséñame el evento de la boda de viernes.',
                [],
                ['events.detail'],
            ],
            'menu item contextual update' => [
                'Mueve ese plato a la sección de postres.',
                [['type' => 'menu_item', 'id' => 'item-1', 'title' => 'Tarta']],
                ['menus.items.move_section'],
            ],
            'recipe update variation' => [
                'Actualiza la receta de salsa ranchera.',
                [],
                ['recipes.list', 'recipes.update'],
            ],
            'prep assignment variation' => [
                'Asígnale la preparación de mañana a Santiago.',
                [],
                ['members.list', 'prep.items.list', 'prep.items.assign'],
            ],
            'team staff variation' => [
                'Crea un turno para el equipo de cocina.',
                [],
                ['shifts.create'],
            ],
            'document operation variation' => [
                'Reintenta la extracción de este documento.',
                [['type' => 'document', 'id' => 'document-1']],
                ['documents.retry_extraction'],
            ],
        ];
    }
}
