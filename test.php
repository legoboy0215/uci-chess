<?php

/**
 * A test script that sets up a round robin tournament with three engines configured at
 * multiple perecent strengths. Runs the tournament an infinite number of times, saving
 * the current ELO of each engine in a file after each match.
 */

include 'vendor/autoload.php';

use Netsensia\Uci\Engine;
use Netsensia\Uci\Tournament\RoundRobin;
use Ryanhs\Chess\Chess;

const REPORT_LOCATION = '/Volumes/Secomba/chris/Boxcryptor/Dropbox/Dashboard/UCITournament.csv';

$count = 0;
run();

function run()
{
    $rivalMillisToSearch1000000Nodes = determineRivalSpeed();
    echo 'Milliseconds to search when timed engines are 100% = ' . $rivalMillisToSearch1000000Nodes . PHP_EOL;
    
    $cuckooSettings = [];
    $rivalSettings = [];
    $fluxSettings = [];
    $deepBrutePosSettings = [];
    $deepJuniorSettings = [];
    
    $lines = file(REPORT_LOCATION);
    
    if (!$lines) {
        die;
    }
    
    for ($i=1; $i<count($lines); $i++) {
        $parts = str_getcsv($lines[$i]);
        $name = $parts[0];
        $elo = $parts[1];
        $nameSplit = explode(' ', $name);
        $modeValue = $nameSplit[1];
        switch ($nameSplit[0]) {
            case 'DeepJunior' :
                $percent = 100;
                $millis = $rivalMillisToSearch1000000Nodes;
                $restrictToElo = $modeValue;
                $deepJuniorSettings[] = [$millis, $elo, $name, $restrictToElo];
                break;
            case 'DeepBrutePos' :
                $percent = str_replace('%', '', $modeValue);
                $millis = ceil(($rivalMillisToSearch1000000Nodes / 100) * $percent);
                $deepBrutePosSettings[] = [$millis, $elo, $name];
                break;
            case 'Cuckoo' :
                $percent = str_replace('%', '', $modeValue);
                $millis = ceil(($rivalMillisToSearch1000000Nodes / 100) * $percent);
                $cuckooSettings[] = [$millis, $elo, $name];
                break;
            case 'Flux' :
                $percent = str_replace('%', '', $modeValue);
                $millis = ceil(($rivalMillisToSearch1000000Nodes / 100) * $percent);
                $fluxSettings[] = [$millis, $elo, $name];
                break;
            case 'Rival' :
                $percent = str_replace('%', '', $modeValue);
                $nodes = 10000 * $percent;
                echo $name . ' will search ' . $nodes . ' nodes' . PHP_EOL;
                $rivalSettings[] = [$nodes, $elo, $name];
                break;            
        }
    }
    
    $tournament = new RoundRobin();
    $tournament->setNumberOfRuns(100);
    
    $engine = new Engine('engines/RivalChess.jar');
    $engine->setMode(Engine::MODE_NODES);
    $engine->setApplicationType(Engine::APPLICATION_TYPE_JAR);
    $engine->setLogEngineOutput(false);
    
    foreach ($rivalSettings as $setting) {
        $engine->setModeValue($setting[0]);
        $engine->setName($setting[2]);
        $engine->setElo($setting[1]);
        $tournament->addEngine($engine);
        $engine = clone $engine;
    }
    
    $engine = new Engine('engines/cuckoo112.jar uci');
    $engine->setApplicationType(Engine::APPLICATION_TYPE_JAR);
    $engine->setMode(Engine::MODE_TIME_MILLIS);
    $engine->setName('Cuckoo');
    $engine->setLogEngineOutput(false);
    
    foreach ($cuckooSettings as $setting) {
        $engine->setModeValue($setting[0]);
        $engine->setElo($setting[1]);
        $engine->setName($setting[2]);
        $tournament->addEngine($engine);
        $engine = clone $engine;
    }
    
    $engine = new Engine('-Xmx1024M engines/Flux-2.2.1.jar');
    $engine->setApplicationType(Engine::APPLICATION_TYPE_JAR);
    $engine->setMode(Engine::MODE_TIME_MILLIS);
    $engine->setName('Cuckoo');
    $engine->setLogEngineOutput(false);
    
    foreach ($fluxSettings as $setting) {
        $engine->setModeValue($setting[0]);
        $engine->setElo($setting[1]);
        $engine->setName($setting[2]);
        $tournament->addEngine($engine);
        $engine = clone $engine;
    }
    
    $engine = new Engine('engines/DeepBrutePos-2.1.jar');
    $engine->setApplicationType(Engine::APPLICATION_TYPE_JAR);
    $engine->setMode(Engine::MODE_TIME_MILLIS);
    $engine->setName('Deep Brute Pos');
    $engine->setLogEngineOutput(false);
    
    foreach ($deepBrutePosSettings as $setting) {
        $engine->setModeValue($setting[0]);
        $engine->setElo($setting[1]);
        $engine->setName($setting[2]);
        $tournament->addEngine($engine);
        $engine = clone $engine;
    }
    
    $engine = new Engine('/Applications/Deep\ Junior\ Yokohama/Deep\ Junior\ Yokohama');
    $engine->setApplicationType(Engine::APPLICATION_TYPE_APP);
    $engine->setMode(Engine::MODE_TIME_MILLIS);
    $engine->setName('Deep Junior');
    $engine->setLogEngineOutput(false);
    
    foreach ($deepJuniorSettings as $setting) {
        $engine->setModeValue($setting[0]);
        $engine->setElo($setting[1]);
        $engine->setName($setting[2]);
        $engine->setRestrictToElo($setting[3]);
        $tournament->addEngine($engine);
        $engine = clone $engine;
    }
    
    foreach ($tournament->matches() as $match) {
        
        echo $match->getWhite()->getName() . ' v ' . $match->getBlack()->getName() . PHP_EOL;
        
        $tournament->play($match);
        
        $tableString = $tournament->table();
        
        $rivalSpeed = determineRivalSpeed();
        
        $tableString = str_replace('Engine,', 'Engine (' . $rivalMillisToSearch1000000Nodes . 'ms),', $tableString);
        echo $tableString;
        
        file_put_contents(REPORT_LOCATION, $tableString);
    }
    
    $tournament->close();
    
    echo PHP_EOL;
}

function determineRivalSpeed()
{
    echo 'Determining Rival Speed...' . PHP_EOL;
    $engine = new Engine('engines/RivalChess.jar');
    $engine->setMode(Engine::MODE_NODES);
    $engine->setApplicationType(Engine::APPLICATION_TYPE_JAR);
    $engine->setLogEngineOutput(false);
    $engine->setModeValue(1000000);
    $start = microtime(true);
    $engine->getMove();
    $time = microtime(true) - $start;
    $engine->unloadEngine();

    return ceil($time * 1000);
}