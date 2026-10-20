<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Période des statistiques (?du=AAAA-MM-JJ&au=AAAA-MM-JJ, bornes comprises).
 * Par défaut : du premier jour du mois, cinq mois en arrière, au dernier jour du mois suivant
 * (six mois écoulés et les rendez-vous déjà pris pour le mois à venir).
 */
class PeriodeStatistiquesRequest extends FormRequest
{
    /** Au-delà, le regroupement par mois devient illisible sur smartphone. */
    public const MOIS_MAXIMUM = 24;

    public function authorize(): bool
    {
        return $this->user()->aLeRole(Role::Administrateur);
    }

    public function rules(): array
    {
        return [
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            [$du, $au] = $this->periode();

            if ($au->lessThan($du)) {
                $validator->errors()->add('au', 'La date de fin doit être postérieure ou égale à la date de début.');
            } elseif ($du->addMonths(self::MOIS_MAXIMUM)->lessThanOrEqualTo($au)) {
                $validator->errors()->add('au', sprintf('La période ne peut pas dépasser %d mois.', self::MOIS_MAXIMUM));
            }
        }];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function periode(): array
    {
        $aujourdhui = CarbonImmutable::today();

        return [
            $this->filled('du') ? CarbonImmutable::createFromFormat('!Y-m-d', $this->input('du')) : $aujourdhui->subMonthsNoOverflow(5)->startOfMonth(),
            $this->filled('au') ? CarbonImmutable::createFromFormat('!Y-m-d', $this->input('au')) : $aujourdhui->addMonthNoOverflow()->endOfMonth()->startOfDay(),
        ];
    }
}
