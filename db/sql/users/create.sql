INSERT INTO /*:cms.prefix:*/users (
	uid,
	type,
	username,
	email,
	password,
	roles,
	active,
	panel_locale,
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
	:data::jsonb,
	:creator,
	:editor
);
