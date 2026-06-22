<?php

namespace App\Console\Commands;

use App\Services\Agents\AgenticToolLoop;
use Illuminate\Console\Command;

class AgentLoopCommand extends Command
{
    protected $signature = 'bosskuai:agent-loop
                            {task : The coding task for the agentic loop to solve}
                            {--role=executor : Tool-permission role to run as}
                            {--max=12 : Hard iteration cap}';

    protected $description = 'Run the agentic tool-use loop (read → edit → verify) against the active project.';

    public function handle(AgenticToolLoop $loop): int
    {
        $task = (string) $this->argument('task');
        if (trim($task) === '') {
            $this->error('Task is required.');

            return self::FAILURE;
        }

        $this->info('Running agentic loop…');
        $result = $loop->run($task, [
            'role' => (string) $this->option('role'),
            'max_iterations' => (int) $this->option('max'),
            'emit' => function (array $event): void {
                $this->line('  · '.($event['summary'] ?? $event['tool'] ?? 'event'));
            },
        ]);

        $this->newLine();
        $this->line('Status: <comment>'.$result['status'].'</comment>');
        $this->line('Iterations: '.$result['iterations'].'  |  Model: '.$result['model_used']);
        $this->line('Tool calls: '.count($result['tool_calls']));
        $this->newLine();
        $this->line(json_encode($result['final'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '(no final output)');

        return $result['status'] === 'completed' ? self::SUCCESS : self::FAILURE;
    }
}
