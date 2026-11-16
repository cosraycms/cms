<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Plugin\Namespaces;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @coversNothing
 */
final class PluginNamespacesTest extends TestCase
{
	public function testAPluginGetsASchemaOfItsOwnWhereCosrayHasOne(): void
	{
		$namespaces = $this->namespaces(['acme-shop', 'review-reports']);

		$this->assertSame('acme_shop.', $namespaces->prefix('acme-shop'));
		$this->assertSame(['acme_shop', 'review_reports'], $namespaces->schemas());
		$this->assertNull($namespaces->prefix('other-plugin'));

		$placeholders = $namespaces->placeholders()['pgsql'];
		$this->assertSame('cms.', $placeholders['cms.prefix']);
		$this->assertSame('review_reports.', $placeholders['review-reports.prefix']);
		$this->assertSame('', $placeholders['review-reports.obj']);
	}

	public function testAPluginFollowsCosraysNamePrefix(): void
	{
		$namespaces = $this->namespaces(['acme-shop'], ['pgsql' => ['cms.prefix' => 'cms_']]);

		$this->assertSame('acme_shop_', $namespaces->prefix('acme-shop'));
		$this->assertSame('acme_shop_', $namespaces->placeholders()['pgsql']['acme-shop.obj']);
		$this->assertSame([], $namespaces->schemas());
	}

	public function testOtherDriversHaveNamePrefixesOnly(): void
	{
		$namespaces = $this->namespaces(['acme-shop'], dsn: 'sqlite::memory:');

		$this->assertSame('acme_shop_', $namespaces->prefix('acme-shop'));
		$this->assertSame('acme_shop_', $namespaces->placeholders()['sqlite']['acme-shop.obj']);
		$this->assertSame([], $namespaces->schemas());
	}

	public function testASiteWithoutACosrayNamespaceStillGivesPluginsOne(): void
	{
		$namespaces = $this->namespaces(['acme-shop'], ['pgsql' => ['cms.prefix' => '']]);

		$this->assertSame('acme_shop.', $namespaces->prefix('acme-shop'));
	}

	public function testASiteChoosesAPluginsNamespace(): void
	{
		$namespaces = $this->namespaces(['acme-shop', 'review-reports'], ['pgsql' => [
			'cms.prefix' => 'cms.',
			'acme-shop.prefix' => 'shop_',
			'review-reports.prefix' => 'reviews.',
			'review-reports.obj' => 'rr_',
		]]);

		$placeholders = $namespaces->placeholders()['pgsql'];
		$this->assertSame(['shop_', 'shop_'], [$placeholders['acme-shop.prefix'], $placeholders['acme-shop.obj']]);
		$this->assertSame(['reviews.', 'rr_'], [
			$placeholders['review-reports.prefix'],
			$placeholders['review-reports.obj'],
		]);
		$this->assertSame(['reviews'], $namespaces->schemas());
	}

	/**
	 * @param list<string> $plugins
	 * @param array<string, string> $placeholders
	 */
	#[DataProvider('refused')]
	public function testANamespaceThatIsNotThePluginsOwnIsRefused(
		array $plugins,
		array $placeholders,
		string $error,
	): void {
		$this->throws(RuntimeException::class, $error);

		$this->namespaces($plugins, ['pgsql' => $placeholders]);
	}

	/** @return iterable<string, array{list<string>, array<string, string>, string}> */
	public static function refused(): iterable
	{
		yield 'empty' => [['acme-shop'], ['acme-shop.prefix' => ''], "Invalid database namespace ''"];
		yield 'the site\'s' => [
			['acme-shop'],
			['acme-shop.prefix' => 'public.'],
			"Invalid database namespace 'public.'",
		];
		yield 'no identifier' => [['acme-shop'], ['acme-shop.prefix' => 'Shop Tables.'], 'Invalid database namespace'];
		yield 'Cosray\'s schema' => [['acme-shop'], ['acme-shop.prefix' => 'cms.'], "overlaps 'cms.' of Cosray"];
		yield 'inside Cosray\'s prefix' => [
			['cms-shop'],
			['cms.prefix' => 'cms_'],
			"The database namespace 'cms_shop_' of plugin cms-shop overlaps 'cms_' of Cosray",
		];
		yield 'another plugin\'s' => [
			['acme-shop', 'acme-shop-export'],
			['cms.prefix' => 'site_'],
			"overlaps 'acme_shop_' of plugin acme-shop",
		];
	}

	public function testSchemasNamedAlikeStayApart(): void
	{
		$namespaces = $this->namespaces(['acme-shop', 'acme-shop-export']);

		$this->assertSame(['acme_shop', 'acme_shop_export'], $namespaces->schemas());
	}

	/**
	 * @param list<string> $plugins
	 * @param array<string, array<string, string>> $placeholders
	 */
	private function namespaces(
		array $plugins,
		array $placeholders = [],
		string $dsn = 'pgsql:dbname=cosray',
	): Namespaces {
		$settings = ['db.dsn' => $dsn];

		if ($placeholders !== []) {
			$settings['db.placeholders'] = $placeholders;
		}

		return new Namespaces($this->config($settings)->db, $plugins);
	}
}
