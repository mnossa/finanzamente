import { usePage } from '@inertiajs/react';
import { PageProps, Module, ModulesMap } from '@/types';

/**
 * Hook per accedere ai moduli disponibili per l'utente corrente.
 * 
 * Fornisce metodi per:
 * - Verificare se un modulo è abilitato
 * - Verificare se un modulo è bloccato per piano
 * - Verificare se l'utente è Pro
 * - Ottenere informazioni su un modulo
 * - Filtrare moduli per categoria
 * - Ottenere hint di sblocco per moduli bloccati
 */
export function useModules() {
    const { modules, plan } = usePage<PageProps>().props;

    const isPro = plan?.current === 'pro';

    /**
     * Verifica se un modulo è abilitato per l'utente corrente.
     */
    const isModuleEnabled = (moduleId: string): boolean => {
        return modules[moduleId]?.enabled ?? false;
    };

    /**
     * Verifica se un modulo è bloccato (non disponibile ma esistente).
     */
    const isModuleLocked = (moduleId: string): boolean => {
        return modules[moduleId]?.locked ?? false;
    };

    /**
     * Verifica se un modulo è bloccato specificamente per piano.
     */
    const isModuleLockedByPlan = (moduleId: string): boolean => {
        return modules[moduleId]?.locked_by_plan ?? false;
    };

    /**
     * Ottiene le informazioni complete su un modulo.
     */
    const getModule = (moduleId: string): Module | undefined => {
        return modules[moduleId];
    };

    /**
     * Ottiene l'hint di sblocco per un modulo bloccato.
     */
    const getUnlockHint = (moduleId: string): string | null => {
        return modules[moduleId]?.unlock_hint ?? null;
    };

    /**
     * Ottiene tutti i moduli abilitati.
     */
    const getEnabledModules = (): Module[] => {
        return Object.values(modules).filter(m => m.enabled);
    };

    /**
     * Ottiene tutti i moduli bloccati.
     */
    const getLockedModules = (): Module[] => {
        return Object.values(modules).filter(m => m.locked);
    };

    /**
     * Ottiene tutti i moduli bloccati per piano.
     */
    const getLockedByPlanModules = (): Module[] => {
        return Object.values(modules).filter(m => m.locked_by_plan);
    };

    /**
     * Ottiene i moduli filtrati per categoria.
     */
    const getModulesByCategory = (
        category: Module['category']
    ): Module[] => {
        return Object.values(modules).filter(m => m.category === category);
    };

    /**
     * Ottiene i moduli abilitati filtrati per categoria.
     */
    const getEnabledModulesByCategory = (
        category: Module['category']
    ): Module[] => {
        return Object.values(modules).filter(
            m => m.category === category && m.enabled
        );
    };

    /**
     * Verifica se almeno un modulo in una categoria è abilitato.
     */
    const hasCategoryEnabled = (category: Module['category']): boolean => {
        return getEnabledModulesByCategory(category).length > 0;
    };

    return {
        modules,
        isPro,
        isModuleEnabled,
        isModuleLocked,
        isModuleLockedByPlan,
        getModule,
        getUnlockHint,
        getEnabledModules,
        getLockedModules,
        getLockedByPlanModules,
        getModulesByCategory,
        getEnabledModulesByCategory,
        hasCategoryEnabled,
    };
}
