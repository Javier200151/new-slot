<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;

class TaskBoardBladeSyntaxTest extends TestCase
{
    public function test_task_board_blade_compiles_to_valid_php(): void
    {
        $path = dirname(__DIR__, 2).'/resources/views/filament/pages/task-board.blade.php';
        $source = file_get_contents($path);
        $source = str_replace(
            ['<x-filament-panels::page>', '</x-filament-panels::page>'],
            ['<div>', '</div>'],
            $source,
        );

        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $compiled = $compiler->compileString($source);
        $tmp = tempnam(sys_get_temp_dir(), 'task-board-').'.php';
        file_put_contents($tmp, $compiled);

        exec(PHP_BINARY.' -l '.escapeshellarg($tmp).' 2>&1', $output, $exitCode);
        @unlink($tmp);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
