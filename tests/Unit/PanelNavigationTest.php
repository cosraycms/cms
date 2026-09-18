<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Request;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Collection;
use Cosray\Collection\Schemas;
use Cosray\Context;
use Cosray\Controller\Panel\Editor;
use Cosray\Field\Schema\Registry;
use Cosray\Field\Services;
use Cosray\Finder\Nodes;
use Cosray\Locales;
use Cosray\Navigation;
use Cosray\Node\Types;
use Cosray\Panel\Extras;
use Cosray\Schema\Blueprints;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Tests\Fixtures\Node\PlainBlock;
use Cosray\Tests\TestCase;
use Cosray\View\Boiler\Renderer;

final class PanelNavigationTest extends TestCase
{
	private Locales $locales;
	private Navigation $navigation;

	protected function setUp(): void
	{
		parent::setUp();

		$this->locales = new Locales();
		$this->locales->add('en', 'English');
		$this->locales->add('de', 'Deutsch');
		$this->locales->catalog('navigation', self::root() . '/tests/Fixtures/lang');

		$this->navigation = new Navigation();
		$section = $this->navigation->section('Content & media');
		$section->collection(NavigationCollection::class);
		$section->section('Help')->link('Help <guide>', '/cp/help');
	}

	protected function tearDown(): void
	{
		Verba::deactivate();

		parent::tearDown();
	}

	public function testSidebarTranslatesAndEscapesLabelsForEachPanelLocale(): void
	{
		foreach ([
			'de' => ['Inhalte & Medien', 'Artikel & Seiten', 'Hilfe', 'Hilfe <Anleitung>'],
			'en' => ['Content & media', 'Articles & pages', 'Help', 'Help <guide>'],
		] as $locale => $labels) {
			Verba::activate(new Translator($locale, $this->locales->catalogs()));
			$html = new Renderer(self::root() . '/panel/views')->render('component/collections', [
				'collections' => $this->navigation->items(),
				'level' => 0,
				'panelPath' => '/cp',
				'currentPath' => '/cp/collection/articles',
			]);

			foreach ($labels as $label) {
				$this->assertHtmlNodeExists('//span[text()="' . $label . '"]', $html);
			}
		}
	}

	public function testCreateEditorUsesThePanelLocaleForItsCollectionBreadcrumb(): void
	{
		$config = $this->config();
		$container = $this->container();
		$types = new Types();
		$container->add(Types::class, $types);
		$container->add(Schemas::class, new Schemas());
		$container->add(Navigation::class, $this->navigation);
		$container->add(Extras::class, new Extras());
		$container->tag(Bootstrap::NODE_TAG)->add('plain-block', PlainBlock::class);
		$request = new Request(
			$this
				->psrRequest()
				->withAttribute('locales', $this->locales)
				->withAttribute('locale', $this->locales->get('en'))
				->withAttribute('panelLocale', 'de')
				->withQueryParams(['from' => 'collection:articles'])
				->withHeader('HX-Request', 'true')
				->withHeader('HX-Target', 'main#main'),
		);
		$db = $this->db();
		$context = new Context($db, $request, $config, $container, $this->factory());
		$cms = new Cms($context, new Services(Registry::withDefaults(), $types));
		$container->add(Cms::class, $cms);
		Verba::activate(new Translator('de', $this->locales->catalogs()));

		$data = new Editor($config, $container, $request)->create($context, $cms, 'plain-block');
		$html = new Renderer(self::root() . '/panel/views')->render('editor', $data);

		$this->assertHtmlNodeExists(
			'//nav[@class="breadcrumb"]/a[text()="Artikel & Seiten"]',
			$html,
		);
		$container->add(Navigation::class, new Navigation());
		$request->wrap($request->unwrap()->withQueryParams([]));
		$data = new Editor($config, $container, $request)->create($context, $cms, 'plain-block');
		$html = new Renderer(self::root() . '/panel/views')->render('editor', $data);
		$this->assertHtmlNodeExists(
			'//form[@id="node-editor-form"][@action="/cp/node/create/plain-block"]',
			$html,
		);
		$this->assertFalse($db->connected());
	}
}

#[Label('Articles & pages'), Handle('articles'), Blueprints(PlainBlock::class)]
final class NavigationCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms->nodes();
	}
}
