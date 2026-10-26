UPDATE /*:cms.prefix:*/rate_limits
SET hits = hits - 1
WHERE bucket = :bucket AND hits > 0 AND ends > now();
