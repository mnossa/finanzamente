import type { FormulaSuggestion } from '@/Components/FormulaWidgets/FormulaStringInput';
import type { SystemVariableMeta } from '@/types/formulaWidget';

export function systemVariableSuggestionHint(variable: SystemVariableMeta): string {
    if (variable.category === 'context') {
        return variable.requires_period ? 'Contesto calendario · richiede periodo' : 'Contesto data/calendario';
    }

    return variable.requires_period ? 'Richiede periodo nel widget' : 'Variabile finanziaria';
}

export function systemVariableToFormulaSuggestion(variable: SystemVariableMeta): FormulaSuggestion {
    return {
        code: variable.code,
        label: variable.label,
        hint: systemVariableSuggestionHint(variable),
        example: variable.example ?? undefined,
    };
}
