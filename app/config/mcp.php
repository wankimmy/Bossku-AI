<?php

/*
 * Registry of external MCP servers Bossku-AI can connect to as a client.
 *
 * Each entry is a stdio MCP server (command + args + env) or an http server
 * (url). Enable a server by setting its env flag and providing its credential;
 * its tools then become callable by agents via the `mcp_call` runtime tool
 * (namespaced `mcp.<server>.<tool>`). Add your own servers by appending entries.
 */

return [
    'servers' => [
        'github' => [
            'transport' => 'stdio',
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-github'],
            'env' => [
                'GITHUB_PERSONAL_ACCESS_TOKEN' => env('BOSSKU_GITHUB_TOKEN', env('GITHUB_TOKEN', '')),
            ],
            'enabled' => (bool) env('BOSSKU_MCP_GITHUB_ENABLED', false),
            'description' => 'GitHub pull requests, issues, and repository management.',
        ],

        'figma' => [
            'transport' => 'stdio',
            'command' => 'npx',
            'args' => ['-y', 'figma-developer-mcp', '--stdio'],
            'env' => [
                'FIGMA_API_KEY' => env('FIGMA_API_KEY', ''),
            ],
            'enabled' => (bool) env('BOSSKU_MCP_FIGMA_ENABLED', false),
            'description' => 'Figma file, frame, and design-token access for design-to-code.',
        ],
    ],

    /** Per-call timeout (seconds) for MCP requests. */
    'timeout_seconds' => (int) env('BOSSKU_MCP_TIMEOUT_SECONDS', 60),
];
