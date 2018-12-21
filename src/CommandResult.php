<?php

namespace SlavaVishnyakov\SshConnection;

class CommandResult
{
    public $stdout;
    public $stderr;
    public $exitCode;

    /**
     * CommandResult constructor.
     * @param $stdout
     * @param $stderr
     * @param $exitCode
     */
    public function __construct($stdout, $stderr, $exitCode)
    {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->exitCode = $exitCode;
    }


}
