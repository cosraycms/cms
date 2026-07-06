SELECT
	(SELECT count(*) FROM /*:cms.prefix:*/asset_references) AS assets,
	(SELECT count(*) FROM /*:cms.prefix:*/node_references) AS nodes;
