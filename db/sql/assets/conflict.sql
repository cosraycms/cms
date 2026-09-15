SELECT uid FROM /*:cms.prefix:*/assets
WHERE hash = :hash AND permission <> :permission
LIMIT 1;
