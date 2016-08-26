<?php
require('cache.php');
$api = new riotapi(new FileSystemCache('cache/'),$config['riot.api.key.type']);
define('API_KEY',$config['riot.api.key']);
define('CACHE_LIFETIME_SECONDS',$config['riot.api.cachetime']);
$servers = array('br' => 'br1', 'eune' => 'eun1', 'euw' => 'euw1', 'kr' => 'kr', 'lan' => 'la1', 'las' => 'la2', 'na' => 'na1', 'oce' => 'oc1', 'tr' => 'tr1', 'ru' => 'ru','jp' => 'jp1'); //Region => Platform ID,  'PBE' => 'PBE1' DISABLED
class riotapi {
	
	
	const API_URL = 'https://{region}.api.pvp.net/api/lol/{region}/v{version}/';
	const API_URL_MASTERY = 'https://{region}.api.pvp.net/championmastery/location/{platform}/player/';
	const API_URL_FEATURED = 'https://{region}.api.pvp.net/observer-mode/rest/featured';
	const API_URL_STATIC = 'https://global.api.pvp.net/api/lol/static-data/{region}/v1.2/';
	const API_URL_SHARDS = 'http://status.leagueoflegends.com/shards';
	const API_URL_CURRENT_GAME = 'https://{region}.api.pvp.net/observer-mode/rest/consumer/getSpectatorGameInfo/';
	const API_LIMIT_LEAGUES = 10; //Max summoners leagues search per query
	const API_LIMIT_SUMMONERS = 40; //Max summoners search per query
	const API_LIMIT_TEAMS = 10; //Max teams search per query

	private $LONG_LIMIT_INTERVAL;	
	private $RATE_LIMIT_LONG;	
	private $SHORT_LIMIT_INTERVAL;	
	private $RATE_LIMIT_SHORT;	
	private $cache;


	private static $errorCodes = array(0   => 'NO_RESPONSE',
									   400 => 'BAD_REQUEST',
									   401 => 'UNAUTHORIZED',
									   403 => 'ACCESS_DENIED',
									   404 => 'NOT_FOUND',
									   429 => 'RATE_LIMIT_EXCEEDED',
									   500 => 'SERVER_ERROR',
									   503 => 'UNAVAILABLE');

	public function __construct(CacheInterface $cache = null,$API_KEY_TYPE = 'DEV')
	{
		if($API_KEY_TYPE == 'DEV')
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

		$this->cache = $cache;
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
	private function request($call, $region = null, $otherQueries = false, $static = false) {
		$url = str_replace('{region}', $region, $call) . ($otherQueries ? '&' : '?') . 'api_key=' . API_KEY;
		$result = array();
		if($this->cache !== null){
			if(stristr($url,'%2c')) //Fix for multi query searchs
			{
				$baseUrl = explode('%2c',$url);
				$singleBaseUrl = null;
						
				$singleEndUrlData = explode('?',array_pop($baseUrl));
				$singleEndUrl = '?'.$singleEndUrlData[1];
				array_push($baseUrl,$singleEndUrlData[0]);
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
							
					if($this->cache->has($singleBaseUrl . $strSingle . $singleEndUrl))
					{
						$result[$finalDataSerialized] = $this->cache->get($singleBaseUrl . $strSingle . $singleEndUrl)[$finalDataSerialized];
					}
					else
					{
						$updateNeeded = true;
					}
				}
			}
			else
			{
				if($this->cache->has($url))
				{
					$result = json_decode($this->cache->get($url),true);
				}
				else
				{
					$updateNeeded = true;
				}
			}
		}

		if(!empty($updateNeeded)) {
			
			if ($static == true) {
				$this->updateLimitQueue($this->longLimitQueue, $this->LONG_LIMIT_INTERVAL, $this->RATE_LIMIT_LONG);
				$this->updateLimitQueue($this->shortLimitQueue, $this->SHORT_LIMIT_INTERVAL, $this->RATE_LIMIT_SHORT);
			}
			
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);	
		
			$result = curl_exec($ch);
			$resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			curl_close($ch);
			if($resultCode == 429) {
				$time_header = get_headers($url,1);
				($time_header) ? $time_needed = (int) $time_header['Retry-After']:$time_needed = 'NOT_SET';
				if(is_int($time_needed))
				{
					sleep($time_needed); //Sleep needed time and then reload function
					$this->request($call, $region, $otherQueries, $static);
				}
				else
				{
					throw new Exception(self::$errorCodes[429]);
				}
			}
			
			if($resultCode == 200) {
				if($this->cache !== null){
					if(stristr($url,'%2c')) //Fix for multi query searchs
					{
						$baseUrl = explode('%2c',$url);
						$singleBaseUrl = null;
						
						$singleEndUrlData = explode('?',array_pop($baseUrl));
						$singleEndUrl = '?'.$singleEndUrlData[1];
						array_push($baseUrl,$singleEndUrlData[0]);
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
							$this->cache->put($singleBaseUrl . $strSingle . $singleEndUrl, $finalDataResult, CACHE_LIFETIME_SECONDS);
						}
					}
					else
					{
						$this->cache->put($url, $result, CACHE_LIFETIME_SECONDS);
					}
				}
			} else {
				throw new Exception(self::$errorCodes[$resultCode]);
			}
			$result = json_decode($result,true);
		}
		return $result;
	}
	
	/* Internal Function */
	public function debug($message) {
		echo "<pre>";
		print_r($message);
		echo "</pre>";
	}
	
	/* Champion Data: Null returns all champ data. If ID has been set, it return only champ data */
	public function champion($id = null){
		$call = 'champion';
		($id != null) ? $call .= '/'.$id:null;
		$call = str_replace('{version}','1.2',self::API_URL) . $call;
		return $this->request($call);
	}
	/* Returns Free To Play champions */
	public function championFreeToPlay(){
		$call = 'champion?freeToPlay=true';
		$call = str_replace('{version}','1.2',self::API_URL) . $call;
		return $this->request($call);
	}
	
	/* Returns Champion Mastery for given user ID. */
	public function championMastery($summonerId,$region){
		$call = $summonerId.'/champions';
		$call = str_replace('{platform}',$servers[$region],self::API_URL_MASTERY) . $call;
		return $this->request($call,$region);
	}

	/* Returns Current Match info for given user ID. */
	public function currentGame($id,$region){
		$call = self::API_URL_CURRENT_GAME . strtoupper($servers[$region]) . '/' . $id;
		return $this->request($call,$region);
	}
	
	/* Returns Featured Games for the given server. */
	public function featuredGames($region){
		$call = self::API_URL_FEATURED;
		return $this->request($call,$region);
	}
	
	/* Return Recent Games for given user ID */
	public function recentGames($summonerId,$region){
		$call = 'game/by-summoner/' . $summonerId . '/recent';
		$call = str_replace('{version}','1.3',self::API_URL). $call;
		return $this->request($call,$region);
	}
	
	/* Returns League for given user ID, entry parameter shows only the summoner given if set . You can set multiple summoners. */
	public function league($summonerId, $region, $entry=null){
		($entry != null) ? $entry = '/entry':$entry=null;
		$leagueVersion = '2.5';
		$call = 'league/by-summoner/';
		if (is_array($summonerId)) {
			if(count($summonerId) > self::API_LIMIT_LEAGUES)
			{
				$summonerIds = array_chunk($summonerId,self::API_LIMIT_LEAGUES,true);
				$return = null;
				foreach($summonerIds as $summonersChunked)
				{
					$call .= implode(",", $summonersChunked);
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
					$return .= $this->request($call,$region);
				}
				return $return . $entry;
			}
			else
			{
				$call .= implode(",", $summonerId);
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
				return $this->request($call,$region) . $entry;
			}
		}
		else {
			$call .= $summonerId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
			return $this->request($call,$region) . $entry;
		}
	}
	
	/* Returns League for given team ID, entry parameter (if $entry is null it returns all league summoners). You can set multiple teams. */ 
	public function teamLeague($teamId, $region, $entry=null){
		($entry != null) ? $entry = '/entry':$entry=null;
		$leagueVersion = '2.5';
		$call = 'league/by-team/';
		if (is_array($teamId)) {
			if(count($teamId) > self::API_LIMIT_LEAGUES)
			{
				$summonerIds = array_chunk($teamId,self::API_LIMIT_LEAGUES,true);
				$return = null;
				foreach($summonerIds as $summonersChunked)
				{
					$call .= implode(",", $summonersChunked);
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
					$return .= $this->request($call,$region);
				}
				return $return . $entry;
			}
			else
			{
				$call .= implode(",", $teamId);
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
				return $this->request($call,$region) . $entry;
			}
		}
		else {
			$call .= $teamId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
			return $this->request($call,$region) . $entry;
		}
	}
	
	/* Returns challenger lader, valid queues are: RANKED_SOLO_5x5, RANKED_TEAM_5x5, RANKED_TEAM_3x3 */
	public function challengerLeague($queue = 'RANKED_SOLO_5x5') {
		$call = 'league/challenger?type='.$queue;
		$call = str_replace('{version}','2.5',self::API_URL) . $call;
		return $this->request($call, true);
	}
	/* Returns master lader, valid queues are: RANKED_SOLO_5x5, RANKED_TEAM_5x5, RANKED_TEAM_3x3 */
	public function masterLeague($queue = 'RANKED_SOLO_5x5') {
		$call = 'league/challenger?type='.$queue;
		$call = str_replace('{version}','2.5',self::API_URL) . $call;
		return $this->request($call, true);
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
	public function staticData($call, $id=null) {
		$call = self::API_URL_STATIC . $call . "/" . $id;
		return $this->request($call, (strpos($call,'?') !== false), true);
	}
	
	/* League of legends game status. You can set a region for retrieve only shards for it. Regions avaliable -> https://developer.riotgames.com/docs/regional-endpoints */
	public function shards($region=null) {
		$call = self::API_URL_SHARDS . "/" . $region;
		return $this->request($call);
	}

	/* Returns details for given match id. TimeLine can be requested. If timeline data is requested, but doesn't exist, then the response won't include it. */
	public function match($matchId, $timeLine=false) {
		$call = str_replace('{version}','2.2',self::API_URL)  . 'match/' . $matchId . ($timeLine ? '?includeTimeline=true' : '');
		return $this->request($call, $timeLine);
	}

	/* Returns all ranked games played (since S3) given summoner id. */
	public function matchHistory($summonerId) {
		$call = str_replace('{version}','2.2',self::API_URL) . 'matchlist/by-summoner/' . $summonerId;
		return $this->request($call);
	}
	
	/* Returns a summoner's stats given summoner id. $option can be summary/ranked. */
	public function stats($summonerId,$option='summary'){
		$call = 'stats/by-summoner/' . $summonerId . '/' . $option;
		$call = str_replace('{version}','1.3',self::API_URL) . $call;
		return $this->request($call);
	}


	/* Returns summoner info giving name */
	public function summonerByName($summonerName,$region){
		$call = 'summoner/by-name/';
		$leagueVersion = '1.4';
		if (is_array($summonerName)) {
			if(count($summonerName) > self::API_LIMIT_SUMMONERS)
			{
				$summonerNames = array_chunk($summonerName,self::API_LIMIT_LEAGUES,true);
				$return = null;
				foreach($summonerNames as $summonersChunked)
				{
					$call .= implode(",",  strtolower(rawurlencode($summonersChunked)));
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
					$return .= $this->request($call,$region);
				}
				return $return;
			}
			else
			{
				$call .= strtolower(rawurlencode(implode(",", $summonerName)));
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
				return $this->request($call,$region);
			}
		}
		else {
			$call .= strtolower(rawurlencode($summonerName));
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
			return $this->request($call,$region,null,null);
		}
	}
	
	/* Returns summoner info given summoner id. You can set multiple summoners. $option can be: masteries,runes,name. */
	public function summonerById($summonerId,$region,$option=null){
		$call = 'summoner/';
		$leagueVersion = '1.4';
		if (is_array($summonerId)) {
			if(count($summonerId) > self::API_LIMIT_SUMMONERS)
			{
				$summonerIds = array_chunk($summonerId,self::API_LIMIT_LEAGUES,true);
				$return = null;
				foreach($summonerIds as $summonersChunked)
				{
					$call .= implode(",", $summonersChunked);
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
					$return .= $this->request($call,$region);
				}
				return $return;
			}
			else
			{
				$call .= implode(",", $summonerId);
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
				return $this->request($call,$region);
			}
		}
		else {
			$call .= $summonerId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
			return $this->request($call,$region);
		}
		switch ($option) {
			case 'masteries':
				$return .= '/masteries';
				break;
			case 'runes':
				$return .= '/runes';
				break;
			case 'name':
				$return .= '/name';
				break;
			default:
				break;
		}
	}

	/* Gets the teams of a summoner, given summoner id. It can be multiple ids. */
	public function teamsBySummoner($summonerId){
		$call = 'team/by-summoner/';
		$leagueVersion = '2.4';
		if (is_array($summonerId)) {
			if(count($summonerId) > self::API_LIMIT_TEAMS)
			{
				$summonerIds = array_chunk($summonerId,self::API_LIMIT_TEAMS,true);
				$return = null;
				foreach($summonerIds as $summonersChunked)
				{
					$call .= implode(",", $summonersChunked);
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
					$return .= $this->request($call,$region);
				}
				return $return;
			}
			else
			{
				$call .= implode(",", $summonerId);
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
				return $this->request($call,$region);
			}
		}
		else {
			$call .= $summonerId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
			return $this->request($call,$region);
		}
	}
	
	/* Gets the teams of a summoner, given summoner id. It can be multiple ids. */
	public function teamsData($teamId){
		$call = 'team/';
		$leagueVersion = '2.4';
		if (is_array($teamId)) {
			if(count($teamId) > self::API_LIMIT_TEAMS)
			{
				$teamIds = array_chunk($teamId,self::API_LIMIT_TEAMS,true);
				$return = null;
				foreach($teamIds as $teamsChunked)
				{
					$call .= implode(",", $teamsChunked);
					$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
					$return .= $this->request($call,$region);
				}
				return $return;
			}
			else
			{
				$call .= implode(",", $teamId);
				$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
				return $this->request($call,$region);
			}
		}
		else {
			$call .= $teamId;
			$call = str_replace('{version}',$leagueVersion,self::API_URL) . $call;
			return $this->request($call,$region);
		}
	}
}
