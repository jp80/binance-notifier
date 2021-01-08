<?php
namespace jmp0x0000\BinanceBot;
require('vendor/autoload.php');
use \React;
use \Psr;
use \Thread;

class AsyncServer
{
    public function startServer()
    {

        $loop = React\EventLoop\Factory::create();

        $server = new React\Http\Server($loop, function (Psr\Http\Message\ServerRequestInterface $request) {
            return new React\Http\Message\Response(
                200,
                array(
                    'Content-Type' => 'text/plain'
                ),
                "Hello World!\n"
            );
        });

        $socket = new React\Socket\Server(8086, $loop);
        $server->listen($socket);

        echo "Server running at http://127.0.0.1:8086\n";

        $loop->run();
    }

}
