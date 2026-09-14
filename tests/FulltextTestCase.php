<?php

declare(strict_types=1);

namespace Cosray\Tests;

use Celema\Quma\Query;
use Cosray\Block\Registry;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Fulltext\Builder;
use Cosray\Fulltext\Sync;
use Cosray\Locales;
use Cosray\Node\PathManager;
use Cosray\Node\Store;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Node\Writer;
use Cosray\Tests\Fixtures\Node\FulltextDocument;
use Cosray\Tests\Fixtures\Node\FulltextPage;

abstract class FulltextTestCase extends IntegrationTestCase
{
	protected Context $context;
	protected Cms $cms;
	protected Store $store;
	protected Writer $writer;
	protected Locales $languages;
	protected Services $services;

	protected function environment(): void
	{
		$this->languages = new Locales();
		$this->languages->add('en', 'English', pgDict: 'english');
		$this->languages->add('de', 'German', fallback: 'en', pgDict: 'german');
		$container = $this->container();
		$container->tag(Bootstrap::NODE_TAG)->add('fulltext-page', FulltextPage::class);
		$container->tag(Bootstrap::NODE_TAG)->add('fulltext-document', FulltextDocument::class);
		$this->context = Context::console($this->db(), $this->config(), $container, $this->factory(), $this->languages);
		$this->services = Services::withDefaults();
		$this->cms = new Cms($this->context, $this->services);
		$this->writer = new Writer($this->context, $this->cms, $this->services->types);
		$this->store = new Store(
			$this->context->db,
			new PathManager(),
			$this->services->types,
			$this->cms->nodeFactory()->uid(),
			factory: $this->cms->nodeFactory(),
			cms: $this->cms,
			context: $this->context,
		);
	}

	protected function page(string $uid, string $name, string $body = ''): void
	{
		$this->writer->create(
			$this->writer
				->prepare(FulltextPage::class, ['name' => ['en' => $name], 'body' => ['en' => $body]])
				->uid($uid)
				->published(),
		);
	}

	protected function node(string $uid): object
	{
		$wrapper = $this->cms->node->byUid($uid, published: null);
		$this->assertNotNull($wrapper);
		return Wrapper::unwrap($wrapper);
	}

	protected function payload(string $uid, string $name, string $body = ''): array
	{
		return $this->writer
			->prepare(FulltextPage::class, ['name' => ['en' => $name], 'body' => ['en' => $body]])
			->uid($uid)
			->published()
			->data();
	}

	protected function source(string $uid): string
	{
		return $this->sql('source', ['uid' => $uid])->first()['source'] ?? '';
	}

	protected function sync(): Sync
	{
		return new Sync($this->db(), new Builder(new Types(), Registry::withDefaults()));
	}

	protected function sql(string $name, array $params = []): Query
	{
		return $this->db()->execute(
			file_get_contents(self::root() . "/tests/Fixtures/sql/fulltext/{$name}.sql"),
			$params,
		);
	}
}
