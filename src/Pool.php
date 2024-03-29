<?php 

namespace Wpjscc\MySQL;

use React\MySQL\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use React\Promise\Deferred;
use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;
use React\EventLoop\Loop;
use React\Promise\Timer\TimeoutException;

class Pool
{
    private $max_connections;
    private $max_wait_queue;
    private $current_connections = 0;
    private $wait_timeout = 0;
    private $idle_connections = [];
    private $wait_queue = [];
    private $loop;
    private $factory;
    private $uri;


    public function __construct(
        $uri,
        $config = [],
        LoopInterface $loop = null,
        ConnectorInterface $connector = null
    )
    {
        $this->uri = $uri;
        $this->max_connections = $config['max_connections'] ?? 10;
        $this->max_wait_queue = $config['max_wait_queue'] ?? 50;
        $this->wait_timeout = $config['wait_timeout'] ?? 0;
        $this->loop = $loop ?: Loop::get();
        $this->factory = new Factory($loop, $connector);;
    }

    public function query($sql, array $params = [])
    {
        $deferred = new Deferred();

        $this->getIdleConnection()->then(function (ConnectionInterface $connection) use ($sql, $params, $deferred) {
            $connection->query($sql, $params)->then(function(QueryResult $command) use ($deferred, $connection) {
                try {
                    $deferred->resolve($command);
                } catch (\Throwable $th) {
                    //todo handle $th
                }
                $this->releaseConnection($connection);
            }, function (\Exception $e) use ($deferred, $connection) {
                $deferred->reject($e);
                $this->releaseConnection($connection);
            });
        }, function (\Exception $e) use ($deferred) {
            $deferred->reject($e);
        });

        return $deferred->promise();

    }

    public function getIdleConnection()
    {
        if ($this->idle_connections) {
            $connection = array_shift($this->idle_connections);
            return \React\Promise\resolve($connection);
        }

        if ($this->current_connections < $this->max_connections) {
            $this->current_connections++;
            return \React\Promise\resolve($this->factory->createLazyConnection($this->uri));
        }

        if ($this->max_wait_queue && count($this->wait_queue) >= $this->max_wait_queue) {
            return \React\Promise\reject(new \Exception("over max_wait_queue: ". $this->max_wait_queue.'-current quueue:'.count($this->wait_queue)));
        }

        $deferred = new Deferred();

        $this->wait_queue[] = $deferred;

        if (!$this->wait_timeout) {
            return $deferred->promise();
        }

        return \React\Promise\Timer\timeout($deferred->promise(), $this->wait_timeout, $this->loop)->then(null, function ($e) use ($deferred) {
            
            foreach ($this->wait_queue as $key => $deferredWait) {
                if ($deferredWait === $deferred) {
                    unset($this->wait_queue[$key]);
                    break;
                }
            }

            if ($e instanceof TimeoutException) {
                throw new \RuntimeException(
                    'wait timed out after ' . $e->getTimeout() . ' seconds (ETIMEDOUT)'. 'and wait queue '.count($this->wait_queue).' count',
                    \defined('SOCKET_ETIMEDOUT') ? \SOCKET_ETIMEDOUT : 110
                );
            }
            throw $e;
        });
    }

    public function releaseConnection(ConnectionInterface $connection)
    {
        if ($this->wait_queue) {
            $deferred = array_shift($this->wait_queue);
            $deferred->resolve($connection);
            return;
        }

        $this->idle_connections[] = $connection;
    }
}
