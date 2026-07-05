<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Richtext\Envelope;
use Cosray\Richtext\Validator;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RichtextValidatorTest extends TestCase
{
	public function testValidKitchenSinkDocument(): void
	{
		$validator = new Validator(
			classes: ['intro' => 'Intro'],
			styles: ['cms-text-xl' => 'Groß'],
		);

		$errors = $validator->validate([
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'attrs' => ['class' => 'intro', 'align' => 'center'],
					'content' => [
						['type' => 'text', 'text' => 'Der Sud kocht '],
						[
							'type' => 'text',
							'text' => 'schon wieder',
							'marks' => [
								['type' => 'bold'],
								['type' => 'style', 'attrs' => ['class' => 'cms-text-xl']],
							],
						],
						['type' => 'hardBreak'],
						['type' => 'image', 'attrs' => ['uid' => 'a1', 'meta' => ['alt' => 'Foto']]],
						[
							'type' => 'text',
							'text' => 'Link',
							'marks' => [[
								'type' => 'link',
								'attrs' => ['href' => 'https://example.com', 'target' => '_blank'],
							]],
						],
						[
							'type' => 'text',
							'text' => 'Seite',
							'marks' => [['type' => 'link', 'attrs' => ['node' => 'n1']]],
						],
						[
							'type' => 'text',
							'text' => 'Datei',
							'marks' => [['type' => 'link', 'attrs' => ['asset' => 'a2']]],
						],
					],
				],
				[
					'type' => 'heading',
					'attrs' => ['level' => 2],
					'content' => [['type' => 'text', 'text' => 'Titel']],
				],
				[
					'type' => 'bulletList',
					'content' => [
						[
							'type' => 'listItem',
							'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Eins']]]],
						],
					],
				],
				[
					'type' => 'orderedList',
					'attrs' => ['start' => 3],
					'content' => [
						[
							'type' => 'listItem',
							'content' => [
								['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Drei']]],
								['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => []]]],
							],
						],
					],
				],
				['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'echo 1;']]],
				['type' => 'horizontalRule', 'attrs' => ['class' => 'fancy']],
			],
		]);

		$this->assertSame([], $errors);
	}

	public function testRootMustBeDoc(): void
	{
		$validator = new Validator();

		$this->assertSame(['doc: not a node object'], $validator->validate('nope'));
		$this->assertSame(
			["doc: root node must have type 'doc'"],
			$validator->validate(['type' => 'paragraph']),
		);
	}

	public function testEmptyDocIsRejected(): void
	{
		$validator = new Validator();

		$this->assertSame(
			["doc: 'doc' must not be empty"],
			$validator->validate(['type' => 'doc', 'content' => []]),
		);
	}

	public function testUnknownNodeAndMarkTypes(): void
	{
		$validator = new Validator();
		$errors = $validator->validate([
			'type' => 'doc',
			'content' => [
				['type' => 'div', 'content' => []],
				[
					'type' => 'paragraph',
					'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'fontSize']]]],
				],
			],
		]);

		$this->assertContains("doc.content.0: unknown node type 'div'", $errors);
		$this->assertContains("doc.content.0: 'div' not allowed in 'doc'", $errors);
		$this->assertContains("doc.content.1.content.0.marks.0: unknown mark type 'fontSize'", $errors);
	}

	public function testUndeclaredClassesAreRejected(): void
	{
		$validator = new Validator();
		$errors = $validator->validate([
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'attrs' => ['class' => 'intro'],
					'content' => [
						[
							'type' => 'text',
							'text' => 'x',
							'marks' => [['type' => 'style', 'attrs' => ['class' => 'big']]],
						],
					],
				],
			],
		]);

		$this->assertContains("doc.content.0: undeclared paragraph class 'intro'", $errors);
		$this->assertContains("doc.content.0.content.0.marks.0: undeclared text style 'big'", $errors);
	}

	public function testDefaultParagraphClassIsImplicit(): void
	{
		$validator = new Validator();
		$errors = $validator->validate([
			'type' => 'doc',
			'content' => [['type' => 'paragraph', 'attrs' => ['class' => 'default'], 'content' => []]],
		]);

		$this->assertSame([], $errors);
	}

	public function testAttributeChecks(): void
	{
		$validator = new Validator();
		$errors = $validator->validate([
			'type' => 'doc',
			'content' => [
				['type' => 'heading', 'attrs' => ['level' => 7], 'content' => []],
				['type' => 'paragraph', 'attrs' => ['align' => 'top'], 'content' => []],
				[
					'type' => 'orderedList',
					'attrs' => ['start' => 0],
					'content' => [
						['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => []]]],
					],
				],
				['type' => 'paragraph', 'attrs' => ['indent' => 2], 'content' => []],
				['type' => 'image', 'attrs' => ['uid' => '', 'meta' => ['size' => 'xl']]],
			],
		]);

		$this->assertContains('doc.content.0: heading level must be an int between 1 and 6', $errors);
		$this->assertContains("doc.content.1: invalid align 'top'", $errors);
		$this->assertContains('doc.content.2: orderedList start must be an int >= 1', $errors);
		$this->assertContains("doc.content.3: unknown attr 'indent'", $errors);
		$this->assertContains('doc.content.4: image needs a non-empty uid', $errors);
		$this->assertContains("doc.content.4: unknown image meta key 'size'", $errors);
		$this->assertContains("doc.content.4: 'image' not allowed in 'doc'", $errors);
	}

	public function testMarkConstraints(): void
	{
		$validator = new Validator();
		$errors = $validator->validate([
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [
						[
							'type' => 'text',
							'text' => 'x',
							'marks' => [
								['type' => 'bold'],
								['type' => 'bold'],
								['type' => 'subscript'],
								['type' => 'superscript'],
								['type' => 'link', 'attrs' => ['href' => 'https://x.de', 'node' => 'n1']],
								['type' => 'link', 'attrs' => []],
							],
						],
					],
				],
			],
		]);

		$path = 'doc.content.0.content.0';
		$this->assertContains("{$path}.marks.1: duplicate mark 'bold'", $errors);
		$this->assertContains("{$path}: subscript and superscript exclude each other", $errors);
		$this->assertContains("{$path}.marks.4: link needs exactly one of href/node/asset", $errors);
		$this->assertContains("{$path}.marks.5: duplicate mark 'link'", $errors);
		$this->assertContains("{$path}.marks.5: link needs exactly one of href/node/asset", $errors);
	}

	public function testContentModels(): void
	{
		$validator = new Validator();
		$errors = $validator->validate([
			'type' => 'doc',
			'content' => [
				['type' => 'bulletList', 'content' => []],
				[
					'type' => 'bulletList',
					'content' => [
						[
							'type' => 'listItem',
							'content' => [['type' => 'heading', 'attrs' => ['level' => 2], 'content' => []]],
						],
					],
				],
				[
					'type' => 'codeBlock',
					'content' => [
						['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'bold']]],
					],
				],
				[
					'type' => 'paragraph',
					'content' => [
						['type' => 'paragraph', 'content' => []],
					],
				],
			],
		]);

		$this->assertContains("doc.content.0: 'bulletList' must not be empty", $errors);
		$this->assertContains('doc.content.1.content.0: listItem must start with a paragraph', $errors);
		$this->assertContains('doc.content.2.content.0: codeBlock content allows no marks', $errors);
		$this->assertContains("doc.content.3.content.0: 'paragraph' not allowed in 'paragraph'", $errors);
	}

	public function testUnknownKeysAreRejected(): void
	{
		$validator = new Validator();
		$errors = $validator->validate([
			'type' => 'doc',
			'content' => [
				['type' => 'paragraph', 'content' => [], 'html' => '<b>x</b>'],
				['type' => 'text', 'text' => ''],
			],
		]);

		$this->assertContains("doc.content.0: unknown key 'html'", $errors);
		$this->assertContains("doc.content.1: 'text' not allowed in 'doc'", $errors);
		$this->assertContains("doc.content.1: text node needs a non-empty string 'text'", $errors);
	}

	public function testEnvelope(): void
	{
		$this->assertSame('html', Envelope::format([]));
		$this->assertSame('html', Envelope::format(['format' => '']));
		$this->assertSame('cosray-richtext', Envelope::format(['format' => 'cosray-richtext']));
		$this->assertFalse(Envelope::isStructured(['value' => ['zxx' => '<p>x</p>']]));
		$this->assertTrue(Envelope::isStructured(['format' => Envelope::FORMAT, 'version' => 1]));
	}
}
