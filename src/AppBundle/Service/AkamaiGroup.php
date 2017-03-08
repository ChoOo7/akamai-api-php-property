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


class AkamaiGroup
{
    
    public function getGroups($contract)
    {
        $client = $this->getClient();

        $url = '/papi/v0/groups';
        $getParams = array( 'contractId' => $contract);
        $response = $client->get($url,
          array(
            'query'=> $getParams
          )
        );
        return json_decode($response->getBody(), true);
    }
    
    protected function getClient() {
        $client = \Akamai\Open\EdgeGrid\Client::createFromEdgeRcFile();
        return $client;
    }
}