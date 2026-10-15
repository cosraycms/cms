<?php

declare(strict_types=1);

namespace Cosray\Controller;

interface Query
{
	public ?bool $map { get; }
	public ?string $query { get; }
	public ?bool $published { get; }
	public ?bool $hidden { get; }
	public ?bool $deleted { get; }
	public ?bool $content { get; }
	/** @var array<array-key, mixed> */
	public array $uids { get; }
	public string $order { get; }
	/** @var array<array-key, mixed> */
	public array $fields { get; }
}
