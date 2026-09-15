INSERT INTO /*:cms.prefix:*/access_attempts AS a (permission, client, attempts, expires)
VALUES (:permission, :client, 1, now() + interval '15 minutes')
ON CONFLICT (permission, client) DO UPDATE
SET attempts = a.attempts + 1
WHERE a.attempts < 10
RETURNING attempts;
