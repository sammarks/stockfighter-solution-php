<?php

namespace Marks\StockfighterSolution\Levels;

use Marks\Stockfighter\Objects\Execution;
use Marks\Stockfighter\Objects\Order;
use Marks\Stockfighter\Objects\Quote;
use Marks\StockfighterSolution\Command;
use Marks\StockfighterSolution\OrderTracker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SellSide extends Command
{
	const MAX_BASIS = 10000;
	const MAX_SHARES = 1000;

	/**
	 * @var OrderTracker
	 */
	protected $tracker = null;

	protected $basis = self::MAX_BASIS;
	protected $actual_basis = self::MAX_BASIS;
	protected $shares = 0;
	protected $actual_shares = 0;
	protected $rolling_average_count = 0;
	protected $rolling_average_total = 0;

	protected function configure()
	{
		parent::configure();

		$this->setName('level:sell-side')
			->setDescription('Run the sell-side solution (level 3).');

		$this->tracker = new OrderTracker();
	}

	protected function conduct(InputInterface $input, OutputInterface $output)
	{
		// Get a quote for the stock to serve as the rolling average.
		$output->write('Getting initial stock price... ');
		$quote = $this->stock()->quote();
		if (!$quote || !$quote->last) {
			$output->writeln('<error>failed.</error>');
			exit;
		}
		$this->reportRollingAverage($quote->last);
		$output->writeln('<info>$' . $this->rollingAverage() / 100 . '</info>');

		// Setup the events.
		$this->tracker->on('executed', [$this, 'executed']);
		$this->tracker->on('complete', [$this, 'complete']);

		// Get the websockets communicator.
		$communicator = $this->stockfighter->getWebSocketCommunicator();
		$quotes_socket = $communicator->quotes($this->account, $this->venue,
			$this->stock);
		$executions_socket = $communicator->executions($this->account, $this->venue,
			$this->stock);

		// Attach the websocket events.
		$quotes_socket->receive([$this, 'receiveQuote'], [$this, 'error']);
		$executions_socket->receive([$this, 'receiveExecution'], [$this, 'error']);

		// Start the sockets.
		$executions_socket->connect();
		$quotes_socket->connect();
	}

	protected function reportRollingAverage($price)
	{
		$this->rolling_average_total += $price;
		$this->rolling_average_count++;
	}

	protected function rollingAverage()
	{
		return $this->rolling_average_total / $this->rolling_average_count;
	}

	protected function executed(Execution $execution)
	{
		if ($execution->order->direction == Order::DIRECTION_BUY) {
			$this->actual_shares += $execution->filled;
		} else {
			$this->actual_shares -= $execution->filled;
		}
	}

	protected function complete(Quote $quote)
	{

	}

	protected function error()
	{

	}

	protected function receiveQuote(Quote $quote)
	{
		// Once we get a quote, update the rolling average to include
		// the value of the quote.

		// See if the quote is around the rolling average within reason.
		// If it's not, stop here.

		// TODO: Purchase logic here.
	}

	protected function receiveExecution(Execution $execution)
	{
		$this->tracker->executed($execution);
	}

}
