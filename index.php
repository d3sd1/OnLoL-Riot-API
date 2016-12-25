<?php
include('kernel/core.php');
/*
	Global variables:
		$userLang - Returns user language
*/
    $r = $api->championFreeToPlay();
    print_r($r);
	echo '<br>Stats given:<br>';
	$statsGiven = $stats->champStatusInformation();
	print_r($statsGiven);
?>