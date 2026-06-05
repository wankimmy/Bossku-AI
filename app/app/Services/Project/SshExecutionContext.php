<?php

namespace App\Services\Project;

/**
 * SSH remote execution (Emdash-style). Commands are built for remote shell; local Process is not used.
 */
class SshExecutionContext
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $user,
        public readonly string $remoteRoot,
        public readonly ?string $identityFile = null,
    ) {}

    public function wrapCommand(string $command): string
    {
        $remote = 'cd '.escapeshellarg($this->remoteRoot).' && '.$command;
        $target = escapeshellarg($this->user.'@'.$this->host);
        $port = $this->port !== 22 ? ' -p '.(int) $this->port : '';
        $identity = $this->identityFile ? ' -i '.escapeshellarg($this->identityFile) : '';

        return 'ssh'.$port.$identity.' '.$target.' '.escapeshellarg($remote);
    }

    public function toExecutionContext(): ExecutionContext
    {
        return new ExecutionContext(
            repoRoot: $this->remoteRoot,
            runId: null,
            mode: 'ssh',
            remoteHost: $this->host,
        );
    }
}
