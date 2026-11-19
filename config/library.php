<?php

return [
    // Comma- or semicolon-separated Smartschool usernames of library administrators.
    // Configure this in the local/server .env; never commit real account data.
    'manager_usernames' => env('LIBRARY_MANAGER_USERNAMES', ''),
];
