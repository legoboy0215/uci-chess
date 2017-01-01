<?php
namespace Netsensia\Uci;

class Engine
{
    const MODE_DEPTH = 1;
    const MODE_TIME_MILLIS = 2;
    const MODE_NODES = 3;
    const MODE_INFINITE = 4;
    
    const APPLICATION_TYPE_JAR = 1;
    const APPLICATION_TYPE_APP = 2;
    
    const STARTPOS = 'startpos';
    
    private $engineLocation;
    private $mode;
    private $modeValue;
    
    private $name;
    
    private $logEngineOutput = true;
    
    private $pipes;
    
    private $position = self::STARTPOS;
    
    private $applicationType = self::APPLICATION_TYPE_APP;
    
    private $errorLog = 'log/error.log';
    private $outputLog = 'log/output.log';
    
    private $process;
    
    private $elo = 1600;
    
    private $restrictToElo = null;
    private $maxThreads = null;
    
    /**
     * @return int $maxThreads
     */
    public function getMaxThreads() : int
    {
        return $this->maxThreads;
    }

    /**
     * @param int $maxThreads
     */
    public function setMaxThreads(int $maxThreads)
    {
        $this->maxThreads = $maxThreads;
    }

    /**
     * @return int $restrictToElo
     */
    public function getRestrictToElo() : int
    {
        return $this->restrictToElo;
    }

    /**
     * @param int $restrictToElo
     */
    public function setRestrictToElo(int $restrictToElo)
    {
        $this->restrictToElo = $restrictToElo;
    }

    /**
     * @return int $elo
     */
    public function getElo() : int
    {
        return $this->elo;
    }

    /**
     * @param int $elo
     */
    public function setElo(int $elo)
    {
        $this->elo = $elo;
    }

    /**
     * @return string $name
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return boolean $logEngineOutput
     */
    public function getLogEngineOutput() : bool
    {
        return $this->logEngineOutput;
    }

    /**
     * @param boolean $logEngineOutput
     */
    public function setLogEngineOutput(bool $logEngineOutput)
    {
        $this->logEngineOutput = $logEngineOutput;
    }

    public function __construct($engineLocation)
    {
        $this->engineLocation = $engineLocation;    
    }
    
    /**
     * @return string $outputLog
     */
    public function getOutputLog() : string
    {
        return $this->outputLog;
    }

    /**
     * @param string $outputLog
     */
    public function setOutputLog(string $outputLog)
    {
        $this->outputLog = $outputLog;
    }

    /**
     * @return string $errorLog
     */
    public function getErrorLog() : string
    {
        return $this->errorLog;
    }

    /**
     * @param string $errorLog
     */
    public function setErrorLog(string $errorLog)
    {
        $this->errorLog = $errorLog;
    }

    /**
     * @return int $applicationType
     */
    public function getApplicationType() : int
    {
        return $this->applicationType;
    }

    /**
     * @param int $applicationType
     */
    public function setApplicationType(int $applicationType)
    {
        $this->applicationType = $applicationType;
    }

    /**
     * @return string $position
     */
    public function getPosition() : string
    {
        return $this->position;
    }

    /**
     * @param string $position
     */
    public function setPosition(string $position)
    {
        $this->position = $position;
    }

    /**
     * @return int $mode
     */
    public function getMode() : int
    {
        return $this->mode;
    }

    /**
     * @param int $mode
     */
    public function setMode(int $mode)
    {
        $this->mode = $mode;
    }

    /**
     * @return int $modeValue
     */
    public function getModeValue() : int
    {
        return $this->modeValue;
    }

    /**
     * @param int $modeValue
     */
    public function setModeValue(int $modeValue)
    {
        $this->modeValue = $modeValue;
    }
    
    /**
     * Start the engine
     */
    public function startEngine()
    {
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("file", $this->errorLog, "a") // stderr is a file to write to
        );
        
        if ($this->applicationType == self::APPLICATION_TYPE_JAR) {
            $command = 'java -jar ';
        } else {
            $command = '';
        }
        
        $command .= $this->engineLocation;

        $this->process = proc_open($command, $descriptorspec, $this->pipes);
        
        if (!is_resource($this->pipes[0])) {
            throw new \Exception('Could not start engine');
        }
        
        $this->sendCommand('uci');
    }
    
    /**
     * Stop the engine process
     */
    public function unloadEngine()
    {
        if (is_resource($this->process)) {
            
            $this->sendCommand('quit');
            
            for ($i=0; $i<2; $i++) {
                if (is_resource($this->pipes[$i])) {
                    fclose($this->pipes[$i]);
                }
            }
            
            proc_close($this->process);
        }
    }
    
    /**
     * Send a command to the engine
     * 
     * @param string $command
     */
    private function sendCommand(string $command)
    {
        if (!is_resource($this->pipes[0])) {
            throw new \Exception('Engine has gone!');
        }
        
        $this->log('>> ' . $command);
        
        fwrite($this->pipes[0], $command . PHP_EOL);
    }
    
    /**
     * @param string $s
     */
    private function log(string $s)
    {
        if ($this->logEngineOutput) {
            file_put_contents($this->outputLog, $s . PHP_EOL, FILE_APPEND);
        }
    }
    
    /**
     * Send each command in the array
     * 
     * @param array $commands
     */
    private function sendCommands(array $commands)
    {
        foreach ($commands as $command) {
            $this->sendCommand($command);
        }
    }
    
    /**
     * Wait for the engine to respond with a command beginning with the give string
     * 
     * @param string $responseStart
     */
    private function waitFor(string $responseStart)
    {
        if (!is_resource($this->pipes[0])) {
            throw new \Exception('Engine has gone!');
        }
        
        do {
            $output = trim(fgets($this->pipes[1]));
            if ($output != null) {
                $this->log('<< ' . $output);
            }
        } while (strpos($output, $responseStart) !== 0);
        
        return $output;
    }
    
    /**
     * Get a move
     * 
     * @param string $startpos
     * @param string $moveList
     * 
     * @return string $move
     */
    public function getMove(string $moveList = null)
    {
        if (!is_resource($this->pipes[0])) {
            $this->startEngine();
        }
        
        switch ($this->mode) {
            case self::MODE_DEPTH : $goCommand = 'depth ' . $this->modeValue; break;
            case self::MODE_NODES : $goCommand = 'nodes ' . $this->modeValue; break;
            case self::MODE_TIME_MILLIS : $goCommand = 'movetime ' . $this->modeValue; break;
            case self::MODE_INFINITE : $goCommand = 'infinite'; break;
        }
        
        $this->sendCommand('uci');
        $this->waitFor('uciok');
        
        if ($this->restrictToElo) {
            $this->sendCommand('setoption name UCI_LimitStrength value true');
            $this->sendCommand('setoption name UCI_Elo value ' . $this->restrictToElo);
        }
        
        if ($this->maxThreads) {
            $this->sendCommand('setoption name Threads value ' . $this->maxThreads);
        }
        
        $command = 'position ' . $this->position;
        if ($moveList != null) {
            $command .= ' moves ' . $moveList;
        }
        $this->sendCommand($command);
        $this->sendCommand('go ' . $goCommand);
        $response = $this->waitFor('bestmove');
        $parts = explode(' ', $response);
        
        if (count($parts) < 2) {
            throw new \Exception('Move format was not correct: ' . print_r($response, true));
        }
        
        $move = $parts[1];
        
        return $move;
    }

    /**
     * Is the engine running?
     * 
     * @return boolean
     */
    public function isEngineRunning() : bool
    {
        return is_resource($this->pipes[0]);
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->unloadEngine();
    }
}

