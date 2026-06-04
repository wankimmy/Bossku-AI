<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Run;
use App\Services\Governance\ApprovalGateService;
use App\Services\Governance\RiskClassifier;
use App\Services\Project\FileWriteApplier;
use App\Services\Project\ProjectFileDiscovery;
use App\Services\Project\ProjectPathResolver;
use App\Services\Project\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class ProjectFilesController extends Controller
{
    private const MAX_FILE_BYTES = 1_048_576;

    private const MAX_TREE_ENTRIES = 500;

    public function __construct(
        protected ProjectPathResolver $paths,
        protected ProjectFileDiscovery $discovery,
        protected ProjectService $projects,
        protected ApprovalGateService $approvals,
        protected RiskClassifier $riskClassifier,
        protected FileWriteApplier $fileWrites,
    ) {}

    public function root()
    {
        $active = $this->paths->activeProject();

        try {
            $root = $this->paths->repoRoot();
            $available = true;
            $error = null;
        } catch (\Throwable $e) {
            $root = $active?->container_path ?? (string) config('bossku.repo_root');
            $available = false;
            $error = $e->getMessage();
        }

        return response()->json([
            'root' => $root,
            'relative' => '',
            'available' => $available,
            'error' => $error,
            'active_project' => $active,
            'workspace' => $this->projects->workspaceMeta(),
        ]);
    }

    public function tree(Request $request)
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:2000',
        ]);

        try {
            $resolved = $this->paths->resolve((string) ($validated['path'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $absolute = $resolved['absolute'];

        if (! is_dir($absolute)) {
            return response()->json(['message' => 'Not a directory.'], 422);
        }

        $entries = [];
        $truncated = false;

        foreach (scandir($absolute) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (count($entries) >= self::MAX_TREE_ENTRIES) {
                $truncated = true;
                break;
            }

            $full = $absolute.DIRECTORY_SEPARATOR.$name;
            $rel = $resolved['relative'] === '' ? $name : $resolved['relative'].'/'.$name;
            $isDir = is_dir($full);

            if ($isDir && $this->paths->shouldSkipDir($name)) {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'path' => $rel,
                'type' => $isDir ? 'dir' : 'file',
            ];
        }

        usort($entries, function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return response()->json([
            'path' => $resolved['relative'],
            'entries' => $entries,
            'truncated' => $truncated,
        ]);
    }

    public function file(Request $request)
    {
        $validated = $request->validate([
            'path' => 'required|string|max:2000',
        ]);

        try {
            $resolved = $this->paths->resolve($validated['path']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $absolute = $resolved['absolute'];

        if (! is_file($absolute)) {
            return response()->json(['message' => 'Not a file.'], 404);
        }

        $size = filesize($absolute);
        if ($size !== false && $size > self::MAX_FILE_BYTES) {
            return response()->json(['message' => 'File exceeds size limit.'], 422);
        }

        $contents = file_get_contents($absolute);
        if ($contents === false) {
            return response()->json(['message' => 'Could not read file.'], 500);
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            return response()->json(['message' => 'Binary files are not supported.'], 422);
        }

        return response()->json([
            'path' => $resolved['relative'],
            'size' => $size,
            'contents' => $contents,
        ]);
    }

    public function manifest(Request $request)
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:2000',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:500',
            'ext' => 'nullable|string|max:20',
        ]);

        try {
            $result = $this->discovery->manifest(
                (string) ($validated['path'] ?? ''),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 200),
                isset($validated['ext']) ? (string) $validated['ext'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'available' => false], 422);
        }

        return response()->json($result);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:500',
            'glob' => 'nullable|string|max:200',
        ]);

        $root = $this->paths->repoRoot();
        $query = $validated['q'];
        $glob = $validated['glob'] ?? '*';

        $finder = Finder::create()
            ->files()
            ->in($root)
            ->name($glob)
            ->ignoreUnreadableDirs()
            ->exclude($this->discovery->skipDirs());

        $matches = [];
        $pattern = '/'.preg_quote($query, '/').'/i';
        $limit = $this->discovery->maxSearchMatches();

        foreach ($finder as $file) {
            if (count($matches) >= $limit) {
                break;
            }

            $absolute = $file->getRealPath();
            if ($absolute === false) {
                continue;
            }

            $size = $file->getSize();
            if ($size > self::MAX_FILE_BYTES) {
                continue;
            }

            $contents = @file_get_contents($absolute);
            if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
                continue;
            }

            if (! preg_match($pattern, $contents)) {
                continue;
            }

            $relative = ltrim(str_replace($root, '', $absolute), DIRECTORY_SEPARATOR);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $line = 1;
            $preview = '';
            foreach (preg_split("/\r\n|\n|\r/", $contents) ?: [] as $idx => $lineText) {
                if (preg_match($pattern, (string) $lineText)) {
                    $line = $idx + 1;
                    $preview = mb_substr(trim((string) $lineText), 0, 200);
                    break;
                }
            }

            $matches[] = [
                'path' => $relative,
                'line' => $line,
                'preview' => $preview,
            ];
        }

        return response()->json(['query' => $query, 'matches' => $matches]);
    }

    public function listChanges(Request $request)
    {
        $query = Approval::query()
            ->where('operation_type', 'file_write')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            $query->where('status', 'pending');
        }

        return response()->json($query->limit(50)->get());
    }

    public function proposeChange(Request $request)
    {
        $validated = $request->validate([
            'path' => 'required|string|max:2000',
            'new_contents' => 'required|string|max:500000',
            'run_id' => 'nullable|uuid|exists:bossku_ai_runs,id',
        ]);

        try {
            $resolved = $this->paths->resolve($validated['path']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $absolute = $resolved['absolute'];
        $before = is_file($absolute) ? (string) file_get_contents($absolute) : '';
        $after = $validated['new_contents'];
        $diff = $this->paths->unifiedDiff($resolved['relative'], $before, $after);

        $runId = $validated['run_id'] ?? null;
        if ($runId === null) {
            $run = Run::query()->create([
                'prompt' => 'Project file change: '.$resolved['relative'],
                'status' => 'running',
                'metadata' => ['source' => 'project_ui'],
            ]);
            $runId = $run->id;
        }

        $risk = $this->riskClassifier->classify($resolved['relative'].' '.$after);

        $approval = $this->approvals->createApproval(
            $runId,
            null,
            'file_write',
            'Write file: '.$resolved['relative'],
            $risk,
            [
                'path' => $resolved['relative'],
                'absolute' => $absolute,
                'before' => $before,
                'after' => $after,
                'diff' => $diff,
            ],
        );

        return response()->json($approval, 201);
    }

    public function approveChange(string $id, Request $request)
    {
        $approval = Approval::query()->findOrFail($id);

        if ($approval->operation_type !== 'file_write') {
            return response()->json(['message' => 'Not a file write approval.'], 422);
        }

        if ($approval->status !== 'pending') {
            return response()->json(['message' => 'Approval is not pending.'], 422);
        }

        $this->approvals->decide(
            $approval->id,
            'approved',
            'project_ui',
            $request->input('note'),
        );

        return response()->json([
            'message' => 'Change approved.',
            'approval' => $approval->fresh(),
        ]);
    }

    public function applyChange(string $id)
    {
        $approval = Approval::query()->findOrFail($id);

        if ($approval->operation_type !== 'file_write') {
            return response()->json(['message' => 'Not a file write approval.'], 422);
        }

        if (! in_array($approval->status, ['approved', 'auto_approved'], true)) {
            return response()->json(['message' => 'Approval must be approved before applying.'], 422);
        }

        try {
            $written = $this->fileWrites->applyApproval($approval);
        }
        catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'File written.',
            'path' => $written['relative'],
            'approval' => $approval->fresh(),
        ]);
    }

    public function rejectChange(string $id, Request $request)
    {
        $approval = Approval::query()->findOrFail($id);

        if ($approval->operation_type !== 'file_write') {
            return response()->json(['message' => 'Not a file write approval.'], 422);
        }

        if ($approval->status !== 'pending') {
            return response()->json(['message' => 'Approval is not pending.'], 422);
        }

        $this->approvals->decide(
            $approval->id,
            'rejected',
            'project_ui',
            $request->input('note'),
        );

        return response()->json([
            'message' => 'Change rejected.',
            'approval' => $approval->fresh(),
        ]);
    }

}
