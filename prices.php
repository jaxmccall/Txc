<?php
/**
 * Cryptocurrency Prices API - Tripple Exchange
 * Fetches live prices from CoinGecko API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class PriceManager {
    private $cacheFile = 'cache/prices.json';
    private $cacheTime = 300; // 5 minutes cache
    
    public function __construct() {
        // Create cache directory if it doesn't exist
        if (!file_exists('cache')) {
            mkdir('cache', 0755, true);
        }
    }
    
    public function getPrices($coins = null) {
        // Default coins if none specified
        if (!$coins) {
            $coins = 'bitcoin,ethereum,tether,binancecoin,solana,cardano,ripple,polkadot,dogecoin,avalanche-2,chainlink,polygon,litecoin,bitcoin-cash,ethereum-classic,stellar,vechain,filecoin,tron,monero';
        }
        
        // Check cache first
        if ($this->isCacheValid()) {
            return json_decode(file_get_contents($this->cacheFile), true);
        }
        
        // Fetch from CoinGecko API
        $url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids={$coins}&order=market_cap_desc&per_page=100&page=1&sparkline=false&price_change_percentage=24h";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Tripple Exchange/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            // Return cached data if API fails
            if (file_exists($this->cacheFile)) {
                return json_decode(file_get_contents($this->cacheFile), true);
            }
            
            // Return mock data if no cache available
            return $this->getMockPrices();
        }
        
        $data = json_decode($response, true);
        
        if ($data && is_array($data)) {
            // Cache the response
            file_put_contents($this->cacheFile, json_encode($data));
            return $data;
        }
        
        return $this->getMockPrices();
    }
    
    private function isCacheValid() {
        if (!file_exists($this->cacheFile)) {
            return false;
        }
        
        return (time() - filemtime($this->cacheFile)) < $this->cacheTime;
    }
    
    private function getMockPrices() {
        return [
            [
                'id' => 'bitcoin',
                'symbol' => 'btc',
                'name' => 'Bitcoin',
                'current_price' => 43250.00,
                'price_change_percentage_24h' => 2.45,
                'market_cap' => 847500000000,
                'total_volume' => 25600000000
            ],
            [
                'id' => 'ethereum',
                'symbol' => 'eth',
                'name' => 'Ethereum',
                'current_price' => 2650.00,
                'price_change_percentage_24h' => 1.85,
                'market_cap' => 318700000000,
                'total_volume' => 15400000000
            ],
            [
                'id' => 'tether',
                'symbol' => 'usdt',
                'name' => 'Tether',
                'current_price' => 1.00,
                'price_change_percentage_24h' => 0.01,
                'market_cap' => 95800000000,
                'total_volume' => 42300000000
            ]
        ];
    }
    
    public function getAssetPrice($symbol) {
        $prices = $this->getPrices();
        
        foreach ($prices as $coin) {
            if (strtolower($coin['symbol']) === strtolower($symbol)) {
                return [
                    'symbol' => strtoupper($symbol),
                    'price' => $coin['current_price'],
                    'change_24h' => $coin['price_change_percentage_24h'] ?? 0,
                    'market_cap' => $coin['market_cap'] ?? 0,
                    'volume' => $coin['total_volume'] ?? 0
                ];
            }
        }
        
        return null;
    }
}

// Handle API requests
$priceManager = new PriceManager();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $coins = $_GET['coins'] ?? null;
            $symbol = $_GET['symbol'] ?? null;
            
            if ($symbol) {
                $result = $priceManager->getAssetPrice($symbol);
                if ($result) {
                    echo json_encode(['success' => true, 'data' => $result]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Asset not found']);
                }
            } else {
                $prices = $priceManager->getPrices($coins);
                echo json_encode(['success' => true, 'data' => $prices]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
