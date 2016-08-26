# OnLoL-Riot-API
Riot api base for PHP. Updated constantly and almost without bugs!
It has no errors on http requests.
It uses Json as data storage, since it's easier and faster than mysql.
Data storaged is also encrypted by a private keys for dont let the users get data when not allowed.
# Configuration
It's easy. Just download it obviously and go to kernel/config.conf. Edit those lanes:

> `riot.api.key=` Riot api key here

>`riot.api.cache.key=` Secret hash 1

>`riot.api.cache.key2=` Secret hash 2

>`web.url=http://localhost` Website url

Configuration done! You've secured your data!

# Usage
You can test those functions on testing.php, thats the script you've to execute on navigator. Replace content in try and put the function you want to execute. It returns an array!
## Functions:
### $api-> champion()
Returns all champions data.
Counts as rate limit.

### $api-> champion(championid)
Returns selected champion data. 
Counts as rate limit.

### $api-> championFreeToPlay()
Returns free to play champions. 
Counts as rate limit.

### $api-> championMastery(summonerId,region)
Returns all champs with mastery on the provided summoner.
Counts as rate limit.

### $api-> currentGame(summonerId,region)
Returns current game if playing, else will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> featuredGames(region)
Returns featured games on provided region.
Counts as rate limit.

### $api-> recentGames(summonerId,region)
Returns last 10 games of the given summoner.  If not exists, will thrown an error (NOT_FOUND), or not played.
Counts as rate limit.

### $api-> league(summonerId,region,entry=null)
Returns league for given users. They're unlimited but it will be chunked in order to API_LIMIT_LEAGUES constant.
Entry parameter is optional. If set, it only returns given users league data. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> teamLeague(teamId,region,entry=null)
Returns league for given teams. They're unlimited but it will be chunked in order to API_LIMIT_LEAGUES constant.
Entry parameter is optional. If set, it only returns given users league data. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> challengerLeague(queue)
Returns challenger lader for given queue. 
Valid queues are **RANKED_SOLO_5x5**, **RANKED_TEAM_5x5**, **RANKED_TEAM_3x3**.
Default is **RANKED_SOLO_5x5**. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> masterLeague(queue)
Returns challenger lader for given queue. 
Valid queues are **RANKED_SOLO_5x5**, **RANKED_TEAM_5x5**, **RANKED_TEAM_3x3**.
Default is **RANKED_SOLO_5x5**. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> staticData(call,id=null)
Returns api static data. Valid parameters on call are: ($id means the id parameter on function caller)
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
	versions -> Full LoL Api versions
Doesn't counts as rate limit.

### $api-> shards(region=null)
Returns shards of all region if not specified one, else, it just returns for its region.
Doesn't counts as rate limit.

### $api-> match(matchId,region,timeline=false)
Returns data of an already end match. Timeline means the minute-by-minute data, if you're not gonna use it, set it to false (default is false). If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> matchHistory(summonerId,region)
Returns all ranked games since S3 for the given summoner. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> stats(summonerId,region,option=summary,season=nul)
Returns stats for given user.
Option parameter can be **summary** (Normal games) or **ranked**. You can set parameter **season** only for ranked stats. Values can be: **SEASON3**,**SEASON2014**,**SEASON2015**,**SEASON2016**.
Counts as rate limit.

### $api-> summonerByName(summonerName,region)
Returns summoner data by giving name. They're unlimited but it will be chunked in order to API_LIMIT_SUMMONERS constant.
Entry parameter is optional. If set, it only returns given users league data. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> summonerById(summonerId,region,option=null)
Returns summoner data by giving id.
Option parameter is null by default. Values can be: **masteries**,**runes**,**name**.
They return the data by option parameter.
They're unlimited but it will be chunked in order to API_LIMIT_SUMMONERS constant.
Entry parameter is optional. If set, it only returns given users league data. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> teamsBySummoner(summonerId,region)
Returns actual teams for the given user. They're unlimited but it will be chunked in order to API_LIMIT_TEAMS constant.
Entry parameter is optional. If set, it only returns given users league data. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> teamsData(summonerId,region)
Returns team data for given teams. They're unlimited but it will be chunked in order to API_LIMIT_TEAMS constant.
Entry parameter is optional. If set, it only returns given users league data. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.
