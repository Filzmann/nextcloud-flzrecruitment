<?php

declare(strict_types=1);

namespace OCA\Recruitment\Permission;

use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionCondition;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionProvider;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionProviderDescriptor;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionProviderResult;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionRule;
use OCA\Recruitment\Service\RecruitmentPermissionPolicy;

final class RecruitmentPermissionProvider implements PermissionProvider {
    private const MANAGE_TEMPORARY_ADMIN_ACCESS = 'manage_temporary_admin_access';

    private const LABELS = [
        RecruitmentPermissionPolicy::VIEW_DOSSIER => 'Bewerbungsakte lesen',
        RecruitmentPermissionPolicy::MANAGE_CATALOG => 'Kataloge verwalten',
        RecruitmentPermissionPolicy::EDIT_APPLICATIONS => 'Bewerbungen bearbeiten',
        RecruitmentPermissionPolicy::INTERVIEW => 'Interviews bearbeiten',
        RecruitmentPermissionPolicy::EDIT_HIRING_DATA => 'Einstellungsdaten bearbeiten',
        RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA => 'Lohndaten bearbeiten',
        RecruitmentPermissionPolicy::VIEW_HIRING_DATA => 'Einstellungsdaten lesen',
        RecruitmentPermissionPolicy::MANAGE_DOCUMENTS => 'Dokumente bearbeiten',
        RecruitmentPermissionPolicy::COMMUNICATE => 'Bewerbungskommunikation bearbeiten',
        RecruitmentPermissionPolicy::MANAGE_FIRST_GUIDE_ACCESS => 'Erstbegleitungszugriff verwalten',
        RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION => 'Basisqualifikation verwalten',
        RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES => 'Mailvorlagen verwalten',
        RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL => 'Bewerberpool verwalten',
        RecruitmentPermissionPolicy::MANAGE_DELEGATIONS => 'Vertretungsfreigaben verwalten',
        RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS => 'Statusübergänge übersteuern',
        'manage_unassigned_inbox' => 'Unzugeordneten Posteingang bearbeiten',
        self::MANAGE_TEMPORARY_ADMIN_ACCESS => 'Zeitlich begrenzten Admin-Vollzugriff verwalten',
    ];

    public function __construct(private RecruitmentPermissionSourceInterface $source) {}

    public function descriptor(): PermissionProviderDescriptor {
        return new PermissionProviderDescriptor('adrecruitment', 'AD Recruitment', '1.0', ['permissions']);
    }

    public function collect(): PermissionProviderResult {
        $organization = $this->source->organization();
        $settings = $this->source->permissionSettings();
        $fullCapabilities = [
            ...RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES,
            RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION,
            RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES,
            RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL,
            RecruitmentPermissionPolicy::MANAGE_DELEGATIONS,
            RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS,
        ];
        $rules = [];
        $rules[] = $this->rule(
            self::MANAGE_TEMPORARY_ADMIN_ACCESS,
            'all',
            PermissionCondition::group('Datenschutzbeauftragte'),
            'Historie lesen sowie Freigaben ausschließlich für aktuelle native Administrationskonten erteilen oder widerrufen',
        );
        foreach ([...$fullCapabilities, RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA, 'manage_unassigned_inbox'] as $capability) {
            $rules[] = $this->rule($capability, 'all', PermissionCondition::all([PermissionCondition::nextcloudAdmin(), PermissionCondition::temporaryAppAdminGrant()]), 'Native Nextcloud-Administration mit aktiver app-lokaler Freigabe (maximal 24 Stunden)');
        }

        $warnings = [];
        $complete = true;
        if (!$organization->isValid()) {
            $complete = false;
            $warnings[] = 'Der kanonische AD-Organisationssnapshot ist ungültig; Nicht-Admin-Rechte bleiben deny by default.';
        } else {
            $hrGroup = $organization->roleGroupId('staff_hr');
            if ($hrGroup !== null) {
                foreach ([...$fullCapabilities, 'manage_unassigned_inbox'] as $capability) {
                    $rules[] = $this->rule($capability, 'all', PermissionCondition::group($hrGroup), 'Kanonische HR-Rolle');
                }
            }

            $payrollGroup = $organization->roleGroupId('payroll');
            if ($payrollGroup !== null) {
                foreach ([RecruitmentPermissionPolicy::VIEW_HIRING_DATA, RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA] as $capability) {
                    $rules[] = $this->rule(
                        $capability,
                        'released-hiring-records',
                        PermissionCondition::group($payrollGroup),
                        'Nur nach ausdrücklicher Einstellungsfreigabe',
                    );
                }
            }

            $firstGuideGroup = trim((string)($settings['firstGuideGroupId'] ?? ''));
            $ebGroup = $organization->roleGroupId('eb');
            if ($firstGuideGroup !== '' && $ebGroup !== null) {
                foreach ($organization->areaKeys() as $areaKey) {
                    $areaGroup = $organization->areaGroupId($areaKey);
                    if ($areaGroup === null) {
                        continue;
                    }
                    $rules[] = $this->rule(
                        RecruitmentPermissionPolicy::VIEW_DOSSIER,
                        'released-first-guide-access:area:' . $areaKey,
                        PermissionCondition::all([
                            PermissionCondition::group($firstGuideGroup),
                            PermissionCondition::group($ebGroup),
                            PermissionCondition::group($areaGroup),
                        ]),
                        'Nur freigegebene oder eingestellte Bewerbung mit aktivem Erstbegleitungszugriff im eigenen Bereich',
                    );
                }
            }
        }

        if (($settings['representatives'] ?? []) !== []) {
            $complete = false;
            $warnings[] = 'UID-basierte Vertretungsfreigaben und ihre Bereichs- oder Einzelakten-Scope sind im öffentlichen V1-Bedingungsmodell nicht verlustfrei darstellbar.';
        }

        return new PermissionProviderResult($rules, $complete, $warnings);
    }

    private function rule(string $capability, string $scope, PermissionCondition $condition, string $detail): PermissionRule {
        if ($capability === RecruitmentPermissionPolicy::MANAGE_DOCUMENTS) {
            $detail .= '; Dateiinhalte werden nicht untersucht';
        }
        return new PermissionRule(
            'Recruitment-Funktion',
            self::LABELS[$capability],
            $detail,
            'recruitment.' . $capability,
            self::LABELS[$capability],
            'allow',
            $scope,
            $condition,
            'adrecruitment:RecruitmentPermissionPolicy',
            'high',
        );
    }
}
