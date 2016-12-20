# OnLoL-Riot-API
Riot api base for PHP. Updated constantly and almost without bugs!
It has no errors on http requests.
It uses Json as data storage, since it's easier and faster than mysql.
Data storaged is also encrypted by a private keys for dont let the users get data when not allowed.
IMPORTANT: TOURNAMENTS ARE NOT ADDED, BECAUSE RIOT DIDN'T LET ME AN API KEY FOR IT. IF YOU CAN CONTRIBUTE AND LET ME AN API KEY FOR IT I'LL WORK ON IT!!
# NEW ON VERSION 1.7
* Fixed a problem that the function didn't return nothing or returned a error outpot text. Now it returns the keyname 'NOT_FOUND' as it was doing all api-life long.
* Added to config the api max values for multiqueries. riot.api.limitperquery.leagues,riot.api.limitperquery.summoners, riot.api.limitperquery.teamleagues, riot.api.limitperquery.recentgames. CONFIGURE JUST IF API CHANGES MAX VALUES.
* Added champion mastery max level to config -> riot.api.summonerschampmastery.maxlevel
* Added option to config.conf to set default value for update per query.
* Added stats for currentGame(), which has information about the regions and patches called. File: activeGames.json
* Fixed stats generator. Now it splits stats into multiple files, so it's easier and faster to load.
* Added method $stats->generalPatches(); It returns an array with all stats by default, or for patches given (The patch has to have stats). P.e $stats->generalPatches(array('6.24','6.25'));

# NEW ON VERSION 1.4 + 1.6!!!
* Secured database path and files it so hard
* Auto detect url
* Auto detect base path
* Removed mysql connection
* Auto key-generator if not exists. Else, it can be changed on "kernel/config.conf -> hash.secretKey && hash.secretKey2"
* Removed access globally to .conf files for all users (not for internal PHP)
* Improved access security on kernel
* Added function actualPatch, which returns public actual patch, or dev actual patch. Default is public actual patch.
* Improved class and added clarity to it
* Removed unused variables from config
* Database now uses less disk space
* Cache now takes shorter time to load data
* Cleaned cache function
* Removed path to internal onlol langs
* Crypt session lang data, and decrypted to a single variable with the internal keys
* Added log/debug option on config so you can see responses per query and the queries you made, with long descriptions, responses, status, and backtrace.
* Added a function on stats so you can call season7 and it will get season7 stats (instead of just giving season2017)
* Removed riot.api.regions on config.conf, so you now don't have to config the regions where your api can make requests. If your api is not made for NA, dont mke calls to NA. If it's made to all: Don't worry! =)
* Added stats manager on config. You can enable it (or disable) on config.conf -> stats.generate
* Added SYSTEM path for stats manager. It's ALWAYS on /database/SYSTEM/stats/[filename].json
* Added stats generator for patches: It shows how many time you called function actualPatch() on the patch and the type of it. Filename: patches.json
* Added default region to queries on $riot->champion()
* Added a param to staticData() and variable ACTUAL_PATCH to riot class.
* Added stats generator for champion(). It saves how many times you call a champion w/ or w/o ID, last time disabled, last time ranked disabled, last free to play time. For the actual patch and all patches merged.
* Did the same about above for championFreeToPlay().
* Added stats generator for championMastery(). It has the summoner id with more levels on the account  and it's value, summoner id with more mastery points and it's value, summoner id with more mastery points on a champ and it's value and of course the champ id. It's valid for global data (all patches) and a specific patch.

# Configuration
It's easy. Just download it obviously and go to kernel/config.conf. Edit those lanes:

> `riot.api.key=` Riot api key here

>`riot.api.key.type=` Api key type (PROD for production and DEV for development)

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

### $api-> championFreeToPlay(region=euw)
Returns free to play champions. You can set region if you want to see a specific region free to play champs.
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

### $api-> challengerLeague(region,queue)
Returns challenger lader for given queue. 
Valid queues are **RANKED_SOLO_5x5**, **RANKED_TEAM_5x5**, **RANKED_TEAM_3x3**.
Default is **RANKED_SOLO_5x5**. If not exists, will thrown an error (NOT_FOUND).
Counts as rate limit.

### $api-> masterLeague(region,queue)
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
