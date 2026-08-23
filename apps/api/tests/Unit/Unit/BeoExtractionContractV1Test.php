<?php

namespace Tests\Unit\Unit;

use App\Data\BeoExtraction\V1\BeoExtractionContractValidator;
use App\Data\BeoExtraction\V1\ExtractionResultData;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BeoExtractionContractV1Test extends TestCase
{
    public function test_standard_fixture_is_accepted_as_typed_result(): void
    {
        $result = $this->validator()->validateResult($this->fixture('standard.json'));

        $this->assertInstanceOf(ExtractionResultData::class, $result);
        $this->assertSame('completed', $result->status);
        $this->assertCount(2, $result->eventOrders);
    }

    public function test_all_result_fixtures_are_accepted(): void
    {
        foreach (['non-food-function.json', 'menu-tbd.json', 'partial-result.json'] as $fixture) {
            $this->validator()->validateResult($this->fixture($fixture));
        }

        $this->assertTrue(true);
    }

    public function test_missing_required_field_fails(): void
    {
        $payload = $this->fixture('standard.json');
        unset($payload['event_orders'][0]['functions'][0]['source_trace']);

        $this->expectException(ValidationException::class);
        $this->validator()->validateResult($payload);
    }

    public function test_unsupported_schema_major_fails(): void
    {
        $payload = $this->fixture('standard.json');
        $payload['schema_version'] = '2.0.0';

        $this->expectException(ValidationException::class);
        $this->validator()->validateResult($payload);
    }

    public function test_confidence_outside_range_fails(): void
    {
        $payload = $this->fixture('standard.json');
        $payload['event_orders'][0]['confidence'] = 1.1;

        $this->expectException(ValidationException::class);
        $this->validator()->validateResult($payload);
    }

    public function test_attendance_counts_remain_distinct(): void
    {
        $result = $this->validator()->validateResult($this->fixture('standard.json'));
        $attendance = $result->eventOrders[0]['functions'][0]['attendance'];

        $this->assertSame(180, $attendance['expected_count']);
        $this->assertSame(140, $attendance['guaranteed_count']);
        $this->assertSame(200, $attendance['set_count']);
    }

    public function test_non_food_function_and_menu_tbd_are_valid(): void
    {
        $nonFood = $this->validator()->validateResult($this->fixture('non-food-function.json'));
        $tbd = $this->validator()->validateResult($this->fixture('menu-tbd.json'));

        $this->assertFalse($nonFood->eventOrders[0]['functions'][0]['relevance_signals']['has_food']);
        $this->assertSame('tbd', $tbd->eventOrders[0]['functions'][0]['menu']['status']);
    }

    public function test_multiple_venues_and_ambiguous_quantity_are_preserved(): void
    {
        $function = $this->validator()->validateResult($this->fixture('standard.json'))->eventOrders[0]['functions'][0];
        $quantity = $function['menu']['sections'][0]['items'][0]['quantity'];

        $this->assertCount(2, $function['venue_candidates']);
        $this->assertSame('(3) Brownies @ $56 per Dozen', $quantity['raw_quantity_text']);
        $this->assertNull($quantity['production_quantity']);
    }

    public function test_partial_result_and_unresolved_item_are_valid(): void
    {
        $result = $this->validator()->validateResult($this->fixture('partial-result.json'));

        $this->assertSame('partial', $result->status);
        $this->assertCount(1, $result->toArray()['unresolved_items']);
    }

    public function test_canonical_ids_are_rejected_and_validator_has_no_persistence_path(): void
    {
        $payload = $this->fixture('standard.json');
        $payload['event_orders'][0]['functions'][0]['venue_id'] = 'canonical-venue';

        $this->expectException(ValidationException::class);
        $this->validator()->validateResult($payload);
    }

    public function test_malformed_nested_structure_fails_deterministically(): void
    {
        $payload = $this->fixture('standard.json');
        $payload['event_orders'][0]['functions'][0]['menu']['sections'][0]['items'] = ['not-an-object'];

        $this->expectException(ValidationException::class);
        $this->validator()->validateResult($payload);
    }

    private function validator(): BeoExtractionContractValidator
    {
        return app(BeoExtractionContractValidator::class);
    }

    private function fixture(string $name): array
    {
        $path = dirname(base_path(), 2).DIRECTORY_SEPARATOR.'contracts'.DIRECTORY_SEPARATOR.'beo-extraction'.DIRECTORY_SEPARATOR.'v1'.DIRECTORY_SEPARATOR.'examples'.DIRECTORY_SEPARATOR.$name;
        $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}
