import React from 'react';
import clsx from 'clsx';

interface CardBoxProps {
  children: React.ReactNode;
  className?: string;
}

const CardBox: React.FC<CardBoxProps> = ({ children, className }) => {
  return (
    <div
      className={clsx(
        'overflow-hidden rounded-2xl border border-gray-200/80 bg-white/95 p-6 shadow-sm backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/95',
        className
      )}
    >
      {children}
    </div>
  );
};

export default CardBox;
