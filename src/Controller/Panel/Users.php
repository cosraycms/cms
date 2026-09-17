<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Celema\Sire\Issue;
use Cosray\Actor;
use Cosray\Config;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Middleware\Permission;
use Cosray\Panel\FormPatch;
use Cosray\Panel\System;
use Cosray\Security\Policy;
use Cosray\Session;
use Cosray\Uid;
use Cosray\User;
use Cosray\User\Fields;
use Cosray\User\Types;
use Cosray\Users as UserStore;
use Cosray\Validation\Account;

final class Users extends Panel
{
	protected const string AREA = 'users';

	private const int PAGE_SIZE = 50;

	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly UserStore $users,
		private readonly Types $types,
		private readonly Policy $policy,
	) {
		parent::__construct($config, $container, $request);
	}

	#[Permission('edit-users')]
	public function index(): array
	{
		$type = $this->request->param('type', '');
		$type = is_string($type) && $this->types->has($type) ? $type : null;
		$search = $this->request->param('q', '');
		$search = is_string($search) ? trim($search) : '';
		$offset = max(0, (int) $this->request->param('offset', 0));
		$total = $this->users->count($type, $search);
		$users = $this->users->list($type, $search, self::PAGE_SIZE, $offset);
		$filter = array_filter(
			['type' => $type, 'q' => $search],
			static fn(?string $value): bool => (
				$value !== null
				&& $value !== ''
			),
		);
		$page = fn(int $to): string => $this->url('', [...$filter, 'offset' => $to]);

		return $this->context([
			'rows' => array_map($this->listRow(...), $users),
			'types' => $this->typeLinks($type, $search),
			'search' => $search,
			'type' => $type,
			'total' => $total,
			'rangeStart' => $total === 0 ? 0 : $offset + 1,
			'rangeEnd' => $offset + count($users),
			'previousUrl' => $offset > 0 ? $page(max(0, $offset - self::PAGE_SIZE)) : null,
			'nextUrl' => ($offset + self::PAGE_SIZE) < $total ? $page($offset + self::PAGE_SIZE) : null,
			'listUrl' => $this->url(),
			'notice' => $this->notice(),
		]);
	}

	#[Permission('edit-users')]
	public function create(Context $context, string $type): array
	{
		return $this->form($context, $this->blank($type), false);
	}

	#[Permission('edit-users')]
	public function store(Context $context, Factory $factory, string $type): array|Response
	{
		[$values, $password, $errors] = $this->submitted($this->fields($context), $this->blank($type), null);

		if ($errors !== []) {
			return $this->rejected($errors);
		}

		$user = $this->users->create($type, $values, (string) $password, $this->actor());

		return Response::create($factory)->redirect($this->url('/' . $user->uid, ['notice' => 'created']), 303);
	}

	#[Permission('edit-users')]
	public function edit(Context $context, string $uid): array
	{
		return $this->form($context, $this->editable($uid), true);
	}

	#[Permission('edit-users')]
	public function update(Context $context, string $uid): array
	{
		return $this->save($context, $this->editable($uid), false);
	}

	/** Every panel user's own account; it needs no `edit-users`. */
	public function profile(Context $context): array
	{
		return $this->form($context, $this->currentUser(), true, true);
	}

	public function saveProfile(Context $context): array
	{
		return $this->save($context, $this->currentUser(), true);
	}

	private function save(Context $context, User $user, bool $profile): array
	{
		[$values, $password, $errors] = $this->submitted($this->fields($context), $user, $user);
		$current = $this->formData()['current_password'] ?? '';

		if ($profile && $password !== null && !password_verify(is_string($current) ? $current : '', $user->password)) {
			$errors[] = ['message' => __('user:error-current-password'), 'path' => ['current_password']];
		}

		if ($errors !== []) {
			return $this->rejected($errors);
		}

		$this->users->update($user, $values, $this->actor());

		if ($password !== null) {
			$this->users->setPassword($user, $password, $this->actor());
			$this->keepOwnSession($user);
		}

		return ['saved' => true, 'message' => __('editor:saved'), 'errors' => []];
	}

	#[Permission('edit-users')]
	public function delete(Factory $factory, string $uid): array|Response
	{
		$user = $this->editable($uid);

		if ($this->isSelf($user)) {
			return $this->rejected([['message' => __('user:error-delete-self'), 'path' => []]]);
		}

		if ($this->users->isLastSuperuser($user)) {
			return $this->rejected([['message' => __('user:error-last-superuser'), 'path' => []]]);
		}

		$this->users->delete($user, $this->actor());

		return Response::create($factory)->redirect($this->url('', ['notice' => 'deleted']), 303);
	}

	private function form(Context $context, User $user, bool $exists, bool $profile = false): array
	{
		$locales = array_map(
			static fn($locale) => ['id' => $locale->id, 'title' => $locale->title, 'fallback' => $locale->fallback],
			iterator_to_array($context->locales(), false),
		);
		$self = $this->isSelf($user);

		return $this->context([
			'area' => $profile ? 'profile' : self::AREA,
			'profile' => $profile,
			'exists' => $exists,
			'action' => match (true) {
				$profile => $this->panelPath() . '/profile',
				$exists => $this->url('/' . $user->uid),
				default => $this->url('/create/' . $user->type),
			},
			'deleteUrl' => $exists && !$self ? $this->url('/' . $user->uid . '/delete') : null,
			'listUrl' => $this->url(),
			'title' => $exists
				? $user->name ?? ($user->username !== '' ? $user->username : $user->email)
				: __('user:new', ['type' => __($this->types->label($user->type))]),
			'typeLabel' => __($this->types->label($user->type)),
			'user' => $user,
			'roles' => $this->roleChoices($user),
			'locked' => $self,
			'panelLocaleChoices' => $this->panelLocales(),
			'fields' => $this->fields($context)->form($user),
			'locales' => $locales,
			'defaultLocale' => $context->locales()->getDefault()->id,
			'system' => new System($this->config, $context->locales())->payload(),
			'notice' => $this->notice(),
		]);
	}

	/**
	 * The submitted account and fields, validated against the stored user.
	 *
	 * @return array{array<string, mixed>, ?string, list<array{message: string, path: list<string|int>}>}
	 */
	private function submitted(Fields $fields, User $model, ?User $stored): array
	{
		$form = $this->formData();

		// A submission cut short by max_input_vars would drop field content.
		if (($form['_complete'] ?? null) !== '1') {
			return [[], null, [['message' => __('editor:incomplete-form'), 'path' => []]]];
		}

		$text = static function (string $key) use ($form): ?string {
			$value = is_string($form[$key] ?? null) ? trim($form[$key]) : '';

			return $value === '' ? null : $value;
		};
		$password = is_string($form['password'] ?? null) && $form['password'] !== '' ? $form['password'] : null;
		$account = [
			'email' => $text('email'),
			'username' => $text('username'),
			'name' => $text('name'),
			'password' => $password,
		];
		$errors = $this->issues(
			new Account($stored === null)
				->validate($account)
				->issues(),
		);

		if ($password !== null && !hash_equals($password, (string) ($form['password_repeat'] ?? ''))) {
			$errors[] = ['message' => __('user:error-password-repeat'), 'path' => ['password_repeat']];
		}

		if ($account['email'] !== null) {
			$taken = $this->users->taken($account['email'], $account['username'], $stored);

			if ($taken['email']) {
				$errors[] = ['message' => __('user:error-email-taken'), 'path' => ['email']];
			}

			if ($taken['username']) {
				$errors[] = ['message' => __('user:error-username-taken'), 'path' => ['username']];
			}
		}

		$roles = $stored?->roles ?? [];
		$active = $stored?->active ?? true;

		if ($stored === null || !$this->isSelf($stored)) {
			$roles = $this->submittedRoles($model, $form['roles'] ?? []);
			$active = ($form['active'] ?? null) === '1';
		}

		if (
			$stored !== null
			&& $this->users->isLastSuperuser($stored)
			&& (
				!$active
				|| !in_array('superuser', $roles, true)
			)
		) {
			$errors[] = ['message' => __('user:error-last-superuser'), 'path' => ['roles']];
		}

		$panelLocale = $text('panel_locale');
		$shape = $fields->form($model);
		$content = new FormPatch($shape['fields'])->content(
			$shape['content'],
			is_array($form['content'] ?? null) ? $form['content'] : [],
		);

		return [
			[
				'email' => (string) $account['email'],
				'username' => $account['username'],
				'name' => $account['name'],
				'roles' => $roles,
				'active' => $active,
				'panelLocale' => isset($this->panelLocales()[$panelLocale ?? '']) ? $panelLocale : null,
				'content' => $content,
			],
			$password,
			[...$errors, ...$this->issues($fields->validate($model, $content)->issues())],
		];
	}

	/**
	 * Roles the actor may hand out replace the ones submitted; roles the
	 * user holds beyond those stay untouched.
	 *
	 * @return list<string>
	 */
	private function submittedRoles(User $user, mixed $submitted): array
	{
		$assignable = array_keys(array_filter(array_column($this->roleChoices($user), 'assignable', 'name')));
		$submitted = is_array($submitted) ? $submitted : [];

		return [
			...array_values(array_diff($user->roles, $assignable)),
			...array_values(array_intersect($assignable, $submitted)),
		];
	}

	/** @return list<array{name: string, label: string, held: bool, assignable: bool}> */
	private function roleChoices(User $user): array
	{
		$actor = $this->currentUser();
		$choices = [];

		foreach ($this->types->roles($user->type, $this->policy) as $name => $label) {
			$choices[] = [
				'name' => $name,
				'label' => $this->roleLabel($name, $label),
				'held' => in_array($name, $user->roles, true),
				'assignable' => $this->policy->covers($actor, $name),
			];
		}

		return $choices;
	}

	private function fields(Context $context): Fields
	{
		return new Fields($this->container->get(Services::class), $context);
	}

	private function editable(string $uid): User
	{
		$user = $this->users->find($uid) ?? throw new HttpNotFound($this->request);

		if (!$this->policy->covers($this->currentUser(), ...$user->roles)) {
			throw new HttpForbidden($this->request);
		}

		return $user;
	}

	private function blank(string $type): User
	{
		if (!$this->types->has($type)) {
			throw new HttpNotFound($this->request);
		}

		return new ($this->types->class($type))([
			'usr' => 0,
			'uid' => new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13)->generate(),
			'type' => $type,
			'email' => '',
			'password' => '',
			'active' => true,
			'created' => '',
			'changed' => '',
			'deleted' => null,
		]);
	}

	/** Changing one's own password must not end the session doing it. */
	private function keepOwnSession(User $user): void
	{
		$session = $this->request->get('session', null);
		$fresh = $this->users->find($user->uid);

		if ($this->isSelf($user) && $session instanceof Session && $fresh !== null) {
			$session->setUser($fresh);
		}
	}

	/** @return array{saved: false, message: string, errors: list<array{message: string, path: list<string|int>}>} */
	private function rejected(array $errors): array
	{
		return ['saved' => false, 'message' => __('node:invalid-data'), 'errors' => $errors];
	}

	/**
	 * @param list<Issue> $issues
	 * @return list<array{message: string, path: list<string|int>}>
	 */
	private function issues(array $issues): array
	{
		return array_map(
			static fn(Issue $issue): array => ['message' => $issue->message, 'path' => $issue->path],
			$issues,
		);
	}

	/** @return array{uid: string, title: string, email: string, type: string, roles: list<string>, active: bool, url: ?string} */
	private function listRow(User $user): array
	{
		$labels = $this->policy->roles();

		return [
			'uid' => $user->uid,
			'title' => $user->name ?? ($user->username !== '' ? $user->username : $user->email),
			'email' => $user->email,
			'type' => __($this->types->label($user->type)),
			'roles' => array_map(fn(string $role): string => $this->roleLabel(
				$role,
				$labels[$role] ?? $role,
			), $user->roles),
			'active' => $user->active,
			'url' => $this->policy->covers($this->currentUser(), ...$user->roles)
				? $this->url('/' . $user->uid)
				: null,
		];
	}

	/** @return list<array{label: string, filterUrl: string, createUrl: string, active: bool}> */
	private function typeLinks(?string $current, string $search): array
	{
		return array_map(fn(string $handle): array => [
			'label' => __($this->types->label($handle)),
			'filterUrl' => $this->url('', array_filter(['type' => $handle, 'q' => $search])),
			'createUrl' => $this->url('/create/' . $handle),
			'active' => $handle === $current,
		], $this->types->handles());
	}

	/** The shipped roles translate here; an app's roles through its own catalog. */
	private function roleLabel(string $name, string $label): string
	{
		return match ($name) {
			'superuser' => __('user:role-superuser'),
			'admin' => __('user:role-admin'),
			'editor' => __('user:role-editor'),
			default => __($label),
		};
	}

	private function notice(): ?string
	{
		return match ($this->request->param('notice', '')) {
			'created' => __('user:notice-created'),
			'deleted' => __('user:notice-deleted'),
			default => null,
		};
	}

	private function url(string $path = '', array $query = []): string
	{
		$url = $this->panelPath() . '/users' . $path;

		return $query === [] ? $url : $url . '?' . http_build_query($query);
	}

	private function isSelf(User $user): bool
	{
		return $user->id === $this->currentUser()->id;
	}

	private function currentUser(): User
	{
		$user = $this->request->get('user', null);

		return $user instanceof User ? $user : throw new HttpForbidden($this->request);
	}

	private function actor(): Actor
	{
		return new Actor($this->currentUser()->id);
	}
}
