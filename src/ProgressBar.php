<?php

namespace Marks\StockfighterSolution;

use Symfony\Component\Console\Output\OutputInterface;

class ProgressBar extends \Symfony\Component\Console\Helper\ProgressBar
{
	/**
	 * The last message.
	 * @var string
	 */
	protected $last_message = '';

	public function __construct(OutputInterface $output, $max)
	{
		parent::__construct($output, $max);
		$this->setFormat('%percent%% (%current% / %max%) %message%');
		$this->setMessage('<comment>Waiting...</comment>', 'message', false);
	}

	public function setMessage($message, $name = 'message', $display = true)
	{
		$diff = strlen($this->last_message) - strlen($message);
		$this->last_message = $message;
		if ($diff > 0) {
			$message .= str_repeat(' ', $diff + 10);
		}

		parent::setMessage($message, $name);

		if ($display) {
			$this->display();
		}
	}
}
