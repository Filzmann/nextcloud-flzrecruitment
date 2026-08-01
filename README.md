# AD Recruitment

`adrecruitment` ist eine eigenständige Nextcloud-App für einen strukturierten,
nachvollziehbaren Bewerbungsprozess. Der erste vertikale Ausschnitt umfasst
Stellen, Personen, Bewerbungen, versionierte Interviewvorlagen und
-instanzen sowie kontrollierte Bewerbungsstatus.

Die App bleibt eigenständig installierbar. Ab zwei aktivierten AD-Fachprodukten
ersetzt OrgSuite den einzelnen Nextcloud-Einstieg durch das gemeinsame AD-Menü.
AD Recruitment ist Bestandteil des vollständigen AD-Suite-Pakets und wird
zusätzlich als eigenes Produktpaket mit LocalBase und OrgSuite gebaut.

## Lokale Entwicklung

In einer Nextcloud-Installation liegt der Einstieg unter
`/index.php/apps/adrecruitment/`. Lokale Mount- und DDEV-Details werden nur im
internen Parent-Workspace gepflegt und sind kein Produktionsvertrag.

Schnelle Prüfungen:

```bash
php tests/run.php
node tests/run-js.mjs
```

Architekturentscheidungen und bewusst noch nicht umgesetzte Integrationen
stehen in `docs/architecture.md`.

## Abnahme und Roadmap

Für die fachliche, visuelle, sicherheits- und datenschutzbezogene
Staging-Prüfung steht ein ausfüllbares
[manuelles Abnahmeformular](docs/manual-acceptance.md) bereit. Bewerbungs- und
Interview-Echtdaten werden darin nicht dokumentiert.

Geplante Erweiterungen stehen in der [Roadmap](ROADMAP.md).
