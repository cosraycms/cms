<?php

declare(strict_types=1);

namespace Cosray;

use Celemas\Core\Exception\OutOfBoundsException;
use Celemas\Core\Exception\ValueError;

/**
 * @psalm-import-type BuiltinConfig from \Cosray\Config\Types
 * @psalm-import-type BuiltinConfigInput from \Cosray\Config\Types
 */
class Config
{
	/** @var BuiltinConfig&array<string, mixed> */
	private readonly array $settings;

	private readonly string $root;
	private readonly Config\Env $environment;

	private ?Config\App $appConfig = null;
	private ?Config\Auth $authConfig = null;
	private ?Config\Path $pathConfig = null;
	private ?Config\Panel $panelConfig = null;
	private ?Config\Error $errorConfig = null;
	private ?Config\Icons $iconsConfig = null;
	private ?Config\Database $dbConfig = null;
	private ?Config\Session $sessionConfig = null;
	private ?Config\Media $mediaConfig = null;
	private ?Config\Richtext $richtextConfig = null;
	private ?Config\Upload $uploadConfig = null;
	private ?Config\Password $passwordConfig = null;
	private ?Config\Uid $uidConfig = null;

	public Config\App $app {
		get => $this->appConfig ??= new Config\App($this);
	}

	public Config\Auth $auth {
		get => $this->authConfig ??= new Config\Auth($this);
	}

	public Config\Path $path {
		get => $this->pathConfig ??= new Config\Path($this);
	}

	public Config\Panel $panel {
		get => $this->panelConfig ??= new Config\Panel($this);
	}

	public Config\Error $error {
		get => $this->errorConfig ??= new Config\Error($this);
	}

	public Config\Icons $icons {
		get => $this->iconsConfig ??= new Config\Icons($this);
	}

	public Config\Database $db {
		get => $this->dbConfig ??= new Config\Database($this);
	}

	public Config\Session $session {
		get => $this->sessionConfig ??= new Config\Session($this);
	}

	public Config\Media $media {
		get => $this->mediaConfig ??= new Config\Media($this);
	}

	public Config\Richtext $richtext {
		get => $this->richtextConfig ??= new Config\Richtext($this);
	}

	public Config\Upload $upload {
		get => $this->uploadConfig ??= new Config\Upload($this);
	}

	public Config\Password $password {
		get => $this->passwordConfig ??= new Config\Password($this);
	}

	public Config\Uid $uid {
		get => $this->uidConfig ??= new Config\Uid($this);
	}

	/** @param BuiltinConfigInput&array<string, mixed> $settings */
	public function __construct(string $root, array $settings = [])
	{
		$this->root = $this->normalizeRoot($root);
		$this->environment = Config\Env::load();
		$this->environment->validate();
		$this->settings = Config\Settings::merge(
			Config\Defaults::values($this->root, $this->environment),
			$settings,
		);
	}

	/** @param non-empty-string|list<non-empty-string> $variables */
	public function requireEnv(string|array $variables): self
	{
		$this->environment->require($variables);

		return $this;
	}

	/**
	 * @template TKey of string
	 * @param TKey $key
	 * @param (TKey is key-of<BuiltinConfig> ? BuiltinConfig[TKey] : mixed) $value
	 */
	public function with(string $key, mixed $value): self
	{
		$settings = Config\Settings::merge($this->settings, [$key => $value]);

		return new self($this->root, $settings);
	}

	public function has(string $key): bool
	{
		return array_key_exists($key, $this->settings);
	}

	/**
	 * @template TKey of string
	 * @param TKey $key
	 * @return (TKey is key-of<BuiltinConfig> ? BuiltinConfig[TKey] : mixed)
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		if (array_key_exists($key, $this->settings)) {
			return $this->settings[$key];
		}

		if (func_num_args() > 1) {
			return $default;
		}

		throw new OutOfBoundsException(
			"The configuration key '{$key}' does not exist",
		);
	}

	public function debug(): bool
	{
		return $this->app->debug;
	}

	public function env(): string
	{
		return $this->app->env;
	}

	protected function normalizeRoot(string $root): string
	{
		if ($root === '') {
			throw new ValueError('The root path must be a non-empty string.');
		}

		return rtrim($root, '/\\') ?: DIRECTORY_SEPARATOR;
	}

	public function printAll(): void
	{
		error_log(print_r($this->settings, true));
	}
}
