-- A minute's precision is enough and spares most requests a write.
UPDATE /*:cms.prefix:*/auth_tokens SET
	used = now()
WHERE
	token = :token
	AND (used IS NULL OR used < now() - interval '1 minute');
