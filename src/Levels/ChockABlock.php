<?php

namespace Marks\StockfighterSolution\Levels;

use Marks\Stockfighter\Objects\Execution;
use Marks\Stockfighter\Objects\Order;
use Marks\Stockfighter\Objects\Quote;
use Marks\StockfighterSolution\Command;
use Marks\StockfighterSolution\OrderTracker;
use Marks\StockfighterSolution\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChockABlock extends Command
{
	const STOCKS_PER_PURCHASE = 2000;
	const STOCKS_TO_PURCHASE = 100000;
	const LAST_THRESHOLD = 100;

	protected function configure()
	{
		parent::configure();

		$this->setName('level:chock-a-block')
			->setDescription('Run the chock-a-block solution (level 2).');
	}

	protected function conduct(InputInterface $input, OutputInterface $output)
	{
		// Prepare the progress bar.
		$progress = new ProgressBar($output, self::STOCKS_TO_PURCHASE);

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

		// Start the progress bar.
		$progress->start();

		// Prepare the order tracker.
		$tracker = new OrderTracker();

		// Setup the events.
		$tracker->on('executed', function (Execution $execution) use (&$stocks_left, &$pending_purchases, $progress) {
			$stocks_left -= $execution->filled;
			$pending_purchases -= $execution->filled;
			$progress->setProgress(self::STOCKS_TO_PURCHASE - $stocks_left);
			$progress->setMessage('<info>+' . $execution->filled . '</info>');
		});
		$tracker->on('complete', function (Order $order) use ($progress) {
			$progress->setMessage('<info>Complete (' . $order->id . ')</info>');
		});

		// Get the websockets communicator.
		$communicator = $this->stockfighter->getWebSocketCommunicator();
		$quotes_socket = $communicator->quotes($this->account, $this->venue, $this->stock);
		$executions_socket = $communicator->executions($this->account, $this->venue, $this->stock);

		// Attach the quotes websocket events.
		$quotes_socket->receive(function (Quote $quote) use ($stocks_left, &$pending_purchases, $target_price, $progress, $tracker) {

			if ($stocks_left <= 0) return true; // Cancel if we don't have any more stocks to purchase.

			// Don't process this quote if it's too expensive.
			if ($quote->last > $target_price + self::LAST_THRESHOLD) return false;

			// Get the number of stocks to purchase.
			$stocks_minus_pending = $stocks_left - $pending_purchases;
			$to_purchase = ($stocks_minus_pending > self::STOCKS_PER_PURCHASE) ?
				self::STOCKS_PER_PURCHASE : $stocks_minus_pending;
			if ($to_purchase <= 0) return true; // Cancel if we don't have any to purhcase.

			// Purchase the stock.
			$pending_purchases += $to_purchase;
			$this->orderAsync($quote->last, $to_purchase)
				->then(function (Order $order) use ($tracker, $progress) {
					$progress->setMessage('Purchasing @ $' . $order->price / 100);
					$tracker->track($order); // Track the order.
				}, function () use ($progress) {
					$progress->setMessage('<comment>purchase fail</comment>');
				});

		}, function () use ($progress) {
			$progress->setMessage('<comment>fail</comment>');
		});

		// Attach to the executions websocket events.
		$executions_socket->receive(function (Execution $execution) use ($stocks_left, $tracker) {
			if ($stocks_left <= 0) return true; // Cancel if we don't have any more stocks to monitor.
			$tracker->executed($execution);
		}, function () use ($progress) {
			$progress->setMessage('<comment>executions fail</comment>');
		});

		// Start the sockets.
		$executions_socket->connect();
		$quotes_socket->connect();
	}
}
