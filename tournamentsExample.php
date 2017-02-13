<?php
include('kernel/core.php');
/*
	Global variables:
		$userLang - Returns user language
*/
    /* Create tournament -> Name: Andrei Test, CallBack URL: http://localhost/lol, Players per team: 5, Spectators allowed from: ALL, Pick type: TOURNAMENT_DRAFT, Map: SUMMONERS_RIFT, Region: EUW*/
    $tournamentData = $tournament->createTournament('Andrei test', 'http://localhost/lol', 5, 'ALL', 'TOURNAMENT_DRAFT', 'SUMMONERS_RIFT', 'EUW');
    echo 'Tournament info: <br>';echo '<br>';
    print_r($tournamentData); echo '<br><br><br><br><br>';
    echo 'Lobby events: <br>';
    $tournamentId = $tournamentData['tournamentInfo']['tournamentId'];
    $tournamentRegion = $tournamentData['tournamentInfo']['region'];
    print_r($tournament->lobbyEvents($tournamentId,$tournamentRegion));
    echo '<br><br><br><br>';
?>