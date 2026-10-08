<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Contract;

interface MailAttachmentStorage {
    /** Stores content under a server-generated app-private path and returns that path. */
    public function store(int $messageId, string $contentHash, string $content): string;
    /** Reads content only from a previously server-generated app-private path. */
    public function read(string $storagePath): string;
}
