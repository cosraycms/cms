<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Field\Schema\Handler;
use Celemas\Cms\Field\Text;
use Celemas\Cms\Node\FieldOwner;
use Celemas\Cms\Tests\TestCase;
use Celemas\Cms\Value\ValueContext;

final class CapabilityFunctionsTest extends TestCase
{
	private function createTextField(string $name = 'test'): Text
	{
		$context = new \Celemas\Cms\Context(
			$this->db(),
			$this->request(),
			$this->config(),
			$this->container(),
			$this->factory(),
		);

		$owner = new FieldOwner($context, 'test-node');

		return new Text($name, $owner, new ValueContext($name, []));
	}

	public function testCapabilityErrorMessage(): void
	{
		$field = $this->createTextField('title');

		// Create a concrete handler to test the protected method
		$handler = new class extends Handler {
			public function apply(object $meta, $field): void {}

			public function properties(object $meta, $field): array
			{
				return [];
			}

			public function testErrorMessage($field, string $capability): string
			{
				return $this->capabilityErrorMessage($field, $capability);
			}
		};

		$message = $handler->testErrorMessage($field, Handler::class);

		$this->assertStringContainsString('title', $message);
		$this->assertStringContainsString(Text::class, $message);
		$this->assertStringContainsString(Handler::class, $message);
	}
}
