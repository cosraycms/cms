INSERT INTO /*:cms.prefix:*/events (
	node,
	actor,
	action,
	data,
	at
) VALUES (
	:node,
	:actor,
	:action,
	:data::jsonb,
	coalesce(CAST(:at AS timestamptz), clock_timestamp())
)

RETURNING at;
