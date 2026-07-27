import * as React from "react"
import * as LabelPrimitive from "@radix-ui/react-label"

import { cn } from "@/lib/utils"

interface LabelProps extends React.ComponentProps<typeof LabelPrimitive.Root> {
  variant?: 'default' | 'bold' | 'muted' | 'primary';
}

function Label({
  className,
  variant = 'default',
  ...props
}: LabelProps) {
  return (
    <LabelPrimitive.Root
      className={cn(
        "text-sm leading-none select-none transition-colors",
        "peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        {
          'font-medium text-gray-700 dark:text-gray-300': variant === 'default',
          'font-semibold text-gray-900 dark:text-gray-100': variant === 'bold',
          'text-gray-600 dark:text-gray-400': variant === 'muted',
          'text-indigo-600 dark:text-indigo-400 font-medium': variant === 'primary',
        },
        className
      )}
      {...props}
    />
  )
}

export { Label }