<?php

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
    die("Could not fork\n");
} else if ($pid) {
    // This is a parent process
    exit;
} else {
    // Make the child process a session leader
    if (posix_setsid() < 0) {
        die("Could not detach from terminal\n");
    }

    // Setup signal handlers
    declare(ticks = 1);
    pcntl_signal(SIGTERM, 'sig_handler');

    // loop until a terminate signal
    while ($server_is_running);
}