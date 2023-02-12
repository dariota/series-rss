<?php

require_once 'shared/http_clients.php';
require_once 'shared/db.php';

class Config {
	# Despite the name this isn't the official one, it's just a very similar sounding scammy looking site
	private string $imdbApiKey;
	# This one has more permissive rate limits, which is better suited for search, but lower quality results
	private string $omdbApiKey;
	private string $dbLocation;
	private ImdbApiClient $imdbApiClient;
	private OmdbApiClient $omdbApiClient;
	private Database $db;

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
			$this->db = new Database($this->dbLocation);
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

	$config->getDb()->updateShow($imdbId, $seasons);
}

function updateLeastRecentShow($config) {
	$toUpdate = $config->getDb()->findLeastRecentStaleShow();
	if (!$toUpdate) return;

	updateSingleShow($config, $toUpdate['imdb_id'], $toUpdate['max_season']);
}

function updateAllShows($config) {
	# Find all shows that haven't been checked in the last day
	$toUpdate = $config->getDb()->findAllStaleShows();

	while ($row = $toUpdate->fetchArray(SQLITE3_ASSOC)) {
		updateSingleShow($config, $row['imdb_id'], $row['max_season']);
	}

	$toUpdate->finalize();
}

?>
