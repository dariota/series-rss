<html>
	<body>

<?php

require 'shared.php';

$config = getConfig();
ensureDb($config->getDb());

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

if (isset($_POST['imdb_id']) && isValidImdbId($_POST['imdb_id'])) {
	$imdbId = $_POST['imdb_id'];

	$trackedName = trackedName($config->getDb(), $imdbId);
	if ($trackedName) {
		echo '<p>Already tracking ' . $trackedName . '.</p>';
	} else {
		$config = getConfig();
		$name = $config->getImdbApiClient()->fetchShowName($imdbId);

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

	$searchOutcome = $config->getOmdbApiClient()->fetchSearchResults($query, $page);
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

		if ($searchOutcome->nextPage) {
			$nextPage = $searchOutcome->nextPage;
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
