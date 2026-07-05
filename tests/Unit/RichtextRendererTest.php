<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\Richtext\Renderer;
use Cosray\Richtext\Resolver;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RichtextRendererTest extends TestCase
{
	private function resolver(): Resolver
	{
		return new class implements Resolver {
			public function asset(string $uid): ?Asset
			{
				return match ($uid) {
					'img1' => new Asset(
						uid: 'img1',
						disk: 'local',
						key: 'ab/img1/photo.jpg',
						filename: 'Photo.jpg',
						kind: 'image',
						mime: 'image/jpeg',
						meta: ['alt' => ['zxx' => 'Ein Foto']],
					),
					'doc1' => new Asset(
						uid: 'doc1',
						disk: 'local',
						key: 'cd/doc1/paper.pdf',
						filename: 'Paper.pdf',
						kind: 'file',
						mime: 'application/pdf',
					),
					default => null,
				};
			}

			public function nodePath(string $uid): ?string
			{
				return $uid === 'n1' ? '/de/ueber-uns' : null;
			}

			public function localize(array $map): mixed
			{
				return $map['de'] ?? $map['zxx'] ?? null;
			}
		};
	}

	public function testRendersBlocksAndAttributes(): void
	{
		$renderer = new Renderer($this->resolver());
		$html = $renderer->render([
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'attrs' => ['class' => 'intro', 'align' => 'center'],
					'content' => [['type' => 'text', 'text' => 'Auf & davon']],
				],
				[
					'type' => 'heading',
					'attrs' => ['level' => 2],
					'content' => [['type' => 'text', 'text' => 'Titel']],
				],
				[
					'type' => 'orderedList',
					'attrs' => ['start' => 3],
					'content' => [
						[
							'type' => 'listItem',
							'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Drei']]]],
						],
					],
				],
				[
					'type' => 'blockquote',
					'content' => [
						['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Zitat']]],
					],
				],
				['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => '<?php echo 1;']]],
				['type' => 'horizontalRule', 'attrs' => ['class' => 'fancy']],
				[
					'type' => 'paragraph',
					'content' => [
						['type' => 'text', 'text' => 'Zeile'],
						['type' => 'hardBreak'],
						['type' => 'text', 'text' => 'zwei'],
					],
				],
			],
		]);

		$this->assertSame(
			'<p class="intro" style="text-align: center">Auf &amp; davon</p>'
			. '<h2>Titel</h2>'
			. '<ol start="3"><li><p>Drei</p></li></ol>'
			. '<blockquote><p>Zitat</p></blockquote>'
			. '<pre><code>&lt;?php echo 1;</code></pre>'
			. '<hr class="fancy">'
			. '<p>Zeile<br>zwei</p>',
			$html,
		);
		$this->assertSame([], $renderer->notices());
	}

	public function testMarkNestingAndLinkKinds(): void
	{
		$renderer = new Renderer($this->resolver());
		$html = $renderer->render([
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [
						[
							'type' => 'text',
							'text' => 'extern',
							'marks' => [
								['type' => 'bold'],
								['type' => 'link', 'attrs' => ['href' => 'https://x.de?a=1&b=2', 'target' => '_blank']],
							],
						],
						[
							'type' => 'text',
							'text' => 'intern',
							'marks' => [['type' => 'link', 'attrs' => ['node' => 'n1', 'class' => 'button']]],
						],
						[
							'type' => 'text',
							'text' => 'Datei',
							'marks' => [['type' => 'link', 'attrs' => ['asset' => 'doc1']]],
						],
						[
							'type' => 'text',
							'text' => 'groß',
							'marks' => [
								['type' => 'style', 'attrs' => ['class' => 'cms-text-xl']],
								['type' => 'italic'],
							],
						],
						['type' => 'text', 'text' => 'H2O', 'marks' => [['type' => 'subscript']]],
						['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'code']]],
					],
				],
			],
		]);

		$this->assertSame(
			'<p>'
			. '<a href="https://x.de?a=1&amp;b=2" target="_blank" rel="noopener noreferrer nofollow"><strong>extern</strong></a>'
			. '<a href="/de/ueber-uns" class="button">intern</a>'
			. '<a href="/assets/cd/doc1/paper.pdf">Datei</a>'
			. '<span class="cms-text-xl"><em>groß</em></span>'
			. '<sub>H2O</sub>'
			. '<code>x</code>'
			. '</p>',
			$html,
		);
	}

	public function testImagesResolveThroughTheCatalog(): void
	{
		$renderer = new Renderer($this->resolver());
		$html = $renderer->render([
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [
						['type' => 'image', 'attrs' => ['uid' => 'img1']],
						[
							'type' => 'image',
							'attrs' => ['uid' => 'img1', 'meta' => ['alt' => 'Anders', 'title' => 'Titel']],
						],
					],
				],
			],
		]);

		$this->assertSame(
			'<p>'
			. '<img src="/cache/ab/img1/photo-block.jpg" alt="Ein Foto">'
			. '<img src="/cache/ab/img1/photo-block.jpg" alt="Anders" title="Titel">'
			. '</p>',
			$html,
		);
	}

	public function testDegradesOnUnknownAndUnresolvable(): void
	{
		$renderer = new Renderer($this->resolver());
		$html = $renderer->render([
			'type' => 'doc',
			'content' => [
				['type' => 'callout', 'content' => [['type' => 'paragraph', 'content' => []]]],
				[
					'type' => 'paragraph',
					'content' => [
						['type' => 'image', 'attrs' => ['uid' => 'gone']],
						[
							'type' => 'text',
							'text' => 'tot',
							'marks' => [['type' => 'link', 'attrs' => ['node' => 'gone']]],
						],
						['type' => 'text', 'text' => 'markiert', 'marks' => [['type' => 'blink']]],
					],
				],
			],
		]);

		$this->assertSame('<p>totmarkiert</p>', $html);
		$this->assertSame(
			[
				"unknown node type 'callout'",
				"unresolvable image 'gone'",
				"unresolvable node link 'gone'",
				"unknown mark type 'blink'",
			],
			$renderer->notices(),
		);
	}

	public function testNonDocInputRendersEmpty(): void
	{
		$renderer = new Renderer($this->resolver());

		$this->assertSame('', $renderer->render(null));
		$this->assertSame('', $renderer->render(['type' => 'paragraph']));
	}
}
