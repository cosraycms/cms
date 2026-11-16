<?php

declare(strict_types=1);

namespace Cosray\Tests;

use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Node\PathManager;
use Cosray\Node\ReservedPaths;
use Cosray\Node\Store;
use Cosray\Node\Wrapper;
use Cosray\Node\Writer;
use Cosray\Tests\Fixtures\Node\TextPage;
use Cosray\Tests\Fixtures\Node\TextSetting;
use Cosray\Tests\Fixtures\Node\TextSubmission;

abstract class TextTestCase extends IntegrationTestCase
{
	protected Context $context;
	protected Cms $cms;
	protected Store $store;
	protected Writer $writer;
	protected Locales $languages;

	protected function setUp(): void
	{
		parent::setUp();

		$this->languages = new Locales();
		$this->languages->add('de', 'Deutsch', pgDict: 'german');
		$this->languages->add('en', 'English', fallback: 'de', pgDict: 'english');
		$container = $this->container();
		$container->tag(Bootstrap::NODE_TAG)->add('text-page', TextPage::class);
		$container->tag(Bootstrap::NODE_TAG)->add('text-setting', TextSetting::class);
		$container->tag(Bootstrap::NODE_TAG)->add('text-submission', TextSubmission::class);
		$this->context = Context::console($this->db(), $this->config(), $container, $this->factory(), $this->languages);
		$services = Services::withDefaults();
		$this->cms = new Cms($this->context, $services);
		$this->writer = new Writer($this->context, $this->cms, $services->types);
		$this->store = new Store(
			$this->context->db,
			new PathManager(ReservedPaths::fromConfig($this->context->config)),
			$services->types,
			$this->cms->nodeFactory()->uid(),
			factory: $this->cms->nodeFactory(),
			cms: $this->cms,
			context: $this->context,
		);
	}

	/** @param array<string, string> $paragraphs body paragraphs by locale */
	protected function page(string $uid, array $titles, array $paragraphs, bool $published = true): void
	{
		$prepared = $this->writer
			->prepare(TextPage::class, [
				'title' => $titles,
				'body' => array_map(static fn(string $text): array => [
					'type' => 'doc',
					'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]],
				], $paragraphs),
			])
			->uid($uid);

		$this->writer->create($published ? $prepared->published() : $prepared);
	}

	protected function node(string $uid): object
	{
		$wrapper = $this->cms->node->working($uid);
		$this->assertNotNull($wrapper);

		return Wrapper::unwrap($wrapper);
	}
}
