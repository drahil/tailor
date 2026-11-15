<?php

declare(strict_types=1);

use drahil\Tailor\Support\DTOs\SessionData;
use drahil\Tailor\Support\DTOs\SessionMetadata;
use drahil\Tailor\Support\Storage\SessionFile;
use drahil\Tailor\Support\ValueObjects\SessionDescription;
use drahil\Tailor\Support\ValueObjects\SessionName;

function sessionWith(array $commands, ?string $description = null, array $tags = []): SessionData
{
    return new SessionData(
        new SessionMetadata(
            name: SessionName::from('work'),
            description: SessionDescription::fromNullable($description),
            tags: $tags,
            createdAt: new DateTime('2026-11-15T21:54:00+00:00'),
            updatedAt: new DateTime('2026-11-15T21:56:00+00:00'),
        ),
        $commands,
    );
}

test('renders a doc comment header and one statement per command', function () {
    $file = SessionFile::render(sessionWith(['Post::count()', '$a = 1;'], 'Quick stats', ['stats', 'safe-for-prod']));

    expect($file)->toBe(<<<'PHP'
    <?php
    /**
     * @description Quick stats
     * @tags stats, safe-for-prod
     * @created 2026-11-15T21:54:00+00:00
     * @updated 2026-11-15T21:56:00+00:00
     */

    Post::count();
    $a = 1;

    PHP);
});

test('round-trips sessions', function () {
    $session = sessionWith(['Post::count()', "foreach ([1, 2] as \$i) {\n    echo \$i;\n}"], 'Stats', ['api']);

    $parsed = SessionFile::parse(SessionFile::render($session), SessionName::from('work'));

    expect($parsed->commands)->toBe(['Post::count();', "foreach ([1, 2] as \$i) {\n    echo \$i;\n}"])
        ->and($parsed->metadata->description?->toString())->toBe('Stats')
        ->and($parsed->metadata->tags)->toBe(['api'])
        ->and($parsed->metadata->createdAt?->format('c'))->toBe('2026-11-15T21:54:00+00:00');
});

test('keeps code that is not valid PHP as a comment so the file stays parseable', function () {
    $file = SessionFile::render(sessionWith(['$a = ', 'Post::count()']));

    expect($file)->toContain('// Not valid PHP: $a =')
        ->and(SessionFile::parse($file, SessionName::from('work'))->commands)->toBe(['Post::count();']);
});

test('keeps a description that would end the doc comment from breaking the file', function () {
    $file = SessionFile::render(sessionWith(['Post::count()'], "Ends */ early\nand spans lines"));

    expect(SessionFile::parse($file, SessionName::from('work'))->metadata->description?->toString())
        ->toBe('Ends * / early and spans lines');
});

test('treats a file without a header as a session without metadata', function () {
    $session = SessionFile::parse("<?php\n\nPost::count();\n", SessionName::from('work'));

    expect($session->commands)->toBe(['Post::count();'])
        ->and($session->metadata->description)->toBeNull()
        ->and($session->metadata->tags)->toBe([]);
});
