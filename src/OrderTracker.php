<?php

namespace Marks\StockfighterSolution;

use Evenement\EventEmitter;
use Marks\Stockfighter\Objects\Execution;
use Marks\Stockfighter\Objects\Fill;
use Marks\Stockfighter\Objects\Order;
use Marks\Stockfighter\Objects\Symbol;

class OrderTracker extends EventEmitter
{
	/**
	 * The running list of orders.
	 * @var Order[]
	 */
	protected $orders = array();

	/**
	 * Track a new order (or update an existing order).
	 *
	 * @param Order $order
	 * @param bool  $first Whether or not this is the first call. Used internally.
	 */
	public function track(Order $order, $first = true)
	{
		$existing_index = -1;
		foreach ($this->orders as $index => &$existing_order) {
			if ($existing_order->id == $order->id) {
				$existing_order = $order;
				$existing_index = $index;
			}
		}

		// Is the order finished?
		if ($order->totalFilled >= $order->qty) {

			// If it's the first time the order has been added, mark it as executed so we
			// can count the fills.
			if ($first) {
				$this->emit('executed', [$this->generateExecution($order)]);
			}

			$this->emit('complete', [$order]);
			if ($existing_index !== -1) {
				unset($this->orders[$existing_index]);
			}
			return;

		}

		if ($existing_index !== -1) {
			$this->emit('updated', [$order]);
		} else {
			$this->orders[] = $order;
			$this->emit('added', [$order]);
		}
	}

	/**
	 * Given an order, generates as much of an execution as possible.
	 *
	 * @param Order $order
	 *
	 * @return Execution
	 */
	protected function generateExecution(Order $order)
	{
		$execution = new Execution(array());
		$execution->account = $order->account;
		$execution->symbol = new Symbol(['symbol' => $order->symbol]);
		$execution->venue = $order->venue;
		$execution->order = $order;
		$execution->standingId = $order->id;
		$execution->incomingId = -1;
		$execution->price = $order->fills[0]->price;
		$execution->filled = $order->totalFilled;
		$execution->filledAt = $order->ts;
		$execution->standingComplete = true;
		$execution->incomingComplete = true;

		return $execution;
	}

	/**
	 * Execution callback (hook up your WebSocket to this).
	 *
	 * @param Execution $execution
	 */
	public function executed(Execution $execution)
	{
		// Check to see if the order belongs to one of ours...
		$order_index = -1;
		foreach ($this->orders as $index => $order) {
			if ($order->id == $execution->standingId) {
				$order_index = $index;
				break;
			}
		}
		if ($order_index === -1) return;

		// Emit the executed event.
		$this->emit('executed', [$execution]);

		// Is the order complete?
		if ($execution->standingComplete) {
			$this->emit('complete', [$this->orders[$order_index]]);
			unset($this->orders[$order_index]);
			return;
		}

		// Update the order.
		$order = $this->orders[$order_index];
		$order->totalFilled += $execution->filled;
		$order->fills[] = new Fill(['price' => $execution->price, 'qty' => $execution->filled, 'ts' => $execution->filledAt]);
		$this->track($order);
	}
}
