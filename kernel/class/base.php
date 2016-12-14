<?php
$core = new base;
define('URL',sprintf("%s://%s%s",isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME']:null,isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI']:null));
define('WEB_BASEDIR',__DIR__.'/../../');

class base{
	public static function addToDebugLog($log,$allowStatus = 'ALLOWED')
	{
		if(!is_dir(WEB_BASEDIR.'kernel/logs/'))
		{
			mkdir(WEB_BASEDIR.'kernel/logs/');
		}
		file_put_contents(WEB_BASEDIR.'kernel/logs/'.date('j_n_Y').'.log', '['.$allowStatus.']'.'['.date('H:i:s e').'] '.$log.PHP_EOL.PHP_EOL, FILE_APPEND);
	}
	public function time()
	{
		return round(microtime(true) * 1000);
	}
	public function crypt($action, $string) {
		$output = false;

		$encrypt_method = "AES-256-CBC";
		$secret_key = $GLOBALS['config']['hash.secretKey'];
		$secret_iv = $GLOBALS['config']['hash.secretKey2'];
		$key = hash('sha256', $secret_key);
		$iv = substr(hash('sha256', $secret_iv), 0, 16);

		if( $action == 'encrypt' ) {
			$output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
			$output = base64_encode($output);
		}
		else if( $action == 'decrypt' ){
			$output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
		}

		return $output;
	}
	public function regionDepure($region)
	{
		global $config;
		global $servers;
		if(array_key_exists($region,$servers))
		{
			return $region;
		}
		else
		{
			return $config['default.region'];
		}
	}
	public function compareLeague($l1, $l1div, $l2, $l2div)
	{
		switch($l1)
		{
			case 'CHALLENGER':
			$l1_points = 7;
			break;
			case 'MASTER':
			$l1_points = 6;
			break;
			case 'DIAMOND':
			$l1_points = 5;
			break;
			case 'PLATINUM':
			$l1_points = 4;
			break;
			case 'GOLD':
			$l1_points = 3;
			break;
			case 'SILVER':
			$l1_points = 2;
			break;
			case 'BRONZE':
			$l1_points = 1;
			break;
			case 'UNRANKED':
			$l1_points = 0;
			break;
		}
		switch($l2)
		{
			case 'CHALLENGER':
			$l2_points = 7;
			break;
			case 'MASTER':
			$l2_points = 6;
			break;
			case 'DIAMOND':
			$l2_points = 5;
			break;
			case 'PLATINUM':
			$l2_points = 4;
			break;
			case 'GOLD':
			$l2_points = 3;
			break;
			case 'SILVER':
			$l2_points = 2;
			break;
			case 'BRONZE':
			$l2_points = 1;
			break;
			case 'UNRANKED':
			$l2_points = 0;
			break;
		}
		if($l1div == 'UNRANKED' OR $l2div == 'UNRANKED')
		{
			if($l1div == 'UNRANKED')
			{
				return '2';
			}
			else
			{
				return '1';
			}			
		}
		else
		{
			if($l2_points == $l1_points)
			{
				switch(strtoupper($l1div))
				{
					case 'I':
					$l1_divison = 1;
					break;
					case 'II':
					$l1_divison = 2;
					break;
					case 'III':
					$l1_divison = 3;
					break;
					case 'IV':
					$l1_divison = 4;
					break;
					case 'V':
					$l1_divison = 5;
					break;
				}
				switch(strtoupper($l2div))
				{
					case 'I':
					$l2_divison = 1;
					break;
					case 'II':
					$l2_divison = 2;
					break;
					case 'III':
					$l2_divison = 3;
					break;
					case 'IV':
					$l2_divison = 4;
					break;
					case 'V':
					$l2_divison = 5;
					break;
				}
				if($l1_divison < $l2_divison)
				{
					return '1';
				}
				else
				{
					return '2';
				}
			}
			elseif($l2_points > $l1_points)
			{
				return '2';
			}
			elseif($l2_points < $l1_points)
			{
				return '1';
			}
		}
	}
	public function setNotify($text,$keyBase)
	{
		global $db;
		if($db->query('SELECT id FROM api_notify WHERE keyBase="'.$keyBase.'"')->num_rows == 0)
		{
			$db->query('INSERT INTO api_notify (description,keyBase,time) VALUES ("'.$text.'","'.$keyBase.'","'.$this->time().'")');
		}
	}
	public function setStatus($status,$key,$reason = null)
	{
		global $db;
		switch($status)
		{
			case 'disabled':
			$status = 'disabled';
			break;
			case 'updating':
			$status = 'updating';
			break;
			case 'enabled':
			$status = 'enabled';
			break;
			default:
			$status = 'disabled';
			break;
		}
		if($db->query('SELECT name FROM web_status WHERE name="'.$key.'"')->num_rows > 0)
		{
			$db->query('UPDATE web_status SET status="'.$status.'" ,reason="'.$reason.'" WHERE name="'.$key.'"');
		}
		else
		{
			$db->query('INSERT INTO web_status VALUES ("'.$key.'","'.$status.'","'.$reason.'")');
		}
	}
	public function getStatus($key)
	{
		global $db;
		if($db->query('SELECT name FROM web_status WHERE name="'.$key.'"')->num_rows > 0)
		{
			return $db->query('SELECT status FROM web_status WHERE name="'.$key.'"')->fetch_row()[0];
		}
		else
		{
			return 'disabled';
		}
	}
}