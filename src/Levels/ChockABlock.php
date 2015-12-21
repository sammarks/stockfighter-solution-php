<?php

namespace Marks\StockfighterSolution\Levels;

use Marks\Stockfighter\Exceptions\StockfighterRequestException;
use Marks\Stockfighter\Objects\Order;
use Marks\StockfighterSolution\Command;
use Symfony\Component\Console\Input\InputArgument;
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
			->setDescription('Run the chock-a-block solution (level 2).')
			->addArgument('account', InputArgument::REQUIRED, 'The account name.')
			->addArgument('stock', InputArgument::REQUIRED, 'The name of the stock to purchase.');
	}

	/**
	 * Gets the stock path object.
	 *
	 * @param InputInterface $input
	 *
	 * @return \Marks\Stockfighter\Paths\Stock
	 * @throws \Marks\StockfighterSolution\Exceptions\StockfighterSolutionException
	 */
	protected function stock(InputInterface $input)
	{
		return $this->venue()->stock($input->getArgument('stock'));
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		parent::execute($input, $output);
		$stock = $input->getArgument('stock');
		$account = $input->getArgument('account');
		$last = 0; // Last time we purchased.

		// Get a quote for the stock.
		$output->write('Getting asking price for the stock... ');
		$quote = $this->stock($input)->quote();
		if (!$quote || !$quote->last) {
			$output->writeln('<error>Failed.</error>');
			exit;
		}
		$target_price = $quote->last;
		$output->writeln('<info>' . $target_price . '</info>');

		// Connect to the websockets.
		$output->write('Connecting to websockets... ');
		$websocket = $this->stockfighter->getWebSocketCommunicator()->quotes($account,
			$input->getArgument('venue'), $stock);
		$websocket->connect();
		$output->writeln('<info>ok.</info>');

		$stocks_left = self::STOCKS_TO_PURCHASE;

		// Wait until we have a quote within the threshold.
		$output->writeln('Waiting for quote to drop below threshold...');
		while (true) {

			if ($stocks_left <= 0) break;

			// Receive a quote.
			$quote = null;
			try {
				$quote = $websocket->receive();
			} catch (ConnectionException $ex) {
				$output->writeln('<comment>Connection lost. Reconnecting...</comment>');
				$websocket->connect();
				continue;
			}
			if ($quote->last <= $target_price + self::LAST_THRESHOLD && microtime(true) > $last + self::WAIT) {
				$to_purchase = $stocks_left > self::STOCKS_PER_PURCHASE ? self::STOCKS_PER_PURCHASE : $stocks_left;
				$output->write('Purchasing ' . $to_purchase . ' shares of ' . $stock . '...');
				$filled = 0;
				while ($filled === 0) {
					$order = null;
					$output->write('.');
					try {
						$order = $this->stock($input)
							->order($account, 0, self::STOCKS_PER_PURCHASE, Order::DIRECTION_BUY, Order::ORDER_MARKET);
					} catch (StockfighterRequestException $ex) {
						$output->writeln('<error>Failed.</error>');
						print_r($ex->body);
						break;
					}
					$filled = $order->totalFilled;
				}
				$output->writeln('<info> ' . $filled . ' filled.</info>');
				$stocks_left -= $filled;
				$last = microtime(true);
			}

		}
	}
}
