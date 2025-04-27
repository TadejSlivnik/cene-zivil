<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Automatically calculates memory usage, running duration and locks the command if another instance is running ($mode).
 * 
 * Use function "executeCommand" instead of "execute".
 * 
 * Helper functions: "getProgressBar".
 */
abstract class AbstractCommand extends Command
{
    use LockableTrait;

	protected const LOG_SOURCE = 'command';

	/** Allow multiple command instances - default Symfony logic. Not recommended. */
	public const MODE_PARALLEL = 3;
	/** Allow only one command instance. Wait until other command instances finish. */
	public const MODE_SEQUENTIAL = 2;
	/** Allow only one command instance. Terminate if another instance is running. */
	public const MODE_SINGULAR = 1;

	/** @var ParameterBagInterface */
	protected $parameterBag;
	/** @var LoggerInterface */
	protected $logger;
	
	/** @var SymfonyStyle */
	protected $io;

	private int $startTime = 0;
	protected bool $isDev = false;
	protected bool $isProd = false;
	protected int $mode = self::MODE_SINGULAR;
	
	/** In mode MODE_SEQUENTIAL - send error mail on "long wait" */
	protected bool $sendMailOnLongWait = true;
	/** In mode MODE_SEQUENTIAL - what is considered a "long wait" */
	protected int $longWaitSeconds = 30;

	public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger)	
	{
		parent::__construct();
		$this->parameterBag = $parameterBag;
		$this->isDev = $parameterBag->get('kernel.environment') === 'dev';
		$this->isProd = $parameterBag->get('kernel.environment') === 'prod';
		$this->logger = $logger;
	}

	/**
	 * Return self::SUCCESS or self::FAILURE
	 */
	protected abstract function executeCommand(InputInterface $input, OutputInterface $output): int;

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->io = new SymfonyStyle($input, $output);

		// check if command is already running
		switch ($this->mode) {
			case self::MODE_SINGULAR:
				if (!$this->lock()) {
					$this->io->warning('An instance of this command is already running. It is not set to wait for release (waitForLockRelease). Terminating...');
					return Command::SUCCESS;
				}
				break;
			case self::MODE_SEQUENTIAL:
				$this->startTime = time();
				$this->io->comment('Command instance is possibly already running. Waiting for release...');
				$this->lock(null, true);
	
				$duration = time() - $this->startTime;
				if ($duration > 5) {
					$this->io->note("Command released after: " . Helper::formatTime($duration));
	
					// if command instance is running long, send email notification, to configure better cronjob times
					if ($this->sendMailOnLongWait && $duration > $this->longWaitSeconds) {
						$this->logger->critical("Command instance release took a long time.", [
							'command' => $this->getName(),
							'startTime' => date('Y-m-d H:i:s', $this->startTime),
							'waitDuration' => Helper::formatTime($duration),
							'messages' => "If this is a cronjob, you should consider configuring different run intervals.",
						]);
					}
				}
				unset($duration);
				break;
			case self::MODE_PARALLEL:
			default:
				break;
		}

        $this->startTime = time();
		
		try {
			$success = $this->executeCommand($input, $output);
		} catch (\Throwable $th) {
			$this->logger->critical($th->getMessage(), $th->getTrace());

			if ($this->isDev) {
				dd($th);
			}

			$this->io->error($this->getRunInfoMessage());
			return Command::FAILURE;
		}

		$this->release();

		switch ($success) {
			case self::SUCCESS:
				$this->io->success($this->getRunInfoMessage());
				break;
			case self::FAILURE:
				$this->io->error($this->getRunInfoMessage());
				break;
			default:
				throw new \Exception($this->getRunInfoMessage() . ". Invalid return value: \"$success\"...");
				break;
		}

		return $success;
	}

	protected function getProgressBar(int $max = 0): ProgressBar
	{
		$progressBar = $this->io->createProgressBar($max);
		$progressBar->setFormat($max ? "debug" : "debug_nomax");
		return $progressBar;
	}

	private function getRunInfo()
	{
		return [
			'duration' => Helper::formatTime(time() - $this->startTime),
			'memory' => Helper::formatMemory(memory_get_peak_usage(true)),
		];
	}

	private function getRunInfoMessage()
	{
		$runInfo = $this->getRunInfo();
		return implode(', ', [
			'Memory used: ' . ($runInfo['memory'] ?? 'null'),
			'Duration: ' . ($runInfo['duration'] ?? 'null'),
		]);
	}
}
