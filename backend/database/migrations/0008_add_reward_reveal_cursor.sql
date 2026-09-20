ALTER TABLE players ADD COLUMN last_reward_seen_at TEXT;

-- Bestehende Spieler auf "jetzt" setzen, damit niemand nach diesem Update
-- ploetzlich Wochen an historischen Aufgaben-Belohnungen als "neu" im
-- RewardReveal serviert bekommt.
UPDATE players SET last_reward_seen_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now');
