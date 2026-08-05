<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Migration\LegacyRichtextHtmlConverter;
use Cosray\Tests\TestCase;

final class LegacyRichtextHtmlConverterTest extends TestCase
{
	public function testEmptyInputNeedsNoConverterProcess(): void
	{
		$this->assertSame([], new LegacyRichtextHtmlConverter()->convert([]));
	}

	public function testConvertsLegacyHtmlWithoutPanelDependencies(): void
	{
		if (!$this->nodeAvailable()) {
			$this->markTestSkipped('Node is not available.');
		}

		$documents = new LegacyRichtextHtmlConverter()->convert([
			'intro' => '<p>Hello <strong>world</strong></p>',
			'empty' => '',
		]);

		$this->assertNull($documents['empty']);
		$this->assertSame(
			[
				'type' => 'doc',
				'content' => [
					[
						'type' => 'paragraph',
						'attrs' => ['class' => 'default'],
						'content' => [
							['type' => 'text', 'text' => 'Hello '],
							[
								'type' => 'text',
								'text' => 'world',
								'marks' => [['type' => 'bold']],
							],
						],
					],
				],
			],
			$documents['intro'],
		);
	}

	private function nodeAvailable(): bool
	{
		$process = proc_open(
			['node', '--version'],
			[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
		);

		if (!is_resource($process)) {
			return false;
		}

		foreach ($pipes as $pipe) {
			fclose($pipe);
		}

		return proc_close($process) === 0;
	}
}
