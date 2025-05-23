<?php

	$mysqli = new mysqli("localhost", "lazzarini21", "", "mylazzarini21");

	if ($mysqli->connect_errno) {
		printf("Connect failed: %s\n", $mysqli->connect_error);
		exit();
	}

?>