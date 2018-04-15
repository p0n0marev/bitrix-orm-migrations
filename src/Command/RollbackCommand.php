<?php
namespace P0n0marev\BitrixMigrations\Command;

use P0n0marev\BitrixMigrations\Migrate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RollbackCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('migrations:rollback')
			->setDescription('Rollback latest a migration.');
	}

	/**
	 * запустить необработаные
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return string
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$migrate = new Migrate();
		$migrate->rollback();
	}
}