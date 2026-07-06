<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field\RichText;
use Cosray\Field\Services;
use Cosray\Richtext\Envelope;
use Cosray\Tests\RichtextOwnerTestCase;
use Cosray\Value\RichText as RichTextValue;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class RichtextValueTest extends RichtextOwnerTestCase
{
	private function value(array $data): RichTextValue
	{
		$owner = $this->owner();
		$field = new RichText('body', $owner, new ValueContext('body', $data));
		$field->init(Services::withDefaults());
		$field->translate();

		return $field->value();
	}

	public function testRendersStructuredDocuments(): void
	{
		$value = $this->value([
			'type' => RichText::class,
			'format' => Envelope::FORMAT,
			'version' => 1,
			'value' => [
				'en' => [
					'type' => 'doc',
					'content' => [
						[
							'type' => 'paragraph',
							'content' => [
								['type' => 'text', 'text' => 'Bold ', 'marks' => [['type' => 'bold']]],
								['type' => 'text', 'text' => '& plain'],
							],
						],
					],
				],
				'de' => null,
			],
		]);

		$this->assertSame('<p><strong>Bold </strong>&amp; plain</p>', (string) $value);
		$this->assertNotNull($value->doc());
		$this->assertTrue($value->isset());
	}

	public function testEmptyStructuredValueRendersEmpty(): void
	{
		$value = $this->value([
			'type' => RichText::class,
			'format' => Envelope::FORMAT,
			'version' => 1,
			'value' => ['en' => null, 'de' => null],
		]);

		$this->assertSame('', (string) $value);
		$this->assertNull($value->doc());
		$this->assertFalse($value->isset());
	}

	public function testFormatlessValuesRenderEmpty(): void
	{
		$value = $this->value([
			'type' => RichText::class,
			'value' => ['en' => '<p>unmigrated legacy html</p>', 'de' => null],
		]);

		$this->assertNull($value->doc());
		$this->assertSame('', (string) $value);
		$this->assertFalse($value->isset());
	}
}
