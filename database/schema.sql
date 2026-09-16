CREATE TABLE IF NOT EXISTS competitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    country_name VARCHAR(150) NULL,
    country_code VARCHAR(10) NULL,
    logo VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    prediction_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_competition_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    short_name VARCHAR(100) NULL,
    country VARCHAR(150) NULL,
    logo VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_provider (provider_id),
    KEY idx_team_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fixtures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id VARCHAR(100) NOT NULL,
    competition_id BIGINT UNSIGNED NOT NULL,
    home_team_id BIGINT UNSIGNED NOT NULL,
    away_team_id BIGINT UNSIGNED NOT NULL,
    kickoff DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
    round_name VARCHAR(100) NULL,
    venue VARCHAR(255) NULL,
    referee_name VARCHAR(255) NULL,
    home_score SMALLINT NULL,
    away_score SMALLINT NULL,
    ht_home_score SMALLINT NULL,
    ht_away_score SMALLINT NULL,
    penalty_home_score SMALLINT NULL,
    penalty_away_score SMALLINT NULL,
    data_quality TINYINT UNSIGNED NOT NULL DEFAULT 0,
    stats_imported TINYINT(1) NOT NULL DEFAULT 0,
    events_imported TINYINT(1) NOT NULL DEFAULT 0,
    lineup_imported TINYINT(1) NOT NULL DEFAULT 0,
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fixture_provider (provider_id),
    KEY idx_fixture_kickoff (kickoff), KEY idx_fixture_status (status),
    CONSTRAINT fk_fixture_competition FOREIGN KEY (competition_id) REFERENCES competitions(id),
    CONSTRAINT fk_fixture_home FOREIGN KEY (home_team_id) REFERENCES teams(id),
    CONSTRAINT fk_fixture_away FOREIGN KEY (away_team_id) REFERENCES teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_match_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, fixture_id BIGINT UNSIGNED NOT NULL, team_id BIGINT UNSIGNED NOT NULL,
    period ENUM('MATCH','FIRST_HALF','SECOND_HALF','EXTRA_TIME') NOT NULL DEFAULT 'MATCH',
    xg DECIMAL(7,3) NULL, xgot DECIMAL(7,3) NULL, expected_assists DECIMAL(7,3) NULL, possession DECIMAL(5,2) NULL,
    shots SMALLINT UNSIGNED NULL, shots_on_target SMALLINT UNSIGNED NULL, shots_off_target SMALLINT UNSIGNED NULL, blocked_shots SMALLINT UNSIGNED NULL,
    shots_inside_box SMALLINT UNSIGNED NULL, shots_outside_box SMALLINT UNSIGNED NULL, woodwork SMALLINT UNSIGNED NULL, big_chances SMALLINT UNSIGNED NULL,
    corners SMALLINT UNSIGNED NULL, touches_opposition_box SMALLINT UNSIGNED NULL, offsides SMALLINT UNSIGNED NULL, free_kicks SMALLINT UNSIGNED NULL,
    passes_attempted SMALLINT UNSIGNED NULL, passes_completed SMALLINT UNSIGNED NULL, pass_accuracy DECIMAL(5,2) NULL,
    long_passes_attempted SMALLINT UNSIGNED NULL, long_passes_completed SMALLINT UNSIGNED NULL, long_pass_accuracy DECIMAL(5,2) NULL,
    final_third_passes_attempted SMALLINT UNSIGNED NULL, final_third_passes_completed SMALLINT UNSIGNED NULL, final_third_pass_accuracy DECIMAL(5,2) NULL,
    crosses_attempted SMALLINT UNSIGNED NULL, crosses_completed SMALLINT UNSIGNED NULL, cross_accuracy DECIMAL(5,2) NULL,
    fouls SMALLINT UNSIGNED NULL, yellow_cards SMALLINT UNSIGNED NULL, red_cards SMALLINT UNSIGNED NULL,
    tackles_attempted SMALLINT UNSIGNED NULL, tackles_won SMALLINT UNSIGNED NULL, duels_won SMALLINT UNSIGNED NULL, clearances SMALLINT UNSIGNED NULL, interceptions SMALLINT UNSIGNED NULL,
    errors_leading_to_shot SMALLINT UNSIGNED NULL, errors_leading_to_goal SMALLINT UNSIGNED NULL, goalkeeper_saves SMALLINT UNSIGNED NULL,
    xgot_faced DECIMAL(7,3) NULL, goals_prevented DECIMAL(7,3) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fixture_team_period (fixture_id, team_id, period), KEY idx_stats_team (team_id),
    CONSTRAINT fk_stats_fixture FOREIGN KEY (fixture_id) REFERENCES fixtures(id) ON DELETE CASCADE,
    CONSTRAINT fk_stats_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, provider VARCHAR(50) NOT NULL DEFAULT 'flashscore4', endpoint VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NULL, entity_id VARCHAR(100) NULL, response_json LONGTEXT NOT NULL, response_hash CHAR(64) NULL,
    http_status SMALLINT NULL, fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_snapshot_entity (entity_type, entity_id), KEY idx_snapshot_endpoint (endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, provider VARCHAR(50) NOT NULL, endpoint VARCHAR(255) NOT NULL, entity_id VARCHAR(100) NULL,
    http_status SMALLINT NULL, duration_ms INT UNSIGNED NULL, success TINYINT(1) NOT NULL DEFAULT 1, error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_api_created (created_at), KEY idx_api_provider (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collector_state (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_key VARCHAR(100) NOT NULL,
    cursor_date DATE NULL,
    end_date DATE NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    last_run_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_collector_state_key (state_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
