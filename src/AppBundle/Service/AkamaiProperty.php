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


class AkamaiProperty
{
    public function getProperty($contract, $group, $property)
    {
        $client = $this->getClient();

        $url = '/papi/v0/properties/'.$property.'';
        $getParams = array( 'contractId' => $contract, 'groupId' => $group );
        $response = $client->get($url,
          array(
            'query'=> $getParams
          )
        );
        return json_decode($response->getBody(), true);
    }
    
    public function getProperties($contract, $group)
    {
        $client = $this->getClient();

        $url = '/papi/v0/properties/';
        $getParams = array( 'contractId' => $contract, 'groupId'=>$group);
        $response = $client->get($url, array('query' => $getParams));
        return json_decode($response->getBody(), true);
    }


    public function activateProperty($contract, $group, $property, $propertyVersion, $network='STAGING', $acknowledgeWarnings = array())
    {
        $client = $this->getClient();

        $url = '/papi/v0/properties/'.$property.'/activations/';
        $getParams = array( 'contractId' => $contract, 'groupId' => $group );
        $rawData = array(
            "propertyVersion"=> $propertyVersion,
            "network"=> $network,
            "note"=> "API activation on ".date('Y-m-d H:i;s'). ' from '.gethostname(),
            "notifyEmails"=> [
                "log@damdy.com",
                "mep@damdy.com"
            ],
            "acknowledgeWarnings"=> $acknowledgeWarnings
        );
        $rawData = json_encode($rawData);
        
        try
        {
            $response = $client->post($url, array('query' => $getParams, 'body' => $rawData, 'headers' => array('Content-Type' => 'application/json')));
        }
        catch(\Exception $e)
        {
            $adapted = false;
            if(empty($acknowledgeWarnings))
            {
                if(method_exists($e, 'getResponse'))
                {
                    $body = $e->getResponse()->getBody();
                    $body = json_decode($body, true);
                    if(array_key_exists('warnings', $body))
                    {
                        foreach($body['warnings'] as $warning)
                        {
                            $acknowledgeWarnings[] = $warning['messageId'];
                        }
                    }
                    if( ! empty($acknowledgeWarnings))
                    {
                        $adapted = true;
                        return $this->activateProperty($contract, $group, $property, $propertyVersion, $network, $acknowledgeWarnings);                        
                    }
                }
            }
            if( ! $adapted)
            {
                throw $e;
            }
        }
        
        return json_decode($response->getBody(), true);
    }
    
    protected function getClient() {
        $client = \Akamai\Open\EdgeGrid\Client::createFromEdgeRcFile();
        return $client;
    }
}