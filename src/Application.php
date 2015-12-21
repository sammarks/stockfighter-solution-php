<?php

namespace Marks\StockfighterSolution;

use Configula\Config;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
	/**
	 * The configuration for the application.
	 * @var Config
	 */
	protected $config = null;

	/**
	 * Sets the configuration for the current application.
	 *
	 * @param Config $config
	 */
	public function setConfig(Config $config)
	{
		$this->config = $config;
	}

	/**
	 * Gets the configuration object.
	 *
	 * @return Config
	 */
	public function config()
	{
		return $this->config;
	}
}
