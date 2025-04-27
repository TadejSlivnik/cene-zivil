<?php

namespace App\Entity;

use App\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table()
 */
class CommandLog
{
    use Timestampable;

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=255)
     */
    protected $command;

    /**
     * @ORM\Column(type="integer", options={"default":0})
     */
    protected $dailyRun = 0;

    /**
     * @ORM\Column(type="integer", options={"default":100})
     */
    protected $maxDailyRuns = 100;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $lastError;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $completedAt;

    public function getCommand(): ?string
    {
        return $this->command;
    }

    public function setCommand(string $command): self
    {
        $this->command = $command;
        return $this;
    }

    public function getDailyRun(): ?int
    {
        return $this->dailyRun;
    }

    public function setDailyRun(int $dailyRun): self
    {
        $this->dailyRun = $dailyRun;
        return $this;
    }

    public function getMaxDailyRuns(): ?int
    {
        return $this->maxDailyRuns;
    }

    public function setMaxDailyRuns(int $maxDailyRuns): self
    {
        $this->maxDailyRuns = $maxDailyRuns;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;
        return $this;
    }

    public function incrementDailyRun(): self
    {
        $this->dailyRun++;
        return $this;
    }

    public function resetDailyRun(): self
    {
        $this->dailyRun = 0;
        return $this;
    }

    public function isMaxDailyRunsReached(): bool
    {
        return $this->dailyRun >= $this->maxDailyRuns;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }
    
    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }
}
