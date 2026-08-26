<?php

use App\Mcp\Servers\RaiStatsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', RaiStatsServer::class)
    ->middleware(['auth:sanctum', 'throttle:mcp']);
