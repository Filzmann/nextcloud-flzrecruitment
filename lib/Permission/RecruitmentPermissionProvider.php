<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Permission;

use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionCondition;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProvider;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderDescriptor;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderResult;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionRule;
use OCA\FlzRecruitment\Service\RecruitmentPermissionPolicy;
use OCP\IL10N;

final class RecruitmentPermissionProvider implements PermissionProvider {
    private function labels(): array {
        return [
        RecruitmentPermissionPolicy::VIEW_DOSSIER => $this->t('Bewerbungsakte lesen'),
        RecruitmentPermissionPolicy::MANAGE_CATALOG => $this->t('Kataloge verwalten'),
        RecruitmentPermissionPolicy::EDIT_APPLICATIONS => $this->t('Bewerbungen bearbeiten'),
        RecruitmentPermissionPolicy::INTERVIEW => $this->t('Interviews bearbeiten'),
        RecruitmentPermissionPolicy::EDIT_HIRING_DATA => $this->t('Einstellungsdaten bearbeiten'),
        RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA => $this->t('Lohndaten bearbeiten'),
        RecruitmentPermissionPolicy::VIEW_HIRING_DATA => $this->t('Einstellungsdaten lesen'),
        RecruitmentPermissionPolicy::MANAGE_DOCUMENTS => $this->t('Dokumente bearbeiten'),
        RecruitmentPermissionPolicy::COMMUNICATE => $this->t('Bewerbungskommunikation bearbeiten'),
        RecruitmentPermissionPolicy::MANAGE_FIRST_GUIDE_ACCESS => $this->t('Erstbegleitungszugriff verwalten'),
        RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION => $this->t('Basisqualifikation verwalten'),
        RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES => $this->t('Mailvorlagen verwalten'),
        RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL => $this->t('Bewerberpool verwalten'),
        RecruitmentPermissionPolicy::MANAGE_DELEGATIONS => $this->t('Vertretungsfreigaben verwalten'),
        RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS => $this->t('Statusübergänge übersteuern'),
        RecruitmentPermissionPolicy::MANAGE_UNASSIGNED_INBOX => $this->t('Unzugeordneten Posteingang bearbeiten'),
        RecruitmentPermissionPolicy::MANAGE_TEMPORARY_ADMIN_ACCESS => $this->t('Zeitlich begrenzten Admin-Vollzugriff verwalten'),
        RecruitmentPermissionPolicy::VIEW_FIRST_GUIDE_DOSSIER => $this->t('Erstbegleitungsakte lesen'),
        ];
    }

    public function __construct(private RecruitmentPermissionSourceInterface $source, private ?IL10N $l10n = null) {}

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
    }

    public function descriptor(): PermissionProviderDescriptor {
        return new PermissionProviderDescriptor('flzrecruitment', $this->t('Filzmann Recruitment'), '1.0', ['permissions']);
    }

    public function collect(): PermissionProviderResult {
        $organization = $this->source->organization();
        $settings = $this->source->permissionSettings();
        $rules = [];
        $rules[] = $this->rule(
            RecruitmentPermissionPolicy::MANAGE_TEMPORARY_ADMIN_ACCESS,
            'all',
            PermissionCondition::group('Datenschutzbeauftragte'),
            $this->t('Historie lesen sowie Freigaben ausschließlich für aktuelle native Administrationskonten erteilen oder widerrufen'),
        );
        $rules[] = $this->rule(
            RecruitmentPermissionPolicy::MANAGE_DELEGATIONS,
            'all',
            PermissionCondition::group('Datenschutzbeauftragte'),
            $this->t('App-eigene Vertretungsfreigaben verwalten'),
        );
        foreach (RecruitmentPermissionPolicy::ACTION_CAPABILITIES as $capability) {
            $rules[] = $this->rule($capability, 'all', PermissionCondition::all([PermissionCondition::nextcloudAdmin(), PermissionCondition::temporaryAppAdminGrant()]), $this->t('Native Nextcloud-Administration mit aktiver app-lokaler Freigabe (maximal 24 Stunden)'));
        }

        $warnings = [];
        $complete = true;
        if (($settings['valid'] ?? true) === false) {
            $complete = false;
            $warnings[] = $this->t('Die app-eigene Berechtigungskonfiguration ist ungültig; Rollen- und Vertretungsrechte bleiben deny by default.');
        }
        if (!$organization->isValid()) {
            $complete = false;
            $warnings[] = $this->t('Der kanonische Filzmann-Organisationssnapshot ist ungültig; Nicht-Admin-Rechte bleiben deny by default.');
        } else {
            foreach (RecruitmentPermissionPolicy::ACTION_CAPABILITIES as $capability) {
                foreach ($settings['roleCapabilities'][$capability] ?? [] as $roleKey) {
                    $groupId = $organization->roleGroupId((string)$roleKey);
                    if ($groupId !== null) {
                        $rules[] = $this->rule($capability, 'all', PermissionCondition::group($groupId), $this->t('App-eigene Zuordnung zur Organigrammrolle ') . $roleKey);
                    }
                }
            }
            $hrGroup = $organization->roleGroupId('staff_hr');
            if ($hrGroup !== null) {
                $rules[] = $this->rule(RecruitmentPermissionPolicy::MANAGE_TEMPORARY_ADMIN_ACCESS, 'all', PermissionCondition::group($hrGroup), $this->t('Kanonische HR-Rolle'));
            }

            $ebGroup = $organization->roleGroupId('eb');
            if (in_array('eb', $settings['roleCapabilities'][RecruitmentPermissionPolicy::VIEW_FIRST_GUIDE_DOSSIER] ?? [], true)
                && $ebGroup !== null) {
                foreach ($organization->areaKeys() as $areaKey) {
                    $areaGroup = $organization->areaGroupId($areaKey);
                    if ($areaGroup === null) {
                        continue;
                    }
                    $rules[] = $this->rule(
                        RecruitmentPermissionPolicy::VIEW_FIRST_GUIDE_DOSSIER,
                        'released-first-guide-access:area:' . $areaKey,
                        PermissionCondition::all([
                            PermissionCondition::group($ebGroup),
                            PermissionCondition::group($areaGroup),
                        ]),
                        $this->t('Nur freigegebene oder eingestellte Bewerbung mit aktivem Erstbegleitungszugriff im eigenen Bereich'),
                    );
                }
            }
        }

        if (($settings['representatives'] ?? []) !== []) {
            $complete = false;
            $warnings[] = $this->t('UID-basierte Vertretungsfreigaben und ihre Bereichs- oder Einzelakten-Scope sind im öffentlichen V1-Bedingungsmodell nicht verlustfrei darstellbar.');
        }

        return new PermissionProviderResult($rules, $complete, $warnings);
    }

    private function rule(string $capability, string $scope, PermissionCondition $condition, string $detail): PermissionRule {
        if ($capability === RecruitmentPermissionPolicy::MANAGE_DOCUMENTS) {
            $detail .= $this->t('; Dateiinhalte werden nicht untersucht');
        }
        return new PermissionRule(
            'Recruitment-Funktion',
            $this->labels()[$capability],
            $detail,
            'recruitment.' . $capability,
            $this->labels()[$capability],
            'allow',
            $scope,
            $condition,
            'flzrecruitment:RecruitmentPermissionPolicy',
            'high',
        );
    }
}
