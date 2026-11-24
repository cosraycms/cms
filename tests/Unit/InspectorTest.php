<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\App;
use Cosray\Contract\InspectorSection;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Wrapper;
use Cosray\Panel\Inspector;
use Cosray\Panel\Inspector\Section;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 *
 * @covers \Cosray\Panel\Inspector
 * @covers \Cosray\Panel\Inspector\Section
 */
final class InspectorTest extends TestCase
{
	public function testAnInspectorStartsWithoutSections(): void
	{
		$inspector = new Inspector();

		$this->assertTrue($inspector->isEmpty());
		$this->assertFalse($inspector->add(new TestInspectorSection())->isEmpty());
	}

	public function testAppExposesItsInspectorAsAProperty(): void
	{
		$app = App::create(dirname(__DIR__, 2));
		$app->inspector->add(TestInspectorSection::class);

		$this->assertFalse($app->inspector->isEmpty());
	}

	public function testSectionClassesMustImplementTheContract(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(
			'Inspector sections must implement ' . InspectorSection::class . ': ' . stdClass::class,
		);

		/** @phpstan-ignore argument.type */
		new Inspector()->add(stdClass::class);
	}

	public function testSectionClassesMustBeInstantiable(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('must be instantiable');

		new Inspector()->add(AbstractInspectorSection::class);
	}

	public function testASectionNamesTheFormItsControlsPostWith(): void
	{
		$section = new Section('acme-shop', 'Stock', 'acme-shop:stock', ['count' => 3], '/panel/acme-shop/stock');

		$this->assertSame('cms-inspector-form-acme-shop', $section->form());
	}

	public function testASectionKeyIsSafeInAnElementId(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(
			"An inspector section's key has lowercase letters, digits and dashes and starts with a letter: Acme Shop",
		);

		new Section('Acme Shop', 'Stock', 'acme-shop:stock');
	}
}

abstract class AbstractInspectorSection implements InspectorSection {}

final class TestInspectorSection implements InspectorSection
{
	public function section(Wrapper $node): Section
	{
		return new Section('test', 'Test', 'test:section');
	}
}
