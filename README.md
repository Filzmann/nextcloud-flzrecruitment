# AD Recruitment

`adrecruitment` ist eine eigenständige Nextcloud-App für einen strukturierten,
nachvollziehbaren Bewerbungsprozess. Der erste vertikale Ausschnitt umfasst
Stellen, Personen, Bewerbungen, versionierte Interviewvorlagen und
-instanzen sowie kontrollierte Bewerbungsstatus.

## Lokale Entwicklung

Die App wird im Parent-Workspace per DDEV nach
`/var/www/html/html/custom_apps/adrecruitment` eingebunden. Die lokale Oberfläche
liegt unter `https://nextcloud-dev.ddev.site/apps/adrecruitment/`.

Schnelle Prüfungen:

```bash
php tests/run.php
node tests/run-js.mjs
```

Architekturentscheidungen und bewusst noch nicht umgesetzte Integrationen
stehen in `docs/architecture.md`.
