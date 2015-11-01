<?php

set_time_limit(0);
// Turn on implicit output flushing so we see what we're getting as it comes in
ob_implicit_flush();

$server_is_running = 1;

function sig_handler($signo)
{
    global $server_is_running;
    switch ($signo) {
        case SIGTERM:
            // Handle shutdown signal
            $server_is_running = 0;
            exit;
            break;
        default:
            // Handle all other signals
    }
}

$pid = pcntl_fork();
if ($pid < 0) {
    error_log('Could not fork' . PHP_EOL, 3, '/tmp/stub_error_log');
    exit;
} else if ($pid) {
    // This is a parent process
    exit;
} else {
    // Make the child process a session leader
    if (posix_setsid() < 0) {
        error_log('Could not detach from terminal' . PHP_EOL, 3, '/tmp/stub_error_log');
        exit;
    }

    // Setup signal handlers
    declare(ticks = 1);
    pcntl_signal(SIGTERM, 'sig_handler');

    // Prepare socket
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($sock === false) {
        error_log('socket_create() failed: reason: ' . socket_strerror(socket_last_error()) . PHP_EOL, 3, '/tmp/stub_error_log');
        exit;
    }
    if (socket_bind($sock, '127.0.0.1', '8000') === false) {
        socket_close($sock);
        error_log('socket_bind() failed: reason: ' . socket_strerror(socket_last_error($sock)) . PHP_EOL, 3, '/tmp/stub_error_log');
        exit;
    }
    if (socket_listen($sock, 1) === false) {
        socket_close($sock);
        error_log('socket_listen() failed: reason: ' . socket_strerror(socket_last_error($sock)) . PHP_EOL, 3, '/tmp/stub_error_log');
        exit;
    }

    // Loop until recieving a terminal signal
    while ($server_is_running) {
        // Block until a connection becomes present
        $msgsock = socket_accept($sock);
        if ($msgsock === false) {
            error_log('socket_accept() failed: reason: ' . socket_strerror(socket_last_error($sock)) . PHP_EOL, 3, '/tmp/stub_error_log');
            break;
        }
        error_log(date('dd/M/YY:HH:II:SS space tzcorrection') . PHP_EOL, 3, '/tmp/stub_access_log');

        $requested_uri_is_readable = false;
        $uri_full_path = __DIR__ . DIRECTORY_SEPARATOR . 'default.html';
        error_log($uri_full_path . PHP_EOL, 3, '/tmp/stub_debug_log');
        $buf = socket_read($msgsock, 4096, PHP_NORMAL_READ);
        if($buf !== false && strlen($buf) < 4096) {
            // Check if it's GET request:
            // Method SP Request-URI SP HTTP-Version CRLF 
            $method_start_pos = strpos($buf, 'GET', 0);
            if($method_start_pos === 0) {
                $space_between_method_and_uri_pos = strpos($buf, ' ', 3);
                if($space_between_method_and_uri_pos === 3) {
                    $space_between_uri_and_protocol_pos = strpos($buf, ' ', $space_between_method_and_uri_pos + 1);
                    if($space_between_uri_and_protocol_pos !== false && $space_between_method_and_uri_pos !== strlen($buf)) {
                        $uri_length = $space_between_uri_and_protocol_pos - $space_between_method_and_uri_pos - 1;
                        $uri = substr($buf, $space_between_uri_and_protocol_pos + 1, $uri_length);
                        $protocol = substr($buf, $space_between_uri_and_protocol_pos + 1);
                        if($protocol === "HTTP/1.1\r\n") {
                        	$resolved_uri = __DIR__ . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . ltrim($uri);
                        	if(file_exists($resolved_uri) && is_readable($resolved_uri)) {
                        		$uri_full_path = $resolved_uri;
                        		$requested_uri_is_readable = true;
                        	}
                        }
                    }
                }
            }
        }
        if(! $requested_uri_is_readable) {
            if(file_exists($uri_full_path) && is_readable($uri_full_path)) {
        		$requested_uri_is_readable = true;
        	}
        }

        if($requested_uri_is_readable) {
	        $msg = "HTTP/1.1 200 OK\r\n"
	             . "Content-Type: text/html; charset=utf-8\r\n"
	             . "\r\n"
	             . file_get_contents($uri_full_path);
        } else {
	        $msg = "HTTP/1.1 403 Forbidden\r\n";
        }

        socket_write($msgsock, $msg, strlen($msg));
        socket_close($msgsock);
    };
}