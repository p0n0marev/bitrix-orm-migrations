<?php
namespace P0n0marev\BitrixMigrations\Command;

use P0n0marev\BitrixMigrations\Migrate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('migrations:status')
			->setDescription('View the status of a set of migrations.')
			->setHelp('View the status of a set of migrations.');
	}

	/**
	 * количество доступных
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return string
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$migrate = new Migrate();
		$migrate->status();
	}
}