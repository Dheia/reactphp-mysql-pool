# reactphp-mysql-pool


## install

```
composer require wpjscc/reactphp-mysql-pool -vvv
```


## example
```
require_once './vendor/autoload.php';

use Wpjscc\MySQL\Pool;
use React\MySQL\QueryResult;
use React\MySQL\ConnectionInterface;

$pool = new Pool('username:password@host?timeout=5/databasename', [
    'max_connections' => 10, // 10 connection --default 10
    'max_wait_queue' => 50, // how many sql in queue --default 50
    'wait_timeout' => 5,// wait time include response time --default 0
]);


for ($i=0; $i < 10; $i++) { 
    // auto releaseConnection
    $pool->query('select * from blog')->then(function (QueryResult $command) use ($i) {
        echo "query:$i\n";
        if (isset($command->resultRows)) {
            // this is a response to a SELECT etc. with some rows (0+)
            // print_r($command->resultFields);
            // print_r($command->resultRows);
            // echo count($command->resultRows) . ' row(s) in set' . PHP_EOL;
        } else {
            // this is an OK message in response to an UPDATE etc.
            if ($command->insertId !== 0) {
                var_dump('last insert ID', $command->insertId);
            }
            echo 'Query OK, ' . $command->affectedRows . ' row(s) affected' . PHP_EOL;
        }
    }, function (Exception $error) {
        // the query was not executed successfully
        echo 'Error: ' . $error->getMessage() . PHP_EOL;
    });
    
}

# or 
for ($i=0; $i < 10; $i++) { 
    $pool->getIdleConnection()->then(function(ConnectionInterface $connection) use ($pool, $i) {
        $connection->query('select * from blog')->then(function (QueryResult $command) use ($i) {
            echo "getIdleConnection:$i\n";

            if (isset($command->resultRows)) {
                // this is a response to a SELECT etc. with some rows (0+)
                // print_r($command->resultFields);
                // print_r($command->resultRows);
                // echo count($command->resultRows) . ' row(s) in set' . PHP_EOL;
            } else {
                // this is an OK message in response to an UPDATE etc.
                if ($command->insertId !== 0) {
                    var_dump('last insert ID', $command->insertId);
                }
                echo 'Query OK, ' . $command->affectedRows . ' row(s) affected' . PHP_EOL;
            }
        }, function (Exception $error) {
            // the query was not executed successfully
            echo 'Error: ' . $error->getMessage() . PHP_EOL;
        })->always(function() use ($pool, $connection) {
            $pool->releaseConnection($connection);
        });
    }, function (Exception $error) {
        // the query was not executed successfully
        echo 'Error: ' . $error->getMessage() . PHP_EOL;
    });
}

```
