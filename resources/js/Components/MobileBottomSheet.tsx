import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { Fragment, type ReactNode } from 'react';
import clsx from 'clsx';

interface MobileBottomSheetProps {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    footer?: ReactNode;
    /** id for aria-controls / testid */
    panelTestId?: string;
}

/**
 * Pannello dal basso per filtri/azioni su mobile e tablet.
 * Su desktop preferire pannelli inline; questo componente è pensato per viewport < lg.
 */
export default function MobileBottomSheet({
    open,
    onClose,
    title,
    children,
    footer,
    panelTestId = 'mobile-bottom-sheet',
}: MobileBottomSheetProps) {
    return (
        <Transition show={open} as={Fragment}>
            <Dialog as="div" className="relative z-50 lg:hidden" onClose={onClose}>
                <TransitionChild
                    as={Fragment}
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" aria-hidden="true" />
                </TransitionChild>

                <div className="fixed inset-0 flex items-end justify-center">
                    <TransitionChild
                        as={Fragment}
                        enter="ease-out duration-200"
                        enterFrom="translate-y-full"
                        enterTo="translate-y-0"
                        leave="ease-in duration-150"
                        leaveFrom="translate-y-0"
                        leaveTo="translate-y-full"
                    >
                        <DialogPanel
                            data-testid={panelTestId}
                            className={clsx(
                                'flex max-h-[85vh] w-full max-w-lg flex-col rounded-t-2xl bg-white shadow-xl',
                                'dark:bg-slate-800',
                                'pb-[env(safe-area-inset-bottom,0px)]',
                            )}
                        >
                            <div className="flex shrink-0 items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                                <DialogTitle className="text-base font-semibold text-gray-900 dark:text-white">
                                    {title}
                                </DialogTitle>
                                <button
                                    type="button"
                                    onClick={onClose}
                                    className="rounded-lg px-2 py-1 text-sm font-medium text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                    aria-label="Chiudi"
                                >
                                    Chiudi
                                </button>
                            </div>
                            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-3">{children}</div>
                            {footer && (
                                <div className="shrink-0 border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                                    {footer}
                                </div>
                            )}
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
