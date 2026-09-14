ALTER TABLE players ADD COLUMN password_hash TEXT;

CREATE TABLE player_login_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    player_id INTEGER NOT NULL REFERENCES players (id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
    last_used_at TEXT,
    revoked_at TEXT
);

CREATE UNIQUE INDEX idx_player_login_tokens_hash ON player_login_tokens (token_hash);
CREATE INDEX idx_player_login_tokens_player_id ON player_login_tokens (player_id);
