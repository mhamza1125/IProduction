<?php

/**
 * Screens use the semantic colours from resources/css/app.css (bg-surface,
 * text-muted, border-line ...), which already adapt to dark mode. Raw grey
 * shades need a hand-written dark: partner and drift out of contrast, so they
 * are not allowed outside printouts (which always print light).
 */
it('uses semantic theme colours instead of raw grey shades in screens', function () {
    $root = dirname(__DIR__, 2);
    $files = array_merge(
        glob($root.'/resources/views/{,*/,*/*/,*/*/*/}*.blade.php', GLOB_BRACE) ?: [],
        glob($root.'/app/Modules/*/resources/views/{,*/,*/*/,*/*/*/}*.blade.php', GLOB_BRACE) ?: [],
    );

    $offenders = [];
    foreach ($files as $file) {
        if (str_contains($file, '/print/')) {
            continue;
        }
        foreach (file($file) ?: [] as $i => $line) {
            if (preg_match('/(?<![\w-])(?:[a-z-]+:)*(?:bg|text|border|ring|divide|placeholder)-(?:zinc|gray|slate|neutral|stone)-\d+/', $line, $m)) {
                $offenders[] = str_replace($root.'/', '', $file).':'.($i + 1).' '.$m[0];
            }
        }
    }

    expect($offenders)->toBe([]);
});
