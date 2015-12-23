<?php

namespace Marks\StockfighterSolution\Levels;

use Marks\Stockfighter\Objects\Order;
use Marks\Stockfighter\Objects\Quote;
use Marks\StockfighterSolution\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChockABlock extends Command
{
	const STOCKS_PER_PURCHASE = 1000;
	const STOCKS_TO_PURCHASE = 100000;
	const LAST_THRESHOLD = 100;
	const WAIT = 0;

	protected function configure()
	{
		parent::configure();

		$this->setName('level:chock-a-block')
			->setDescription('Run the chock-a-block solution (level 2).');
	}

	protected function conduct(InputInterface $input, OutputInterface $output)
	{
		$last = 0; // Last time we purchased.
		$progress = new ProgressBar($output, self::STOCKS_TO_PURCHASE);
		$progress->setFormat('%percent%% (%current% / %max%) %message%');

		// Get a quote for the stock.
		$output->write('Getting asking price for the stock... ');
		$quote = $this->stock()->quote();
		if (!$quote || !$quote->last) {
			$output->writeln('<error>Failed.</error>');
			exit;
		}
		$target_price = $quote->last;
		$output->writeln('<info>$' . $target_price / 100 . '</info>');

		// Prepare the variables.
		$stocks_left = self::STOCKS_TO_PURCHASE;
		$pending_purchases = 0;
		$output->writeln('Waiting for quote to drop below threshold...');

		// Connect to websockets.
		$this->quotes(function (Quote $quote) use (&$stocks_left, &$last, &$pending_purchases, $target_price, $output, &$progress) {
			if ($stocks_left <= 0) return true;
			if ($quote->last <= $target_price + self::LAST_THRESHOLD && microtime(true) > $last + self::WAIT) {
				$stocks_minus_pending = $stocks_left - $pending_purchases;
				$to_purchase = $stocks_minus_pending > self::STOCKS_PER_PURCHASE ? self::STOCKS_PER_PURCHASE : $stocks_minus_pending;
				if ($to_purchase <= 0) return false;
				$pending_purchases += $to_purchase;
				$this->orderAsync($quote->last, $to_purchase)->then(function (Order $order) use (&$pending_purchases, &$stocks_left, &$progress) {

					$pending_purchases -= $order->totalFilled;
					$stocks_left -= $order->totalFilled;

					// Start the progress if we haven't.
					if (!$progress->getStartTime()) {
						$progress->start();
					}
					$progress->setProgress(self::STOCKS_TO_PURCHASE - $stocks_left);
					$progress->setMessage('<info>$' . $order->price / 100 . '</info>');

				}, function () use ($progress) {
					$progress->setMessage('<comment>fail</comment>');
				});
				$last = microtime(true);
			}
		});
	}
}
