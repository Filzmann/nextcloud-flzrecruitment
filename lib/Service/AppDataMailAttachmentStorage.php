<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\Contract\MailAttachmentStorage;
use OCP\Files\IAppData;
use OCA\FlzRecruitment\Exception\NotFoundException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/** Speichert Original-PDFs ausschließlich im app-privaten Nextcloud-AppData. */
final class AppDataMailAttachmentStorage implements MailAttachmentStorage {
    private const ROOT_FOLDER = 'mail-inbox';

    public function __construct(private IAppData $appData) {}

    public function store(int $messageId, string $contentHash, string $content): string {
        if ($messageId < 1 || preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1) {
            throw new \InvalidArgumentException('Ungültiger serverseitiger Ablagepfad.');
        }
        $root = $this->folder($this->appData, self::ROOT_FOLDER);
        $messageFolderName = 'message-' . $messageId;
        $messageFolder = $this->folder($root, $messageFolderName);
        $storedName = $contentHash . '.pdf';
        if (!$messageFolder->fileExists($storedName)) {
            $messageFolder->newFile($storedName, $content);
        }
        return self::ROOT_FOLDER . '/' . $messageFolderName . '/' . $storedName;
    }

    public function read(string $storagePath): string {
        if (preg_match('#^mail-inbox/(message-[1-9][0-9]*)/([a-f0-9]{64}\.pdf)$#', $storagePath, $matches) !== 1) {
            throw new NotFoundException('Das Dokument wurde nicht gefunden.');
        }
        try {
            return $this->appData
                ->getFolder(self::ROOT_FOLDER)
                ->getFolder($matches[1])
                ->getFile($matches[2])
                ->getContent();
        } catch (FilesNotFoundException) {
            throw new NotFoundException('Das Dokument wurde nicht gefunden.');
        }
    }

    private function folder(IAppData|ISimpleFolder $parent, string $name): ISimpleFolder {
        try {
            return $parent->getFolder($name);
        } catch (FilesNotFoundException) {
            return $parent->newFolder($name);
        }
    }
}
