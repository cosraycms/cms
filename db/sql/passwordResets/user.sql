SELECT usr
FROM /*:cms.prefix:*/password_resets
WHERE token = :token AND expires > now();
