<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celemas\Core\Request;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\FieldHydrator;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Node\Factory;
use Cosray\Tests\Fixtures\Node\TestConditionalDocument;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Uid;

/**
 * Read-time enforcement of #[When]: an inactive field presents as
 * empty everywhere while its stored value survives and stays reachable
 * through Field::raw().
 *
 * @internal
 *
 * @coversNothing
 */
final class FieldWhenTest extends IntegrationTestCase
{
	private Factory $nodeFactory;
	private FieldHydrator $hydrator;

	protected function setUp(): void
	{
		parent::setUp();
		$this->nodeFactory = new Factory(
			$this->container(),
			Services::withDefaults(),
			new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13),
		);
		$this->hydrator = $this->nodeFactory->hydrator();
	}

	private function document(array $content): object
	{
		$context = $this->context();

		return $this->nodeFactory->create(
			TestConditionalDocument::class,
			$context,
			new Cms($context, Services::withDefaults()),
			['content' => $content],
		);
	}

	/** Like createContext(), but with the defaultLocale request attribute values need. */
	private function context(): Context
	{
		$psr = $this->psrRequest();
		$locales = $psr->getAttribute('locales');
		assert($locales instanceof Locales, 'psrRequest provides the locales attribute');

		return new Context(
			$this->db(),
			new Request($psr->withAttribute('defaultLocale', $locales->getDefault())),
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}

	public function testInactiveFieldPresentsAsEmptyAndKeepsItsRawValue(): void
	{
		$node = $this->document([
			'multiDay' => ['type' => 'checkbox', 'value' => ['zxx' => false]],
			'endDate' => ['type' => 'text', 'value' => ['zxx' => 'kept-dormant']],
		]);

		$endDate = $this->hydrator->getField($node, 'endDate');

		$this->assertFalse($endDate->isset());
		$this->assertSame('', (string) $endDate);
		$this->assertSame('kept-dormant', $endDate->raw()['value']['zxx']);
	}

	public function testActiveFieldPresentsItsValue(): void
	{
		$node = $this->document([
			'multiDay' => ['type' => 'checkbox', 'value' => ['zxx' => true]],
			'endDate' => ['type' => 'text', 'value' => ['zxx' => 'visible']],
		]);

		$this->assertSame('visible', (string) $this->hydrator->getField($node, 'endDate'));
	}

	public function testEqualityConditionAgainstSiblingValue(): void
	{
		$node = $this->document([
			'title' => ['type' => 'text', 'value' => ['zxx' => 'hero']],
			'layoutHint' => ['type' => 'text', 'value' => ['zxx' => 'wide']],
		]);

		$this->assertSame('wide', (string) $this->hydrator->getField($node, 'layoutHint'));

		$node = $this->document([
			'title' => ['type' => 'text', 'value' => ['zxx' => 'plain']],
			'layoutHint' => ['type' => 'text', 'value' => ['zxx' => 'wide']],
		]);

		$this->assertSame('', (string) $this->hydrator->getField($node, 'layoutHint'));
	}

	public function testConditionSerializesIntoTheFieldPayload(): void
	{
		$node = $this->document([]);
		$properties = $this->hydrator->getField($node, 'endDate')->properties();

		$this->assertSame(
			['field' => 'multiDay', 'op' => 'truthy', 'value' => null],
			$properties['when'],
		);
	}
}
