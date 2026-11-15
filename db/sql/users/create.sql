INSERT INTO /*:cms.prefix:*/users (
	uid,
	type,
	username,
	email,
	password,
	roles,
	active,
	panel_locale,
	panel_region,
	panel_timezone,
	data,
	creator,
	editor
) VALUES (
	:uid,
	:type,
	:username,
	:email,
	:password,
	ARRAY(SELECT jsonb_array_elements_text(:roles::jsonb)),
	:active,
	:panel_locale,
	:panel_region,
	:panel_timezone,
	:data::jsonb,
	:creator,
	:editor
);
