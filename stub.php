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
        $msg = "HTTP/1.1 200 OK\r\n"
             . "Content-Type: text/html; charset=utf-8\r\n"
             . "\r\n";
        if ($fh = fopen('default.html', 'r')) {
        	$msg .= fread($fh, 4096);
            fclose($fh);
        }

        socket_write($msgsock, $msg, strlen($msg));
        socket_close($msgsock);
    };
}