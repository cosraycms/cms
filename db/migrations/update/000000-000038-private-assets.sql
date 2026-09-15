ALTER TABLE /*:cms.prefix:*/assets ADD COLUMN permission text NOT NULL DEFAULT 'everyone';
ALTER TABLE /*:cms.prefix:*/assets ADD CONSTRAINT /*:cms.obj:*/ck_assets_permission
	CHECK ((permission = 'everyone' AND disk = 'local') OR
		(permission ~ '^[a-z][a-z0-9-]{0,63}$' AND permission <> 'everyone' AND disk = 'private'));
