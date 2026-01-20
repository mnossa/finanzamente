import React from 'react';
import clsx from 'clsx';
import Modal from './Modal';

interface ConfirmDeleteDialogProps {
  open: boolean;
  title?: string;
  description?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  onConfirm: () => void;
  onCancel: () => void;
  className?: string;
  children?: React.ReactNode;
  variant?: 'danger' | 'warning' | 'info';
}

export const ConfirmDeleteDialog: React.FC<ConfirmDeleteDialogProps> = ({
  open,
  title = 'Conferma eliminazione',
  description = 'Sei sicuro di voler eliminare questo elemento? Questa azione non può essere annullata.',
  confirmLabel = 'Elimina',
  cancelLabel = 'Annulla',
  onConfirm,
  onCancel,
  className,
  children,
  variant = 'danger',
}) => {
  const variantClasses = {
    danger: {
      title: 'text-red-600',
      button: 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
    },
    warning: {
      title: 'text-amber-600',
      button: 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
    },
    info: {
      title: 'text-blue-600',
      button: 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500',
    },
  };

  const colors = variantClasses[variant];

  return (
    <Modal show={open} onClose={onCancel} maxWidth="sm" closeable={true}>
      <div className={clsx('p-6', className)}>
        <h2 className={clsx('text-lg font-semibold mb-2', colors.title)}>{title}</h2>
        <p className="text-gray-700 dark:text-gray-300 mb-4">{description}</p>
        {children}
        <div className="flex justify-end gap-2 mt-4">
          <button
            type="button"
            className="px-4 py-2 rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            onClick={onCancel}
          >
            {cancelLabel}
          </button>
          <button
            type="button"
            className={clsx('px-4 py-2 rounded-lg text-white focus:outline-none focus:ring-2', colors.button)}
            onClick={onConfirm}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </Modal>
  );
};
