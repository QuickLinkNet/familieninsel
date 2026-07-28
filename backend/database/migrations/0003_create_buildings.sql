CREATE TABLE buildings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    unlock_minigame_key TEXT,
    is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE building_costs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    building_id INTEGER NOT NULL REFERENCES buildings (id) ON DELETE CASCADE,
    resource_id INTEGER NOT NULL REFERENCES resources (id),
    required_amount INTEGER NOT NULL CHECK (required_amount > 0)
);

CREATE INDEX idx_building_costs_building_id ON building_costs (building_id);

CREATE TABLE family_buildings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    family_id INTEGER NOT NULL REFERENCES families (id) ON DELETE CASCADE,
    building_id INTEGER NOT NULL REFERENCES buildings (id),
    status TEXT NOT NULL DEFAULT 'in_progress' CHECK (status IN ('in_progress', 'completed')),
    stage INTEGER NOT NULL DEFAULT 1,
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE INDEX idx_family_buildings_family_id ON family_buildings (family_id);

CREATE TABLE building_contributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    family_building_id INTEGER NOT NULL REFERENCES family_buildings (id) ON DELETE CASCADE,
    resource_id INTEGER NOT NULL REFERENCES resources (id),
    amount INTEGER NOT NULL CHECK (amount > 0),
    contributed_by_player_id INTEGER NOT NULL REFERENCES players (id),
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE INDEX idx_building_contributions_family_building_id ON building_contributions (family_building_id);
