<?php
$core = new base;
define('URL',$config['web.url']);
class base{
	public function time()
	{
		return round(microtime(true) * 1000);
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