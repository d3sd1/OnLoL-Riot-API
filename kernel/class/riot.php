<?php
require('cache.php');
$api = new riotapi(new FileSystemCache());
$stats = new stats();
$servers = array('br' => 'br1', 'eune' => 'eun1', 'euw' => 'euw1', 'kr' => 'kr', 'lan' => 'la1', 'las' => 'la2', 'na' => 'na1', 'oce' => 'oc1', 'tr' => 'tr1', 'ru' => 'ru','jp' => 'jp1'); //Region => Platform ID,  'PBE' => 'PBE1' DISABLED
require('riotTournaments.php');
class riotapi {
	const API_URL = 'https://{region}.api.pvp.net/api/lol/{region}/v{version}/';
	const API_URL_V3 = 'https://{region}.api.riotgames.com/lol/';
	const API_URL_MASTERY = 'https://{region}.api.pvp.net/championmastery/location/{platform}/player/';
	const API_URL_FEATURED = 'https://{region}.api.pvp.net/observer-mode/rest/featured';
	const API_URL_STATIC = 'https://global.api.pvp.net/api/lol/static-data/{region}/v1.2/';
	const API_URL_SHARDS = 'http://status.leagueoflegends.com/shards';
	const API_URL_CURRENT_GAME = 'https://{region}.api.pvp.net/observer-mode/rest/consumer/getSpectatorGameInfo/';
	private $API_LIMIT_LEAGUES;
	private $API_LIMIT_SUMMONERS;
	private $API_LIMIT_TEAMS;
	private $API_RECENTGAMES_LIMIT;
	private $CHAMP_MASTERY_MAX_LEVEL;
	private $LONG_LIMIT_INTERVAL;	
	private $ACTUAL_SEASON;	
	private $FORCE_UPDATE;
	private $API_KEY;
	private $API_KEY_TYPE;
	private $RATE_LIMIT_LONG;	
	private $SHORT_LIMIT_INTERVAL;	
	private $RATE_LIMIT_SHORT;	
	private $CACHE;
	public $DEFAULT_REGION;
	private $ACTUAL_PATCH;

	public static $errorCodes = array(0 => 'NO_RESPONSE',400 => 'BAD_REQUEST',401 => 'UNAUTHORIZED',403 => 'ACCESS_DENIED',404 => 'NOT_FOUND',429 => 'RATE_LIMIT_EXCEEDED',500 => 'SERVER_ERROR',503 => 'UNAVAILABLE');

	public function __construct(CacheInterface $CACHE = null)
	{
		$this->API_KEY = $GLOBALS['config']['riot.api.key'];
		$this->API_KEY_TYPE = $GLOBALS['config']['riot.api.key.type'];
		$this->CACHE_DEFAULT_INTERVAL = $GLOBALS['config']['riot.api.cache.interval.default'];
		$this->DEFAULT_REGION = $GLOBALS['config']['default.region'];
		$this->CHAMP_MASTERY_MAX_LEVEL = $GLOBALS['config']['riot.api.summonerschampmastery.maxlevel'];
		$this->API_RECENTGAMES_LIMIT = $GLOBALS['config']['riot.api.limitperquery.recentgames'];
		$this->API_LIMIT_TEAMS = $GLOBALS['config']['riot.api.limitperquery.teamleagues'];
		$this->API_LIMIT_SUMMONERS = $GLOBALS['config']['riot.api.limitperquery.summoners'];
		$this->API_LIMIT_LEAGUES = $GLOBALS['config']['riot.api.limitperquery.leagues'];
		$this->FORCE_UPDATE = $GLOBALS['config']['force.update'];
		if($this->API_KEY_TYPE == 'DEV')
		{
			$this->LONG_LIMIT_INTERVAL = 600;
			$this->RATE_LIMIT_LONG = 500;
			$this->SHORT_LIMIT_INTERVAL = 10;
			$this->RATE_LIMIT_SHORT = 10;
		}
		else
		{
			$this->LONG_LIMIT_INTERVAL = 600;
			$this->RATE_LIMIT_LONG = 180000;
			$this->SHORT_LIMIT_INTERVAL = 10;
			$this->RATE_LIMIT_SHORT = 3000;
		}

		$this->shortLimitQueue = new SplQueue();
		$this->longLimitQueue = new SplQueue();

		$this->cache = $CACHE;
		$actualVersion = explode('.',$this->staticData('versions', $fulldata = false, $locale = 'en_US', $version = null, $region='NOT_SET', $id=null, $classCall = false)[0]);
		$this->ACTUAL_PATCH = $actualVersion[0].'.'.$actualVersion[1];
		$this->ACTUAL_SEASON = $this->actualSeason();
	}
	/* Internal Function */
	public function forceUpdate($status = true){
		if($status == true)
		{
			$this->FORCE_UPDATE = true;
		}
		else
		{
			$this->FORCE_UPDATE = false;
		}
	}
	/* Internal Function */
	private function updateLimitQueue($queue, $interval, $call_limit){
		
		while(!$queue->isEmpty()){
			$timeSinceOldest = time() - $queue->bottom();
			if($timeSinceOldest > $interval){
					$queue->dequeue();
			}
			elseif($queue->count() >= $call_limit){
				if($timeSinceOldest < $interval){ 
					sleep($interval - $timeSinceOldest);
				}
			}
			else {
				break;
			}
		}
		$queue->enqueue(time());
	}
	
	/* Internal Function */
	private function request($call, $dbPath, $dbFile, $dbTime = 'NOT_SET', $region = null, $otherQueries = false, $static = false) {
            if($this->API_KEY == null)
            {
                die('You must put an api key into kernel/config.conf in order to make calls.');
            }
	try{
		if($dbTime == 'NOT_SET')
		{
			$dbTime = $this->CACHE_DEFAULT_INTERVAL;
		}
		$url = str_replace('{region}', $region, $call) . ($otherQueries ? '&' : '?') . 'api_key=' . $this->API_KEY;
		$result = array();
		if($this->cache !== null){
			if(stristr($url,'%2c')) //Fix for multi query searchs
			{
				$baseUrl = explode('%2c',strtolower($url));
				$singleBaseUrl = null;
						
				$singleEndUrlData = explode('?',array_pop($baseUrl));
				$singleEndUrl = '?'.$singleEndUrlData[1];
				array_push($baseUrl,$singleEndUrlData[0]);
				if(strstr(end($baseUrl),'/'))
				{
					$baseUrl = str_replace('/'.explode('/',end($baseUrl))[1],null,$baseUrl); // Fix for /query urls
				}
				if($GLOBALS['config']['logs.enabled'])
				{
					base::addToDebugLog('Called MULTI-function '.debug_backtrace()[1]['function'].'(MULTI_VALUE)'.PHP_EOL.$url,'CACHE - ALLOWED');
				}
				foreach($baseUrl as $strNum => $strData)
				{
					if($strNum == 0)
					{
						$strClear = explode('/',$strData);
						$strSingle = array_pop($strClear);
						$singleBaseUrl = implode('/',$strClear).'/';
					}
					else
					{
						$strSingle = $strData;
					}
					$finalDataSerialized = str_replace(' ', null,strtolower(rawurldecode($strSingle)));
					$dbFileResult = str_replace('{strSingle}',$finalDataSerialized,$dbFile);
					if($this->cache->has($dbPath, $dbFileResult, $dbTime))
					{
						$result[$finalDataSerialized] = $this->cache->get($dbPath, $dbFileResult, $dbTime)[$finalDataSerialized];
					}
					else
					{
						$updateNeeded = true;
					}
				}
			}
			else
			{
				if($this->cache->has($dbPath, $dbFile, $dbTime))
				{
					$resultData = $this->cache->get($dbPath, $dbFile, $dbTime);
					
					if($GLOBALS['config']['logs.enabled'])
					{
						base::addToDebugLog('Called function '.debug_backtrace()[1]['function'].'()'.PHP_EOL.$url,'CACHE - ALLOWED');
					}
					if(is_array($resultData))
					{
						$result = $resultData;
					}
					else
					{
						$result = json_decode($resultData,true);
					}
				}
				else
				{
					$updateNeeded = true;
				}
			}
		}
		if($this->FORCE_UPDATE == true)
		{
			$updateNeeded = true;
		}
		if(!empty($updateNeeded)) {
			if ($static == true) {
				$this->updateLimitQueue($this->longLimitQueue, $this->LONG_LIMIT_INTERVAL, $this->RATE_LIMIT_LONG);
				$this->updateLimitQueue($this->shortLimitQueue, $this->SHORT_LIMIT_INTERVAL, $this->RATE_LIMIT_SHORT);
			}
	
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);	
		
			$result = curl_exec($ch);
			$resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
			curl_close($ch);
			if($resultCode == 429) {
				if($GLOBALS['config']['logs.enabled'])
				{
					base::addToDebugLog('Called function '.debug_backtrace()[1]['function'].'()'.PHP_EOL.$url,'API - DENIED (RATE_LIMIT)');
				}
				$time_header = get_headers($url,1);
				($time_header) ? $time_needed = (int) @$time_header['Retry-After']:$time_needed = 'NOT_SET';
				if(is_int($time_needed))
				{
					sleep($time_needed); //Sleep needed time and then reload function
					$this->request($call, $dbPath, $dbFile, $dbTime, $region, $otherQueries, $static);
				}
				else
				{
					throw new Exception(self::$errorCodes[429]);
				}
			}
			if($resultCode == 404) {
				if($GLOBALS['config']['logs.enabled'])
				{
					base::addToDebugLog('Called function '.debug_backtrace()[1]['function'].'('.@implode(',',debug_backtrace()[1]['args']).')'.PHP_EOL.$url,'API - ALLOWED_ERROR (NOT_FOUND)');
				}
				$jsonFile = $this->cache->getPath($dbPath, $dbFile);
				if(file_exists($jsonFile))
				{
					unlink($jsonFile);
				}
			}
			
			if($resultCode == 0) {
				if($GLOBALS['config']['logs.enabled'])
				{
					base::addToDebugLog('Called function '.debug_backtrace()[1]['function'].'('.@implode(',',debug_backtrace()[1]['args']).')'.PHP_EOL.$url,'API - NO_RESPONSE');
				}
				$this->request($call, $dbPath, $dbFile, $dbTime, $region, $otherQueries, $static);
			}
			
			if($resultCode == 200) {
				if($GLOBALS['config']['logs.enabled'])
				{
					base::addToDebugLog('Called function '.debug_backtrace()[1]['function'].'('.@implode(',',debug_backtrace()[1]['args']).')'.PHP_EOL.$url,'API - ALLOWED');
				}
				if($this->cache !== null){
					if(stristr($url,'%2c')) //Fix for multi query searchs
					{
						$baseUrl = explode('%2c',strtolower($url));
						$singleBaseUrl = null;
						
						$singleEndUrlData = explode('?',array_pop($baseUrl));
						$singleEndUrl = '?'.$singleEndUrlData[1];
						array_push($baseUrl,$singleEndUrlData[0]);
						if(strstr(end($baseUrl),'/'))
						{
							$baseUrl = str_replace('/'.explode('/',end($baseUrl))[1],null,$baseUrl); // Fix for /query urls
						}
						$baseData = json_decode($result,true);
						foreach($baseUrl as $strNum => $strData)
						{
							if($strNum == 0)
							{
								$strClear = explode('/',$strData);
								$strSingle = array_pop($strClear);
								$singleBaseUrl = implode('/',$strClear).'/';
							}
							else
							{
								$strSingle = $strData;
							}
							$finalDataSerialized = str_replace(' ', null,strtolower(rawurldecode($strSingle)));
							
							$finalDataResult = array($finalDataSerialized => $baseData[$finalDataSerialized]);
							$dbFileResult = str_replace('{strSingle}',$finalDataSerialized,$dbFile);
							$this->cache->put($dbPath, $dbFileResult, $finalDataResult, $dbTime);
						}
					}
					else
					{
						$this->cache->put($dbPath, $dbFile, $result, $dbTime);
					}
				}
			} else {
				throw new Exception(self::$errorCodes[$resultCode]);
			}
			if(is_array($result))
			{
				$result = $result;
			}
			else
			{
				$result = json_decode($result,true);
			}
		}
		if($GLOBALS['config']['save.last.query'])
		{
			$GLOBALS['core']->saveLastQueryLog('Query function: '.debug_backtrace()[1]['function'].'('.@implode(',',debug_backtrace()[1]['args']).')'.PHP_EOL.PHP_EOL.json_encode($result, JSON_FORCE_OBJECT|JSON_PRETTY_PRINT));
		}
		if($GLOBALS['config']['save.last.session.queries'])
		{
			$GLOBALS['core']->saveLastSessionQueries('Query function: '.debug_backtrace()[1]['function'].'('.@implode(',',debug_backtrace()[1]['args']).')'.PHP_EOL.PHP_EOL.json_encode($result, JSON_FORCE_OBJECT|JSON_PRETTY_PRINT),debug_backtrace()[1]['function']);
		}
		return $result;
	} catch (Exception $e) {
		if($GLOBALS['config']['save.errors.to.log'])
		{
			$GLOBALS['core']->saveToErrorLog('ERROR -> Function: '.debug_backtrace()[1]['function'].'('.@implode(',',debug_backtrace()[1]['args']).'), Message: '.$e->getMessage().PHP_EOL.$url);
		}
		if($GLOBALS['config']['show.errors.on.exec'])
		{
			echo 'ERROR -> Function: '.debug_backtrace()[1]['function'].'('.@implode(',',debug_backtrace()[1]['args']).'), Message: '.$e->getMessage().PHP_EOL.str_replace($GLOBALS['config']['riot.api.key'],null,$url);
		}
		return $e->getMessage();
    }
	}
	/* Scans a dir and outpots required json data */
	private function readStatDir($dir,$files)
	{
		if(!is_array($files))
		{
			$files = array($files);
		}
		$outputJson = array();
		if ($gestor = opendir($dir)) {
			while (false !== ($file = readdir($gestor))) {
				if ($file != '.' && $file != '..' && array_search(str_replace('.json',null,$file),$files) !== false) {
					$outputJson[str_replace('.json',null,$file)] = json_decode(file_get_contents($dir.((substr($dir, -1, 1) != '/')? '/':null).$file),true);
				}
			}
			closedir($gestor);
		}
		return $outputJson;
	}
	public function actualSeason()
        {
            return 'SEASON'.substr(date('Y'),0,-1).explode('.',$this->staticData('versions')[0])[0];
        }
	/* Actual patch: Returns actual patch or actual patch with dev patchs */
	public function actualPatch($devpatchs = false)
	{
		$actualVersion = explode('.',$this->staticData('versions')[0]);
		
		if($GLOBALS['config']['stats.generate'])
		{
			$statDir = WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/patches';
			$baseContent = array('global' => array('totalCalls' => 0), $this->ACTUAL_PATCH => array('callsThisPatch' => 0, 'devCalls' => 0, 'verCalls' => 0));
			if(!is_dir($statDir))
			{
				mkdir($statDir);
			}
			if(file_exists($statDir.'/global.json'))
			{
				if(!file_exists($statDir.'/'.$this->ACTUAL_PATCH.'.json'))
				{
					$saveStatForActualPatch = fopen($statDir.'/'.$this->ACTUAL_PATCH.'.json','w+');
					fwrite($saveStatForActualPatch, json_encode($baseContent[$this->ACTUAL_PATCH]));
					fclose($saveStatForActualPatch);
				}
				$preContent = $this->readStatDir($statDir,array('global',$this->ACTUAL_PATCH)); //dir + files to be changed
			}
			else
			{
				$preContent = $baseContent;
			}
			$preContent['global']['totalCalls']++;
			if(!array_key_exists($this->ACTUAL_PATCH,$preContent))
			{
				$preContent[$this->ACTUAL_PATCH] = array();
			}
			$preContent[$this->ACTUAL_PATCH]['callsThisPatch']++;
			if($devpatchs == true)
			{
				$preContent[$this->ACTUAL_PATCH]['devCalls']++;
			}
			else
			{
				$preContent[$this->ACTUAL_PATCH]['verCalls']++;
			}
			
			$saveStatGlobal = fopen($statDir.'/global.json','w+');
				
			fwrite($saveStatGlobal, json_encode($preContent['global']));
			unset($preContent['global']);
			fclose($saveStatGlobal);
			foreach($preContent as $patch => $patchValues)
			{
				$saveStatPatch = fopen($statDir.'/'.$patch.'.json','w+');
					
				fwrite($saveStatPatch, json_encode($patchValues));
				fclose($saveStatPatch);
			}
		}
		
		if($devpatchs == false)
		{
			return $actualVersion[0].'.'.$actualVersion[1];
		}
		else
		{
			$result = $this->staticData('versions')[0];
			return $result;
		}
	}
	/* Champion Data: Null returns all champ data. If ID has been set, it return only champ data */
	public function champion($id = null,$region = 'NOT_SET'){ //It uses same array keys than championFreeToPlay() on stats
		if($region == 'NOT_SET')
		{
			$region = $this->DEFAULT_REGION;
		}
		if($id == null)
		{
			$dbPath = 'champions';
			$dbFile = 'champions'; 
		}
		else
		{
			$dbPath = 'champions';
			$dbFile = $id;
		}
		
		$dbTime = $GLOBALS['config']['cache.champions'];
		$call = 'champion';
		($id != null) ? $call .= '/'.$id:null;
		$call = str_replace('{version}','1.2',self::API_URL) . $call;
		$return = $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		if($GLOBALS['config']['stats.generate'])
		{
			$statDir = WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/champions';
			$baseContent = array('global' => array('totalCalls' => 0, 'champIdGivenCalls' => 0, 'allChampCalls' => 0));
			$patchBaseContent = array('numberCalledTimes' => 0, 'lastDisabledTimeRanked' => 0, 'lastFreeToPlayTime' => 0, 'lastDisabledTime' => 0);
			if(!is_dir($statDir))
			{
				mkdir($statDir);
			}
			if(file_exists($statDir.'/global.json'))
			{
				if(!file_exists($statDir.'/'.$this->ACTUAL_PATCH.'.json'))
				{
					$saveStatForActualPatch = fopen($statDir.'/'.$this->ACTUAL_PATCH.'.json','w+');
					fwrite($saveStatForActualPatch, json_encode($baseContent[$this->ACTUAL_PATCH]));
					fclose($saveStatForActualPatch);
				}
				$preContent = $this->readStatDir($statDir,array('global',$this->ACTUAL_PATCH,'champId_'.$id)); //dir + files to be changed
			}
			else
			{
				$preContent = $baseContent;
			}
			if(!array_key_exists($this->ACTUAL_PATCH,$preContent))
			{
				$preContent[$this->ACTUAL_PATCH] = array('numberCalledTimes' => 0, 'champIdGivenCalls' => 0, 'allChampCalls' => 0);
			}
			$preContent['global']['totalCalls']++;
			$preContent[$this->ACTUAL_PATCH]['numberCalledTimes']++;
			if($id != null)
			{
				$preContent['global']['champIdGivenCalls']++;
				$preContent[$this->ACTUAL_PATCH]['champIdGivenCalls']++;
				if(!array_key_exists('champId_'.$id,$preContent[$this->ACTUAL_PATCH]))
				{
					$preContent[$this->ACTUAL_PATCH]['champId_'.$id] = $patchBaseContent;
				}
				$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['numberCalledTimes']++;
				if($return['rankedPlayEnabled'] == false)
				{
					$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastDisabledTimeRanked'] = time();
				}
				else
				{
					$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastDisabledTimeRanked'] = 0;
				}
				if($return['freeToPlay'] == true)
				{
					$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastFreeToPlayTime'] = time();
				}
				else
				{
					$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastFreeToPlayTime'] = 0;
				}
				if($return['active'] == false)
				{
					$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastDisabledTime'] = time();
				}
				else
				{
					$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastDisabledTime'] = 0;
				}
				/* Now do the same but on all patches */
				if(!array_key_exists('champId_'.$id,$preContent))
				{
					$preContent['champId_'.$id] = $patchBaseContent;
				}
				$preContent['champId_'.$id]['numberCalledTimes']++;
				if($return['rankedPlayEnabled'] == false)
				{
					$preContent['champId_'.$id]['lastDisabledTimeRanked'] = time();
				}
				else
				{
					$preContent['champId_'.$id]['lastDisabledTimeRanked'] = 0;
				}
				if($return['freeToPlay'] == true)
				{
					$preContent['champId_'.$id]['lastFreeToPlayTime'] = time();
				}
				else
				{
					$preContent['champId_'.$id]['lastFreeToPlayTime'] = 0;
				}
				if($return['active'] == false)
				{
					$preContent['champId_'.$id]['lastDisabledTime'] = time();
				}
				else
				{
					$preContent['champId_'.$id]['lastDisabledTime'] = 0;
				}
			}
			else
			{
				$preContent['global']['allChampCalls']++;
				$preContent[$this->ACTUAL_PATCH]['allChampCalls']++;
			}
			$saveStatGlobal = fopen($statDir.'/global.json','w+');
				
			fwrite($saveStatGlobal, json_encode($preContent['global']));
			unset($preContent['global']);
			fclose($saveStatGlobal);
			foreach($preContent as $patch => $patchValues)
			{
				$saveStatPatch = fopen($statDir.'/'.$patch.'.json','w+');
				fwrite($saveStatPatch, json_encode($patchValues));
				fclose($saveStatPatch);
			}
		}
		return $return;
	}
	/* Returns Free To Play champions */
	public function championFreeToPlay($region='NOT_SET'){ //It uses same array keys than champion() on stats
		if($region == 'NOT_SET')
		{
			$region = $this->DEFAULT_REGION;
		}
		$dbPath = 'champions';
		$dbFile = 'freetoplay_'.$region;
		$dbTime = $GLOBALS['config']['cache.champions'];
		$call = 'champion?freeToPlay=true';
		$call = str_replace('{version}','1.2',self::API_URL) . $call;
		$return = $this->request($call,$dbPath,$dbFile,$dbTime,$region,true);
		if($GLOBALS['config']['stats.generate'])
		{
			foreach($return['champions'] as $champ)
			{
				$id = $champ['id'];
				$statDir = WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/champions';
				$baseContent = array('global' => array('totalCalls' => 0, 'champIdGivenCalls' => 0, 'allChampCalls' => 0));
				$patchBaseContent = array('numberCalledTimes' => 0, 'lastDisabledTimeRanked' => 0, 'lastFreeToPlayTime' => 0, 'lastDisabledTime' => 0);
				if(!is_dir($statDir))
				{
					mkdir($statDir);
				}
				if(file_exists($statDir.'/global.json'))
				{
					if(!file_exists($statDir.'/'.$this->ACTUAL_PATCH.'.json'))
					{
						$saveStatForActualPatch = fopen($statDir.'/'.$this->ACTUAL_PATCH.'.json','w+');
						fwrite($saveStatForActualPatch, json_encode($baseContent[$this->ACTUAL_PATCH]));
						fclose($saveStatForActualPatch);
					}
					$preContent = $this->readStatDir($statDir,array('global',$this->ACTUAL_PATCH,'champId_'.$id)); //dir + files to be changed
				}
				else
				{
					$preContent = $baseContent;
				}
				if(!array_key_exists($this->ACTUAL_PATCH,$preContent))
				{
					$preContent[$this->ACTUAL_PATCH] = array('numberCalledTimes' => 0, 'champIdGivenCalls' => 0, 'allChampCalls' => 0);
				}
				$preContent['global']['totalCalls']++;
				$preContent[$this->ACTUAL_PATCH]['numberCalledTimes']++;
				if($id != null)
				{
					$preContent['global']['champIdGivenCalls']++;
					$preContent[$this->ACTUAL_PATCH]['champIdGivenCalls']++;
					if(!array_key_exists('champId_'.$id,$preContent[$this->ACTUAL_PATCH]))
					{
						$preContent[$this->ACTUAL_PATCH]['champId_'.$id] = $patchBaseContent;
					}
					$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['numberCalledTimes']++;
					if($champ['rankedPlayEnabled'] == false)
					{
						$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastDisabledTimeRanked'] = time();
					}
					else
					{
						$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastDisabledTimeRanked'] = 0;
					}
					if($champ['freeToPlay'] == true)
					{
						$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastFreeToPlayTime'] = time();
					}
					else
					{
						$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastFreeToPlayTime'] = 0;
					}
					if($champ['active'] == false)
					{
						$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastDisabledTime'] = time();
					}
					else
					{
						$preContent[$this->ACTUAL_PATCH]['champId_'.$id]['lastDisabledTime'] = 0;
					}
					/* Now do the same but on all patches */
					if(!array_key_exists('champId_'.$id,$preContent))
					{
						$preContent['champId_'.$id] = $patchBaseContent;
					}
					$preContent['champId_'.$id]['numberCalledTimes']++;
					if($champ['rankedPlayEnabled'] == false)
					{
						$preContent['champId_'.$id]['lastDisabledTimeRanked'] = time();
					}
					else
					{
						$preContent['champId_'.$id]['lastDisabledTimeRanked'] = 0;
					}
					if($champ['freeToPlay'] == true)
					{
						$preContent['champId_'.$id]['lastFreeToPlayTime'] = time();
					}
					else
					{
						$preContent['champId_'.$id]['lastFreeToPlayTime'] = 0;
					}
					if($champ['active'] == false)
					{
						$preContent['champId_'.$id]['lastDisabledTime'] = time();
					}
					else
					{
						$preContent['champId_'.$id]['lastDisabledTime'] = 0;
					}
				}
				else
				{
					$preContent['global']['allChampCalls']++;
					$preContent[$this->ACTUAL_PATCH]['allChampCalls']++;
				}
				$saveStatGlobal = fopen($statDir.'/global.json','w+');
					
				fwrite($saveStatGlobal, json_encode($preContent['global']));
				unset($preContent['global']);
				fclose($saveStatGlobal);
				foreach($preContent as $patch => $patchValues)
				{
					$saveStatPatch = fopen($statDir.'/'.$patch.'.json','w+');
					fwrite($saveStatPatch, json_encode($patchValues));
					fclose($saveStatPatch);
				}
			}
		}
		return $return;
	}
	
	/* Returns Champion Mastery for given user ID. */
	public function championMastery($summonerId,$region){ // ME HE QUEDADO AQUIX D
		global $servers;
		$dbPath = 'summoner/mastery';
		$dbFile = $summonerId.'_'.$region; 
		$dbTime = $GLOBALS['config']['cache.summonerschampmastery'];
		$call = $summonerId.'/champions';
		$call = str_replace('{platform}',$servers[$region],self::API_URL_MASTERY) . $call;
		$return = $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		if($GLOBALS['config']['stats.generate'])
		{
			/* Summoner max */
			$summonerLevelsMax = 0;
			$summonerTotalPoints = 0;
			$summonerMaxPointsChamp = 0;
			$summonerMaxPointsChampId = 0;
			foreach($return as $champ)
			{
				if($champ['championLevel'] == $this->CHAMP_MASTERY_MAX_LEVEL)
				{
					$summonerLevelsMax++;
				}
				if($champ['championPoints'] > $summonerMaxPointsChamp)
				{
					$summonerMaxPointsChamp = $champ['championPoints'];
					$summonerMaxPointsChampId = $champ['championId'];
				}
				$summonerTotalPoints += $champ['championPoints'];
			}
			/* Summoner champ max and write data */
			foreach($return as $champ)
			{
				if(file_exists(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/champMastery.json'))
				{
					$preContent = json_decode(file_get_contents(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/champMastery.json'),true);
					/* For global data */
					if($preContent['summonerMaxLevelsMax'] < $summonerLevelsMax)
					{
						$preContent['summonerMaxLevelsMax'] = $summonerLevelsMax;
						$preContent['summonerIdMaxLevelsMax'] = $summonerId;
					}
					if($preContent['summonerMaxPoints'] < $summonerTotalPoints)
					{
						$preContent['summonerMaxPoints'] = $summonerTotalPoints;
						$preContent['summonerIdMaxPoints'] = $summonerId;
					}
					if($preContent['summonerMaxPointsSingleChamp'] < $summonerMaxPointsChamp)
					{
						$preContent['summonerMaxPointsSingleChamp'] = $summonerMaxPointsChamp;
						$preContent['summonerIdMaxPointsSingleChamp'] = $summonerId;
						$preContent['summonerMaxPointsSingleChampId'] = $summonerMaxPointsChampId;
					}
					/* For this patch */
					if(!array_key_exists($this->ACTUAL_PATCH,$preContent))
					{
						$preContent[$this->ACTUAL_PATCH] = array();
					}
					if($preContent[$this->ACTUAL_PATCH]['summonerMaxLevelsMax'] < $summonerLevelsMax)
					{
						$preContent[$this->ACTUAL_PATCH]['summonerMaxLevelsMax'] = $summonerLevelsMax;
						$preContent[$this->ACTUAL_PATCH]['summonerIdMaxLevelsMax'] = $summonerId;
					}
					if($preContent[$this->ACTUAL_PATCH]['summonerMaxPoints'] < $summonerTotalPoints)
					{
						$preContent[$this->ACTUAL_PATCH]['summonerMaxPoints'] = $summonerTotalPoints;
						$preContent[$this->ACTUAL_PATCH]['summonerIdMaxPoints'] = $summonerId;
					}
					if($preContent[$this->ACTUAL_PATCH]['summonerMaxPointsSingleChamp'] < $summonerMaxPointsChamp)
					{
						$preContent[$this->ACTUAL_PATCH]['summonerMaxPointsSingleChamp'] = $summonerMaxPointsChamp;
						$preContent[$this->ACTUAL_PATCH]['summonerIdMaxPointsSingleChamp'] = $summonerId;
						$preContent[$this->ACTUAL_PATCH]['summonerMaxPointsSingleChampId'] = $summonerMaxPointsChampId;
					}
				}
				else
				{
					$preContent = array('summonerMaxLevelsMax' => $summonerLevelsMax, 'summonerIdMaxLevelsMax' => $summonerId, 'summonerMaxPoints' => $summonerTotalPoints, 'summonerIdMaxPoints' => $summonerId, 'summonerMaxPointsSingleChamp' => $summonerMaxPointsChamp, 'summonerIdMaxPointsSingleChamp' => $summonerId, 'summonerMaxPointsSingleChampId' => $summonerMaxPointsChampId);
					$preContent[$this->ACTUAL_PATCH] = array('summonerMaxLevelsMax' => $summonerLevelsMax, 'summonerIdMaxLevelsMax' => $summonerId, 'summonerMaxPoints' => $summonerTotalPoints, 'summonerIdMaxPoints' => $summonerId, 'summonerMaxPointsSingleChamp' => $summonerMaxPointsChamp, 'summonerIdMaxPointsSingleChamp' => $summonerId, 'summonerMaxPointsSingleChampId' => $summonerMaxPointsChampId);
				}
			
				$saveStat = fopen(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/champMastery.json','w+');
				fwrite($saveStat, json_encode($preContent));
				fclose($saveStat);
			}
		}
		
		
		return $return;
	}

	/* Returns Current Match info for given user ID. */
	public function currentGame($summonerId,$region){
		global $servers;
		global $config;
		$dbPath = 'summoner/livegame';
		$dbFile = $summonerId.'_'.$region; 
		$dbTime = $GLOBALS['config']['cache.currentgame'];
		$call = self::API_URL_CURRENT_GAME . strtoupper($servers[$region]) . '/' . $summonerId;
		$return = $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		if($GLOBALS['config']['stats.generate'])
		{
			if(file_exists(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/activeGames.json'))
			{
				$preContent = json_decode(file_get_contents(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/activeGames.json'),true);
			}
			else
			{
				$preContent = array('totalCalls' => 0, 'totalRegionCalls' => array(), $this->ACTUAL_PATCH => array('callsThisPatch' => 0));
			}
			$preContent[$this->ACTUAL_PATCH]['callsThisPatch']++;
			$preContent['totalCalls']++;
			if(!array_key_exists($region,$preContent[$this->ACTUAL_PATCH]))
			{
				$preContent[$this->ACTUAL_PATCH][$region] = array('callsThisPatch' => 0);
				$preContent[$this->ACTUAL_PATCH][$region]['callsThisPatch']++;
			}
			else
			{
				$preContent[$this->ACTUAL_PATCH][$region]['callsThisPatch']++;
			}
			if(!array_key_exists($region,$preContent['totalRegionCalls']))
			{
				$preContent['totalRegionCalls'][$region] = array('callsThisPatch' => 0);
				$preContent['totalRegionCalls'][$region]['callsThisPatch']++;
			}
			else
			{
				$preContent['totalRegionCalls'][$region]['callsThisPatch']++;
			}
			$saveStat = fopen(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/activeGames.json','w+');	
			fwrite($saveStat, json_encode($preContent));
			fclose($saveStat);
		}
		return $return;
	}
	
	/* Returns Featured Games for the given server. */
	public function featuredGames($region = 'euw'){
		$dbPath = 'featured';
		$dbFile = $region; 
		$dbTime = $GLOBALS['config']['cache.featuredgames'];
		$call = self::API_URL_FEATURED;
		$return = $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		if($GLOBALS['config']['stats.generate'])
		{
			foreach($return['gameList'] as $gameVal)
			{
				if(file_exists(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/featuredGames.json'))
				{
					$preContent = json_decode(file_get_contents(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/featuredGames.json'),true);
				}
				else
				{
					$preContent = array('totalCalls' => 0, 'totalRegionCalls' => array(), 'gameModes' => array(), $this->ACTUAL_PATCH => array('callsThisPatch' => 0, 'gameModes' => array()));
				}
				$preContent[$this->ACTUAL_PATCH]['callsThisPatch']++;
				$preContent['totalCalls']++;
				if(!array_key_exists($region,$preContent[$this->ACTUAL_PATCH]))
				{
					$preContent[$this->ACTUAL_PATCH][$region] = array('callsThisPatch' => 0);
					$preContent[$this->ACTUAL_PATCH][$region]['callsThisPatch']++;
					if(!array_key_exists($gameVal['gameMode'],$preContent[$this->ACTUAL_PATCH][$region]))
					{
						$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']] = array('callsThisPatch' => 0, 'summonersMostSearched' => array());
						$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['callsThisPatch']++;
						foreach($gameVal['participants'] as $summonerVal)
						{
							if(!array_key_exists($summonerVal['summonerName'],$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched']))
							{
								$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched'][$summonerVal['summonerName']] = 0;
							}
							$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched'][$summonerVal['summonerName']]++;
						}
					}
					else
					{
						$preContent[$this->ACTUAL_PATCH][$region]['callsThisPatch']++;
						if(!array_key_exists($gameVal['gameMode'],$preContent[$this->ACTUAL_PATCH]['gameModes']))
						{
							$preContent[$this->ACTUAL_PATCH]['gameModes'][$gameVal['gameMode']] = 0;
						}
						$preContent[$this->ACTUAL_PATCH]['gameModes'][$gameVal['gameMode']]++;
						if(!array_key_exists($gameVal['gameMode'],$preContent['gameModes']))
						{
							$preContent['gameModes'][$gameVal['gameMode']] = 0;
						}
						$preContent['gameModes'][$gameVal['gameMode']]++;
						foreach($gameVal['participants'] as $summonerVal)
						{
							if(!array_key_exists($summonerVal['summonerName'],$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched']))
							{
								$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched'][$summonerVal['summonerName']] = 0;
							}
							$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched'][$summonerVal['summonerName']]++;
						}
					}
				}
				else
				{
					$preContent[$this->ACTUAL_PATCH][$region]['callsThisPatch']++;
					$preContent[$this->ACTUAL_PATCH][$region]['callsThisPatch']++;
					if(!array_key_exists($gameVal['gameMode'],$preContent[$this->ACTUAL_PATCH][$region]))
					{
						$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']] = array('callsThisPatch' => 0, 'summonersMostSearched' => array());
						$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['callsThisPatch']++;
						foreach($gameVal['participants'] as $summonerVal)
						{
							if(!array_key_exists($summonerVal['summonerName'],$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched']))
							{
								$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched'][$summonerVal['summonerName']] = 0;
							}
							$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched'][$summonerVal['summonerName']]++;
						}
					}
					else
					{
						$preContent[$this->ACTUAL_PATCH][$region]['callsThisPatch']++;
						if(!array_key_exists($gameVal['gameMode'],$preContent[$this->ACTUAL_PATCH]['gameModes']))
						{
							$preContent[$this->ACTUAL_PATCH]['gameModes'][$gameVal['gameMode']] = 0;
						}
						$preContent[$this->ACTUAL_PATCH]['gameModes'][$gameVal['gameMode']]++;
						if(!array_key_exists($gameVal['gameMode'],$preContent['gameModes']))
						{
							$preContent['gameModes'][$gameVal['gameMode']] = 0;
						}
						$preContent['gameModes'][$gameVal['gameMode']]++;
						foreach($gameVal['participants'] as $summonerVal)
						{
							if(!array_key_exists($summonerVal['summonerName'],$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched']))
							{
								$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched'][$summonerVal['summonerName']] = 0;
							}
							$preContent[$this->ACTUAL_PATCH][$region][$gameVal['gameMode']]['summonersMostSearched'][$summonerVal['summonerName']]++;
						}
					}
				}
				if(!array_key_exists($region,$preContent['totalRegionCalls']))
				{
					$preContent['totalRegionCalls'][$region] = array('callsThisPatch' => 0);
					$preContent['totalRegionCalls'][$region]['callsThisPatch']++;
				}
				else
				{
					$preContent['totalRegionCalls'][$region]['callsThisPatch']++;
				}
				$saveStat = fopen(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/featuredGames.json','w+');	
				fwrite($saveStat, json_encode($preContent));
				fclose($saveStat);
			}
		}
		return $return;
	}
	
	/* Return Recent Games for given user ID */
	public function recentGames($summonerId,$region){
		$dbPath = 'summoner/games/';
		$dbFile = $summonerId.'_'.$region; 
		$dbTime = $GLOBALS['config']['cache.recentgames'];
		$call = str_replace('{version}','3',self::API_URL). 'matchlists/by-account/' . $summonerId . '/recent';
		return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
	}
	
	/* Returns League for given user ID, entry parameter shows only the summoner given if set . You can set multiple summoners. */
	public function league($summonerId, $region, $entry=null){
		($entry != null) ? $entry = '/entry':$entry=null;
		$dbPath = 'summoner/league';
		$dbTime = $GLOBALS['config']['cache.leagues'];
		$leagueVersion = '2.5';
		$call = 'league/by-summoner/';
		if (is_array($summonerId) && count($summonerId) > 1) {
			$dbFile = '{strSingle}'.'_'.$region.str_replace('/','_',$entry);
			if(count($summonerId) > $this->API_LIMIT_LEAGUES)
			{
				$summonerIds = array_chunk($summonerId,$this->API_LIMIT_LEAGUES,true);
				$return = null;
				foreach($summonerIds as $summonersChunked)
				{
					$call .= rawurlencode(implode(",", $summonersChunked));
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $entry;
					$return .= $this->request($call,$dbPath,$dbFile,$dbTime,$region);
				}
				return $return;
			}
			else
			{
				$call .= rawurlencode(implode(",", $summonerId));
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $entry;
				return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
			}
		}
		else {
			(is_array($summonerId)) ? $summonerId = implode(null,$summonerId):null;
			$dbFile = $summonerId.'_'.$region . str_replace('/','_',$entry); 
			$call .= $summonerId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $entry;
			return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		}
	}
	
	/* Returns League for given team ID, entry parameter (if $entry is null it returns all league summoners). You can set multiple teams. */ 
	public function teamLeague($teamId, $region, $entry=null){
		($entry != null) ? $entry = '/entry':$entry=null;
		$dbPath = 'team/league';
		$dbFile = $teamId.'_'.$region . str_replace('/','_',$entry); 
		$dbTime = $GLOBALS['config']['cache.leagues'];
		$leagueVersion = '2.5';
		$call = 'league/by-team/';
		if (is_array($teamId) && count($teamId) > 1) {
			$dbFile = '{strSingle}'.'_'.$region.str_replace('/','_',$entry);
			if(count($teamId) > $this->API_LIMIT_LEAGUES)
			{
				$summonerIds = array_chunk($teamId,$this->API_LIMIT_LEAGUES,true);
				$return = null;
				foreach($summonerIds as $summonersChunked)
				{
					$call .= rawurlencode(implode(",", $summonersChunked));
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $entry;
					$return .= $this->request($call,$dbPath,$dbFile,$dbTime,$region);
				}
				return $return . $entry;
			}
			else
			{
				$call .= rawurlencode(implode(",", $teamId));
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $entry;
				return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
			}
		}
		else {
			(is_array($teamId)) ? $teamId = implode(null,$teamId):null;
			$dbFile = $teamId.'_'.$region . str_replace('/','_',$entry); 
			$call .= $teamId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $entry;
			return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		}
	}
	
	/* Returns challenger lader, valid queues are: RANKED_SOLO_5x5, RANKED_TEAM_5x5, RANKED_TEAM_3x3 */
	public function challengerLeague($region,$queue = 'RANKED_SOLO_5x5') {
		$dbPath = 'league';
		$dbFile = 'challenger_'.$region.'_'.$queue; 
		$dbTime = $GLOBALS['config']['cache.ladders'];
		$call = 'league/challenger?type='.$queue;
		$call = str_replace('{version}','2.5',self::API_URL) . $call;
		return $this->request($call,$dbPath,$dbFile,$dbTime,$region, true);
	}
	
	/* Returns master lader, valid queues are: RANKED_SOLO_5x5, RANKED_TEAM_5x5, RANKED_TEAM_3x3 */
	public function masterLeague($region,$queue = 'RANKED_SOLO_5x5') {
		$dbPath = 'league';
		$dbFile = 'master_'.$region.'_'.$queue; 
		$dbTime = $GLOBALS['config']['cache.ladders'];
		$call = 'league/master?type='.$queue;
		$call = str_replace('{version}','2.5',self::API_URL) . $call;
		return $this->request($call,$dbPath,$dbFile,$dbTime,$region, true);
	}
	
	/* Static data loader. It's not counted on rate limit. $id is an optional value, just set if needed. Given Static data options (for $call) are [$call -> explain]:
	champion -> Full game champions data
	champion/$id -> Full data for given champion
	item -> Full game items data
	item/$id -> Full data for given item
	language-strings ->  Language names
	languages -> LoL Languages
	map -> Maps
	mastery -> Full game masteries data
	mastery/$id -> Full data for given mastery
	realm -> LoL Official assets links
	rune -> Full game runes data
	rune/$id -> Full data for given rune
	summoner-spell -> Full game summoner spells data
	summoner-spell/$id -> Full data for given summoner spell
	versions -> Full LoL Api versions */
	public function staticData($call, $fulldata = false, $locale = 'en_US', $version = null, $region='NOT_SET', $id=null, $classCall = false) {
		if($classCall == true)
		{
			//stats here
		}
		if($region == 'NOT_SET')
		{
			$region = $this->DEFAULT_REGION;
		}
		$dbPath = 'static/'.$call;
		$dbFile = ($id != null ? $id.'_' : null).$locale.'_'.($version != null ? $version.'_' : null).$region.'fulldata_'.$fulldata;
		$dbTime = $GLOBALS['config']['cache.staticdata'];
		$basecall = $call;
		$call = self::API_URL_STATIC . $call . ($id != null ? '/'.$id : null);
		$call .= '?locale='.$locale;
		($version != null) ? $call .= '&version='.$version:null;
		if($fulldata == true)
		{
			switch($basecall)
			{
				case 'champion':
				$call .= '&champData=all';
				break;
				case 'item':
				$call .= '&itemData=all';
				break;
				case 'mastery':
				$call .= '&masteryData=all';
				break;
				case 'rune':
				$call .= '&runeData=all';
				break;
				case 'summoner-spell':
				$call .= '&spellData=all';
				break;
			}
		}
		return $this->request($call,$dbPath,$dbFile,$dbTime, $region, (strpos($call,'?') !== false), true);
	}
	
	/* League of legends game status. You can set a region for retrieve only shards for it. Regions avaliable -> https://developer.riotgames.com/docs/regional-endpoints */
	public function shards($region=null) {
		($region != null) ? $region='/'.$region:null;
		$dbPath = 'shards';
		$dbFile = $region; 
		$dbTime = $GLOBALS['config']['cache.shards'];
		$call = self::API_URL_SHARDS . $region;
		return $this->request($call,$dbPath,$dbFile,$dbTime);
	}

	/* Returns details for given match id. TimeLine can be requested. If timeline data is requested, but doesn't exist, then the response won't include it. */
	public function match($matchId, $region, $timeLine=false) {
		$dbPath = 'match';
		$dbFile = $matchId.'_'.$region.($timeLine ? '_timeline' : null); 
		$dbTime = $GLOBALS['config']['cache.matches'];
		$call = str_replace('{version}','3',self::API_URL)  . 'matches/' . $matchId;
		if($timeLine)
		{
			$ret = $this->request($call,$dbPath,$dbFile,$dbTime,$region,$timeLine) . $this->request(str_replace('{version}','3',self::API_URL)  . 'timelines/by-match/' . $matchId,$dbPath,$dbFile,$dbTime,$region,$timeLine);
		}
		else
		{
			$ret = $this->request($call,$dbPath,$dbFile,$dbTime,$region,$timeLine);
		}
		return $ret;
	}

	/* Returns all ranked games played (since S3) given summoner id. */
	public function matchHistory($summonerId,$region) {
		$dbPath = 'summoner/matchlist';
		$dbFile = $summonerId.'_'.$region; 
		$dbTime = $GLOBALS['config']['cache.matchhistory'];
		$call = str_replace('{version}','3',self::API_URL) . 'v3/matchlists/by-account/' . $summonerId;
		return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
	}
	
	/* Returns a summoner's stats given summoner id. $option can be summary/ranked. */
	public function stats($summonerId,$region,$option='summary',$season='NOT_SET'){
		if($season == 'NOT_SET')
		{
			$season = $this->ACTUAL_SEASON;
		}
		$fixForSeasonNames = str_ireplace('SEASON',null,$season);
		if(strlen($fixForSeasonNames) == 1)
		{
			$season = 'SEASON201'.$fixForSeasonNames;
		}
		$dbPath = 'summoner/stats/'.$option;
		$dbFile = $summonerId.'_'.$region.'_'.strtoupper($season); 
		$dbTime = $GLOBALS['config']['cache.stats'];
		$call = 'stats/by-summoner/' . $summonerId . '/' . $option.'?season='.strtoupper($season);
		$call = str_replace('{version}','1.3',self::API_URL) . $call;
		return $this->request($call,$dbPath,$dbFile,$dbTime,$region,true);
	}


	/* Returns summoner info giving name */
	public function summonerByName($summonerName,$region){
		switch(strtolower($region))
		{
			case 'ru':
			$regionStr = 'RU';
			break;
			case 'kr':
			$regionStr = 'KR';
			break;
			case 'tr':
			$regionStr = 'TR1';
			break;
			case 'jp':
			$regionStr = 'JP1';
			break;
			case 'na':
			$regionStr = 'NA1';
			break;
			case 'eune':
			$regionStr = 'EUN1';
			break;
			case 'euw':
			$regionStr = 'EUW1';
			break;
			case 'oc':
			$regionStr = 'OC1';
			break;
			case 'lan':
			$regionStr = 'LA1';
			break;
			case 'las':
			$regionStr = 'LA2';
			break;
		}
		$leagueVersion = '3';
		$call = 'summoner/v'.$leagueVersion.'/summoners/by-name/';
		$dbPath = 'summoner/name';
		$dbTime = $GLOBALS['config']['cache.summoners'];
		if (is_array($summonerName) && count($summonerName) > 1) {
			$dbFile = '{strSingle}'.'_'.$region;
			if(count($summonerName) > $this->API_LIMIT_SUMMONERS)
			{
				$summonerNames = array_chunk($summonerName,$this->API_LIMIT_SUMMONERS,true);
				$return = null;
				foreach($summonerNames as $summonersChunked)
				{
					$dbFile = base64_encode($summonerName).'_'.$region; 
					$call .= strtolower(rawurlencode(implode(",",  $summonersChunked)));
					$call = str_replace('{version}',$leagueVersion,self::API_URL_V3) . $call;
					$return .= $this->request($call,$dbPath,$dbFile,$dbTime,$regionStr);
				}
				return $return;
			}
			else
			{
				$dbFile = base64_encode($summonerName).'_'.$region; 
				$call .= strtolower(rawurlencode(implode(",", $summonerName)));
				$call = str_replace('{version}',$leagueVersion,self::API_URL_V3) . $call;
				return $this->request($call,$regionStr);
			}
		}
		else {
			(is_array($summonerName)) ? $summonerName = implode(null,$summonerName):null;
			$dbFile = base64_encode($summonerName).'_'.$region; 
			$call .= strtolower(rawurlencode($summonerName));
			$call = str_replace('{version}',$leagueVersion,self::API_URL_V3) . $call;
			return $this->request($call,$dbPath,$dbFile,$dbTime,$regionStr);
		}
	}
	
	/* Returns summoner info given summoner id. You can set multiple summoners. $option can be: masteries,runes,name. */
	public function summonerBySummonerId($summonerId,$region,$option=null){
		$dbPath = 'summoner/id';
		$dbTime = $GLOBALS['config']['cache.summoners'];
		$call = 'summoners/';
		$leagueVersion = '3';
		switch ($option) {
			case 'masteries':
				$option = '/masteries';
				break;
			case 'runes':
				$option = '/runes';
				break;
			case 'name':
				$option = '/name';
				break;
			default:
			$option = null;
				break;
		}
		if (is_array($summonerId) && count($summonerId) > 1) {
			$dbFile = '{strSingle}'.'_'.$region.str_replace('/','_',$option);
			if(count($summonerId) > $this->API_LIMIT_SUMMONERS)
			{
				$summonerIds = array_chunk($summonerId,$this->API_LIMIT_SUMMONERS,true);
				$return = null;
				foreach($summonerIds as $summonersChunked)
				{
					$call .= rawurlencode(implode(",", $summonersChunked));
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $option;
					$return .= $this->request($call,$dbPath,$dbFile,$dbTime,$region);
				}
				return $return;
			}
			else
			{
				$call .= rawurlencode(implode(",", $summonerId));
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $option;
				return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
			}
		}
		else {
			(is_array($summonerId)) ? $summonerId = implode(null,$summonerId):null;
			$dbFile = $summonerId.'_'.$region.($option != null ? '_'.$option : null); 
			$call .= $summonerId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $option;
			return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		}
	}

	public function summonerByAccountId($accountId,$region,$option=null){
		$dbPath = 'summoner/id';
		$dbTime = $GLOBALS['config']['cache.summoners'];
		$call = 'summoners//by-account/';
		$leagueVersion = '3';
		switch ($option) {
			case 'masteries':
				$option = '/masteries';
				break;
			case 'runes':
				$option = '/runes';
				break;
			case 'name':
				$option = '/name';
				break;
			default:
			$option = null;
				break;
		}
		if (is_array($summonerId) && count($summonerId) > 1) {
			$dbFile = '{strSingle}'.'_'.$region.str_replace('/','_',$option);
			if(count($summonerId) > $this->API_LIMIT_SUMMONERS)
			{
				$summonerIds = array_chunk($summonerId,$this->API_LIMIT_SUMMONERS,true);
				$return = null;
				foreach($summonerIds as $summonersChunked)
				{
					$call .= rawurlencode(implode(",", $summonersChunked));
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $option;
					$return .= $this->request($call,$dbPath,$dbFile,$dbTime,$region);
				}
				return $return;
			}
			else
			{
				$call .= rawurlencode(implode(",", $summonerId));
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $option;
				return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
			}
		}
		else {
			(is_array($summonerId)) ? $summonerId = implode(null,$summonerId):null;
			$dbFile = $summonerId.'_'.$region.($option != null ? '_'.$option : null); 
			$call .= $summonerId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call . $option;
			return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		}
	}
	/* Gets the teams of a summoner, given summoner id. It can be multiple ids. */
	public function teamsBySummoner($summonerId,$region){
		$dbPath = 'summoner/team';
		$dbTime = $GLOBALS['config']['cache.teams'];
		$call = 'team/by-summoner/';
		$leagueVersion = '2.4';
		if (is_array($summonerId) && count($summonerId) > 1) {
			$dbFile = '{strSingle}'.'_'.$region;
			if(count($summonerId) > $this->API_LIMIT_TEAMS)
			{
				$summonerIds = array_chunk($summonerId,$this->API_LIMIT_TEAMS,true);
				$return = null;
				foreach($summonerIds as $summonersChunked)
				{
					$call .= rawurlencode(implode(",", $summonersChunked));
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
					$return .= $this->request($call,$dbPath,$dbFile,$dbTime,$region);
				}
				return $return;
			}
			else
			{
				$call .= rawurlencode(implode(",", $summonerId));
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
				return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
			}
		}
		else {
			(is_array($summonerId)) ? $summonerId = implode(null,$summonerId):null;
			$dbFile = $summonerId.'_'.$region.($option != null ? '_'.$option : null); 
			$call .= $summonerId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
			return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		}
	}
	
	/* Gets the teams of a summoner, given team id. It can be multiple ids. */
	public function teamsData($teamId,$region){
		$dbPath = 'team';
		$dbTime = $GLOBALS['config']['cache.teams'];
		$call = 'team/';
		$leagueVersion = '2.4';
		if (is_array($summonerId) && count($summonerId) > 1) {
			$dbFile = '{strSingle}'.'_'.$region;
			if(count($teamId) > $this->API_LIMIT_TEAMS)
			{
				$teamIds = array_chunk($teamId,$this->API_LIMIT_TEAMS,true);
				$return = null;
				foreach($teamIds as $teamsChunked)
				{
					$call .= rawurlencode(implode(",", $teamsChunked));
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
					$return .= $this->request($call,$dbPath,$dbFile,$dbTime,$region);
				}
				return $return;
			}
			else
			{
				$call .= rawurlencode(implode(",", $teamId));
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
				return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
			}
		}
		else {
			$call .= $teamId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
			return $this->request($call,$dbPath,$dbFile,$dbTime,$region);
		}
	}
	/* Gets the MMR for a level 30 ranked summoner, given ID and region */
	function getMMR($summonerId,$region) {
		global $api;
		try {
			$api->forceUpdate(false);
			$summonerLeague = $api->league($summonerId,$region);
		} catch(Exception $e) {
			throw new Exception('SUMMONER_NOT_RANKED');
		};
		$rg = $api->recentGames($summonerId,$region);
		for ($i = 0; $i < count($rg['games']); $i++)
		{
			$g = $rg['games'][$i];
			$subType = str_replace('_',' ',$g['subType']);             
			if (($subType == "RANKED SOLO 5x5" || $subType == "RANKED PREMADE 5x5" || $subType == "TEAM BUILDER DRAFT RANKED 5x5")  && $g['gameMode'] == "CLASSIC")
			{
				$game = $g;
				$champId = $g['championId'];
				$teamId = $g['teamId'];
				$win = $g['stats']['win'] ? 1 : 0;
				break;
			}
		}
		
		
		$ids = array();  
		$fellow = array();
		if(!empty($game)){
			array_push($fellow,array('id' => 0, 'cid' => $champId, 'teamId' => $teamId));
			foreach($game['fellowPlayers'] as $fp) {
				array_push($ids,$fp['summonerId']);
				$fellow[$fp['summonerId']] = array('cid' => $fp['championId'], 'teamId' => $fp['teamId']);
			}
			$lg = $api->league(implode(',',$ids),$region,true);
			$ranks100 = array();
			$ranks200 = array();
			
			foreach($ids as $pid) {
				if (!empty($pid) && $pid != $rg['summonerId']) {
					if(array_key_exists($pid,$lg))
					{
						switch ($lg[$pid][0]['tier'] . "" . $lg[$pid][0]['entries'][0]['division']) {
							case "BRONZEV": $trueElo = 870; break;
							case "BRONZEIV": $trueElo = 940; break;
							case "BRONZEIII": $trueElo = 1010; break;
							case "BRONZEII": $trueElo = 1080; break;
							case "BRONZEI": $trueElo = 1150; break;
							case "SILVERV": $trueElo = 1220; break;
							case "SILVERIV": $trueElo = 1290; break;
							case "SILVERIII": $trueElo = 1360; break;
							case "SILVERII": $trueElo = 1430; break;
							case "SILVERI": $trueElo = 1500; break;
							case "GOLDV": $trueElo = 1570; break;
							case "GOLDIV": $trueElo = 1640; break;
							case "GOLDIII": $trueElo = 1710; break;
							case "GOLDII": $trueElo = 1780; break;
							case "GOLDI": $trueElo = 1850; break;
							case "PLATINUMV": $trueElo = 1920; break;
							case "PLATINUMIV": $trueElo = 1990; break;
							case "PLATINUMIII": $trueElo = 2060; break;
							case "PLATINUMII": $trueElo = 2130; break;
							case "PLATINUMI": $trueElo = 2200; break;
							case "DIAMONDV": $trueElo = 2270; break;
							case "DIAMONDIV": $trueElo = 2340; break;
							case "DIAMONDIII": $trueElo = 2410; break;
							case "DIAMONDII": $trueElo = 2480; break;
							case "DIAMONDI": $trueElo = 2550; break;
							case "MASTERI": $trueElo = 2600; break;
							case "CHALLENGERI": $trueElo = 2900; break;
						}
						if($fellow[$pid]['teamId'] == 100){
							array_push($ranks100,round($trueElo + ($lg[$pid][0]['entries'][0]['leaguePoints'] * 70) / 100));
						}else{
							array_push($ranks200,round($trueElo + ($lg[$pid][0]['entries'][0]['leaguePoints'] * 70) / 100));
						}
					}
				}
			}
			if(count($ranks100) > 0 && count($ranks200) > 0)
			{
				$mmr100 = array_sum($ranks100) / count($ranks100);
				$mmr200 = array_sum($ranks200) / count($ranks200);
				
				$mmr_ewr = $teamId == 100 ? 1/(1+ (pow(10,(($mmr200-$mmr100)/400)))) :1/(1+ (pow(10,(($mmr100-$mmr200)/400))));
				$curr_mmr = $teamId == 100 ? $mmr100 : $mmr200;
				$mmr = round($curr_mmr + round(15*($win-$mmr_ewr)));
		
				if($mmr > 0 && $mmr <= 940) { $skillLevel = "BRONZE V"; }
				if($mmr > 940 && $mmr <= 1010) { $skillLevel = "BRONZE IV"; }
				if($mmr > 1010 && $mmr <= 1080) { $skillLevel = "BRONZE III"; }
				if($mmr > 1080 && $mmr <= 1150) { $skillLevel = "BRONZE II"; }
				if($mmr > 1150 && $mmr <= 1220) { $skillLevel = "BRONZE I"; }
				if($mmr > 1220 && $mmr <= 1290) { $skillLevel = "SILVER V"; }
				if($mmr > 1290 && $mmr <= 1360) { $skillLevel = "SILVER IV"; }
				if($mmr > 1360 && $mmr <= 1430) { $skillLevel = "SILVER III"; }
				if($mmr > 1430 && $mmr <= 1500) { $skillLevel = "SILVER II"; }
				if($mmr > 1500 && $mmr <= 1570) { $skillLevel = "SILVER I"; }
				if($mmr > 1570 && $mmr <= 1640) { $skillLevel = "GOLD V"; }
				if($mmr > 1640 && $mmr <= 1710) { $skillLevel = "GOLD IV"; }
				if($mmr > 1710 && $mmr <= 1780) { $skillLevel = "GOLD III"; }
				if($mmr > 1780 && $mmr <= 1850) { $skillLevel = "GOLD II"; }
				if($mmr > 1850 && $mmr <= 1920) { $skillLevel = "GOLD I"; }
				if($mmr > 1920 && $mmr <= 1990) { $skillLevel = "PLATINUM V"; }
				if($mmr > 1990 && $mmr <= 2060) { $skillLevel = "PLATINUM IV"; }
				if($mmr > 2060 && $mmr <= 2130) { $skillLevel = "PLATINUM III"; }
				if($mmr > 2130 && $mmr <= 2200) { $skillLevel = "PLATINUM II"; }
				if($mmr > 2200 && $mmr <= 2270) { $skillLevel = "PLATINUM I"; }
				if($mmr > 2270 && $mmr <= 2340) { $skillLevel = "DIAMOND V"; }
				if($mmr > 2340 && $mmr <= 2410) { $skillLevel = "DIAMOND IV"; }
				if($mmr > 2410 && $mmr <= 2480) { $skillLevel = "DIAMOND III"; }
				if($mmr > 2480 && $mmr <= 2550) { $skillLevel = "DIAMOND II"; }
				if($mmr > 2550 && $mmr <= 2600) { $skillLevel = "DIAMOND I"; }
				if($mmr > 2600 && $mmr <= 2900) { $skillLevel = "MASTER I"; }
				if($mmr > 2900 && $mmr <= 4500) { $skillLevel = "CHALLENGER I"; }
		
				if(strstr($skillLevel,"BRONZE") != null) { $tierSkill = "BRONZE"; }
				if(strstr($skillLevel,"SILVER") != null) { $tierSkill = "SILVER"; }
				if(strstr($skillLevel,"GOLD") != null) { $tierSkill = "GOLD"; }
				if(strstr($skillLevel,"PLATINUM") != null) { $tierSkill = "PLATINUM"; }
				if(strstr($skillLevel,"DIAMOND") != null) { $tierSkill = "DIAMOND"; }
				if(strstr($skillLevel,"MASTER") != null) { $tierSkill = "MASTER"; }
				if(strstr($skillLevel,"CHALLENGER") != null) { $tierSkill = "CHALLENGER"; }
				
				$divisionSkill = str_replace($tierSkill, null,$skillLevel);
		
				if($skillLevel == "BRONZE V") { $range = array('start' => 0,'end' => 940);} 
				if($skillLevel == "BRONZE IV") { $range = array('start' => 940,'end' => 1010);} 
				if($skillLevel == "BRONZE III") { $range = array('start' => 1010,'end' => 1080);} 
				if($skillLevel == "BRONZE II") { $range = array('start' => 1080,'end' => 1150);} 
				if($skillLevel == "BRONZE I") { $range = array('start' => 1150,'end' => 1220);} 
				if($skillLevel == "SILVER V") { $range = array('start' => 1220,'end' => 1290);} 
				if($skillLevel == "SILVER IV") { $range = array('start' => 1290,'end' => 1360);} 
				if($skillLevel == "SILVER III") { $range = array('start' => 1360,'end' => 1430);} 
				if($skillLevel == "SILVER II") { $range = array('start' => 1430,'end' => 1500);} 
				if($skillLevel == "SILVER I") { $range = array('start' => 1500,'end' => 1570);} 
				if($skillLevel == "GOLD V") { $range = array('start' => 1570,'end' => 1640);} 
				if($skillLevel == "GOLD IV") { $range = array('start' => 1640,'end' => 1710);} 
				if($skillLevel == "GOLD III") { $range = array('start' => 1710,'end' => 1780);} 
				if($skillLevel == "GOLD II") { $range = array('start' => 1780,'end' => 1850);} 
				if($skillLevel == "GOLD I") { $range = array('start' => 1850,'end' => 1920);} 
				if($skillLevel == "PLATINUM V") { $range = array('start' => 1920,'end' => 1990);} 
				if($skillLevel == "PLATINUM IV") { $range = array('start' => 1990,'end' => 2060);} 
				if($skillLevel == "PLATINUM III") { $range = array('start' => 2060,'end' => 2130);} 
				if($skillLevel == "PLATINUM II") { $range = array('start' => 2130,'end' => 2200);} 
				if($skillLevel == "PLATINUM I") { $range = array('start' => 2130,'end' => 2270);} 
				if($skillLevel == "DIAMOND V") { $range = array('start' => 2270,'end' => 2340);} 
				if($skillLevel == "DIAMOND IV") { $range = array('start' => 2340,'end' => 2410);} 
				if($skillLevel == "DIAMOND III") { $range = array('start' => 2410,'end' => 2480);} 
				if($skillLevel == "DIAMOND II") { $range = array('start' => 2480,'end' => 2550);} 
				if($skillLevel == "DIAMOND I") { $range = array('start' => 2550,'end' => 2600);} 
				if($skillLevel == "MASTER I") { $range = array('start' => 2600,'end' => 2900);} 
				if($skillLevel == "CHALLENGER I") { $range = array('start' => 2900,'end' => 4500);} 
		
				$srp = round(((($mmr - $range['start']) / ($range['end'] - $range['start'])) * 100));
				$skillrange_percentile = $srp < 10 ? 10 : $srp > 90 ? 90 : $srp;
				return array('SKILLRANGE' => (100 - $skillrange_percentile), 'TIER' => $tierSkill, 'DIVISION' => $divisionSkill, 'MMR' => $mmr);
			}
			else
			{
				//'NOT_RANKED_PARTNERS';
			}
		}
		else
		{
			//'NO_RECENT_GAMES';
		}
	}
	
}
class stats{
	/* Scans a dir and outpots all json data */
	private function readStatDirFull($dir)
	{
		$outputJson = array();
		if ($gestor = opendir($dir)) {
			while (false !== ($file = readdir($gestor))) {
				if ($file != "." && $file != "..") {
					$outputJson[str_replace('.json',null,$file)] = json_decode(file_get_contents($dir.((substr($dir, -1, 1) != '/')? '/':null).$file),true);
				}
			}
			closedir($gestor);
		}
		return $outputJson;
	}
	/* Scans a dir and outpots required json data */
	private function readStatDir($dir,$files)
	{
		if(!is_array($files))
		{
			$files = array($files);
		}
		$outputJson = array();
		if ($gestor = opendir($dir)) {
			while (false !== ($file = readdir($gestor))) {
				if ($file != '.' && $file != '..' && array_search(str_replace('.json',null,$file),$files) !== false) {
					$outputJson[str_replace('.json',null,$file)] = json_decode(file_get_contents($dir.((substr($dir, -1, 1) != '/')? '/':null).$file),true);
				}
			}
			closedir($gestor);
		}
		return $outputJson;
	}
	/* Returns stats for actualPatch() */
	public function generalPatches($patches = 'all')
	{
		if($patches == 'all')
		{
			return $this->readStatDirFull(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/patches');
		}
		else
		{
			return $this->readStatDir(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/patches',$patches);
		}
	}
	/* Return stats for champion() */
	public function champStatusInformation($patches = 'all')
	{
		if($patches == 'all')
		{
			return $this->readStatDirFull(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/champions');
		}
		else
		{
			return $this->readStatDir(WEB_BASEDIR.'/'.$GLOBALS['config']['database.dir'].'/SYSTEM/stats/champions',$patches);
		}
	}
}