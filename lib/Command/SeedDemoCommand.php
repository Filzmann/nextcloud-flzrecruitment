<?php

declare(strict_types=1);

namespace OCA\Recruitment\Command;

use OCA\Recruitment\Service\RecruitmentDemoDataService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Stellt den synthetischen Recruitment-Datensatz für lokale Demo- und Abnahmeumgebungen bereit. */
final class SeedDemoCommand extends Command {
    public function __construct(private RecruitmentDemoDataService $demoData) { parent::__construct(); }

    protected function configure(): void {
        $this->setName('adrecruitment:demo:seed')
            ->setDescription('Erzeugt wiederholbar synthetische Stellen, Bewerber*innen, Bewerbungen, BQ- und Interviewdaten.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $result = $this->demoData->install();
        $output->writeln(sprintf(
            '<info>%d Stellen, %d Bewerber*innen, %d Bewerbungen, %d BQ-Durchläufe und %d Vorlagen sind als Demo verfügbar.</info>',
            $result['jobs'],
            $result['people'],
            $result['applications'],
            $result['basisQualifications'],
            $result['templates'],
        ));
        return self::SUCCESS;
    }
}
