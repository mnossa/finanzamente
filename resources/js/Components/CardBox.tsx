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
        'overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800',
        className
      )}
    >
      {children}
    </div>
  );
};

export default CardBox;
