<?php

declare(strict_types=1);

namespace Celemas\Cms\Controller;

interface Query
{
	public ?bool $map { get; }
	public ?string $query { get; }
	public ?bool $published { get; }
	public ?bool $hidden { get; }
	public ?bool $deleted { get; }
	public ?bool $content { get; }
	public array $uids { get; }
	public string $order { get; }
	public array $fields { get; }
}
