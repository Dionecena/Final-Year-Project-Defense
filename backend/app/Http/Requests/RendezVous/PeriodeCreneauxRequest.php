<?php

namespace App\Http\Requests\RendezVous;

use App\Services\CreneauService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Période de calcul des créneaux (?du=AAAA-MM-JJ&au=AAAA-MM-JJ), bornée par l'horizon
 * config('rendezvous.horizon_jours'). Par défaut : d'aujourd'hui à la fin de l'horizon.
 */
class PeriodeCreneauxRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route publique (section 14).
        return true;
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

            [$du, $au] = $this->periodeDemandee();
            [, $dernierJour] = app(CreneauService::class)->horizon();

            if ($au->lessThan($du)) {
                $validator->errors()->add('au', 'La date de fin doit être postérieure ou égale à la date de début.');
            } elseif ($au->greaterThan($dernierJour)) {
                $validator->errors()->add('au', sprintf(
                    'Les créneaux sont proposés au plus %d jours à l\'avance (jusqu\'au %s).',
                    (int) config('rendezvous.horizon_jours'),
                    $dernierJour->format('d/m/Y'),
                ));
            }
        }];
    }

    /**
     * Période validée ; un début antérieur à aujourd'hui est ramené à aujourd'hui (les créneaux
     * passés ne sont jamais proposés).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periode(): array
    {
        [$du, $au] = $this->periodeDemandee();

        return [$du->max(CarbonImmutable::today()), $au];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function periodeDemandee(): array
    {
        [$debutHorizon, $finHorizon] = app(CreneauService::class)->horizon();

        return [
            $this->filled('du') ? CarbonImmutable::createFromFormat('!Y-m-d', $this->input('du')) : $debutHorizon,
            $this->filled('au') ? CarbonImmutable::createFromFormat('!Y-m-d', $this->input('au')) : $finHorizon,
        ];
    }
}
