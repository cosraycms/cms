<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field\RichLine;
use Cosray\Field\Services;
use Cosray\Richtext\Envelope;
use Cosray\Schema\Tool;
use Cosray\Tests\RichtextOwnerTestCase;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class RichLineTest extends RichtextOwnerTestCase
{
	private function field(array $data = []): RichLine
	{
		$field = new RichLine('subtitle', $this->owner(), new ValueContext('subtitle', $data));
		$field->init(Services::withDefaults());

		return $field;
	}

	private static function envelope(?array $doc): array
	{
		return [
			'type' => RichLine::class,
			'format' => Envelope::FORMAT,
			'version' => Envelope::VERSION,
			'value' => ['zxx' => $doc],
		];
	}

	private static function line(array ...$content): array
	{
		return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => $content]]];
	}

	public function testRendersMarkedTextWithoutAParagraph(): void
	{
		$value = $this->field(self::envelope(self::line(
			['type' => 'text', 'text' => 'Fresh ', 'marks' => [['type' => 'italic']]],
			['type' => 'text', 'text' => 'beer & more'],
		)))->value();

		$this->assertSame('<em>Fresh </em>beer &amp; more', (string) $value);
		$this->assertSame(['html' => '<em>Fresh </em>beer &amp; more'], $value->json());
		$this->assertSame('', (string) $this->field(self::envelope(null))->value());
	}

	public function testAcceptsOneParagraphOfText(): void
	{
		$shape = $this->field()->shape();

		$this->assertTrue(
			$shape
				->validate(self::envelope(self::line(
					['type' => 'text', 'text' => 'Bold', 'marks' => [['type' => 'bold']]],
				)))
				->valid(),
		);
		// The default paragraph attributes are no structure.
		$this->assertTrue(
			$shape
				->validate(self::envelope([
					'type' => 'doc',
					'content' => [[
						'type' => 'paragraph',
						'attrs' => ['class' => 'default'],
						'content' => [
							['type' => 'text', 'text' => 'Plain'],
						],
					]],
				]))
				->valid(),
		);
	}

	public function testRefusesStructure(): void
	{
		$shape = $this->field()->shape();
		$paragraph = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Text']]];

		foreach ([
			'two paragraphs' => ['type' => 'doc', 'content' => [$paragraph, $paragraph]],
			'a heading' => [
				'type' => 'doc',
				'content' => [
					['type' => 'heading', 'attrs' => ['level' => 2], 'content' => $paragraph['content']],
				],
			],
			'a line break' => self::line(
				['type' => 'text', 'text' => 'One'],
				['type' => 'hardBreak'],
				[
					'type' => 'text',
					'text' => 'two',
				],
			),
			'an alignment' => ['type' => 'doc', 'content' => [['attrs' => ['align' => 'center'], ...$paragraph]]],
		] as $case => $doc) {
			$this->assertFalse($shape->validate(self::envelope($doc))->valid(), $case);
		}
	}

	public function testOffersOnlyTheToolsThatMarkText(): void
	{
		$field = $this->field();

		$this->assertTrue($field->properties()['line']);
		$this->assertSame(
			array_map(static fn(Tool $tool): string => $tool->value, Tool::MINIMAL),
			$field->getTools(),
		);

		$field->tools(...Tool::DEFAULT);

		$this->assertSame(['undo', 'redo', 'bold', 'italic', 'strike', 'link'], $field->getTools());
	}
}
