<?php

namespace App\Services\Agents;

/**
 * Maps a command's leading tokens to a human-understandable label, so
 * approval prompts show "npm run test" rather than "npm run test --filter
 * --foo --bar". Ported from opencode's BashArity dictionary.
 *
 * The arity is the number of leading tokens that form the command identity;
 * everything after that is arguments/flags that are collapsed for display.
 */
final class BashArity
{
    /**
     * @var array<string, int> command-prefix (lowercase, space-normalized) → arity
     */
    private const ARITY = [
        'git' => 2,
        'git push' => 2,
        'git pull' => 2,
        'git checkout' => 2,
        'git commit' => 2,
        'git merge' => 2,
        'git rebase' => 2,
        'git reset' => 2,
        'git stash' => 2,
        'git add' => 2,
        'git branch' => 2,
        'git tag' => 2,
        'git log' => 2,
        'git diff' => 2,
        'git show' => 2,
        'git status' => 2,
        'git fetch' => 2,
        'git clone' => 2,
        'npm' => 2,
        'npm run' => 3,
        'npm test' => 2,
        'npm install' => 2,
        'npx' => 2,
        'yarn' => 2,
        'yarn run' => 3,
        'pnpm' => 2,
        'pnpm run' => 3,
        'pnpm test' => 2,
        'composer' => 2,
        'composer require' => 3,
        'composer install' => 2,
        'composer update' => 2,
        'php artisan' => 3,
        'php artisan test' => 3,
        'php artisan migrate' => 3,
        'php artisan db' => 3,
        'php artisan tinker' => 3,
        'php artisan serve' => 3,
        'php artisan route' => 3,
        'php artisan config' => 3,
        'php artisan cache' => 3,
        'php artisan vendor' => 3,
        'php artisan make' => 3,
        'php artisan db:seed' => 3,
        'php artisan migrate:rollback' => 3,
        'php artisan migrate:fresh' => 3,
        'phpunit' => 1,
        'pest' => 1,
        'docker' => 2,
        'docker compose' => 3,
        'docker compose up' => 4,
        'docker compose exec' => 4,
        'docker compose build' => 4,
        'docker compose restart' => 4,
        'docker compose down' => 3,
        'docker compose logs' => 4,
        'docker build' => 3,
        'docker run' => 3,
        'docker exec' => 3,
        'docker logs' => 3,
        'docker ps' => 2,
        'docker images' => 2,
        'docker stop' => 3,
        'docker rm' => 3,
        'kubectl' => 2,
        'kubectl apply' => 3,
        'kubectl delete' => 3,
        'kubectl get' => 3,
        'kubectl logs' => 3,
        'helm' => 2,
        'helm install' => 3,
        'helm upgrade' => 3,
        'terraform' => 2,
        'terraform apply' => 3,
        'terraform plan' => 3,
        'terraform init' => 2,
        'terraform destroy' => 3,
        'ansible' => 2,
        'cargo' => 2,
        'cargo build' => 3,
        'cargo test' => 3,
        'cargo run' => 3,
        'cargo check' => 3,
        'cargo clippy' => 3,
        'go' => 2,
        'go test' => 3,
        'go build' => 3,
        'go run' => 3,
        'go vet' => 2,
        'python' => 2,
        'python3' => 2,
        'pip' => 2,
        'pip install' => 3,
        'pipenv' => 2,
        'poetry' => 2,
        'poetry install' => 3,
        'poetry run' => 3,
        'pytest' => 1,
        'ruff' => 2,
        'ruff check' => 3,
        'rake' => 2,
        'bundle' => 2,
        'bundle exec' => 3,
        'rails' => 2,
        'rails db' => 3,
        'rails test' => 3,
        'rails server' => 3,
        'dotnet' => 2,
        'dotnet build' => 3,
        'dotnet test' => 3,
        'dotnet run' => 3,
        'dotnet add' => 3,
        'swift' => 2,
        'xcodebuild' => 2,
        'make' => 1,
        'curl' => 1,
        'wget' => 1,
        'ssh' => 1,
        'scp' => 1,
        'rsync' => 1,
        'tar' => 1,
        'zip' => 1,
        'unzip' => 1,
        'chmod' => 1,
        'chown' => 1,
        'mkdir' => 1,
        'rm' => 1,
        'cp' => 1,
        'mv' => 1,
        'ls' => 1,
        'cat' => 1,
        'echo' => 1,
        'grep' => 1,
        'find' => 1,
        'sed' => 1,
        'awk' => 1,
        'head' => 1,
        'tail' => 1,
        'wc' => 1,
        'sort' => 1,
        'uniq' => 1,
        'diff' => 1,
        'jq' => 1,
        'node' => 1,
        'deno' => 1,
        'bun' => 1,
        'bun run' => 3,
        'tsc' => 1,
        'eslint' => 1,
        'prettier' => 1,
        'oxlint' => 1,
        'black' => 1,
        'isort' => 1,
        'phpstan' => 1,
        'psalm' => 1,
        'rector' => 1,
        'php cs fixer' => 3,
    ];

    /**
     * Reduce a command string to its human-understandable label by keeping the
     * first N tokens (where N is the arity for the matching prefix) and
     * collapsing the rest into a single ellipsis. If no prefix matches, keep
     * the first two tokens.
     */
    public static function label(string $command): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', $command) ?? $command);
        if ($trimmed === '') {
            return '';
        }

        $tokens = explode(' ', $trimmed);
        $lower = strtolower($trimmed);

        // Try the longest prefix first (3-token, then 2-token, then 1-token).
        for ($arity = min(4, count($tokens)); $arity >= 1; $arity--) {
            $prefix = implode(' ', array_slice($tokens, 0, $arity));
            $key = strtolower($prefix);
            if (isset(self::ARITY[$key]) && self::ARITY[$key] === $arity) {
                return self::formatLabel($tokens, $arity);
            }
        }

        // Fallback: longest matching prefix arity even if the exact arity
        // table entry differs (e.g. "git status" not listed but "git" arity 2).
        for ($arity = min(3, count($tokens)); $arity >= 1; $arity--) {
            $key = strtolower(implode(' ', array_slice($tokens, 0, $arity)));
            if (isset(self::ARITY[$key])) {
                return self::formatLabel($tokens, self::ARITY[$key]);
            }
        }

        return self::formatLabel($tokens, min(2, count($tokens)));
    }

    /** @param list<string> $tokens */
    private static function formatLabel(array $tokens, int $arity): string
    {
        $head = array_slice($tokens, 0, $arity);
        $hasMore = count($tokens) > $arity;

        return implode(' ', $head).($hasMore ? ' …' : '');
    }
}