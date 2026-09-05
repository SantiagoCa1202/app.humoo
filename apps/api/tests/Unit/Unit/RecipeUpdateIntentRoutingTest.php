<?php

namespace Tests\Unit\Unit;

use PHPUnit\Framework\Attributes\Group;

use App\AI\Providers\RuleBasedAIProvider;
use Tests\TestCase;

#[Group('legacy-semantic-routing')]
class RecipeUpdateIntentRoutingTest extends TestCase
{
    public function test_rule_based_router_does_not_parse_recipe_update_language(): void
    {
        $decision = (new RuleBasedAIProvider())->generate([
            'locale' => 'es',
            'message' => 'cambia la sal en la receta del ranch a 2 tbsp',
        ]);

        $this->assertSame('clarify_scope', $decision['intent']);
    }
}
