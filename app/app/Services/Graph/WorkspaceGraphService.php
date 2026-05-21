<?php

namespace App\Services\Graph;

use App\Services\Project\ProjectPathResolver;
use Illuminate\Support\Facades\File;

class WorkspaceGraphService
{
    public function __construct(
        private readonly ProjectPathResolver $paths
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $resolved = $this->resolveSkillsGraphRoot();
        $root = $resolved['root'];
        $indexPath = $root.DIRECTORY_SEPARATOR.'skill-index.json';

        if (! is_readable($indexPath)) {
            return [
                'error' => 'skill-index.json missing',
                'repo_root' => $root,
                'active_repo_root' => $resolved['active_root'],
                'toolkit_repo_root' => $resolved['toolkit_root'],
                'skills_source' => $resolved['source'],
                'nodes' => [],
                'edges' => [],
                'node_count' => 0,
                'edge_count' => 0,
            ];
        }

        $idx = json_decode(File::get($indexPath), true);
        if (! is_array($idx) || ! is_array($idx['skills'] ?? null)) {
            return [
                'error' => 'skill-index.json invalid',
                'repo_root' => $root,
                'active_repo_root' => $resolved['active_root'],
                'toolkit_repo_root' => $resolved['toolkit_root'],
                'skills_source' => $resolved['source'],
                'nodes' => [],
                'edges' => [],
                'node_count' => 0,
                'edge_count' => 0,
            ];
        }

        $skillsDir = $root.DIRECTORY_SEPARATOR.'ai-assistant'.DIRECTORY_SEPARATOR.'skills';
        $refsDir = $root.DIRECTORY_SEPARATOR.'ai-assistant'.DIRECTORY_SEPARATOR.'references';
        $marquee = array_flip(config('bossku_graph.marquee_skills', []));
        $deepMin = (int) config('bossku_graph.depth_deep_min', 250);
        $okMin = (int) config('bossku_graph.depth_ok_min', 100);

        $nodes = [];
        $skillIds = [];

        foreach ($idx['skills'] as $s) {
            if (! is_array($s) || empty($s['id'])) {
                continue;
            }
            $skillIds[] = (string) $s['id'];
        }
        $skillIdSet = array_flip($skillIds);

        foreach ($idx['skills'] as $s) {
            if (! is_array($s) || empty($s['id'])) {
                continue;
            }
            $sid = (string) $s['id'];
            [$fm, $body] = $this->readSkillMd($skillsDir, $sid);
            $skillPath = $skillsDir.DIRECTORY_SEPARATOR.$sid.DIRECTORY_SEPARATOR.'SKILL.md';
            $skillLines = $this->lineCount($skillPath);

            $playbookRefs = [];
            if (preg_match_all('/(?:playbooks\/|references\/playbooks\/)([a-z0-9-]+\.md)/i', $body, $m)) {
                $playbookRefs = array_values(array_unique($m[1]));
            }

            $playbookLinesMax = 0;
            foreach ($playbookRefs as $pb) {
                $p = $refsDir.DIRECTORY_SEPARATOR.'playbooks'.DIRECTORY_SEPARATOR.$pb;
                $playbookLinesMax = max($playbookLinesMax, $this->lineCount($p));
            }

            $total = $skillLines + $playbookLinesMax;
            $triggers = is_array($s['triggers'] ?? null) ? $s['triggers'] : [];
            $keywords = is_array($s['keywords'] ?? null) ? $s['keywords'] : [];

            $nodes[] = [
                'id' => $sid,
                'label' => str_replace('bosskuai-', '', $sid),
                'category' => $this->category($sid),
                'is_marquee' => isset($marquee[$sid]),
                'is_core' => (bool) ($s['core'] ?? false),
                'depth' => $this->depthLabel($total, $deepMin, $okMin),
                'skill_lines' => $skillLines,
                'playbook_lines' => $playbookLinesMax,
                'total_lines' => $total,
                'triggers' => $triggers,
                'keywords' => $keywords,
                'trigger_count' => count($triggers),
                'description' => mb_substr((string) ($fm['description'] ?? ''), 0, 300),
                'playbook_refs' => $playbookRefs,
            ];
        }

        $edges = [];
        $seen = [];

        foreach ($idx['skills'] as $s) {
            if (! is_array($s) || empty($s['id'])) {
                continue;
            }
            $sid = (string) $s['id'];
            [, $body] = $this->readSkillMd($skillsDir, $sid);

            foreach (array_keys($skillIdSet) as $other) {
                if ($other === $sid) {
                    continue;
                }
                $pattern = '/\b'.preg_quote($other, '/').'\b/';
                if (! preg_match($pattern, $body)) {
                    continue;
                }
                $key = $sid.'|'.$other;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $edges[] = [
                    'source' => $sid,
                    'target' => $other,
                    'kind' => 'cross_ref',
                ];
            }
        }

        $inv = [];
        foreach ($idx['skills'] as $s) {
            if (! is_array($s) || empty($s['id'])) {
                continue;
            }
            $sid = (string) $s['id'];
            $terms = array_merge(
                is_array($s['triggers'] ?? null) ? $s['triggers'] : [],
                is_array($s['keywords'] ?? null) ? $s['keywords'] : [],
            );
            foreach ($terms as $t) {
                $term = mb_strtolower(trim((string) $t));
                if ($term === '') {
                    continue;
                }
                $inv[$term] ??= [];
                $inv[$term][$sid] = true;
            }
        }

        foreach ($inv as $sids) {
            if (count($sids) < 2) {
                continue;
            }
            $list = array_values(array_keys($sids));
            sort($list);
            $n = count($list);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $key = $list[$i].'|'.$list[$j].'|overlap';
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $edges[] = [
                        'source' => $list[$i],
                        'target' => $list[$j],
                        'kind' => 'overlap',
                    ];
                }
            }
        }

        $byCat = [];
        foreach ($nodes as $node) {
            $cat = $node['category'];
            $byCat[$cat] = ($byCat[$cat] ?? 0) + 1;
        }

        return [
            'version' => $idx['version'] ?? '?',
            'repo_root' => $root,
            'active_repo_root' => $resolved['active_root'],
            'toolkit_repo_root' => $resolved['toolkit_root'],
            'skills_source' => $resolved['source'],
            'node_count' => count($nodes),
            'edge_count' => count($edges),
            'categories' => $byCat,
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Prefer skill-index.json on the active project; fall back to the Bossku-AI toolkit mount (/repo).
     *
     * @return array{root: string, source: 'active'|'toolkit', active_root: ?string, toolkit_root: string}
     */
    private function resolveSkillsGraphRoot(): array
    {
        $toolkitRoot = $this->toolkitRepoRoot();
        $activeRoot = null;

        try {
            $activeRoot = $this->paths->repoRoot();
            if ($this->hasSkillIndex($activeRoot)) {
                return [
                    'root' => $activeRoot,
                    'source' => 'active',
                    'active_root' => $activeRoot,
                    'toolkit_root' => $toolkitRoot,
                ];
            }
        } catch (\Throwable) {
            //
        }

        if ($this->hasSkillIndex($toolkitRoot)) {
            return [
                'root' => $toolkitRoot,
                'source' => 'toolkit',
                'active_root' => $activeRoot,
                'toolkit_root' => $toolkitRoot,
            ];
        }

        return [
            'root' => $activeRoot ?? $toolkitRoot,
            'source' => 'active',
            'active_root' => $activeRoot,
            'toolkit_root' => $toolkitRoot,
        ];
    }

    private function toolkitRepoRoot(): string
    {
        $fallback = (string) config('bossku.repo_root');
        $real = realpath($fallback);

        return $real !== false ? $real : $fallback;
    }

    private function hasSkillIndex(string $root): bool
    {
        return is_readable($root.DIRECTORY_SEPARATOR.'skill-index.json');
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function readSkillMd(string $skillsDir, string $skillId): array
    {
        $path = $skillsDir.DIRECTORY_SEPARATOR.$skillId.DIRECTORY_SEPARATOR.'SKILL.md';
        if (! is_readable($path)) {
            return [[], ''];
        }

        $text = File::get($path);
        $fm = [];
        if (str_starts_with($text, '---')) {
            $end = strpos($text, "\n---", 4);
            if ($end !== false) {
                $block = substr($text, 3, $end - 3);
                foreach (preg_split('/\r\n|\r|\n/', trim($block)) as $line) {
                    if (! str_contains($line, ':')) {
                        continue;
                    }
                    [$k, $v] = array_map('trim', explode(':', $line, 2));
                    $fm[$k] = $v;
                }
                $text = ltrim(substr($text, $end + 4));
            }
        }

        return [$fm, $text];
    }

    private function lineCount(string $path): int
    {
        if (! is_readable($path)) {
            return 0;
        }

        $lines = 0;
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return 0;
        }
        while (! feof($fh)) {
            $chunk = fread($fh, 8192);
            if ($chunk === false) {
                break;
            }
            $lines += substr_count($chunk, "\n");
        }
        fclose($fh);

        return max(1, $lines + 1);
    }

    private function depthLabel(int $total, int $deepMin, int $okMin): string
    {
        if ($total >= $deepMin) {
            return 'DEEP';
        }
        if ($total >= $okMin) {
            return 'OK';
        }

        return 'THIN';
    }

    private function category(string $skillId): string
    {
        $s = str_replace('bosskuai-', '', $skillId);

        $match = fn (array $terms) => $this->containsAny($s, $terms);

        if ($match(['laravel', 'nuxt', 'frontend', 'backend', 'api-design', 'engineering', 'code', 'rigorous', 'polyglot', 'documentation', 'browser', 'agent-security', 'ai-model'])) {
            return 'engineering';
        }
        if ($match(['docker', 'vps', 'deploy', 'devops', 'iac', 'github', 'operations', 'ops'])) {
            return 'infra';
        }
        if ($match(['redis', 'cache', 'queue'])) {
            return 'runtime';
        }
        if ($match(['database', 'mongo', 'data-arch', 'sql'])) {
            return 'data';
        }
        if ($match(['security', 'owasp', 'auth', 'cyber'])) {
            return 'security';
        }
        if ($match(['seo', 'content', 'marketing', 'social', 'growth', 'paid'])) {
            return 'growth';
        }
        if ($match(['sales', 'go-to-market', 'gtm', 'lead', 'launch', 'commercial'])) {
            return 'sales';
        }
        if ($match(['ui', 'ux', 'design', 'brand', 'i18n', '3d', 'gsap', 'lenis', 'smooth'])) {
            return 'design';
        }
        if ($match(['cofounder', 'execut', 'operating'])) {
            return 'operating';
        }
        if ($match(['research', 'discovery', 'investor', 'competitor', 'financial', 'market', 'analytics', 'metrics', 'customer', 'deep-research', 'rapid'])) {
            return 'research';
        }
        if ($match(['test', 'review', 'audit', 'qa', 'bug', 'incident', 'performance', 'root-cause'])) {
            return 'quality';
        }
        if ($match(['skill-creator', 'skill-stocktake', 'vector', 'memory', 'rules', 'claude-md', 'workspace', 'token-saver', 'ask-clarifying', 'search-first', 'tooling', 'prompt', 'caveman'])) {
            return 'meta';
        }

        return 'other';
    }

    private function containsAny(string $haystack, array $terms): bool
    {
        foreach ($terms as $t) {
            if (str_contains($haystack, $t)) {
                return true;
            }
        }

        return false;
    }
}
