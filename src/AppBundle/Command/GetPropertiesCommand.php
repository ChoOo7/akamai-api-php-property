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


class GetPropertiesCommand extends ContainerAwareCommand
{


    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $propertyService = $this->getContainer()->get('akamai.property');
        

        $contract = $this->input->getOption('contract');
        $group = $this->input->getOption('group');
        
        try
        {
            $properties = $propertyService->getProperties($contract, $group);
            $this->output->writeln(json_encode($properties, JSON_PRETTY_PRINT));
        }
        catch(\Exception $e)
        {
            $this->output->writeln('<error>'.$e->getMessage().'</error>');
        }
        
        
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('akamai:getproperties');
        $this->setDescription('Get rules for a property');

        $this->addOption(
          'contract',
          '',
          InputOption::VALUE_REQUIRED,
          'Contract',
          null
        );
        $this->addOption(
          'group',
          '',
          InputOption::VALUE_REQUIRED,
          'Group',
          null
        );

    }
}