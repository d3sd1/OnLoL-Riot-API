<?php
include('kernel/core.php');

try {
    $r = $api->summonerById(array(44089097,50638126), 'euw');
    print_r(json_encode($r));
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
};

?>