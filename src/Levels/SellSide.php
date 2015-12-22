<?php

namespace Marks\StockfighterSolution\Levels;

use Marks\StockfighterSolution\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SellSide extends Command
{
	protected function configure()
	{
		parent::configure();

		$this->setName('level:sell-side')
			->setDescription('Run the sell-side solution (level 3).');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		parent::execute($input, $output);


	}
}
