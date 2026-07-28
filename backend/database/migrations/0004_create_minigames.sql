CREATE TABLE minigames (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE family_minigames (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    family_id INTEGER NOT NULL REFERENCES families (id) ON DELETE CASCADE,
    minigame_id INTEGER NOT NULL REFERENCES minigames (id),
    unlocked_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
    first_completion_at TEXT,
    reward_claimed_at TEXT,
    UNIQUE (family_id, minigame_id)
);

CREATE INDEX idx_family_minigames_family_id ON family_minigames (family_id);
