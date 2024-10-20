<?php
require 'chatgpt_websocket.php';
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$openaiApiKey = 'your-openai-api-key-here';

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatGPTWebSocket($openaiApiKey)
        )
    ),
    8080  // The port number to listen on
);

echo "WebSocket server running on port 8080...\n";

$server->run();
