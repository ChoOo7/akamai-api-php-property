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


class SetRuleCommand extends ContainerAwareCommand
{


    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        
        $ruleService = $this->getContainer()->get('akamai.rule');        

        $contract = $this->input->getOption('contract');
        $group = $this->input->getOption('group');
        $property = $this->input->getOption('property');
        $ruleFile = $this->input->getOption('ruleFile');
        
        $version = $this->input->getOption('propertyversion');
        if($version == null)
        {
            $propertyService = $this->getContainer()->get('akamai.property');
            $propertyInfo = $propertyService->getProperty($contract, $group, $property);
            $version = $propertyInfo['properties']['items'][0]['latestVersion'];
        }
        
        //TODO : test file exists
        $ruleContent = file_get_contents($ruleFile);

        $rule = $ruleService->getRule($contract, $group, $property, $version);
        $versionEtag = $rule['etag'];

        $ruleResult = $ruleService->setRule($contract, $group, $property, $version, $versionEtag, $ruleContent);
        
        $this->output->writeln(json_encode($ruleResult, JSON_PRETTY_PRINT));
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('akamai:setrule');
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

        $this->addOption(
          'property',
          '',
          InputOption::VALUE_REQUIRED,
          'Property',
          null
        );
        
        $this->addOption(
          'propertyversion',
          '',
          InputOption::VALUE_OPTIONAL,
          'Version',
          null
        );

        $this->addOption(
          'ruleFile',
          '',
          InputOption::VALUE_REQUIRED,
          'ruleFile',
          null
        );
        
        
    }
}