
<?php

require_once 'private/shared.php';

$config = getConfig();

$query = $config->getDb()->getShowsByLastReleased();

?>
<html>
	<body>
		<table>
			<tr>
				<th>Show</th>
				<th>Last Release</th>
				<th>Last Checked</th>
			</tr>
<?php
while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
	$url = "https://www.imdb.com/title/" . $row['imdb_id'] . "/";
	$name = $row['name'];
	$releaseDate = strftime("%Y-%m-%d", $row['released']);
	$checkDate = strftime("%Y-%m-%d", $row['last_checked']);

	echo <<<ITEM
			<tr>
				<td><a href="$url">$name</a></td>
				<td>$releaseDate</td>
				<td>$checkDate</td>
			</tr>
ITEM;
}
?>

		</table>
	</body>
</html>
