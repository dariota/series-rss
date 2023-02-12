<html>
	<body>

<?php

require 'shared.php';

$db = getDb();
ensureDb($db);

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

if (isset($_POST['imdb_id']) && isValidImdbId($_POST['imdb_id'])) {
	$imdbId = $_POST['imdb_id'];

	$trackedName = trackedName($db, $imdbId);
	if ($trackedName) {
		echo '<p>Already tracking ' . $trackedName . '.</p>';
	} else {
		$config = getConfig();
		$name = fetchShowName($config, $imdbId);

		if ($name && trackShow($db, $imdbId, $name)) {
			echo '<p>Now tracking ' . $name . '.</p>';
			updateAllShows($db, $config);
		} else {
			echo '<p>Failed to track show.</p>';
		}
	}
}
?>
		<form method='post'>
			<label for='imdb_id'>IMDB ID</label>
			<input type='text' name='imdb_id'><br>
			<input type='submit'>
		</form>
	</body>
</html> 
