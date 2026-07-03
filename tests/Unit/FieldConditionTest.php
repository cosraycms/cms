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
}
