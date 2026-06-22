<?php

namespace Tests\Unit;

use App\Services\Project\EditAmbiguousException;
use App\Services\Project\EditNotFoundException;
use App\Services\Project\FileEditEngine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileEditEngineTest extends TestCase
{
    private FileEditEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FileEditEngine;
    }

    #[Test]
    public function exact_match_replaces_in_place(): void
    {
        $content = "line one\nline two\nline three\n";
        $result = $this->engine->replace($content, 'line two', 'LINE TWO');

        $this->assertSame("line one\nLINE TWO\nline three\n", $result);
    }

    #[Test]
    public function matches_despite_leading_whitespace_drift(): void
    {
        // Model quoted the line without the file's real indentation.
        $content = "function foo() {\n        return 42;\n}\n";
        $result = $this->engine->replace($content, 'return 42;', 'return 43;');

        $this->assertSame("function foo() {\n        return 43;\n}\n", $result);
    }

    #[Test]
    public function matches_multiline_block_with_indentation_shift(): void
    {
        $content = "class A {\n    public function run() {\n        doThing();\n        doOther();\n    }\n}\n";
        // Search block dedented relative to the file.
        $old = "public function run() {\ndoThing();\ndoOther();\n}";
        $new = "public function run() {\n        doNew();\n    }";
        $result = $this->engine->replace($content, $old, $new);

        $this->assertStringContainsString('doNew();', $result);
        $this->assertStringNotContainsString('doThing();', $result);
    }

    #[Test]
    public function matches_when_internal_whitespace_collapsed(): void
    {
        $content = "const   x   =    1;\n";
        $result = $this->engine->replace($content, 'const x = 1;', 'const x = 2;');

        $this->assertStringContainsString('2;', $result);
        $this->assertStringNotContainsString('= 1;', $result);
    }

    #[Test]
    public function replace_all_changes_every_occurrence(): void
    {
        $content = "foo();\nfoo();\nfoo();\n";
        $result = $this->engine->replace($content, 'foo()', 'bar()', true);

        $this->assertSame("bar();\nbar();\nbar();\n", $result);
        $this->assertStringNotContainsString('foo()', $result);
    }

    #[Test]
    public function ambiguous_match_without_replace_all_throws(): void
    {
        $content = "foo();\nfoo();\n";

        $this->expectException(EditAmbiguousException::class);
        $this->engine->replace($content, 'foo()', 'bar()');
    }

    #[Test]
    public function missing_old_string_throws_not_found(): void
    {
        $this->expectException(EditNotFoundException::class);
        $this->engine->replace("a\nb\nc\n", 'nonexistent snippet', 'x');
    }

    #[Test]
    public function identical_strings_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->replace('abc', 'abc', 'abc');
    }

    #[Test]
    public function empty_old_string_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->replace('abc', '', 'x');
    }

    #[Test]
    public function apply_edits_runs_sequentially(): void
    {
        $content = "a = 1\nb = 2\nc = 3\n";
        $result = $this->engine->applyEdits($content, [
            ['old_string' => 'a = 1', 'new_string' => 'a = 10'],
            ['old_string' => 'c = 3', 'new_string' => 'c = 30'],
        ]);

        $this->assertSame("a = 10\nb = 2\nc = 30\n", $result);
    }

    #[Test]
    public function block_anchor_tolerates_drifted_middle_line(): void
    {
        $content = "if (ok) {\n    step_one();\n    step_two();\n    step_three();\n}\n";
        // First and last lines anchor; middle drifted slightly.
        $old = "if (ok) {\n    step_one_renamed();\n    step_two();\n    step_three();\n}";
        $new = "if (ok) {\n    done();\n}";
        $result = $this->engine->replace($content, $old, $new);

        $this->assertStringContainsString('done();', $result);
        $this->assertStringNotContainsString('step_two();', $result);
    }
}
