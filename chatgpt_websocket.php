<?php
require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use GuzzleHttp\Client;

class ChatGPTWebSocket implements MessageComponentInterface {
    protected $clients;
    private $openai_api_key;
    private $client;

    public function __construct($apiKey) {
        $this->clients = new \SplObjectStorage;
        $this->openai_api_key = $apiKey;
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com',
        ]);
    }

    // When a new WebSocket connection is established
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    // When a message is received from a client
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        $input = $data['input'] ?? '';
        $industry = $data['industry'] ?? 'general';

        echo "Message received from {$from->resourceId}: $input\n";

        // Send input to ChatGPT and get a response
        $response = $this->handleRequest($input, $industry);

        // Send the response back to the client
        $from->send(json_encode(['response' => $response]));
    }

    // When a connection is closed
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    // When an error occurs with a connection
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    // Method to handle requests to ChatGPT via OpenAI API
    public function handleRequest($input, $industry) {
        $prompt = $this->createIndustryPrompt($input, $industry);

        try {
            // Send the request to OpenAI API
            $response = $this->client->post('/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openai_api_key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4',
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt],
                        ['role' => 'user', 'content' => $input],
                    ],
                    'max_tokens' => 100, // Adjust as needed
                ]
            ]);

            $body = json_decode($response->getBody(), true);
            return $body['choices'][0]['message']['content'];
        } catch (\Exception $e) {
            return "Error communicating with ChatGPT: " . $e->getMessage();
        }
    }

    // Customize the agent's behavior based on industry
    private function createIndustryPrompt($input, $industry) {
        switch ($industry) {
            case 'real_estate':
                return "You are a real estate expert helping clients with real estate inquiries.";
            case 'ecommerce':
                return "You are an eCommerce specialist assisting with product purchases and decisions.";
            case 'healthcare':
                return "You are a healthcare assistant providing health-related advice.";
            default:
                return "You are a general assistant helping with various inquiries.";
        }
    }
}
