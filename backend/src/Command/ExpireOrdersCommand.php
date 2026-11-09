<?php

declare(strict_types=1);

namespace App\Command;

use App\Checkout\OrderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:orders:expire', description: 'Cancel pending orders whose key reservation expired and release their keys')]
final class ExpireOrdersCommand extends Command
{
    public function __construct(private readonly OrderService $orders)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('Cancelled %d expired order(s).', $this->orders->expireStale()));

        return Command::SUCCESS;
    }
}
