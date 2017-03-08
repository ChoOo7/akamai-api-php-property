<?php

/*
 * This file is part of the SncRedisBundle package.
 *
 * (c) Henrik Westphal <henrik.westphal@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;


class GetGroupsCommand extends ContainerAwareCommand
{


    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $groupService = $this->getContainer()->get('akamai.group');
        
        $contract = $this->input->getOption('contract');        

        $groups = $groupService->getGroups($contract);
        
        $this->output->writeln(json_encode($groups, JSON_PRETTY_PRINT));
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('akamai:getgroups');
        $this->setDescription('Get rules for a property');

        $this->addOption(
          'contract',
          '',
          InputOption::VALUE_REQUIRED,
          'Contract',
          null
        );
    }
}