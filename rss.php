<?php

if (isset($_GET['id'])) {
	# Some RSS readers don't parse correctly and rely on link. I include a nonsense link that points back here,
	# so if I see it being fetched we can just quickly do nothing.
	http_response_code(200);
	return 0;
}

require 'private/shared.php';

$db = getDb();
$config = getConfig();
updateLeastRecentShow($db, $config);

$results = $db->query(<<<SQL
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

header('Content-Type: application/xml');

?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>Series Tracker</title>
		<link>https://ntun.es/series/rss.php</link>
		<description>Release information on shows we're watching</description>
		<ttl>240</ttl>
		<atom:link href="https://ntun.es/series/rss.php" rel="self" type="application/rss+xml"/>
<?php
$lastBuild = false;
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
	# You can really tell the sort of people who designed RSS by this obnoxious format
	$pubDate = strftime("%a, %d %b %Y %T GMT", $row['released']);

	if (!$lastBuild) {
		echo <<<BUILD
		<lastBuildDate>$pubDate</lastBuildDate>

BUILD;
		$lastBuild = true;
	}

	$name = $row['name'];
	$seasonNumber = $row['season_number'];
	$guid = $row['imdb_id'] . '-S' . $seasonNumber;
	# See above - without this phony link some readers break.
	$link = $_SERVER['REQUEST_URI'] . "?id=$guid";

	echo <<<ITEM
		<item>
			<title>$name, Season $seasonNumber</title>
			<pubDate>$pubDate</pubDate>
			<guid isPermalink="false">$guid</guid>
			<link>$link</link>
			<category>$name</category>
		</item>

ITEM;
}
?>
	</channel>
</rss>
