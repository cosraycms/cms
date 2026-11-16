<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Cosray\Actor;
use Cosray\Commands\ExportTexts;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Node\Factory;
use Cosray\Node\Wrapper;
use Cosray\Tests\Fixtures\Node\TextSetting;
use Cosray\Tests\Fixtures\Node\TextSubmission;
use Cosray\Tests\TextTestCase;
use Cosray\Text\Edits;
use Cosray\Text\Text;
use Cosray\Text\Texts;

/**
 * @internal
 *
 * @coversNothing
 */
final class TextsTest extends TextTestCase
{
	public function testTheTextsOfAFinderQueryCarryTheirNodesTitleAndPath(): void
	{
		$this->page('text-live', ['de' => 'Bier', 'en' => 'Beer'], ['de' => 'Klimaneutral gebraut.']);
		$this->page('text-other', ['de' => 'Wasser'], ['de' => 'Aus der Quelle.']);

		$texts = iterator_to_array($this->texts()->of($this->cms->nodes->only('text-live')), false);

		$this->assertSame(
			[
				['de', '/title/value/de', 'Bier', 'Bier', '/text/text-live'],
				['en', '/title/value/en', 'Beer', 'Beer', '/text/text-live'],
				['de', '/body/value/de/content/0', 'Klimaneutral gebraut.', 'Bier', '/text/text-live'],
			],
			array_map(
				static fn(Text $text): array => [
					$text->locale,
					$text->pointer,
					$text->value,
					$text->title,
					$text->path,
				],
				$texts,
			),
		);
		$this->assertSame('text-page', $texts[0]->type);
		$this->assertSame('body', $texts[2]->field);
		$this->assertSame(Text::id('text-live', 'body', 'de', 'Klimaneutral gebraut.', 1), $texts[2]->id);
	}

	public function testTheExportWritesPublishedNodesOnly(): void
	{
		$this->page('text-live', ['de' => 'Bier', 'en' => 'Beer'], ['de' => 'Klimaneutral gebraut.']);
		$this->page('text-offline', ['de' => 'Entwurf'], ['de' => 'Nachhaltig.'], published: false);
		$this->writer->create(
			$this->writer->prepare(TextSubmission::class, ['message' => 'Bitte zurückrufen'])->uid('text-record'),
		);

		$lines = $this->export();

		$this->assertSame(['text-live'], array_values(array_unique(array_column($lines, 'node'))));
		$this->assertSame(
			['id', 'node', 'type', 'title', 'path', 'field', 'locale', 'pointer', 'text'],
			array_keys($lines[0]),
		);
		$this->assertSame('Beer', $lines[1]['title']);
		$this->assertSame('/text/text-live', $lines[0]['path']);
		$this->assertSame(16, strlen($lines[0]['id']));
	}

	public function testTheExportSelectsOrLeavesOutTypes(): void
	{
		$this->page('text-live', ['de' => 'Bier'], ['de' => 'Gut.']);

		$this->assertSame([], $this->export(exclude: ['text-page']));
		$this->assertSame([], $this->export(type: ['text-setting']));
		$this->assertCount(2, $this->export(type: ['text-page']));
	}

	public function testAnEditSavesItsChangesAsOneDraftWithItsCause(): void
	{
		$this->page('text-beer', ['de' => 'Bier'], ['de' => 'Klimaneutral gebraut und natürlich gut.']);
		$edit = $this->edits()->open('text-beer');
		$this->assertNotNull($edit);
		$pointer = '/body/value/de/content/0';

		$edit->replace($pointer, 0, 20, 'Mit Ökostrom gebraut');
		$this->assertSame('Mit Ökostrom gebraut und natürlich gut.', $edit->text($pointer)?->value);
		$edit->replace($pointer, 25, 35, '');
		$edit->save(new Actor(1), ['source' => 'test', 'finding' => 'f1']);

		$this->assertSame('Mit Ökostrom gebraut und gut.', $this->paragraph('text-beer'));
		$this->assertSame('Klimaneutral gebraut und natürlich gut.', $this->paragraph('text-beer', live: true));
		$this->assertSame(
			[['drafted', 'test', 'f1']],
			array_map(
				static fn(array $row): array => [$row['action'], $row['source'], $row['finding']],
				$this->db()->execute(
					"SELECT e.action, e.data->>'source' AS source, e.data->>'finding' AS finding
					FROM cms.events e JOIN cms.nodes n USING (node)
					WHERE n.uid = 'text-beer' AND e.data->>'source' = 'test' ORDER BY e.event",
				)->all(),
			),
		);

		$this->throws(RuntimeException::class, 'open the node again');
		$edit->replace($pointer, 0, 3, 'Mit');
	}

	public function testATypeWithoutDraftsTakesTheEditInItsMainVersion(): void
	{
		$this->writer->create(
			$this->writer
				->prepare(TextSetting::class, [
					'title' => ['de' => 'Allgemein'],
					'slogan' => ['de' => 'Umweltfreundlich seit 1874'],
				])
				->uid('text-setting')
				->published(),
		);
		$edit = $this->edits()->open('text-setting');
		$this->assertNotNull($edit);

		$edit->replace('/slogan/value/de', 0, 16, 'Familiengeführt');
		$edit->save(new Actor(1));

		$live = $this->cms->node->byUid('text-setting', published: null);
		$this->assertNotNull($live);
		$this->assertSame(
			'Familiengeführt seit 1874',
			Factory::meta(Wrapper::unwrap($live), 'content')['slogan']['value']['de'],
		);
	}

	public function testAnEditFindsTheWorkingCopysTextsAndNothingWhereAFieldIsGone(): void
	{
		$this->page('text-moved', ['de' => 'Bier'], ['de' => 'Natürlich gebraut.']);
		$this->rewrite('text-moved', ['Neu oben.', 'Natürlich gebraut.']);
		$edit = $this->edits()->open('text-moved');
		$this->assertNotNull($edit);

		$this->assertSame('Natürlich gebraut.', $edit->text('/body/value/de/content/1')?->value);
		$this->assertNull($edit->text('/body/value/de/content/2'));
		$this->assertNull($edit->text('/body/value/en/content/0'));
		$this->assertNull($this->edits()->open('text-gone'));

		$this->throws(RuntimeException::class, 'No text at /body/value/de/content/2');
		$edit->replace('/body/value/de/content/2', 0, 1, 'x');
	}

	public function testAnEditWithoutChangesSavesNothing(): void
	{
		$this->page('text-same', ['de' => 'Bier'], ['de' => 'Gut.']);
		$before = $this->db()->execute('SELECT count(*) AS n FROM cms.revisions')->one()['n'];

		$this->edits()->open('text-same')?->save(new Actor(1));

		$this->assertSame($before, $this->db()->execute('SELECT count(*) AS n FROM cms.revisions')->one()['n']);
	}

	private function texts(): Texts
	{
		return new Texts($this->context, $this->cms, Services::withDefaults()->types);
	}

	private function edits(): Edits
	{
		return new Edits($this->context, $this->cms, Services::withDefaults()->types);
	}

	/**
	 * @param list<string> $type
	 * @param list<string> $exclude
	 * @return list<array<string, mixed>>
	 */
	private function export(array $type = [], array $exclude = []): array
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'texts');
		$command = new ExportTexts($this->cms, $this->texts());

		try {
			$this->assertSame(0, $command(new Io(new Buffer()), out: $file, type: $type, exclude: $exclude));
			$lines = array_filter(explode("\n", (string) file_get_contents($file)));
		} finally {
			unlink($file);
		}

		return array_values(array_map(static fn(string $line): array => json_decode($line, true), $lines));
	}

	/** @param list<string> $paragraphs */
	private function rewrite(string $uid, array $paragraphs): void
	{
		$node = $this->node($uid);
		$content = Factory::meta($node, 'content');
		$content['body']['value']['de']['content'] = array_map(
			static fn(string $text): array => [
				'type' => 'paragraph',
				'content' => [['type' => 'text', 'text' => $text]],
			],
			$paragraphs,
		);
		$this->store->amend($node, $content, $this->languages, new Actor(1));
	}

	private function paragraph(string $uid, int $index = 0, bool $live = false): string
	{
		$wrapper = $live ? $this->cms->node->byUid($uid, published: null) : $this->cms->node->working($uid);
		$this->assertNotNull($wrapper);
		$content = Factory::meta(Wrapper::unwrap($wrapper), 'content');

		return implode('', array_column($content['body']['value']['de']['content'][$index]['content'] ?? [], 'text'));
	}
}
