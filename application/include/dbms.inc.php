<?php

    $host = "localhost";
    $user = "lazzarini21";
    $password = "";
    $database = "my_lazzarini21";

    $conn = new mysqli($host, $user, $password, $database);
        // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error . "<br/>");
    }

    /* connection to mysql succesful

?>