<?php
include('kernel/core.php');
/*
	Global variables:
		$userLang - Returns user language
*/
try {
    $r = $api->stats(44089097, 'euw');
    print_r(json_encode($r));
	$api->actualPatch();
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
};

?>