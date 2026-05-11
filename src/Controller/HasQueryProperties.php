<?php

declare(strict_types=1);

namespace Celemas\Cms\Controller;

trait HasQueryProperties
{
	private ?bool $_map;
	private ?string $_query;
	private ?bool $_published;
	private ?bool $_hidden;
	private ?bool $_deleted;
	private ?bool $_content;
	private array $_uids;
	private string $_order;
	private array $_fields;
	public ?bool $map {
		get => $this->_map;
	}
	public ?string $query {
		get => $this->_query;
	}
	public ?bool $published {
		get => $this->_published;
	}
	public ?bool $hidden {
		get => $this->_hidden;
	}
	public ?bool $deleted {
		get => $this->_deleted;
	}
	public ?bool $content {
		get => $this->_content;
	}
	public array $uids {
		get => $this->_uids;
	}
	public string $order {
		get => $this->_order;
	}
	public array $fields {
		get => $this->_fields;
	}
}
