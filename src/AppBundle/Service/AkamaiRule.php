<?php

/*
 * This file is part of the SncRedisBundle package.
 *
 * (c) Henrik Westphal <henrik.westphal@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Service;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;


class AkamaiRule
{
    public function getRule($contract, $group, $property, $version)
    {
        $client = $this->getClient();

        $url = '/papi/v0/properties/'.$property.'/versions/'.$version.'/rules/';
        $getParams = array( 'contractId' => $contract, 'groupId' => $group );
        $response = $client->get($url,
          array(
            'query'=> $getParams
          )
        );
        return json_decode($response->getBody(), true);
    }

    public function setRule($contract, $group, $property, $version, $versionEtag, $ruleContent)
    {
        $client = $this->getClient();

        $url = '/papi/v0/properties/'.$property.'/versions/'.$version.'/rules/';
        $getParams = array( 'contractId' => $contract, 'groupId' => $group );
        $rules = $ruleContent;
        if(is_string($rules))
        {
            $rules = json_decode($ruleContent, true);
        }
        $rawData = json_encode(array('rules'=>$rules));
        
        $response = $client->put($url,
          array(
            'query'=> $getParams,
            'body' => $rawData,
            'headers'=>array(
              'Content-Type' => 'application/vnd.akamai.papirules.latest+json',
              'If-Match'=> '"'.$versionEtag.'"'
            )
          )
        );
        return json_decode($response->getBody(), true);
    }

    protected function getClient() {
        $client = \Akamai\Open\EdgeGrid\Client::createFromEdgeRcFile();
        return $client;
    }
}