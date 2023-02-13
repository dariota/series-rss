
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

$query = $config->getDb()->getShowsByLastReleased();

?>
<html>
	<body>
		<table>
			<tr>
				<th>Show</th>
				<th>Last Release</th>
				<th>Last Checked</th>
				<th>Cancelled?</th>
				<th>Toggle Cancel</th>
				<th>Force Update</th>
			</tr>
<?php
while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
	$imdbId = $row['imdb_id'];
	$url = "https://www.imdb.com/title/" . $imdbId . "/";
	$name = $row['name'];
	$releaseDate = strftime("%Y-%m-%d", $row['released']);
	$checkDate = strftime("%Y-%m-%d", $row['last_checked']);
	$isCancelled = $row['cancelled'] == 1;
	$cancelled = $isCancelled ? "<strong>Yes</strong>" : "No";
	$cancelToggle = $isCancelled ? 0 : 1;


	echo <<<ITEM
			<tr>
				<td><a href="$url">$name</a></td>
				<td>$releaseDate</td>
				<td>$checkDate</td>
				<td>$cancelled</td>
				<td>
					<form method="post">
						<input type="hidden" name="cancel" value="$cancelToggle">
						<input type="hidden" name="imdb_id" value="$imdbId">
						<input type="submit" value="Toggle">
					</form>
				</td>
				<td>
					<form method="post">
						<input type="hidden" name="update" value="true">
						<input type="hidden" name="imdb_id" value="$imdbId">
						<input type="submit" value="Update">
					</form>
				<td>
			</tr>
ITEM;
}
?>

		</table>
	</body>
</html>
