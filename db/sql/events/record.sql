INSERT INTO /*:cms.prefix:*/events (
	node,
	actor,
	action,
	revision,
	data,
	at
) VALUES (
	:node,
	:actor,
	:action,
	:revision,
	:data::jsonb,
	coalesce(CAST(:at AS timestamptz), clock_timestamp())
)

RETURNING at;
