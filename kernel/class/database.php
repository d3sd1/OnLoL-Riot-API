<?php
$db_host = $config['mysql.host'];
$db_user = $config['mysql.user'];
$db_pass = $config['mysql.pass'];
$db_base = $config['mysql.db'];
$db = new mysqli($db_host,$db_user,$db_pass,$db_base);
if ($db->connect_errno) {
    exit();
}
$db ->query("SET NAMES 'utf8'"); //Encode ES types