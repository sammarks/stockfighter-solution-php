<?php

namespace Marks\StockfighterSolution\Levels;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class ChockABlock extends Command
{
	const STOCKS_PER_LOOP = 100;
	const LOOP_DELAY = 0.5;

	protected function configure()
	{
		$this->setName('level:chock-a-block')
			->setDescription('Run the chock-a-block solution (level 2).');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$loops = 100000 / self::STOCKS_PER_LOOP;
		for ($i = 0; $i < $loops; $i++) {

			// TODO: Purchase STOCKS_PER_LOOP stocks.
			sleep(self::LOOP_DELAY);

		}
	}
}
