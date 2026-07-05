<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Richtext\Scanner;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RichtextScannerTest extends TestCase
{
	public function testCollectsAllReferenceCarriers(): void
	{
		$refs = Scanner::scan([
			'type' => 'doc',
			'content' => [
				[
					'type' => 'paragraph',
					'content' => [
						['type' => 'image', 'attrs' => ['uid' => 'img1']],
						[
							'type' => 'text',
							'text' => 'a',
							'marks' => [['type' => 'link', 'attrs' => ['asset' => 'doc1']]],
						],
						[
							'type' => 'text',
							'text' => 'b',
							'marks' => [['type' => 'link', 'attrs' => ['node' => 'n1']]],
						],
						[
							'type' => 'text',
							'text' => 'c',
							'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://x.de']]],
						],
					],
				],
				[
					'type' => 'blockquote',
					'content' => [
						[
							'type' => 'paragraph',
							'content' => [
								['type' => 'image', 'attrs' => ['uid' => 'img1']],
								[
									'type' => 'text',
									'text' => 'd',
									'marks' => [['type' => 'link', 'attrs' => ['node' => 'n2']]],
								],
							],
						],
					],
				],
			],
		]);

		$this->assertSame(['assets' => ['img1', 'doc1'], 'nodes' => ['n1', 'n2']], $refs);
	}

	public function testToleratesGarbage(): void
	{
		$this->assertSame(['assets' => [], 'nodes' => []], Scanner::scan(null));
		$this->assertSame(
			['assets' => [], 'nodes' => []],
			Scanner::scan(['type' => 'doc', 'content' => ['x', 1]]),
		);
	}
}
