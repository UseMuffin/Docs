<?php

/**
 * GitHub webhook handler template.
 *
 * @see  https://developer.github.com/webhooks/
 * @author  Miloslav Hůla (https://github.com/milo)
 * @author  Jad Bitar (https://github.com/jadb)
 */

$hookSecret = getenv('GITHUB_WEBHOOK_SECRET');  # set NULL to disable check


set_error_handler(function($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error on line {$e->getLine()}: " . htmlSpecialChars($e->getMessage());
    die();
});

$rawPost = NULL;
if ($hookSecret !== NULL) {
    if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
        throw new \Exception("HTTP header 'X-Hub-Signature' is missing.");
    } elseif (!extension_loaded('hash')) {
        throw new \Exception("Missing 'hash' extension to check the secret code validity.");
    }

    list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
    if (!in_array($algo, hash_algos(), TRUE)) {
        throw new \Exception("Hash algorithm '$algo' is not supported.");
    }

    $rawPost = file_get_contents('php://input');
    $hashedPost = hash_hmac($algo, $rawPost, $hookSecret);
    if ((version_compare(PHP_VERSION, '5.6', '>=') && !hash_equals($hash, $hashedPost)) || $hash !== $hashedPost) {
        throw new \Exception('Hook secret does not match.');
    }
};

if (!isset($_SERVER['CONTENT_TYPE'])) {
    throw new \Exception("Missing HTTP 'Content-Type' header.");
} elseif (!isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
    throw new \Exception("Missing HTTP 'X-Github-Event' header.");
}

switch ($_SERVER['CONTENT_TYPE']) {
    case 'application/json':
        $json = $rawPost ?: file_get_contents('php://input');
        break;

    case 'application/x-www-form-urlencoded':
        $json = $_POST['payload'];
        break;

    default:
        throw new \Exception("Unsupported content type: $_SERVER[HTTP_CONTENT_TYPE]");
}

# Payload structure depends on triggered event
# https://developer.github.com/v3/activity/events/types/
$payload = json_decode($json, true);

switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
    case 'ping':
        echo 'pong';
        break;

    case 'push':
        $output = [];
        $dir = __DIR__;
        $repo = strtolower($payload['repository']['name']);

        if ($repo !== 'docs') {
            $dir .= '/packages/' . $repo;
        }

        if (!file_exists($dir)) {
            $url = 'https://github.com/usemuffin/' . $repo . '.git';
            exec('cd ' . __DIR__ . '/packages && git clone ' . $url, $output);
        } else {
            exec('cd ' . $dir . ' && git pull', $output);
        }

        $target = $dir . '/docs';
        $site = $dir . '/user/sites/' . $repo;
        $config = $dir . '/user/config';
        if ($repo !== 'docs'
            && file_exists($target)
            && !file_exists($site)
        ) {
            shell_exec('mkdir -p ' . $site . '/config');
            symlink($dir . '/docs', $link . '/pages');
            symlink($config . '/system_subdirectories.yaml', $site . '/config/.');
            file_put_contents($site . '/config/site.yaml', sprintf(
                file_get_contents($config . '/site_subdirectories.yaml'),
                'Muffin/' . $payload['repository']['name'],
                $payload['repository']['description']
            );
        }

        echo implode("\n", $output);
        break;

//  case 'create':
//      break;

    default:
        header('HTTP/1.0 404 Not Found');
        echo "Event: $_SERVER[HTTP_X_GITHUB_EVENT]\nPayload: ";
        print_r($payload); # For debug only. Can be found in GitHub hook log.
        die();
}
