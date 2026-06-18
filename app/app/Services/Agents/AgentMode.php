<?php

namespace App\Services\Agents;

enum AgentMode: string
{
    case Primary = 'primary';
    case Subagent = 'subagent';
    case All = 'all';
    case Hidden = 'hidden';
}
