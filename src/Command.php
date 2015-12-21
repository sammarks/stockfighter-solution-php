<?php

namespace Marks\StockfighterSolution;

use Marks\Stockfighter\Stockfighter;
use Marks\StockfighterSolution\Exceptions\StockfighterSolutionException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends SymfonyCommand
{
	/**
	 * The Stockfighter instance.
	 * @var Stockfighter
	 */
	protected $stockfighter;

	/**
	 * The name of the venu for this command.
	 * @var string|bool
	 */
	protected $venue = false;

	public function __construct($name = null)
	{
		parent::__construct($name);
		$this->stockfighter = new Stockfighter();
	}

	protected function configure()
	{
		$this->addArgument('venue', InputArgument::REQUIRED, 'The name of the venue for the level.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->venue = $input->getArgument('venue');
		$output->write('Making sure the venue is up... ');

		if ($this->venue()->heartbeat()) {
			$output->writeln('Yep!');
		} else {
			$output->writeln('Nope :(');
			throw new StockfighterSolutionException('The venue ' . $this->venue . ' is down or does not exist.');
		}
	}

	/**
	 * Gets the application (just like the parent class, except
	 * with the type change).
	 *
	 * @return Application
	 */
	public function getApplication()
	{
		return parent::getApplication();
	}

	/**
	 * Gets the configuration from the application.
	 *
	 * @return \Configula\Config
	 */
	public function config()
	{
		return $this->getApplication()->config();
	}

	/**
	 * Sets the venue for this command.
	 *
	 * @param string $venue
	 *
	 * @return Command The current command instance.
	 */
	public function setVenue($venue)
	{
		$this->venue = $venue;
		return $this;
	}

	/**
	 * Gets the venue path for the current venu.
	 *
	 * @return \Marks\Stockfighter\Paths\Venue
	 * @throws StockfighterSolutionException
	 */
	public function venue()
	{
		if ($this->venue) {
			return $this->stockfighter->venue($this->venue);
		} else {
			throw new StockfighterSolutionException('Cannot call venue() on a command where a venue has not been set.');
		}
	}
}
