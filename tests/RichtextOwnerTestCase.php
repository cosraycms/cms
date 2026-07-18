<?php

declare(strict_types=1);

namespace Cosray\Tests;

use Celema\Core\Request;
use Cosray\Assets\Repository;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Owner;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\UrlPaths;

/**
 * Base for richtext tests needing a field owner with config access.
 *
 * @internal
 *
 * @coversNothing
 */
abstract class RichtextOwnerTestCase extends TestCase
{
	protected function owner(array $settings = []): Owner
	{
		$config = $this->config($settings);
		$locales = new Locales();
		$locales->add('en', 'English');
		$locales->add('de', 'Deutsch', fallback: 'en');

		return new class($config, $locales) implements Owner {
			public function __construct(
				private readonly Config $config,
				private readonly Locales $locales,
			) {}

			public function uid(): string
			{
				return 'test-node';
			}

			public function locale(): Locale
			{
				return $this->locales->get('en');
			}

			public function defaultLocale(): Locale
			{
				return $this->locales->getDefault();
			}

			public function locales(): Locales
			{
				return $this->locales;
			}

			public function request(): Request
			{
				throw new RuntimeException('Not available in this test');
			}

			public function config(): Config
			{
				return $this->config;
			}

			public function assets(): Repository
			{
				throw new RuntimeException('Not available in this test');
			}

			public function paths(): UrlPaths
			{
				throw new RuntimeException('Not available in this test');
			}
		};
	}
}
