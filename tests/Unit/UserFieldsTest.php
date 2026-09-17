<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Field\Text;
use Cosray\Locales;
use Cosray\Schema\Translate;
use Cosray\Tests\Fixtures\User\Teacher;
use Cosray\Tests\TestCase;
use Cosray\User;
use Cosray\User\Fields;

final class UserFieldsTest extends TestCase
{
	public function testStoredContentReachesTheModelAndTheForm(): void
	{
		$teacher = new Teacher($this->row([
			'name' => 'Ada',
			'content' => ['subject' => ['type' => 'text', 'value' => ['zxx' => 'Maths']]],
		]));
		$form = $this->fields()->form($teacher);

		$this->assertSame('Maths', $teacher->subject());
		$this->assertSame(['subject', 'room'], array_column($form['fields'], 'name'));
		$this->assertSame('Maths', $form['content']['subject']['value']['zxx']);
		$this->assertArrayHasKey('room', $form['content']);
	}

	public function testRequiredFieldsAreValidated(): void
	{
		$teacher = new Teacher($this->row([]));
		$fields = $this->fields();

		$subject = $fields->form($teacher)['content']['subject'];

		$missing = $fields->validate($teacher, ['subject' => $subject]);
		$given = $fields->validate($teacher, ['subject' => ['value' => ['zxx' => 'Maths']] + $subject]);

		$this->assertFalse($missing->valid());
		$this->assertSame(['content', 'subject'], array_slice($missing->issues()[0]->path, 0, 2));
		$this->assertTrue($given->valid());
	}

	public function testAUserWithoutDeclaredFieldsHasAnEmptyForm(): void
	{
		$form = $this->fields()->form(new User($this->row(['name' => 'Ada'])));

		$this->assertSame(['fields' => [], 'fieldsets' => [], 'content' => []], $form);
	}

	public function testTranslatedFieldsAreRejected(): void
	{
		$user = new class($this->row([])) extends User {
			#[Translate]
			protected Text $motto;
		};

		$this->throws(RuntimeException::class, 'must not be translated');

		$this->fields()->hydrate($user);
	}

	private function fields(): Fields
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');

		return new Fields(
			Services::withDefaults(),
			Context::console($this->db(), $this->config(), $this->container(), $this->factory(), $locales),
		);
	}

	/** @param array<string, mixed> $data */
	private function row(array $data): array
	{
		return [
			'usr' => 42,
			'uid' => 'someone',
			'email' => 'someone@example.com',
			'password' => 'hash',
			'roles' => [],
			'active' => true,
			'data' => $data,
			'created' => '2024-01-01T00:00:00+00:00',
			'changed' => '2024-01-01T00:00:00+00:00',
			'deleted' => null,
		];
	}
}
