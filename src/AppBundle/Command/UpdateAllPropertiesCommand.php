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


class UpdateAllPropertiesCommand extends ContainerAwareCommand
{


    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $propertyService = $this->getContainer()->get('akamai.property');
        $groupService = $this->getContainer()->get('akamai.group');

        $contract = $this->input->getOption('contract');
        $property = $this->input->getOption('property');

        $groups = $groupService->getGroups($contract);
        foreach($groups['groups']['items'] as $groupInfo)
        {
            try
            {
                //var_dump($groupInfo);
                if( ! array_key_exists('contractIds', $groupInfo) || $groupInfo['contractIds'][0] != $contract)
                {
                    continue;
                }
                $properties = $propertyService->getProperties($contract, $groupInfo['groupId']);
                //var_dump($properties);
                foreach ($properties['properties']['items'] as $propertyInfo)
                {
                    $ruleService = $this->getContainer()->get('akamai.rule');
                    if($property && $property != $propertyInfo['propertyName'])
                    {
                        continue;
                    }
                    
                    $version = $propertyInfo['productionVersion'];

                    $rule = $ruleService->getRule($contract, $groupInfo['groupId'], $propertyInfo['propertyId'], $version);
                    /*
                    var_dump("_________");
                    var_dump($propertyInfo['propertyName']);
                    var_dump($rule);
                    */
                    $ruleEdited = $rule;

                    $ruleEdited = $this->tryToAddIfUnmodifiedSince($ruleEdited);
                    $ruleEdited = $this->tryToAddCacheForVideoOrigin($ruleEdited);
                    if($ruleEdited != $rule)
                    {
                        $this->output->writeln('Rule '.$propertyInfo['propertyName'].' edited ! ');

                        $versionService = $this->getContainer()->get('akamai.version');

                        $this->output->writeln('Creating new version from '.$version);
                        
                        $versionEtag = $rule['etag'];
                        $versionResult = $versionService->createVersion($contract, $groupInfo['groupId'], $propertyInfo['propertyId'], $version, $versionEtag);


                        $_propertyInfoOf = $propertyService->getProperty($contract, $groupInfo['groupId'], $propertyInfo['propertyId']);
                        $newVersion = $_propertyInfoOf['properties']['items'][0]['latestVersion'];
                        
                        $this->output->writeln('Getting new version etag of version '.$newVersion);
                        $newVersionRule = $ruleService->getRule($contract, $groupInfo['groupId'], $propertyInfo['propertyId'], $newVersion);
                        $newVersionRuleEtag = $newVersionRule['etag'];
                        
                        //$this->output->writeln('Etag : '.$newVersionRuleEtag);                        

                        $this->output->writeln('Editing property');
                        $ruleResult = $ruleService->setRule($contract, $groupInfo['groupId'], $propertyInfo['propertyId'], $newVersion, $newVersionRuleEtag, $ruleEdited['rules']);

                        $this->output->writeln('Activating on STAGING');
                        $propertyService->activateProperty($contract, $groupInfo['groupId'], $propertyInfo['propertyId'], $newVersion, 'STAGING');
                        $this->output->writeln('Activating on STAGING Done');

                        $this->output->writeln('Activating PRODUCTION');
                        $actRes = $propertyService->activateProperty($contract, $groupInfo['groupId'], $propertyInfo['propertyId'], $newVersion, 'PRODUCTION');
                        $this->output->writeln('Activating on PRODUCTIOn Done');


                    }else{
                        $this->output->writeln('Rule '.$propertyInfo['propertyName'].' untouched');
                    }
                }
            }
            catch(\GuzzleHttp\Exception\ClientException $e)
            {
                $this->output->writeln('<error>'.$e->getMessage().'</error>');
                $this->output->writeln('<error>'.$e->getResponse()->getBody().'</error>');
            }
        }



        //$this->output->writeln(json_encode($properties, JSON_PRETTY_PRINT));
    }

    protected function tryToAddIfUnmodifiedSince($rule)
    {
        if(array_key_exists('rules', $rule))
        {
            $rule['rules'] = $this->tryToAddIfUnmodifiedSince($rule['rules']);
        }
        if(array_key_exists('children', $rule))
        {
            foreach ($rule['children'] as $childrenKey => $children)
            {
                $thisChildrenContainsIfMatch = false;
                $thisChildrenContainsIfUnmodifiedSince = false;
                $childrenName = $children['name'];
                foreach ($children['behaviors'] as $behavior)
                {
                    //var_dump($behavior, $behavior['name']);
                    if ($behavior['name'] == 'modifyIncomingRequestHeader')
                    {
                        if ($behavior['options']['customHeaderName'] == 'If-Match')
                        {
                            $thisChildrenContainsIfMatch = true;
                        }
                        if ($behavior['options']['customHeaderName'] == 'If-Unmodified-Since')
                        {
                            $thisChildrenContainsIfUnmodifiedSince = true;
                        }
                    }
                }
                if ($thisChildrenContainsIfMatch && !$thisChildrenContainsIfUnmodifiedSince)
                {
                    $rule['children'][$childrenKey]['behaviors'][] = array(
                      'name' => 'modifyIncomingRequestHeader', 'options' => array("action" => "DELETE", "standardDeleteHeaderName" => "OTHER", "customHeaderName" => "If-Unmodified-Since"), "uuid"=>$this->gen_uuid());
                }

                if (array_key_exists('children', $children))
                {
                    $rule['children'][$childrenKey] = $this->tryToAddIfUnmodifiedSince($rule['children'][$childrenKey]);
                }
            }
        }
        return $rule;
    }
    
    protected function tryToAddCacheForVideoOrigin($rule)
    {
        if(array_key_exists('rules', $rule))
        {
            $rule['rules'] = $this->tryToAddCacheForVideoOrigin($rule['rules']);
        }
        if(array_key_exists('children', $rule))
        {
            foreach ($rule['children'] as $childrenKey => $children)
            {
                $thisChildrenContainsIfMatch = false;
                $thisChildrenContainsCacheDirective = false;
                $childrenName = $children['name'];
                foreach ($children['behaviors'] as $behavior)
                {
                    //var_dump($behavior, $behavior['name']);
                    if ($behavior['name'] == 'modifyIncomingRequestHeader')
                    {
                        if ($behavior['options']['customHeaderName'] == 'If-Match')
                        {
                            $thisChildrenContainsIfMatch = true;
                        }
                    }
                    if($behavior['name'] == 'caching' && $behavior['options']['behavior'] == 'CACHE_CONTROL_AND_EXPIRES')
                    {
                        $thisChildrenContainsCacheDirective = true;
                    }
                }
                if ($thisChildrenContainsIfMatch && ! $thisChildrenContainsCacheDirective)
                {
                    $rule['children'][$childrenKey]['behaviors'][] = array('name' => 'caching', 'options' =>
                      array("behavior" => "CACHE_CONTROL_AND_EXPIRES", "mustRevalidate" => false, "defaultTtl" => "7d"),
                      "uuid"=>$this->gen_uuid()
                    );
                    $rule['children'][$childrenKey]['behaviors'][] = array('name' => 'modifyOutgoingResponseHeader', 'options' =>
                      array("action" => "MODIFY", "standardModifyHeaderName" => "CACHE_CONTROL", "newHeaderValue" => "public, max-age=604800", "avoidDuplicateHeaders"=>true),
                      "uuid"=>$this->gen_uuid()
                    );
                }

                if (array_key_exists('children', $children))
                {
                    $rule['children'][$childrenKey] = $this->tryToAddCacheForVideoOrigin($rule['children'][$childrenKey]);
                }
            }
        }
        return $rule;
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('akamai:updateallproperties');
        $this->setDescription('Update rules for all property');

        $this->addOption(
          'contract',
          '',
          InputOption::VALUE_REQUIRED,
          'Contract',
          null
        );
        $this->addOption(
          'property',
          '',
          InputOption::VALUE_OPTIONAL,
          'Property',
          null
        );

    }

    protected function gen_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
          // 32 bits for "time_low"
          mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

          // 16 bits for "time_mid"
          mt_rand( 0, 0xffff ),

          // 16 bits for "time_hi_and_version",
          // four most significant bits holds version number 4
          mt_rand( 0, 0x0fff ) | 0x4000,

          // 16 bits, 8 bits for "clk_seq_hi_res",
          // 8 bits for "clk_seq_low",
          // two most significant bits holds zero and one for variant DCE1.1
          mt_rand( 0, 0x3fff ) | 0x8000,

          // 48 bits for "node"
          mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}