<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Context;
use Cosray\Node\FieldOwner;
use Cosray\Tests\Fixtures\Field\TestBlocks;
use Cosray\Tests\TestCase;
use Cosray\Value\Blocks as BlocksValue;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlocksValueTest extends TestCase
{
	private function createContext(): Context
	{
		$psrRequest = $this->psrRequest();
		$locales = new \Cosray\Locales();
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

	private function createBlocksValue(array $data): BlocksValue
	{
		$context = $this->createContext();
		$owner = $this->createOwner($context);
		$field = new TestBlocks('blocks', $owner, new ValueContext('blocks', $data));

		return $field->value();
	}

	public function testUnwrapReturnsColumnsAndPreparedData(): void
	{
		$blocks = $this->createBlocksValue([
			'columns' => 12,
			'value' => [
				'en' => [
					['type' => 'text', 'value' => 'Hello', 'colspan' => 12, 'rowspan' => 1],
				],
			],
		]);

		$unwrapped = $blocks->unwrap();
		$this->assertSame(12, $unwrapped['columns']);
		$this->assertIsIterable($unwrapped['data']);
	}

	public function testHasImageDetectsImageItems(): void
	{
		$blocks = $this->createBlocksValue([
			'columns' => 12,
			'value' => [
				['type' => 'text', 'value' => 'Hello', 'colspan' => 12, 'rowspan' => 1],
				['type' => 'image', 'files' => [['file' => 'test.jpg']], 'colspan' => 12, 'rowspan' => 1],
			],
		]);

		$this->assertTrue($blocks->hasImage());
	}

	public function testExcerptReturnsEmptyWhenNoHtml(): void
	{
		$blocks = $this->createBlocksValue([
			'columns' => 12,
			'value' => [
				['type' => 'text', 'value' => 'Hello', 'colspan' => 12, 'rowspan' => 1],
			],
		]);

		$this->assertSame('', $blocks->excerpt());
	}

	public function testIssetReturnsFalseForEmptyValue(): void
	{
		$blocks = $this->createBlocksValue([
			'columns' => 12,
			'value' => [],
		]);

		$this->assertFalse($blocks->isset());
	}
}
