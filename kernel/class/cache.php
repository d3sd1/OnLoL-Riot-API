<?php
if(!@$config['hash.secretKey'])
{
	$writeKey1 = fopen(WEB_BASEDIR.'/kernel/config.conf', 'a+');
	fwrite($writeKey1, PHP_EOL.'hash.secretKey='.md5(microtime().rand()).PHP_EOL);
	fclose($writeKey1);
	$key1Written = true;
}
if(!@$config['hash.secretKey2'])
{
	$writeKey2 = fopen(WEB_BASEDIR.'/kernel/config.conf', 'a+');
	fwrite($writeKey2, (!$key1Written) ? PHP_EOL:null.'hash.secretKey2='.md5(microtime().rand()));
	fclose($writeKey2);
}
$config = parse_ini_file(WEB_BASEDIR.'/kernel/config.conf');
interface CacheInterface {

}

class FileSystemCache implements CacheInterface {
	
	public function has($dbPath, $dbFile, $interval = 'NOT_SET')
	{
		if($interval == 'NOT_SET')
		{
			$interval = $GLOBALS['config']['riot.api.cache.interval.default'];
		}
		if ( ! file_exists($this->getPath($dbPath, $dbFile)))
			return false;

		$entry = $this->load($dbPath, $dbFile);
		return !$this->expired($dbPath, $dbFile, $interval);
	}

	public function get($dbPath, $dbFile, $interval = 'NOT_SET')
	{
		if($interval == 'NOT_SET')
		{
			$interval = $GLOBALS['config']['riot.api.cache.interval.default'];
		}
		$entry = $this->load($dbPath, $dbFile);

		if ( ! $this->expired($dbPath, $dbFile, $interval))
			$data = $entry;

		return $data;
	}
	
	public function put($dbPath, $dbFile, $data)
	{
		$encPath = $this->getPath($dbPath, $dbFile);
		clearstatcache(true,$encPath);
		file_put_contents($encPath, json_encode($data));
	}

	private function load($dbPath, $dbFile)
	{
		return json_decode(file_get_contents($this->getPath($dbPath, $dbFile)),true);
	}

	public function getPath($dbPath, $dbFile)
	{
		if ( ! file_exists(WEB_BASEDIR . '/'.$GLOBALS['config']['database.dir'].'/' . $dbPath))
		{
			mkdir(WEB_BASEDIR . '/'.$GLOBALS['config']['database.dir'].'/' . $dbPath, 0777, true);
		}
		return WEB_BASEDIR . '/'.$GLOBALS['config']['database.dir'].'/' . $dbPath . '/' . $this->hash($dbFile) . '.json';
	}

	private function expired($dbPath, $dbFile, $interval)
	{
		return time() >= (filemtime($this->getPath($dbPath, $dbFile)) + $interval);
	}

	private function hash($hash)
	{
		return md5($GLOBALS['config']['hash.secretKey'].$hash.$GLOBALS['config']['hash.secretKey2']);
	}

}