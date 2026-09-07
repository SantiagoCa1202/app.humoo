<?php

namespace Tests\Unit\Unit;

use App\AI\Objectives\ResultAssertions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ResultAssertionsTest extends TestCase
{
    public function test_verifies_count_identity_and_assignment_from_tool_evidence(): void
    {
        ResultAssertions::check(['items' => [['id' => 'task-1', 'assignee' => 'owner']]], [
            ['path' => ['items'], 'operator' => 'count_equals', 'value' => 1],
            ['path' => ['items', '*', 'id'], 'operator' => 'contains', 'value' => ['$from' => 'create.id']],
            ['path' => ['items', 0, 'assignee'], 'operator' => 'equals', 'value' => 'owner'],
        ], ['create' => ['id' => 'task-1']]);
        $this->expectException(ValidationException::class);
        ResultAssertions::check(['items' => [['id' => 'task-1', 'assignee' => 'wrong']]], [
            ['path' => ['items', 0, 'assignee'], 'operator' => 'equals', 'value' => 'owner'],
        ], []);
    }
}
