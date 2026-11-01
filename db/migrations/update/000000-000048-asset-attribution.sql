-- The catalog's credit line is now the attribution, which a use can override.

UPDATE /*:cms.prefix:*/assets
SET meta = (meta - 'credit') || jsonb_build_object('attribution', meta -> 'credit')
WHERE meta ? 'credit';
