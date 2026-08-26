<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field\Condition;
use Cosray\Schema\When;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class FieldConditionTest extends TestCase
{
	private function content(mixed $value): array
	{
		return ['flag' => ['type' => 'x', 'value' => ['zxx' => $value]]];
	}

	public function testTruthyShorthand(): void
	{
		$condition = new When('flag')->condition();

		$this->assertTrue(Condition::active($condition, $this->content(true)));
		$this->assertTrue(Condition::active($condition, $this->content('yes')));
		$this->assertFalse(Condition::active($condition, $this->content(false)));
		$this->assertFalse(Condition::active($condition, $this->content('')));
		$this->assertFalse(Condition::active($condition, $this->content('0')));
		$this->assertFalse(Condition::active($condition, []));
	}

	public function testEqualityNormalizesFormAndStoredValues(): void
	{
		$condition = new When('flag', 'hero')->condition();

		$this->assertTrue(Condition::active($condition, $this->content('hero')));
		$this->assertFalse(Condition::active($condition, $this->content('plain')));

		// Stored bools compare like their form representation.
		$boolish = new When('flag', true)->condition();
		$this->assertTrue(Condition::active($boolish, $this->content(true)));
		$this->assertTrue(Condition::active($boolish, $this->content('1')));

		$numeric = new When('flag', 5)->condition();
		$this->assertTrue(Condition::active($numeric, $this->content('5')));
		$this->assertTrue(Condition::active($numeric, $this->content(5.0)));
	}

	public function testMembership(): void
	{
		$condition = new When('flag', in: ['a', 'b'])->condition();

		$this->assertTrue(Condition::active($condition, $this->content('b')));
		$this->assertFalse(Condition::active($condition, $this->content('c')));
	}

	public function testExplicitOperators(): void
	{
		$empty = new When('flag', op: 'empty')->condition();
		$this->assertTrue(Condition::active($empty, $this->content('')));
		$this->assertTrue(Condition::active($empty, []));
		$this->assertFalse(Condition::active($empty, $this->content('x')));

		$notEmpty = new When('flag', op: 'notEmpty')->condition();
		$this->assertTrue(Condition::active($notEmpty, $this->content('x')));
		$this->assertFalse(Condition::active($notEmpty, $this->content('')));

		$neq = new When('flag', 'a', op: 'neq')->condition();
		$this->assertTrue(Condition::active($neq, $this->content('b')));
		$this->assertFalse(Condition::active($neq, $this->content('a')));
	}

	public function testUnknownOperatorKeepsTheFieldActive(): void
	{
		$this->assertTrue(Condition::active(
			['field' => 'flag', 'op' => 'bogus', 'value' => null],
			$this->content(''),
		));
	}

	public function testContractFixtures(): void
	{
		$fixtures = json_decode(
			(string) file_get_contents(__DIR__ . '/../../contract/conditions.json'),
			true,
		);
		$this->assertNotEmpty($fixtures['cases']);

		foreach ($fixtures['cases'] as $case) {
			$field = $case['condition']['field'];
			$content = array_key_exists('stored', $case)
				? [$field => ['type' => 'x', 'value' => ['zxx' => $case['stored']]]]
				: [];

			$this->assertSame(
				$case['active'],
				Condition::active($case['condition'], $content),
				"contract case: {$case['name']}",
			);
		}
	}
}
