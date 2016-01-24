<?php

namespace Marks\StockfighterSolution\Levels;

use Marks\Stockfighter\Objects\Execution;
use Marks\Stockfighter\Objects\Order;
use Marks\Stockfighter\Objects\Quote;
use Marks\Stockfighter\WebSocket\WebSocket;
use Marks\StockfighterSolution\Command;
use Marks\StockfighterSolution\OrderTracker;
use Marks\StockfighterSolution\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChockABlock extends Command
{
	const PURCHASE_MIN = 2000;
	const PURCHASE_MAX = 20000;
	const STOCKS_TO_PURCHASE = 100000;
	const COOLDOWN_MIN = 3; // In seconds.
	const COOLDOWN_MAX = 10;
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
		$root = $this;

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
		$pending_order = false;
		$last_activity = 0;
		$cooldown = 0;
		$output->writeln('Waiting for quote to drop below threshold...');

		// Start the progress bar.
		$progress->start();

		// Prepare the order tracker.
		$tracker = new OrderTracker();

		// Setup the events.
		$tracker->on('executed', function (Execution $execution) use (&$stocks_left, &$last_activity, $progress) {
			$stocks_left -= $execution->filled;
			$last_activity = microtime(true);
			$progress->setProgress(self::STOCKS_TO_PURCHASE - $stocks_left);
			$progress->setMessage('<info>+' . $execution->filled . '</info>');
		});
		$tracker->on('complete', function (Order $order) use ($progress, &$last_activity, &$pending_order) {
			$pending_order = false;
			$last_activity = microtime(true);
			$progress->setMessage('<info>Complete (' . $order->id . ')</info>');
		});

		// Get the websockets communicator.
		$communicator = $this->stockfighter->getWebSocketCommunicator();
		$quotes_socket = $communicator->quotes($this->account, $this->venue, $this->stock);
		$executions_socket = $communicator->executions($this->account, $this->venue, $this->stock);

		// Attach the quotes websocket events.
		$quotes_socket->on('message', function (WebSocket $client, Quote $quote) use ($stocks_left, &$last_activity, &$cooldown, &$pending_order, $target_price, $progress, $tracker, $root) {

			if ($stocks_left <= 0) {
				$client->close();
				return; // Cancel if we don't have any more stocks to purchase.
			}
			if ($pending_order) return; // Skip this quote if we have an unfilled order.
			if (microtime(true) - $last_activity <= $cooldown) return; // Don't order if we're not past the cooldown.
			$cooldown = rand(self::COOLDOWN_MIN, self::COOLDOWN_MAX);
			$last_activity = microtime(true);

			// Don't process this quote if it's too expensive.
			if ($quote->last > $target_price + self::LAST_THRESHOLD) return;

			// Get the number of stocks to purchase.
			$per_purchase = rand(self::PURCHASE_MIN, self::PURCHASE_MAX);
			$to_purchase = ($stocks_left > $per_purchase) ?
				$per_purchase : $stocks_left;
			if ($to_purchase <= 0) {
				$client->close();
				return; // Close if we don't have any more stocks to purchase.
			}

			// Purchase the stock.
			$root->orderAsync($target_price, $to_purchase)
				->then(function (Order $order) use ($tracker, $to_purchase, $progress, &$pending_order) {
					$pending_order = true;
					$progress->setMessage('Purchasing ' . $to_purchase . ' @ $' . $order->price / 100);
					$tracker->track($order); // Track the order.
				}, function () use ($progress) {
					$progress->setMessage('<comment>purchase fail</comment>');
				});

		});
		$quotes_socket->on('error', function () use ($progress) {
			$progress->setMessage('<comment>fail</comment>');
		});

		// Attach to the executions websocket events.
		$executions_socket->on('message', function (WebSocket $client, Execution $execution) use ($stocks_left, $tracker) {
			if ($stocks_left <= 0) {
				$client->close();
				return;
			}
			$tracker->executed($execution);
		});
		$executions_socket->on('error', function () use ($progress) {
			$progress->setMessage('<comment>executions fail</comment>');
		});

		// Start the sockets.
		$executions_socket->connect();
		$quotes_socket->connect();
	}
}
