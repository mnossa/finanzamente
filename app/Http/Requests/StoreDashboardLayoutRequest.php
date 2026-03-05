<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardLayoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'config'                        => ['required', 'array'],
            'config.widgets'                => ['required', 'array', 'min:1', 'max:50'],
            'config.widgets.*.id'           => ['required', 'string', 'max:64'],
            'config.widgets.*.visible'      => ['required', 'boolean'],
            'config.widgets.*.position'     => ['required', 'integer', 'min:0'],
            'config.widgets.*.size'         => ['required', 'string', 'in:sm,md,lg,xl'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'config.required'                    => 'La configurazione è obbligatoria.',
            'config.array'                       => 'La configurazione deve essere un oggetto valido.',
            'config.widgets.required'            => 'La lista dei widget è obbligatoria.',
            'config.widgets.array'               => 'La lista dei widget deve essere un array.',
            'config.widgets.min'                 => 'Deve essere presente almeno un widget.',
            'config.widgets.max'                 => 'Il numero massimo di widget è 50.',
            'config.widgets.*.id.required'       => 'Ogni widget deve avere un identificatore.',
            'config.widgets.*.id.string'         => 'L\'identificatore del widget deve essere una stringa.',
            'config.widgets.*.visible.required'  => 'Il campo visibilità del widget è obbligatorio.',
            'config.widgets.*.visible.boolean'   => 'Il campo visibilità deve essere vero o falso.',
            'config.widgets.*.position.required' => 'La posizione del widget è obbligatoria.',
            'config.widgets.*.position.integer'  => 'La posizione deve essere un numero intero.',
            'config.widgets.*.size.required'     => 'La dimensione del widget è obbligatoria.',
            'config.widgets.*.size.in'           => 'La dimensione del widget deve essere sm, md, lg o xl.',
        ];
    }

    /**
     * Ensure only allowed widget IDs are accepted.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $allowedIds = [
            'total_balance',
            'monthly_stats',
            'annual_revenue',
            'tax_thermometer',
            'lifestyle_widget',
            'accounts',
            'recent_transactions',
            'active_budgets',
            'debts_credits',
            'quick_actions',
        ];

        $validator->after(function ($v) use ($allowedIds) {
            $widgets = $this->input('config.widgets', []);
            $ids = array_column((array) $widgets, 'id');
            $unknownIds = array_diff($ids, $allowedIds);
            if (! empty($unknownIds)) {
                $v->errors()->add(
                    'config.widgets',
                    'Uno o più widget non sono riconosciuti: ' . implode(', ', $unknownIds)
                );
            }
        });
    }
}
