<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\ApplicationMailFieldExtractor;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;

TestRunner::test('extracts Contact Form 7 fields as sourced suggestions', static function (): void {
    $extractor = new ApplicationMailFieldExtractor();

    $result = $extractor->extract(
        'website@example.invalid',
        "Dein Name*: Ari Beispiel\r\n"
        . "E-Mail*: ari@example.invalid\r\n"
        . "Telefonnummer*: +49 30 555 01 01\r\n"
        . "Ich bewerbe mich als...*: Assistenz\r\n"
        . "Nachricht (optional, max. 250 Zeichen): Ich freue mich auf ein Gespräch.\r\n",
    );

    assertSame(true, $result['structuredForm']);
    assertSame(['value' => 'Ari Beispiel', 'source' => 'mail_body_labeled'], $result['name']);
    assertSame(['value' => 'ari@example.invalid', 'source' => 'mail_body_labeled'], $result['email']);
    assertSame(['value' => '+49 30 555 01 01', 'source' => 'mail_body_labeled'], $result['phone']);
    assertSame(['value' => 'Assistenz', 'source' => 'mail_body_labeled'], $result['jobPreference']);
    assertSame('assistance', $result['jobCategory']);
    assertSame(
        ['value' => 'Ich freue mich auf ein Gespräch.', 'source' => 'mail_body_labeled'],
        $result['message'],
    );
});

TestRunner::test('accepts form labels and values on separate lines', static function (): void {
    $extractor = new ApplicationMailFieldExtractor();

    $result = $extractor->extract(
        'website@example.invalid',
        "Dein Name*\nMika Muster\n\nE-Mail*\nmika@example.invalid\n"
        . "Telefonnummer*\n030 5550202\nIch bewerbe mich als...*\nPflegefachkraft\n"
        . "Nachricht (optional, max. 250 Zeichen)\nGern per E-Mail antworten.",
    );

    assertSame(['value' => 'Mika Muster', 'source' => 'mail_body_labeled'], $result['name']);
    assertSame(['value' => 'mika@example.invalid', 'source' => 'mail_body_labeled'], $result['email']);
    assertSame('nursing_specialist', $result['jobCategory']);
    assertSame(
        ['value' => 'Gern per E-Mail antworten.', 'source' => 'mail_body_labeled'],
        $result['message'],
    );
});

TestRunner::test('keeps a free email minimal and does not invent missing contact data', static function (): void {
    $extractor = new ApplicationMailFieldExtractor();

    $result = $extractor->extract(
        'bewerbung@example.invalid',
        "Guten Tag,\n\nich interessiere mich für eine Mitarbeit.\n\nViele Grüße",
    );

    assertSame(false, $result['structuredForm']);
    assertSame(null, $result['name']);
    assertSame(['value' => 'bewerbung@example.invalid', 'source' => 'mail_sender'], $result['email']);
    assertSame(null, $result['phone']);
    assertSame(null, $result['jobPreference']);
    assertSame(null, $result['jobCategory']);
    assertSame(null, $result['message']);
});

TestRunner::test('suggests the only unlabelled email address found in a free mail body', static function (): void {
    $extractor = new ApplicationMailFieldExtractor();

    $result = $extractor->extract(
        'weiterleitung@example.invalid',
        "Guten Tag, meine Kontaktadresse ist person@example.invalid.\nViele Grüße",
    );

    assertSame(false, $result['structuredForm']);
    assertSame(['value' => 'person@example.invalid', 'source' => 'mail_body_detected'], $result['email']);
});

TestRunner::test('does not select one of several unlabelled body addresses', static function (): void {
    $extractor = new ApplicationMailFieldExtractor();

    $result = $extractor->extract(
        'bewerbung@example.invalid',
        'Bitte antworte an person@example.invalid oder vertretung@example.invalid.',
    );

    assertSame(['value' => 'bewerbung@example.invalid', 'source' => 'mail_sender'], $result['email']);
});

TestRunner::test('does not classify one ordinary labelled contact line as the website form', static function (): void {
    $extractor = new ApplicationMailFieldExtractor();

    $result = $extractor->extract(
        'bewerbung@example.invalid',
        "E-Mail: person@example.invalid\nIch interessiere mich für eine Mitarbeit.",
    );

    assertSame(false, $result['structuredForm']);
});

TestRunner::test('keeps an unknown job preference as an unassigned suggestion', static function (): void {
    $extractor = new ApplicationMailFieldExtractor();

    $result = $extractor->extract(
        'website@example.invalid',
        "E-Mail: person@example.invalid\nIch bewerbe mich als: Ergotherapie",
    );

    assertSame(['value' => 'Ergotherapie', 'source' => 'mail_body_labeled'], $result['jobPreference']);
    assertSame(null, $result['jobCategory']);
});

TestRunner::test('requires at least one valid contact email without accepting malformed body data', static function (): void {
    $extractor = new ApplicationMailFieldExtractor();

    assertThrows(
        static fn () => $extractor->extract('kein-postfach', "E-Mail: ebenfalls-keine-adresse"),
        ValidationException::class,
    );
});
