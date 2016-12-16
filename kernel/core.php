<?php
session_start();
$config = parse_ini_file('config.conf');
date_default_timezone_set($config['time.zone']); // CDT
require('class/security.php');
require('class/base.php');
require('class/riot.php');
if(!isset($_SESSION[$core->crypt('encrypt','userlang')]))
{
	
	(array_key_exists('HTTP_ACCEPT_LANGUAGE',$_SERVER)) ? $navLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'],0,2):$navLang=null;
	if(in_array($navLang,explode(',',$config['langs'])) == TRUE && $navLang != null)
	{
		$userLang = substr($navLang,0,2);
		$_SESSION[$core->crypt('encrypt','userlang')] = $userLang;
	}
	else
	{
		$userLang = $config['default.lang'];
		$_SESSION[$core->crypt('encrypt','userlang')] = $config['default.lang'];
	}
}
else
{
	if(in_array($_SESSION[$core->crypt('encrypt','userlang')],explode(',',$config['langs'])) == TRUE)
	{
		$userLang = substr($_SESSION[$core->crypt('encrypt','userlang')],0,2);
	}
	else
	{
		$userLang = $config['default.lang'];
	}
}
if($config['save.last.session.queries'])
{
	function rmLastSessionQueriesDir()
	{
		foreach(glob(WEB_BASEDIR.'kernel/logs/lastSessionQueries'.'/*') as $folderFiles)
		{
			if (is_dir($folderFiles))
			{
				rmLastSessionQueriesDir($folderFiles);
			}
			else
			{
				unlink($folderFiles);
			}
		} 
		rmdir(WEB_BASEDIR.'kernel/logs/lastSessionQueries');
	}
	if(is_dir(WEB_BASEDIR.'kernel/logs/lastSessionQueries'))
	{
		rmLastSessionQueriesDir();
	}
}
if($config['stats.generate'])
{
	if(!is_dir(WEB_BASEDIR.'/'.$config['database.dir'].'/SYSTEM'))
	{
		mkdir(WEB_BASEDIR.'/'.$config['database.dir'].'/SYSTEM');
	}	
	if(!is_dir(WEB_BASEDIR.'/'.$config['database.dir'].'/SYSTEM/stats'))
	{
		mkdir(WEB_BASEDIR.'/'.$config['database.dir'].'/SYSTEM/stats');
	}	
}