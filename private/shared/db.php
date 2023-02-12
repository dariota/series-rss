<?php

class Database {
	private SQLite3 $db;

	public function __construct($dbLocation) {
		$this->db = new SQLite3($dbLocation);
	}

	# Creates tables and indexes if they don't already exist
	public function ensure() {
		$success = $this->db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS shows (
	imdb_id TEXT PRIMARY KEY,
	name TEXT NOT NULL,
	last_checked INTEGER NOT NULL DEFAULT 0
)
SQL
		);
		$success &= $this->db->exec(<<<SQL
CREATE UNIQUE INDEX IF NOT EXISTS show_ids
	ON shows (imdb_id)
SQL
		);

		$success &= $this->db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS seasons (
	imdb_id TEXT NOT NULL,
	number INTEGER NOT NULL,
	released INTEGER NOT NULL,
	FOREIGN KEY(imdb_id) REFERENCES shows(imdb_id)
)
SQL
		);
		$success &= $this->db->exec(<<<SQL
CREATE UNIQUE INDEX IF NOT EXISTS season_numbers
	ON seasons (imdb_id, number)
SQL
		);

		return $success;
	}

	public function updateShow($imdbId, $seasons) {
		if (sizeof($seasons) > 0) {
			$this->db->exec('BEGIN TRANSACTION');

			$stmt = $this->db->prepare('INSERT INTO seasons (imdb_id, number, released) VALUES (:imdb_id, :number, :release)');
			foreach ($seasons as $season) {
				$stmt->reset();

				$stmt->bindValue(':imdb_id', $imdbId);
				$stmt->bindValue(':number', $season['number']);
				$stmt->bindValue(':release', $season['release']);
				$stmt->execute();
			}
			$stmt->close();

			$showStmt = $this->db->prepare('UPDATE shows SET last_checked = strftime(\'%s\', datetime(\'now\')) WHERE imdb_id = :imdb_id');
			$showStmt->bindValue(':imdb_id', $imdbId);
			$showStmt->execute();
			$showStmt->close();

			$this->db->exec('COMMIT');
		}
	}

	private function staleShowQuery($limit = false) {
		$query = <<<SQL
SELECT
		shows.imdb_id AS imdb_id,
		IFNULL(MAX(seasons.number), 0) AS max_season
	FROM shows
		LEFT JOIN seasons USING(imdb_id)
	WHERE
		shows.last_checked < strftime('%s', 'now', '-1 day')
	GROUP BY shows.imdb_id
SQL;

		# only the query with a limit needs an order so throwing all of this in here
		if ($limit) $query .= " ORDER BY shows.last_checked ASC LIMIT 1";

		return $query;
	}

	# Returns the show that hasn't been updated for the most time, so long as its last update was over a day ago.
	# Returns false if there are no such shows.
	public function findLeastRecentStaleShow() {
		$query = $this->db->query($this->staleShowQuery(true));

		return $query->fetchArray(SQLITE3_ASSOC);
	}

	public function findAllStaleShows() {
		return $this->db->query($this->staleShowQuery());
	}

	# Returns the name of a series if it's already being tracked, or false otherwise
	public function getTrackedName($imdbId) {
		$stmt = $this->db->prepare('SELECT name FROM shows WHERE imdb_id=:imdb_id');
		$stmt->bindValue(':imdb_id', $imdbId, SQLITE3_TEXT);
		$result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

		if ($result) {
			return $result['name'];
		} else {
			return false;
		}
	}

	public function trackShow($imdbId, $name) {
		$stmt = $this->db->prepare('INSERT INTO shows (imdb_id, name) VALUES (:imdb_id, :name)');
		$stmt->bindValue(':imdb_id', $imdbId);
		$stmt->bindValue(':name', $name);

		return $stmt->execute();
	}

	public function getSeasonsByLastReleased() {
		return $this->db->query(<<<SQL
SELECT
		shows.name AS name,
		shows.imdb_id AS imdb_id,
		seasons.number AS season_number,
		seasons.released AS released
	FROM shows
		JOIN seasons USING(imdb_id)
	ORDER BY seasons.released DESC
SQL
		);
	}

	public function getShowsByLastReleased() {
		return $this->db->query(<<<SQL
WITH season_releases AS (
	SELECT
			imdb_id,
			MAX(released) AS released
		FROM seasons
		GROUP BY imdb_id
)
SELECT
	shows.imdb_id,
	shows.name,
	shows.last_checked,
	season_releases.released
	FROM shows
		LEFT JOIN season_releases USING(imdb_id)
	ORDER BY season_releases.released DESC
SQL
		);
	}

}

?>
