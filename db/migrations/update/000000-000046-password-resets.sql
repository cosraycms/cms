-- Password reset links. Only the sha256 of a token is stored. A user
-- has at most one open link; a new one replaces it, and setting the
-- password removes it.

CREATE TABLE /*:cms.prefix:*/password_resets (
	token text NOT NULL,
	usr bigint NOT NULL,
	created timestamp with time zone NOT NULL DEFAULT now(),
	expires timestamp with time zone NOT NULL,
	CONSTRAINT /*:cms.obj:*/pk_password_resets PRIMARY KEY (token),
	CONSTRAINT /*:cms.obj:*/uc_password_resets_usr UNIQUE (usr),
	CONSTRAINT /*:cms.obj:*/fk_password_resets_users FOREIGN KEY (usr)
		REFERENCES /*:cms.prefix:*/users (usr) ON DELETE CASCADE,
	CONSTRAINT /*:cms.obj:*/ck_password_resets_token CHECK (char_length(token) <= 128)
);
