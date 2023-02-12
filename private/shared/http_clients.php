
<?php

require_once 'utils.php';

class HttpClient {
	protected static function curlGet($url) {
		try {
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

			$result = curl_exec($curl);
			if (!$result) {
				$info = curl_getinfo($curl);
				error_log('Failed to retrieve ' . $url . ', code: ' . $info['http_code'] . ', in: ' . $info['total_time']);
				throw new Exception('Failed to retrieve information from IMDB API');
			}

			return $result;
		} finally {
			curl_close($curl);
		}
	}
}

class ImdbApiClient extends HttpClient {
	private string $apiKey;

	public function __construct($apiKey) {
		$this->apiKey = $apiKey;
	}

	# Returns a unix timestamp representing the release date of the first episode of the season, or false
	public function fetchSeasonRelease($imdbId, $seasonNumber) {
		if (!isValidImdbId($imdbId)) throw new Exception("Invalid IMDB ID provided");

		$url = 'https://imdb-api.com/en/API/SeasonEpisodes/' . $this->apiKey . '/' . $imdbId . '/' . $seasonNumber;
		$result = json_decode($this->curlGet($url), true);

		# some episodes have no dates, so we search them all for a date
		$released = [];
		foreach ($result['episodes'] as $episode) {
			$releasedAt = strtotime($episode['released']);
			if ($releasedAt) {
				array_push($released, $releasedAt);
			}
		}

		if (sizeof($released) == 0) {
			return false;
		}

		return min($released);
	}

	# Returns a show title or false if no such show exists
	public function fetchShowName($imdbId) {
		if (!isValidImdbId($imdbId)) throw new Exception("Invalid IMDB ID provided");

		# Use the season release API rather than the title API because this one returns useful error messages
		# when a show doesn't exist, while the other returns a "server busy" error
		$url = 'https://imdb-api.com/en/API/SeasonEpisodes/' . $this->apiKey . '/' . $imdbId . '/1';
		$result = json_decode($this->curlGet($url), true);

		if (isset($result['errorMessage']) && substr($result['errorMessage'], 0, 3) == "404") {
			error_log('Error while retrieving ' . $imdbId . ', ' . $result['errorMessage']);
			return false;
		}

		return $result['title'];
	}
}

class OmdbApiClient extends HttpClient {
	private string $apiKey;

	public function __construct($apiKey) {
		$this->apiKey = $apiKey;
	}

	# Returns an instance of stdClass with a field `results`, an array of associative arrays with keys of `Poster` (some
	# random image from IMDB, not always the actual poster), `Title`, `Year`, and `imdbId`, and a field `nextPage`, for
	# pagination, which may be false if there are no more results.
	public function fetchSearchResults($query, $page) {
		$pagesToAggregate = 2;
		$outcome = new \stdClass();
		$outcome->results = [];

		# Each page from OMDB is 10 results, which is rather small, so let's get two pages at a time and paginate internally
		$effectivePage = ($page - 1) * 2 + 1;
		$hasMore = true;
		for ($i = 0; $i < $pagesToAggregate && $hasMore; $i++) {
			$queryPage = $effectivePage + $i;
			$url = 'https://www.omdbapi.com/?apikey=' . $this->apiKey . '&type=series&s=' . $query . '&page=' . $queryPage;
			$result = json_decode($this->curlGet($url), true);

			if ($result['Response'] == 'False') {
				# There are no more results, and possibly there are none at all
				$outcome->more = false;
				break;
			}

			foreach($result['Search'] as $series) {
				array_push($outcome->results, $series);
			}

			$totalResults = $result['totalResults'];
			$alreadyRetrieved = ($effectivePage - 1) * 10 + sizeof($outcome->results);
			$hasMore = $alreadyRetrieved < $totalResults;
		}

		if ($hasMore) {
			$outcome->nextPage = $effectivePage + $pagesToAggregate;
		} else {
			$outcome->nextPage = false;
		}

		return $outcome;
	}
}

?>
