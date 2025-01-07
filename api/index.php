<?php
// Save as public_html/bot.php
header('Content-Type: application/json');

// Your bot token
$botToken = '7657838266:AAG8Vf6OOXBTehoE5-mWJL8kmaxyN5L33iA';
$geniusToken = 'V3LW6Fa99KPiIBUyN_Oa5m8w-IPLbLHEetZw4XUX7KW6f0v-YsJJHoDAJ3J22Ztf';

// Get incoming update
$update = json_decode(file_get_contents('php://input'), true);

// Check if this is a message
if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $message = $update['message']['text'];

    // Ignore commands except /start and /help
    if (strpos($message, '/') === 0) {
        if ($message === '/start') {
            $response = "Welcome to Radio X Lyrics fetcher! 🎵\n\nJust send me the name of any song, and I'll fetch the lyrics for you!\n\nExample:\nToxic Britney Spears";
            sendMessage($chatId, $response);
            exit;
        }
        if ($message === '/help') {
            $response = "Just send me the name of any song, and I'll fetch the lyrics for you!\n\nExample:\nToxic Britney Spears";
            sendMessage($chatId, $response);
            exit;
        }
        exit;
    }

    // Search for song on Genius
    $song = searchSong($message, $geniusToken);
    if ($song) {
        // Send "searching" message
        $searchMsg = "✨ Found: {$song['title']} by {$song['primary_artist']['name']}\n🔍 Fetching lyrics...";
        sendMessage($chatId, $searchMsg);

        // Get lyrics
        $lyrics = getLyrics($song['url']);
        if ($lyrics) {
            // Format with header
            $header = "[ {$song['title']} - {$song['primary_artist']['name']} ]\n";
            $fullLyrics = $header . $lyrics;

            // Split long messages if needed
            $maxLength = 4096;
            $parts = str_split($fullLyrics, $maxLength);
            foreach ($parts as $part) {
                sendMessage($chatId, $part);
            }
        } else {
            sendMessage($chatId, "❌ Sorry, couldn't fetch the lyrics.\nYou can find them here: {$song['url']}");
        }
    } else {
        sendMessage($chatId, "❌ Sorry, couldn't find that song.");
    }
}

function sendMessage($chatId, $text) {
    global $botToken;
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function searchSong($query, $token) {
    $url = "https://api.genius.com/search?" . http_build_query(['q' => $query]);
    
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$token}\r\n" .
                       "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response) {
        $data = json_decode($response, true);
        return $data['response']['hits'][0]['result'] ?? null;
    }
    
    return null;
}

function getLyrics($url) {
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
        ]
    ];

    $context = stream_context_create($options);
    $html = file_get_contents($url, false, $context);
    
    if ($html) {
        // Look for JSON-LD data first
        if (preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches)) {
            $data = json_decode($matches[1], true);
            if (isset($data['lyrics'])) {
                return trim($data['lyrics']);
            }
        }
        
        // Fallback to HTML parsing
        if (preg_match('/<div[^>]*data-lyrics-container="true"[^>]*>(.*?)<\/div>/s', $html, $matches)) {
            $lyrics = strip_tags($matches[1]);
            return trim($lyrics);
        }
    }
    
    return null;
}
?>
