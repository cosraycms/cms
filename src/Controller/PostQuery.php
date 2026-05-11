<?php

declare(strict_types=1);

namespace Celemas\Cms\Controller;

use Celemas\Core\Request;

class PostQuery implements Query
{
	use HasQueryProperties;

	public function __construct(Request $request)
	{
		$json = $request->json();
		$this->_map = $json['map'] ?? null;
		$this->_query = $json['query'] ?? null;
		$this->_published = $json['published'] ?? null;
		$this->_hidden = $json['hidden'] ?? false;
		$this->_deleted = $json['deleted'] ?? false;
		$this->_content = $json['content'] ?? false;
		$this->_uids = $json['uids'] ?? [];
		$this->_order = $json['order'] ?? 'changed';
		$this->_fields = is_array($json['fields'] ?? '')
			? $json['fields']
			: explode(',', $json['fields'] ?? '');
	}
}
