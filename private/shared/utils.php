<?php

function isValidImdbId($imdbId) {
	return preg_match('/^tt\d+$/', $imdbId) == 1;
}

?>
