<?php
define('JSON_STORE_SECRET_KEY', $config['riot.api.cache.key']);
define('JSON_STORE_SECRET_KEY2', $config['riot.api.cache.key2']);
interface CacheInterface {

	public function has($dbPath, $dbFile);

	public function get($dbPath, $dbFile);

	public function put($dbPath, $dbFile, $data, $ttl = 0);

}

class FileSystemCache implements CacheInterface {
	
	public function has($dbPath, $dbFile)
	{
		if ( ! file_exists($this->getPath($dbPath, $dbFile)))
			return false;

		$entry = $this->load($dbPath, $dbFile);
		return !$this->expired($entry);
	}

	public function get($dbPath, $dbFile)
	{
		$entry = $this->load($dbPath, $dbFile);

		$data = null;

		if ( ! $this->expired($entry))
			$data = $entry['data'];

		return $data;
	}
	
	public function put($dbPath, $dbFile, $data, $ttl = 0)
	{
		$this->store($dbPath, $dbFile, $data, $ttl, time());
	}

	private function load($dbPath, $dbFile)
	{
		return json_decode(file_get_contents($this->getPath($dbPath, $dbFile)),true);
	}

	private function store($dbPath, $dbFile, $data, $ttl, $createdAt)
	{
		$entry = array(
			'createdAt' => $createdAt,
			'ttl' => $ttl,
			'data' => $data
		);

		file_put_contents($this->getPath($dbPath, $dbFile), json_encode($entry));
	}

	public function getPath($dbPath, $dbFile)
	{
		if ( ! file_exists(WEB_BASEDIR . '/' . DATABASE_PATH .'/' . $dbPath))
			mkdir(WEB_BASEDIR . '/' . DATABASE_PATH . '/' . $dbPath, 0777, true);
		return WEB_BASEDIR . '/' . DATABASE_PATH . '/' . $dbPath . '/' . $this->hash($dbFile) . '.json';
	}

	private function expired($entry)
	{
		return $entry === null || time() >= ($entry['createdAt'] + $entry['ttl']);
	}

	private function hash($hash)
	{
		return md5(JSON_STORE_SECRET_KEY.$hash.JSON_STORE_SECRET_KEY2);
	}

}