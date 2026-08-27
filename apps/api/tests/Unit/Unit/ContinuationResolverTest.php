<?php

namespace Tests\Unit\Unit;

use App\AI\Orchestration\ContinuationResolver;
use App\AI\Orchestration\OrchestrationContext;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Tests\TestCase;

class ContinuationResolverTest extends TestCase
{
    public function test_recipe_draft_save_resolves_without_entity_lookup(): void
    {
        $resolver = app(ContinuationResolver::class);
        $resolution = $resolver->resolve($this->context('Save this recipe', [
            $this->draft('Baguette Italiano'),
        ]));

        $this->assertSame('resolved', $resolution->status);
        $this->assertSame('draft', $resolution->source);
        $this->assertSame('recipes.create', $resolution->actionKey);
        $this->assertSame('Baguette Italiano', $resolution->data['draft']['label']);
    }

    public function test_numeric_reply_resolves_pending_clarification_without_router_or_ai(): void
    {
        $context = $this->context('1.5', []);
        $context->conversation->metadata = [
            'pending_clarifications' => [[
                'actor_id' => $context->actor->id,
                'clarification_id' => 'clarification-1',
                'conversation_id' => $context->conversation->id,
                'expected_type' => 'number',
                'status' => 'pending',
                'workspace_id' => $context->workspace->id,
                'workflow' => 'recipes.create',
            ]],
        ];

        $resolution = app(ContinuationResolver::class)->resolve($context);

        $this->assertSame('resolved', $resolution->status);
        $this->assertSame('clarification', $resolution->source);
        $this->assertSame('custom', $resolution->data['input']['selected_option_id']);
        $this->assertSame(1.5, $resolution->data['input']['custom_value']);
    }

    public function test_multiple_drafts_are_ambiguous_but_a_named_draft_is_selected(): void
    {
        $drafts = [$this->draft('Ranch'), $this->draft('Caesar')];

        $ambiguous = app(ContinuationResolver::class)->resolve($this->context('save it', $drafts));
        $named = app(ContinuationResolver::class)->resolve($this->context('save the ranch one', $drafts));

        $this->assertSame('ambiguous', $ambiguous->status);
        $this->assertSame('resolved', $named->status);
        $this->assertSame('Ranch', $named->data['draft']['label']);
    }

    /** @param array<int, array<string, mixed>> $continuations */
    private function context(string $message, array $continuations): OrchestrationContext
    {
        $workspace = new Workspace(['id' => 'workspace-1']);
        $actor = new User(['id' => 'user-1']);
        $conversation = new Conversation([
            'created_by' => $actor->id,
            'id' => 'conversation-1',
            'metadata' => ['pending_continuations' => $continuations],
            'workspace_id' => $workspace->id,
        ]);
        $current = new Message(['content_text' => $message, 'id' => 'message-1']);
        $assistant = new Message(['id' => 'message-2']);

        return new OrchestrationContext(
            workspace: $workspace,
            actor: $actor,
            membership: new WorkspaceMembership(['id' => 'membership-1']),
            conversation: $conversation,
            currentMessage: $current,
            assistantMessage: $assistant,
            locale: 'en',
            timezone: 'UTC',
            entityRefs: [],
            recentMessages: [],
            availableTools: [],
            systemInstructions: '',
            pendingContinuations: $continuations,
            activeEntities: [],
            lastInteraction: null,
            correlationId: 'correlation-1',
        );
    }

    /** @return array<string, mixed> */
    private function draft(string $name): array
    {
        return [
            'action_key' => 'recipes.create',
            'actor_id' => 'user-1',
            'continuation_id' => strtolower($name).'-draft',
            'conversation_id' => 'conversation-1',
            'entity_type' => 'recipe',
            'kind' => 'draft',
            'label' => $name,
            'payload' => ['name' => $name],
            'status' => 'pending',
            'target_type' => 'recipe_draft',
            'workspace_id' => 'workspace-1',
        ];
    }
}
