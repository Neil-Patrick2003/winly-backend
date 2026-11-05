<?php

use App\Mcp\Servers\WelleServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * The welle MCP server, for AI apps. Callers authenticate with a personal AI
 * integration token made under Settings → AI integration, sent as a bearer
 * header. Mobile app tokens are refused here, and these tokens are refused
 * everywhere else (see AppServiceProvider::configureAiIntegrationTokens).
 */
Mcp::web('/mcp', WelleServer::class)
    ->middleware(['auth:sanctum', 'throttle:mcp'])
    ->name('mcp');
