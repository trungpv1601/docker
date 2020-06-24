<?php

namespace Spatie\Docker;

use Spatie\Macroable\Macroable;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DockerContainerInstance
{
    use Macroable;

    private DockerContainer $config;

    private string $dockerIdentifier;

    private string $name;

    private bool $sudo = false;

    public function __construct(
        DockerContainer $config,
        string $dockerIdentifier,
        string $name
    ) {
        $this->config = $config;

        $this->dockerIdentifier = $dockerIdentifier;

        $this->name = $name;
    }

    public function __destruct()
    {
        if ($this->config->stopOnDestruct) {
            $this->stop();
        }
    }

    public function stop(): Process
    {
        $fullCommand = ($this->sudo ? 'sudo ' : '') . "docker stop {$this->getShortDockerIdentifier()}";

        $process = Process::fromShellCommandline($fullCommand);

        $process->run();

        return $process;
    }

    public function remove($force = true, $sudo = false): Process
    {
        $fullCommand = ($this->sudo ? 'sudo ' : '') . "docker rm {$this->getShortDockerIdentifier()}" . ($force ? ' -f' : '');

        $process = Process::fromShellCommandline($fullCommand);

        $process->run();

        return $process;
    }

    public function logs($tail = 100): Process
    {
        $fullCommand = "docker logs --tail {$tail} {$this->getShortDockerIdentifier()}";

        $process = Process::fromShellCommandline($fullCommand);

        $process->run();

        return $process;
    }

    public function status(): string
    {
        $fullCommand = "docker container inspect -f '{{.State.Status}}' {$this->getShortDockerIdentifier()}";

        $process = Process::fromShellCommandline($fullCommand);

        $process->run();

        return $process->getOutput();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(): DockerContainer
    {
        return $this->config;
    }

    public function getDockerIdentifier(): string
    {
        return $this->dockerIdentifier;
    }

    public function getShortDockerIdentifier(): string
    {
        return substr($this->dockerIdentifier, 0, 12);
    }

    /**
     * @param string|array $command
     *
     * @return \Symfony\Component\Process\Process
     */
    public function execute($command): Process
    {
        if (is_array($command)) {
            $command = implode(';', $command);
        }

        $fullCommand = "echo \"{$command}\" | docker exec --interactive {$this->getShortDockerIdentifier()} bash -";

        $process = Process::fromShellCommandline($fullCommand);

        $process->run();

        return $process;
    }

    public function addPublicKey(string $pathToPublicKey, string $pathToAuthorizedKeys = '/root/.ssh/authorized_keys'): self
    {
        $publicKeyContents = trim(file_get_contents($pathToPublicKey));

        $this->execute('echo \'' . $publicKeyContents . '\' >> ' . $pathToAuthorizedKeys);

        $this->execute("chmod 600 {$pathToAuthorizedKeys}");
        $this->execute("chown root:root {$pathToAuthorizedKeys}");

        return $this;
    }

    public function addFiles(string $fileOrDirectoryOnHost, string $pathInContainer): self
    {
        $process = Process::fromShellCommandline("docker cp {$fileOrDirectoryOnHost} {$this->getShortDockerIdentifier()}:{$pathInContainer}");
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $this;
    }

    public function useSudo() : self
    {
        $this->sudo = true;
        return $this;
    }
}
