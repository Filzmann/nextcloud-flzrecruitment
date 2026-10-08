<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\Exception\ValidationException;

/** Kanonische Fachregel für den unverbindlichen Wunschstundenwert oder -bereich. */
final class DesiredWeeklyHoursService {
    /** @return array{desiredWeeklyHours:?float,desiredWeeklyHoursMax:?float} */
    public function normalize(?float $minimum, ?float $maximum = null): array {
        if ($minimum !== null && (!is_finite($minimum) || $minimum <= 0 || $minimum > 80)) {
            throw new ValidationException('Die gewünschten Wochenstunden müssen größer als 0 und höchstens 80 sein.');
        }
        if ($maximum !== null && (!is_finite($maximum) || $maximum <= 0 || $maximum > 80)) {
            throw new ValidationException('Die Obergrenze der gewünschten Wochenstunden muss größer als 0 und höchstens 80 sein.');
        }
        if ($minimum === null && $maximum !== null) {
            throw new ValidationException('Für einen Wunschstundenbereich ist ein Von-Wert erforderlich.');
        }
        if ($minimum !== null && $maximum !== null) {
            if ($maximum < $minimum) {
                throw new ValidationException('Die Obergrenze der Wunschstunden darf nicht unter dem Von-Wert liegen.');
            }
            if ($maximum === $minimum) $maximum = null;
        }
        return ['desiredWeeklyHours' => $minimum, 'desiredWeeklyHoursMax' => $maximum];
    }

    /** @return array{desiredWeeklyHours:float,desiredWeeklyHoursMax:?float} */
    public function parseSuggestion(mixed $value): array {
        if (!is_scalar($value)) throw new ValidationException('Der Wunschstunden-Vorschlag ist ungültig.');
        $text = trim(str_replace(',', '.', (string)$value));
        if (preg_match('/^(?:ca\.?|circa)?\s*(\d+(?:\.\d+)?)\s*(?:(?:-|bis)\s*(\d+(?:\.\d+)?))?\s*(?:stunden|std\.?)?$/iu', $text, $matches) !== 1) {
            throw new ValidationException('Der Wunschstunden-Vorschlag ist ungültig.');
        }
        $normalized = $this->normalize(
            (float)$matches[1],
            isset($matches[2]) && $matches[2] !== '' ? (float)$matches[2] : null,
        );
        return [
            'desiredWeeklyHours' => (float)$normalized['desiredWeeklyHours'],
            'desiredWeeklyHoursMax' => $normalized['desiredWeeklyHoursMax'],
        ];
    }
}
