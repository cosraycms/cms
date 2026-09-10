<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Field\DateTime;
use Cosray\Migration\DateTimeConverter;
use Cosray\Tests\TestCase;

final class DateTimeConverterTest extends TestCase
{
	public function testConvertsNestedDateTimeFieldsWithoutReshapingTheirEnvelope(): void
	{
		$content = [
			'entries' => [
				'value' => [
					'zxx' => [[
						'fields' => [
							'observedAt' => [
								'type' => DateTime::class,
								'value' => ['zxx' => '2026-07-30 19:00:00'],
								'meta' => ['timezone' => ['zxx' => 'Europe/Berlin']],
								'custom' => 'kept',
							],
						],
					]],
				],
			],
		];

		$converted = new DateTimeConverter()->convert($content);
		$field = $converted['entries']['value']['zxx'][0]['fields']['observedAt'];

		$this->assertSame('2026-07-30T17:00:00Z', $field['value']['zxx']);
		$this->assertSame('kept', $field['custom']);
	}

	public function testRejectsInvalidValuesWithTheirContentPath(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('content.observedAt.value.zxx');

		new DateTimeConverter()->convert([
			'observedAt' => [
				'type' => DateTime::class,
				'value' => ['zxx' => 'not a date'],
			],
		]);
	}
}
