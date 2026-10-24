INSERT INTO /*:cms.prefix:*/auth_tokens
	(token, uid, usr, name, expires, creator, editor)
VALUES
	(:token, :uid, :usr, :name, (:expires)::timestamptz, :actor, :actor);
