<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Cosray\User;
use Cosray\Users;
use Psr\Http\Message\ResponseInterface;

/**
 * The users area: who may manage whom, and what a save may change.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelUsersTest extends End2EndTestCase
{
	private const string PASSWORD = 'correct horse battery';

	public function testEditorsAreKeptOut(): void
	{
		$this->authenticateAs('editor');

		$this->assertResponseStatus(403, $this->makeRequest('GET', '/cp/users'));
	}

	public function testTheListShowsUsersAndTheirTypes(): void
	{
		$this->authenticateAs('superuser');
		$this->save('/cp/users/create/teacher', ['email' => 'ada@example.com', 'name' => 'Ada Lovelace']);

		$this->assertResponseOk($this->makeRequest('GET', '/cp/users'));

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/cp/users', ['query' => ['q' => 'lovelace']]));

		$this->assertStringContainsString('Ada Lovelace', $html);
		$this->assertStringContainsString('Teacher', $html);
		$this->assertStringNotContainsString('system@cosray.dev', $html);
	}

	public function testATypedUserIsCreatedWithItsFieldsAndAssignableRoles(): void
	{
		$this->authenticateAs('superuser');

		$response = $this->save('/cp/users/create/teacher', [
			'email' => 'ada@example.com',
			'username' => 'ada',
			'roles' => ['editor', 'superuser'],
		]);
		$user = $this->users()->byLogin('ada');

		$this->assertResponseStatus(303, $response);
		$this->assertSame('teacher', $user->type);
		$this->assertSame(['editor'], $user->roles, 'a teacher can only become an editor');
		$this->assertSame('Maths', $user->content()['subject']['value']['zxx']);
		$this->assertTrue(password_verify(self::PASSWORD, $user->password));
	}

	public function testInvalidSubmissionsAreRejectedWithTheFieldTheyConcern(): void
	{
		$this->authenticateAs('superuser');
		$this->save('/cp/users/create/user', ['email' => 'taken@example.com']);

		$html = $this->getHtmlResponse($this->save('/cp/users/create/teacher', [
			'email' => 'TAKEN@example.com',
			'username' => 'with@sign',
			'password_repeat' => 'something else entirely',
			'content' => ['subject' => ['value' => ['zxx' => '']]],
		]));

		$this->assertStringNotContainsString('data-saved', $html);
		$this->assertStringContainsString('["email"]', $html);
		$this->assertStringContainsString('["username"]', $html);
		$this->assertStringContainsString('["password_repeat"]', $html);
		$this->assertStringContainsString('["content","subject"', $html);
		$this->assertSame('user', $this->users()->byLogin('taken@example.com')->type);
	}

	public function testACustomerTypeTakesNoRoles(): void
	{
		$this->authenticateAs('superuser');

		$this->save('/cp/users/create/shop-customer', ['email' => 'buyer@example.com', 'roles' => ['editor']]);

		$this->assertSame([], $this->users()->byLogin('buyer@example.com')->roles);
	}

	public function testAnAdminCannotReachBeyondTheirOwnPermissions(): void
	{
		$this->authenticateAs('admin');
		$superuser = $this->users()->byId($this->createTestUserWith(['superuser']));
		$editor = $this->users()->byId($this->createTestUserWith(['editor']));

		$this->assertResponseStatus(403, $this->makeRequest('GET', "/cp/users/{$superuser->uid}"));
		$this->assertResponseStatus(403, $this->save("/cp/users/{$superuser->uid}", ['email' => 'mine@example.com']));

		$this->save("/cp/users/{$editor->uid}", [
			'email' => $editor->email,
			'password' => '',
			'password_repeat' => '',
			'roles' => ['admin', 'superuser'],
		]);

		$this->assertSame(['admin'], $this->users()->find($editor->uid)->roles);
	}

	public function testNobodyChangesTheirOwnAccessOrDeletesThemselves(): void
	{
		$this->authenticateAs('superuser');
		$self = $this->currentUser();

		$this->save("/cp/users/{$self->uid}", [
			'email' => $self->email,
			'name' => 'Renamed',
			'password' => '',
			'password_repeat' => '',
			'roles' => [],
			'active' => '0',
		]);
		$delete = $this->getHtmlResponse($this->makeRequest('POST', "/cp/users/{$self->uid}/delete"));
		$saved = $this->users()->find($self->uid);

		$this->assertSame('Renamed', $saved->name);
		$this->assertSame(['superuser'], $saved->roles);
		$this->assertTrue($saved->active);
		$this->assertStringNotContainsString('data-saved', $delete);
	}

	public function testAPasswordChangeTakesEffectAndDeletedUsersAreGone(): void
	{
		$this->authenticateAs('superuser');
		$editor = $this->users()->byId($this->createTestUserWith(['editor']));

		$this->save("/cp/users/{$editor->uid}", [
			'email' => $editor->email,
			'password' => 'a brand new long password',
			'password_repeat' => 'a brand new long password',
			'roles' => ['editor'],
		]);

		$this->assertTrue(password_verify('a brand new long password', $this->users()->find($editor->uid)->password));

		$this->assertResponseStatus(303, $this->makeRequest('POST', "/cp/users/{$editor->uid}/delete"));
		$this->assertNull($this->users()->find($editor->uid));
	}

	/** @param array<string, mixed> $form */
	private function save(string $uri, array $form): ResponseInterface
	{
		return $this->makeRequest('POST', $uri, [
			'headers' => ['HX-Request' => 'true'],
			'body' => $form
				+ [
					'password' => self::PASSWORD,
					'password_repeat' => self::PASSWORD,
					'active' => '1',
					'content' => ['subject' => ['value' => ['zxx' => 'Maths']]],
					'_complete' => '1',
				],
		]);
	}

	/** @param list<string> $roles */
	private function createTestUserWith(array $roles): int
	{
		$uid = uniqid('user-');

		return (int) $this->db()->execute(
			"INSERT INTO cms.users (uid, email, password, roles, active, data, creator, editor)
			VALUES (:uid, :email, 'x', ARRAY(SELECT jsonb_array_elements_text(:roles::jsonb)), true, '{}'::jsonb, 1, 1)
			RETURNING usr",
			['uid' => $uid, 'email' => "{$uid}@example.com", 'roles' => json_encode($roles)],
		)->one()['usr'];
	}

	private function currentUser(): User
	{
		return $this->users()->byAuthToken((string) $this->defaultAuthToken);
	}

	private function users(): Users
	{
		return new Users($this->db());
	}
}
