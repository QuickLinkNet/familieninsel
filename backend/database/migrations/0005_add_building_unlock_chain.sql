ALTER TABLE buildings ADD COLUMN unlocks_building_key TEXT;

CREATE UNIQUE INDEX idx_family_buildings_family_building ON family_buildings (family_id, building_id);
