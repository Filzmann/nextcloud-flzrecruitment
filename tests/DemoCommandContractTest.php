<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('demo seed is registered as an explicit reusable occ command', static function (): void {
    $root = dirname(__DIR__);
    $info = file_get_contents($root . '/appinfo/info.xml');
    $command = @file_get_contents($root . '/lib/Command/SeedDemoCommand.php');
    assertTrue($info !== false && $command !== false, 'Demo command sources are missing');
    assertTrue(str_contains($info, '<command>OCA\FlzRecruitment\Command\SeedDemoCommand</command>'));
    assertTrue(str_contains($command, "setName('flzrecruitment:demo:seed')"));
    assertTrue(str_contains($command, 'RecruitmentDemoDataService'));
    assertTrue(str_contains($command, '->install()'));
});

TestRunner::test('synthetic inbox seed is explicit, credential-free and repeatable', static function (): void {
    $root = dirname(__DIR__);
    $info = file_get_contents($root . '/appinfo/info.xml');
    $command = @file_get_contents($root . '/lib/Command/SeedInboxCommand.php');
    assertTrue($info !== false && $command !== false, 'Synthetic inbox command sources are missing');
    assertTrue(str_contains($info, '<command>OCA\FlzRecruitment\Command\SeedInboxCommand</command>'));
    assertTrue(str_contains($command, "setName('flzrecruitment:inbox:seed')"));
    assertTrue(str_contains($command, 'MailInboxService'));
    assertTrue(!str_contains(strtolower($command), 'password'));
    assertTrue(!str_contains($command, 'example.invalid'), 'Auslieferbarer Demo-Code darf den Delivery-Platzhalter nicht enthalten.');
    assertTrue(str_contains($command, 'example.org'), 'Der Demo-Seed muss eine reservierte Dokumentationsdomain verwenden.');
});
