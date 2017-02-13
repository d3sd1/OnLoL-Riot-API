<?php
$tournament = new tournaments($config['riot.api.key.type']);
$stats = new stats();
class tournaments extends riotapi{
    private $API_KEY;
    const PROVIDER_URL = 'https://global.api.pvp.net/tournament/stub/v1/provider';
    const TOURNAMENT_URL = 'https://global.api.pvp.net/tournament/stub/v1/tournament';
    const CODE_URL = 'https://global.api.pvp.net/tournament/stub/v1/code';
    const LOBBY_URL = 'https://global.api.pvp.net/tournament/stub/v1/lobby/events/by-code/{tournamentCode}';
    public function __construct()
    {
	$this->API_KEY = $GLOBALS['config']['riot.tournaments.api.key'];
	$this->DEFAULT_REGION = $GLOBALS['config']['default.region'];
    }
    public function createTournament($name, $postGameResultsUrl, $teamSize, $spectatorType = 'ALL', $pickType = 'TOURNAMENT_DRAFT', $mapType = 'SUMMONERS_RIFT', $region = 'NOT_SET', $allowedSummonerIds = null, $metaData = null)
    {
        if($this->API_KEY == null)
        {
            die('You must set a valid tournaments key.');
        }
        if($region == 'NOT_SET')
        {
            $region = $this->DEFAULT_REGION;
        }
        $provider = (int) $this->getProvider($region,$postGameResultsUrl);
        if($provider != 0)
        {
            $tournamentId = $this->postTournament($name,$provider);
        }
        if(isset($tournamentId) && $tournamentId != 0)
        {
            $participants = $teamSize*2;
            if($spectatorType == 'ALL' || $spectatorType == 'LOBBYONLY')
            {
                $participants += 3;
            }
            $tournamentCodes = $this->getTournamentCode($tournamentId, $participants, $teamSize, $spectatorType, $pickType, $mapType, $allowedSummonerIds, $metaData);
            return array('tournamentInfo' => array('name' => $name, 'region' => strtoupper($region), 'gamePostUrl' => $postGameResultsUrl, 'providerId' => $provider, 'tournamentId' => $tournamentId), 'tournamentCodes' => $tournamentCodes);
        }
        else
        {
            return 'An error has ocurred, check logs for more info.';
        }
    }
    private function getProvider($region,$postGameResultsUrl)
    {
        try{
            $ch = curl_init(self::PROVIDER_URL);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS,'{"region" : "'.strtoupper($region).'","url" : "'.$postGameResultsUrl.'"}');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','X-Riot-Token: '.$this->API_KEY));
            $result = curl_exec($ch);
            $resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($resultCode == 200)
            {
                return $result;
            }
            else
            {
                if($GLOBALS['config']['logs.enabled'])
                {
                    base::addToDebugLog('Error detected when creating tournament: getProvider() -> '.$resultCode);
                }
                throw new Exception(self::$errorCodes[$resultCode]);
            }
        }
        catch (Exception $e) {
		if($GLOBALS['config']['save.errors.to.log'])
		{
			$GLOBALS['core']->saveToErrorLog('Error detected when creating tournament: getProvider() -> '.$resultCode.PHP_EOL);
		}
		if($GLOBALS['config']['show.errors.on.exec'])
		{
                    echo 'Error detected when creating tournament: getProvider() -> '.$resultCode.PHP_EOL;
		}
        }
    }
    private function postTournament($name,$provider)
    {
        try{
            $ch = curl_init(self::TOURNAMENT_URL);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS,'{"name" : "'.$name.'","providerId" : '.$provider.'}');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','X-Riot-Token: '.$this->API_KEY));
            $result = curl_exec($ch);
            $resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($resultCode == 200)
            {
                return $result;
            }
            else
            {
                if($GLOBALS['config']['logs.enabled'])
                {
                    base::addToDebugLog('Error detected when creating tournament: postTournament() -> '.$resultCode);
                }
                throw new Exception(self::$errorCodes[$resultCode]);
            }
        }
        catch (Exception $e) {
		if($GLOBALS['config']['save.errors.to.log'])
		{
			$GLOBALS['core']->saveToErrorLog('Error detected when creating tournament: postTournament() -> '.$resultCode.PHP_EOL);
		}
		if($GLOBALS['config']['show.errors.on.exec'])
		{
                    echo 'Error detected when creating tournament: postTournament() -> '.$resultCode.PHP_EOL;
		}
        }
    }
    private function getTournamentCode($tournamentId, $participants, $teamSize, $spectatorType, $pickType, $mapType, $allowedSummonerIds, $medaData)
    {
        try{
            if(is_array($allowedSummonerIds))
            {
                $allowedSumIds = ',['.implode(',',$allowedSummonerIds).']';
            }
            else
            {
                $allowedSumIds = null;
            }
            $ch = curl_init(str_replace(array('{tournamentId}','{participants}'),array($tournamentId,$participants),self::CODE_URL.'?tournamentId={tournamentId}&count={participants}'));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS,'{"teamSize" : '.$teamSize.','.$allowedSumIds.'"spectatorType" : "'.$spectatorType.'","pickType" : "'.$pickType.'","mapType" : "'.$mapType.'","metadata" : "'.$medaData.'"}');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','X-Riot-Token: '.$this->API_KEY));
            $result = curl_exec($ch);
            $resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($resultCode == 200)
            {
                $returnDetailed = array();
                $teamCount = 1;
                $actualPlayer = 1;
                foreach(explode(',',str_replace(array('[',']','"'), array(null,null,null), $result)) as $tournamentCode)
                {
                    if($actualPlayer <= $teamSize)
                    {
                        if($teamCount == 1)
                        {
                            $teamName = 'TEAM_BLUE';
                        }
                        else if($teamCount == 2)
                        {
                            $teamName = 'TEAM_PURPLE';
                        }
                        else if($teamCount == 3)
                        {
                            $teamName = 'SPECTATORS';
                        }
                        if($allowedSummonerIds != null)
                        {
                            $returnDetailed[$teamName][$allowedSummonerIds[$actualPlayer]] = $tournamentCode;
                        }
                        else
                        {
                            $returnDetailed[$teamName][$actualPlayer] = $tournamentCode;
                        }
                    }
                    else
                    {
                        $teamCount++;
                        $actualPlayer = 1;
                        if($teamCount == 1)
                        {
                            $teamName = 'TEAM_BLUE';
                        }
                        else if($teamCount == 2)
                        {
                            $teamName = 'TEAM_PURPLE';
                        }
                        else if($teamCount == 3)
                        {
                            $teamName = 'SPECTATORS';
                        }
                        if($allowedSummonerIds != null)
                        {
                            $returnDetailed[$teamName][$allowedSummonerIds[$actualPlayer]] = $tournamentCode;
                        }
                        else
                        {
                            $returnDetailed[$teamName][$actualPlayer] = $tournamentCode;
                        }
                    }
                    $actualPlayer++;
                }
                return $returnDetailed;
            }
            else
            {
                if($GLOBALS['config']['logs.enabled'])
                {
                    base::addToDebugLog('Error detected when creating tournament: getTournamentCode() -> '.$resultCode);
                }
                throw new Exception(self::$errorCodes[$resultCode]);
            }
        }
        catch (Exception $e) {
		if($GLOBALS['config']['save.errors.to.log'])
		{
			$GLOBALS['core']->saveToErrorLog('Error detected when creating tournament: getTournamentCode() -> '.$resultCode.PHP_EOL);
		}
		if($GLOBALS['config']['show.errors.on.exec'])
		{
                    echo 'Error detected when creating tournament: getTournamentCode() -> '.$resultCode.PHP_EOL;
		}
        }
    }
    public function lobbyEvents($tournamentId, $region)
    {
        try{
            $ch = curl_init(str_replace('{tournamentCode}',strtoupper($region).$tournamentId.'-TOURNAMENTCODE0001',self::LOBBY_URL));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','X-Riot-Token: '.$this->API_KEY));
            $result = curl_exec($ch);
            $resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($resultCode == 200)
            {
                return $result;
            }
            else
            {
                if($GLOBALS['config']['logs.enabled'])
                {
                    base::addToDebugLog('Error detected when fetching tournament: lobbyEvents() -> '.$resultCode);
                }
                throw new Exception(self::$errorCodes[$resultCode]);
            }
        }
        catch (Exception $e) {
		if($GLOBALS['config']['save.errors.to.log'])
		{
			$GLOBALS['core']->saveToErrorLog('Error detected when fetching tournament: lobbyEvents() -> '.$resultCode.PHP_EOL);
		}
		if($GLOBALS['config']['show.errors.on.exec'])
		{
                    echo 'Error detected when fetching tournament: lobbyEvents() -> '.$resultCode.PHP_EOL;
		}
        }
    }
}