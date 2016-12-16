<?php
include('kernel/core.php');
/*
	Global variables:
		$userLang - Returns user language
*/
    $r = $api->championMastery(44089098, 'euw');
    print_r($r);

?>