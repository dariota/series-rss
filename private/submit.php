<html>
	<body>

<?php

require 'shared.php';

$config = getConfig();
ensureDb($config->getDb());

function isValidImdbId($imdbId) {
	return preg_match('/^tt\d+$/', $imdbId) == 1;
}

function trackedName($db, $imdbId) {
	$stmt = $db->prepare('SELECT name FROM shows WHERE imdb_id=:imdb_id');
	$stmt->bindValue(':imdb_id', $imdbId, SQLITE3_TEXT);
	$result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

	if ($result) {
		return $result['name'];
	} else {
		return false;
	}
}

function trackShow($db, $imdbId, $name) {
	$stmt = $db->prepare('INSERT INTO shows (imdb_id, name) VALUES (:imdb_id, :name)');
	$stmt->bindValue(':imdb_id', $imdbId);
	$stmt->bindValue(':name', $name);

	return $stmt->execute();
}

function fetchShowName($config, $imdbId) {
	$curl = curl_init();

	try {
		curl_setopt($curl, CURLOPT_URL, 'https://imdb-api.com/en/API/Title/' . $config->getImdbApiKey() . '/' . $imdbId);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($curl);
		if (!$result) {
			$info = curl_getinfo($curl);
			error_log('Failed to retrieve ' . $imdbId . ', code: ' . $info['http_code'] . ', in: ' . $info['total_time']);
			return false;
		}

		$result = json_decode($result, true);
		if (is_null($result['title'])) {
			error_log('Error while retrieving ' . $imdbId . ', ' . $result['errorMessage']);
			return false;
		}

		return $result['title'];
	} finally {
		curl_close($curl);
	}
}

function fetchSearchResults($config, $query, $page) {
	if (!$config->supportsSearch()) throw new Exception('Search requires an OMDB api key in config');

	$outcome = new \stdClass();
	$outcome->results = [];
	$outcome->more = true;

	# Each page from OMDB is 10 results, which is rather small, so let's get two pages at a time and paginate internally
	$effectivePage = ($page - 1) * 2;
	for ($i = 1; $i < 3 && $outcome->more; $i++) {
		try {
			$curl = curl_init();

			$queryPage = $effectivePage + $i;
			curl_setopt($curl, CURLOPT_URL, 'https://www.omdbapi.com/?apikey=' . $config->getOmdbApiKey() . '&type=series&s=' . $query . '&page=' . $queryPage);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($curl);
			if (!$result) {
				$info = curl_getinfo($curl);
				error_log('Failed to search ' . $query . ' on page ' . $effectivePage . ', code: ' . $info['http_code'] . ', in: ' . $info['total_time']);
				return false;
			}
		} finally {
			curl_close($curl);
		}

		$result = json_decode($result, true);
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
		$outcome->more = $alreadyRetrieved < $totalResults;
	}

	return $outcome;
}

if (isset($_POST['imdb_id']) && isValidImdbId($_POST['imdb_id'])) {
	$imdbId = $_POST['imdb_id'];

	$trackedName = trackedName($config->getDb(), $imdbId);
	if ($trackedName) {
		echo '<p>Already tracking ' . $trackedName . '.</p>';
	} else {
		$config = getConfig();
		$name = fetchShowName($config, $imdbId);

		if ($name && trackShow($config->getDb(), $imdbId, $name)) {
			echo '<p>Now tracking ' . $name . '.</p>';
			updateAllShows($config);
		} else {
			echo '<p>Failed to track show.</p>';
		}
	}
} else if (isset($_GET['query']) && $config->supportsSearch()) {
	$query = $_GET['query'];
	$page = 1;
	if (isset($_GET['page'])) {
		$page = $_GET['page'];
	}

	$searchOutcome = fetchSearchResults($config, $query, $page);
	if ($searchOutcome) {
		echo <<<THEADER
		<table>
			<tr>
				<th></th>
				<th>Title</th>
				<th></th>
			</tr>

THEADER;

		foreach ($searchOutcome->results as $result) {
			$poster = null;
			if ($result['Poster'] != 'N/A') {
				$poster = '<img src="' . $result['Poster'] . '" style="width:100px">';
			}
			$name = $result['Title'];
			$year = $result['Year'];
			$imdbId = $result['imdbID'];

			echo <<<RESULT
			<tr>
				<td>$poster</td>
				<td>$name ($year)</td>
				<td>
					<form method="post">
						<input type="hidden" name="imdb_id" value="$imdbId">
						<input type="submit" value="Track">
					</form>
				</td>
			</tr>

RESULT;
		}

		echo <<<TFOOTER
		</table>

TFOOTER;

		if ($searchOutcome->more) {
			$nextPage = $page + 1;
			echo <<<NEXTPAGE
		<form method="get">
			<input type="hidden" name="query" value="$query">
			<input type="hidden" name="page" value="$nextPage">
			<input type="Submit" value="More Results">
		</form>

NEXTPAGE;
		}
	}
}
?>
		<form method='post'>
			<label for='imdb_id'>IMDB ID</label>
			<input type='text' name='imdb_id'>
			<input type='submit' value='Track'>
		</form>
<?php
if ($config->supportsSearch()) {
	echo <<<SEARCH
		<form method='get'>
			<label for='query'>Search Term</label>
			<input type='text' name='query'>
			<input type='submit' value='Search'>
		</form>

SEARCH;
}
?>
	</body>
</html> 
