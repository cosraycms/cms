<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celemas\Core\Request;
use Cosray\Context;
use Cosray\Field\FieldHydrator;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Node\Factory;
use Cosray\Node\Serializer;
use Cosray\Node\Types;
use Cosray\Tests\Fixtures\Field\TestMoney;
use Cosray\Tests\Fixtures\Node\TestMoneyDocument;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Uid;

/**
 * A field type shipped by a plugin needs no core changes to hydrate,
 * serialize and expose editor properties — it is referenced by its
 * class on the node property.
 */
final class PluginFieldTest extends IntegrationTestCase
{
	public function testPluginFieldRoundTripsThroughSerializer(): void
	{
		$factory = new Factory(
			$this->container(),
			Services::withDefaults(),
			new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13),
		);
		$context = $this->localizedContext();
		$cms = $this->createCms();

		$node = $factory->create(TestMoneyDocument::class, $context, $cms, [
			'content' => [
				'price' => ['type' => TestMoney::class, 'value' => ['zxx' => '9.99']],
			],
		]);

		$field = FieldHydrator::getField($node, 'price');
		$this->assertInstanceOf(TestMoney::class, $field);
		$this->assertSame('9.99', $field->value()->unwrap());

		$serializer = new Serializer(new Types(), $factory->uid());
		$content = $serializer->content($node, [], ['price']);
		$this->assertSame(TestMoney::class, $content['price']['type']);

		$properties = $serializer->fields($node, ['price']);
		$this->assertSame('price', $properties[0]['name']);
		$this->assertSame(TestMoney::class, $properties[0]['type']);
		$this->assertSame('Price', $properties[0]['label']);
	}

	private function localizedContext(): Context
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$psrRequest = $this
			->psrRequest()
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());

		return new Context(
			$this->db(),
			new Request($psrRequest),
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}
}
