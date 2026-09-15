# Football Predict

A PHP/MySQL football intelligence and probability platform designed for shared hosting.

## Phase 1

- Flashscore4/RapidAPI provider abstraction
- Normalized football database
- Fixtures, statistics and event importers
- Shared-hosting friendly cron jobs
- Data-quality tracking

## Planned prediction markets

- Match result (1X2)
- Goals / BTTS / totals
- Total shots
- Shots on target
- Corners
- Cards
- Fouls

## Requirements

- PHP 8.2+
- MySQL 8+ or compatible MariaDB
- Composer
- PHP cURL and PDO MySQL extensions

## Security

Copy `.env.example` to `.env` locally and add your own credentials. Never commit `.env` or API keys.
