<?php

require_once 'shared/http_clients.php';

class Config {
	# Despite the name this isn't the official one, it's just a very similar sounding scammy looking site
	private string $imdbApiKey;
	# This one has more permissive rate limits, which is better suited for search, but lower quality results
	private string $omdbApiKey;
	private string $dbLocation;
	private ImdbApiClient $imdbApiClient;
	private OmdbApiClient $omdbApiClient;
	private SQLite3 $db;

	public function __construct($rawConfig) {
		$this->imdbApiKey = $rawConfig['imdb_api_key'];
		$this->dbLocation = $rawConfig['db_location'];
		if (isset($rawConfig['omdb_api_key'])) {
			$this->omdbApiKey = $rawConfig['omdb_api_key'];
		}
	}

	public function getImdbApiClient() {
		if (!isset($this->imdbApiClient)) {
			$this->imdbApiClient = new ImdbApiClient($this->imdbApiKey);
		}

		return $this->imdbApiClient;
	}

	public function getDb() {
		if (!isset($this->db)) {
			$this->db = new SQLite3($this->dbLocation);
		}

		return $this->db;
	}

	public function supportsSearch() {
		return isset($this->omdbApiKey);
	}

	public function getOmdbApiClient() {
		if (!$this->supportsSearch()) throw new Exception("No OMDB Api Key configured");

		if (!isset($this->omdbApiClient)) {
			$this->omdbApiClient = new OmdbApiClient($this->omdbApiKey);
		}

		return $this->omdbApiClient;
	}
}

function getConfig() {
	$rawConfig = json_decode(file_get_contents("/usr/local/apache/seriesRssSecrets.json"), true);
	return new Config($rawConfig);
}

function ensureDb($db) {
	$success = $db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS shows (
	imdb_id TEXT PRIMARY KEY,
	name TEXT NOT NULL,
	last_checked INTEGER NOT NULL DEFAULT 0
)
SQL
	);
	$success &= $db->exec(<<<SQL
CREATE UNIQUE INDEX IF NOT EXISTS show_ids
	ON shows (imdb_id)
SQL
	);

	$success &= $db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS seasons (
	imdb_id TEXT NOT NULL,
	number INTEGER NOT NULL,
	released INTEGER NOT NULL,
	FOREIGN KEY(imdb_id) REFERENCES shows(imdb_id)
)
SQL
	);
	$success &= $db->exec(<<<SQL
CREATE UNIQUE INDEX IF NOT EXISTS season_numbers
	ON seasons (imdb_id, number)
SQL
	);

	return $success;
}

function updateSingleShow($config, $imdbId, $maxSeason) {
	$seasons = [];
	$seasonNumber = $maxSeason + 1;

	# Optimistically retrieve the next season we don't have locally
	while ($release = $config->getImdbApiClient()->fetchSeasonRelease($imdbId, $seasonNumber)) {
		$arr = [];
		$arr['number'] = $seasonNumber;
		$arr['release'] = $release;
		array_push($seasons, $arr);

		$seasonNumber += 1;
	}

	$db = $config->getDb();
	if (sizeof($seasons) > 0) {
		$db->exec('BEGIN TRANSACTION');

		$stmt = $db->prepare('INSERT INTO seasons (imdb_id, number, released) VALUES (:imdb_id, :number, :release)');
		foreach ($seasons as $season) {
			$stmt->reset();

			$stmt->bindValue(':imdb_id', $imdbId);
			$stmt->bindValue(':number', $season['number']);
			$stmt->bindValue(':release', $season['release']);
			$stmt->execute();
		}
		$stmt->close();

		$showStmt = $db->prepare('UPDATE shows SET last_checked = strftime(\'%s\', datetime(\'now\')) WHERE imdb_id = :imdb_id');
		$showStmt->bindValue(':imdb_id', $imdbId);
		$showStmt->execute();
		$showStmt->close();

		$db->exec('COMMIT');
	}
}

function updateLeastRecentShow($config) {
	# Pick out the show that's been checked least recently, unless they've all been checked in the past day, in which
	# case we don't need to update them unnecessarily.
	$query = $config->getDb()->query(<<<SQL
WITH season_numbers AS (
	SELECT
			imdb_id,
			MAX(number) AS number
		FROM seasons 
		GROUP BY imdb_id
)
SELECT
	shows.imdb_id,
	shows.name,
	shows.last_checked,
	season_numbers.number
	FROM shows
		LEFT JOIN season_numbers USING(imdb_id)
	WHERE
		shows.last_checked < strftime('%s', 'now', '-1 day')
	ORDER BY shows.last_checked ASC
	LIMIT 1
SQL
	);

	$toUpdate = $query->fetchArray(SQLITE3_ASSOC);
	if (!$toUpdate) return;

	updateSingleShow($config, $toUpdate['imdb_id'], $toUpdate['number']);

	$query->finalize();
}

function updateAllShows($config) {
	# Find all shows that haven't been checked in the last day
	$toUpdate = $config->getDb()->query(<<<SQL
SELECT shows.imdb_id AS imdb_id, IFNULL(MAX(seasons.number), 0) AS max_season
	FROM shows
		LEFT JOIN seasons USING(imdb_id)
	WHERE
		shows.last_checked < strftime('%s', 'now', '-1 day')
	GROUP BY shows.imdb_id
SQL
	);

	while ($row = $toUpdate->fetchArray(SQLITE3_ASSOC)) {
		updateSingleShow($config, $row['imdb_id'], $row['max_season']);
	}

	$toUpdate->finalize();
}

?>
