<?php

namespace Marks\StockfighterSolution\Levels;

use Marks\Stockfighter\Objects\Quote;
use Marks\StockfighterSolution\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WebSocket\ConnectionException;

class ChockABlock extends Command
{
	const STOCKS_PER_PURCHASE = 10000;
	const STOCKS_TO_PURCHASE = 100000;
	const LAST_THRESHOLD = 100;
	const WAIT = 10;

	protected function configure()
	{
		parent::configure();

		$this->setName('level:chock-a-block')
			->setDescription('Run the chock-a-block solution (level 2).');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		parent::execute($input, $output);
		$last = 0; // Last time we purchased.

		// Get a quote for the stock.
		$output->write('Getting asking price for the stock... ');
		$quote = $this->stock()->quote();
		if (!$quote || !$quote->last) {
			$output->writeln('<error>Failed.</error>');
			exit;
		}
		$target_price = $quote->last;
		$output->writeln('<info>' . $target_price . '</info>');

		// Prepare the variables.
		$stocks_left = self::STOCKS_TO_PURCHASE;
		$output->writeln('Waiting for quote to drop below threshold...');

		// Connect to websockets.
		$this->quotes($output, function (Quote $quote) use (&$stocks_left, &$last, $target_price, $output) {
			if ($stocks_left <= 0) return true;
			if ($quote->last <= $target_price + self::LAST_THRESHOLD && microtime(true) > $last + self::WAIT) {
				$to_purchase = $stocks_left > self::STOCKS_PER_PURCHASE ? self::STOCKS_PER_PURCHASE : $stocks_left;
				$order = $this->order($output, 0, $to_purchase);
				$stocks_left -= $order->totalFilled;
				$last = microtime(true);
			}
			return false;
		});
	}
}
