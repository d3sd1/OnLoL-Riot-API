<?php
include('kernel/core.php');
/*
	Global variables:
		$userLang - Returns user language
*/
    $r = $api->actualPatch( 'euw');
    print_r($r);
	echo '<br>Stats given:<br>';
	$statsGiven = $stats->generalPatches();
	print_r($statsGiven);
?>