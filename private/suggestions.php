
<?php

require_once 'shared.php';

$config = getConfig();

if (isset($_POST['cancel'])) {
	$config->getDb()->setCancelled($_POST['imdb_id'], $_POST['cancel'] == '1');
} else if (isset($_POST['update'])) {
	$imdbId = $_POST['imdb_id'];
	$maxSeason = $config->getDb()->getShowMaxSeason($imdbId);
	updateSingleShow($config, $imdbId, $maxSeason);
}

$query = $config->getDb()->getSuggestionsByCount();

?>
<html>
	<body>
		<table>
			<tr>
				<th>Suggested Show</th>
				<th>Number of Hits</th>
				<th>Add to Tracking</th>
			</tr>
<?php
while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
	$imdbId = $row['imdb_id'];
	$url = "https://www.imdb.com/title/" . $imdbId . "/";
	$name = $row['name'];
	$timesSuggested = $row['times_suggested'];


	echo <<<ITEM
			<tr>
				<td><a href="$url">$name</a></td>
				<td>$timesSuggested</td>
				<td>
					<form target="_blank" method="post" action="submit.php">
						<input type="hidden" name="imdb_id" value="$imdbId">
						<input type="submit" value="Track">
					</form>
				</td>
			</tr>
ITEM;
}
?>

		</table>
	</body>
</html>
