UPDATE /*:cms.prefix:*/access_attempts
SET attempts = greatest(0, attempts - 1)
WHERE permission = :permission AND client = :client;
