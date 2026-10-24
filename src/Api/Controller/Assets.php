<?php

declare(strict_types=1);

namespace Cosray\Api\Controller;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\Api\NodeQuery;
use Cosray\Api\Responses;
use Cosray\Api\Serializer;
use Cosray\Assets\Ingest;
use Cosray\Assets\Library;
use Cosray\Assets\Meta;
use Cosray\Config;
use Cosray\Context;
use Cosray\Exception\IngestError;
use Cosray\Security\Policy;
use Cosray\User;
use finfo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\UploadedFileInterface;

/**
 * The asset catalog. Anyone reads an asset by uid, as its file is public
 * anyway; listing the catalog takes `read-nodes`, uploading `edit-nodes`.
 */
final class Assets
{
	public function __construct(
		private readonly Responses $responses,
		private readonly Serializer $serializer,
		private readonly Policy $policy,
	) {}

	public function get(Request $request, Context $context, string $uid): Response
	{
		$asset = $context->assets()->get($uid)
			?? throw new HttpNotFound($request, payload: ['message' => "There is no asset '{$uid}'."]);

		return $this->responses->json($this->serializer->asset($asset));
	}

	/** Newest first; `kind` takes image, video, audio and document, `q` matches the filename. */
	public function list(Request $request, Library $library): Response
	{
		$this->permit($request, 'read-nodes', 'list the catalog');
		$params = $request->params();
		$kinds = $params['kind'] ?? [];
		$q = $params['q'] ?? '';
		$query = NodeQuery::fromQuery(array_intersect_key($params, ['limit' => true, 'offset' => true]));

		if (!is_string($kinds) && !is_array($kinds) || !is_string($q)) {
			throw new HttpBadRequest($request, payload: ['message' => '`kind` and `q` are strings.']);
		}

		$list = $library->list(
			array_values(array_filter(is_string($kinds) ? explode(',', $kinds) : $kinds, is_string(...))),
			$q,
			Library::since($params['since'] ?? null),
			$query->limit,
			$query->offset,
		);

		return $this->responses->json([
			'items' => array_map($this->serializer->asset(...), $list['assets']),
			'total' => $list['total'],
			'limit' => $query->limit,
			'offset' => $query->offset,
		]);
	}

	/**
	 * Takes a multipart `file`, an optional `type` (image, video or file,
	 * otherwise read off the file) and optional `meta` JSON with `alt`,
	 * `title` and `caption` maps and a `credit`. Identical bytes answer with
	 * the asset already in the catalog.
	 */
	public function upload(Request $request, Context $context, Config $config, Database $db): Response
	{
		$user = $this->permit($request, 'edit-nodes', 'upload');
		$file = $request->unwrap()->getUploadedFiles()['file'] ?? null;

		if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
			throw new HttpBadRequest($request, payload: ['message' => 'Send the file as the multipart field `file`.']);
		}

		$contents = (string) $file->getStream();
		$fields = $request->unwrap()->getParsedBody();
		$fields = is_array($fields) ? $fields : [];
		$type = $fields['type'] ?? $this->type($config, $contents);

		if (!in_array($type, ['image', 'video', 'file'], true)) {
			throw new HttpBadRequest($request, payload: ['message' => '`type` is image, video or file.']);
		}

		$meta = json_decode(is_string($fields['meta'] ?? null) ? $fields['meta'] : '{}', true);
		$localeIds = array_keys(iterator_to_array($context->locales()));

		try {
			$result = new Ingest($config, $db)->ingest(
				$contents,
				(string) $file->getClientFilename(),
				$type,
				$user === null ? Actor::system() : new Actor($user->id),
				Meta::apply([], is_array($meta) ? $meta : [], $localeIds, $type === 'image'),
			);
		} catch (IngestError $e) {
			throw new HttpBadRequest(
				$request,
				payload: [
					'message' => $e->userMessage,
					'errors' => [new \Celema\Sire\Issue(['file'], 'rejected', $e->userMessage)],
				],
				previous: $e,
			);
		}

		$uid = (string) $result->row['uid'];
		$asset = $context->assets()->get($uid)
			?? throw new HttpNotFound($request, payload: ['message' => "There is no asset '{$uid}'."]);

		return $this->responses->json($this->serializer->asset($asset), $result->created ? 201 : 200, [
			'Location' => rtrim($request->uri()->getPath(), '/') . "/{$uid}",
		]);
	}

	private function type(Config $config, string $contents): string
	{
		$mime = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents);

		return match (true) {
			isset($config->upload->image[$mime]) => 'image',
			isset($config->upload->video[$mime]) => 'video',
			default => 'file',
		};
	}

	private function permit(Request $request, string $permission, string $action): ?User
	{
		$user = $request->get('user', null);
		$user = $user instanceof User ? $user : null;

		if (!$this->policy->permits($user, $permission)) {
			throw new HttpForbidden($request, payload: ['message' => "The token may not {$action}."]);
		}

		return $user;
	}
}
