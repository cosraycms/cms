<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Context;
use Celemas\Cms\Node\FieldOwner;
use Celemas\Cms\Tests\Fixtures\Field\TestGrid;
use Celemas\Cms\Tests\TestCase;
use Celemas\Cms\Value\Grid as GridValue;
use Celemas\Cms\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class GridValueTest extends TestCase
{
	private function createContext(): Context
	{
		$psrRequest = $this->psrRequest();
		$locales = new \Celemas\Cms\Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$locales->add('de', title: 'Deutsch', domains: ['www.example.de'], fallback: 'en');

		$psrRequest = $psrRequest
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());

		$request = new \Celemas\Core\Request($psrRequest);

		return new Context(
			$this->db(),
			$request,
			$this->config(['path.prefix' => '/cms']),
			$this->container(),
			$this->factory(),
		);
	}

	private function createOwner(Context $context): FieldOwner
	{
		return new FieldOwner($context, 'test-node');
	}

	private function createGridValue(array $data): GridValue
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestGrid('grid', $owner, new ValueContext('grid', $data));

		return $field->value();
	}

	public function testUnwrapReturnsColumnsAndPreparedData(): void
	{
		$grid = $this->createGridValue([
			'columns' => 12,
			'value' => [
				'en' => [
					['type' => 'text', 'value' => 'Hello', 'colspan' => 12, 'rowspan' => 1],
				],
			],
		]);

		$unwrapped = $grid->unwrap();
		$this->assertSame(12, $unwrapped['columns']);
		$this->assertIsIterable($unwrapped['data']);
	}

	public function testHasImageDetectsImageItems(): void
	{
		$grid = $this->createGridValue([
			'columns' => 12,
			'value' => [
				['type' => 'text', 'value' => 'Hello', 'colspan' => 12, 'rowspan' => 1],
				['type' => 'image', 'files' => [['file' => 'test.jpg']], 'colspan' => 12, 'rowspan' => 1],
			],
		]);

		$this->assertTrue($grid->hasImage());
	}

	public function testExcerptReturnsEmptyWhenNoHtml(): void
	{
		$grid = $this->createGridValue([
			'columns' => 12,
			'value' => [
				['type' => 'text', 'value' => 'Hello', 'colspan' => 12, 'rowspan' => 1],
			],
		]);

		$this->assertSame('', $grid->excerpt());
	}

	public function testIssetReturnsFalseForEmptyValue(): void
	{
		$grid = $this->createGridValue([
			'columns' => 12,
			'value' => [],
		]);

		$this->assertFalse($grid->isset());
	}
}
