import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import {
    formatTransactionSideSummary,
    type TransactionSideLabelInput,
} from '@/utils/duplicateTransactionLabels';
import { formatCurrency } from '@/utils/format';

export type DuplicateConfirmAction =
    | {
          kind: 'dismiss';
          description: string | null;
      }
    | {
          kind: 'resolveAllRecurring';
          count: number;
      }
    | {
          kind: 'keepRecurring';
          recurring: TransactionSideLabelInput;
          manual: TransactionSideLabelInput;
          recurringDescription: string | null;
      }
    | {
          kind: 'remove';
          removeSide: 'primary' | 'candidate';
          toRemove: TransactionSideLabelInput;
          toKeep: TransactionSideLabelInput;
          removeRoleLabel: string;
          keepRoleLabel: string;
      };

interface Props {
    show: boolean;
    action: DuplicateConfirmAction | null;
    processing: boolean;
    onClose: () => void;
    onConfirm: () => void;
}

export default function DuplicateTransactionConfirmModal({
    show,
    action,
    processing,
    onClose,
    onConfirm,
}: Props) {
    if (!action) {
        return null;
    }

    const isDismiss = action.kind === 'dismiss';
    const isKeepRecurring = action.kind === 'keepRecurring';
    const isResolveAllRecurring = action.kind === 'resolveAllRecurring';

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {isDismiss
                        ? 'Non sono duplicati?'
                        : isResolveAllRecurring
                          ? 'Risolvere tutte le ricorrenze?'
                          : isKeepRecurring
                            ? 'Mantenere la ricorrenza?'
                            : 'Eliminare un movimento?'}
                </h2>

                {isDismiss ? (
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        La segnalazione verrà archiviata. Entrambe le transazioni resteranno nel registro
                        {action.description ? (
                            <>
                                {' '}
                                (es. <span className="font-medium">{action.description}</span>)
                            </>
                        ) : null}
                        .
                    </p>
                ) : isResolveAllRecurring ? (
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Per <span className="font-medium">{action.count}</span> segnalazioni verrà eliminata la
                        transazione inserita a mano e mantenuta quella generata dalla rispettiva ricorrenza. Le altre
                        segnalazioni non verranno modificate.
                    </p>
                ) : isKeepRecurring ? (
                    <>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Una transazione è stata generata dalla ricorrenza
                            {action.recurringDescription ? (
                                <>
                                    {' '}
                                    <span className="font-medium">«{action.recurringDescription}»</span>
                                </>
                            ) : null}
                            , l&apos;altra sembra un inserimento manuale dello stesso pagamento.
                        </p>
                        <div className="mt-4 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 text-sm dark:border-emerald-900/50 dark:bg-emerald-950/30">
                            <p className="font-medium text-emerald-800 dark:text-emerald-200">Da mantenere</p>
                            <p className="text-emerald-900 dark:text-emerald-100">
                                {formatTransactionSideSummary(action.recurring, 'Da ricorrenza')}
                            </p>
                        </div>
                        <div className="mt-2 space-y-2 rounded-lg border border-red-200 bg-red-50/60 p-3 text-sm dark:border-red-900/50 dark:bg-red-950/30">
                            <p className="font-medium text-red-800 dark:text-red-200">Da eliminare</p>
                            <p className="text-red-900 dark:text-red-100">
                                {formatTransactionSideSummary(action.manual, 'Inserimento manuale')}
                            </p>
                        </div>
                    </>
                ) : (
                    <>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Verrà eliminato il movimento indicato come{' '}
                            <span className="font-medium">{action.removeRoleLabel}</span> e mantenuto quello{' '}
                            <span className="font-medium">{action.keepRoleLabel}</span>.
                        </p>
                        <div className="mt-4 space-y-2 rounded-lg border border-red-200 bg-red-50/60 p-3 text-sm dark:border-red-900/50 dark:bg-red-950/30">
                            <p className="font-medium text-red-800 dark:text-red-200">Da eliminare</p>
                            <p className="text-red-900 dark:text-red-100">
                                {formatTransactionSideSummary(action.toRemove, action.removeRoleLabel)}
                            </p>
                        </div>
                        <div className="mt-2 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 text-sm dark:border-emerald-900/50 dark:bg-emerald-950/30">
                            <p className="font-medium text-emerald-800 dark:text-emerald-200">Da mantenere</p>
                            <p className="text-emerald-900 dark:text-emerald-100">
                                {formatTransactionSideSummary(action.toKeep, action.keepRoleLabel)}
                            </p>
                        </div>
                        <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            Importo eliminato:{' '}
                            <span className="font-medium tabular-nums">
                                {formatCurrency(action.toRemove.amount, action.toRemove.currency_code)}
                            </span>
                        </p>
                    </>
                )}

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" onClick={onClose} disabled={processing} className="justify-center">
                        Annulla
                    </SecondaryButton>
                    <DangerButton
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="justify-center"
                    >
                        {processing
                            ? 'Attendere…'
                            : isDismiss
                              ? 'Conferma, non sono duplicati'
                              : isResolveAllRecurring
                                ? 'Risolvi tutte'
                                : isKeepRecurring
                                  ? 'Mantieni da ricorrenza'
                                  : 'Elimina movimento selezionato'}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    );
}
