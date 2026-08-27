<?php

namespace Tests\Unit\Unit;

use App\AI\Providers\RuleBasedAIProvider;
use Tests\TestCase;

class RecipeUpdateIntentRoutingTest extends TestCase
{
    public function test_recipe_update_language_is_not_captured_as_a_menu_item_update(): void
    {
        $decision = (new RuleBasedAIProvider())->generate([
            'locale' => 'es',
            'message' => 'cambia la sal en la receta del ranch a 2 tbsp',
        ]);

        $this->assertSame('tool_action', $decision['intent']);
        $this->assertSame('recipes.update', $decision['slots']['action_key']);
        $this->assertSame('del ranch', $decision['slots']['input']['recipe_search']);
    }
}
