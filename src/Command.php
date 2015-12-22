<?php

namespace Marks\StockfighterSolution;

use Marks\Stockfighter\Exceptions\StockfighterRequestException;
use Marks\Stockfighter\Objects\Order;
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

	/**
	 * The name of the stock for this command.
	 * @var string|bool
	 */
	protected $stock = false;

	/**
	 * The name of the account for this command.
	 * @var string|bool
	 */
	protected $account = false;

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
		// Parse the arguments.
		$this->venue = $input->getArgument('venue');
		if ($input->hasArgument('stock')) {
			$this->stock = $input->getArgument('stock');
		}
		if ($input->hasArgument('account')) {
			$this->account = $input->getArgument('account');
		}

		// Make sure the venue is up.
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

	/**
	 * Gets the stock path object.
	 *
	 * @return \Marks\Stockfighter\Paths\Stock|null
	 * @throws StockfighterSolutionException
	 */
	protected function stock()
	{
		if (!$this->stock) return null;
		return $this->venue()->stock($this->stock);
	}

	/**
	 * Order a stock.
	 *
	 * @param OutputInterface $output
	 * @param int             $price
	 * @param int             $quantity
	 * @param string          $direction
	 * @param string          $order_type
	 *
	 * @return Order|null
	 */
	protected function order(OutputInterface $output, $price, $quantity, $direction = Order::DIRECTION_BUY, $order_type = Order::ORDER_MARKET)
	{
		$output->write('Purchasing ' . $quantity . ' shares of ' . $this->stock . '...');
		$filled = 0;
		$order = null;
		while ($filled === 0) {
			$output->write('.');
			try {
				$order = $this->stock()->order($this->account, $price,
					$quantity, $direction, $order_type);
			} catch (StockfighterRequestException $ex) {
				$output->writeln('<error>Failed.</error>');
				print_r($ex->body);
				break;
			}
			$filled = $order->totalFilled;
		}
		$output->writeln('<info> ' . $filled . ' filled.</info>');
		return $order;
	}
}
