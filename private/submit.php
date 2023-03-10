<html>
	<body>

<?php

require_once 'shared.php';
require_once 'shared/utils.php';

$config = getConfig();
$config->getDb()->ensure();

function trackShow($config, $imdbId) {
	$name = $config->getImdbApiClient()->fetchShowName($imdbId);

	if (!$name) {
		return false;
	}

	// Now we've confirmed it exists, we can fetch similar shows for recommendations
	$similarShows = $config->getImdbApiClient()->fetchSimilarShows($imdbId);

	// Start a transaction
	$config->getDb()->beginTransaction();

	// Save the similar shows and the one to be tracked
	try {
		$config->getDb()->trackShow($imdbId, $name);
		foreach ($similarShows as $similarShow) {
			$config->getDb()->markSimilar($imdbId, $similarShow->imdbId, $similarShow->showName);
		}
	} catch (Exception $e) {
		// Rollback the transaction
		$config->getDb()->exec('ROLLBACK');
		return false;
	}

	$config->getDb()->commitTransaction();

	try {
		updateAllShows($config);
	} catch (Exception $e) {
		// Don't actually care, this can be updated some other time
	}

	return $name;
}

if (isset($_POST['imdb_id']) && isValidImdbId($_POST['imdb_id'])) {
	$imdbId = $_POST['imdb_id'];

	$trackedName = $config->getDb()->getTrackedName($imdbId);
	if ($trackedName) {
		echo '<p>Already tracking ' . $trackedName . '.</p>';
	} else {
		$config = getConfig();
		$name = trackShow($config, $imdbId);

		if ($name) {
			echo '<p>Now tracking ' . $name . '.</p>';
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
