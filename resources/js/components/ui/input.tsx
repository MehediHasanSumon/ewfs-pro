import * as React from "react"

import { cn } from "@/lib/utils"

interface InputProps extends React.ComponentProps<"input"> {
  error?: boolean;
}

function Input({ className, type, error, ...props }: InputProps) {
  return (
    <input
      type={type}
      className={cn(
        "w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg transition-all duration-200 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-[13px]",
        "focus:border-indigo-500 dark:focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-indigo-900",
        "disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-50 dark:disabled:bg-gray-900",
        "placeholder:text-gray-400 dark:placeholder:text-gray-500",
        "file:border-0 file:bg-transparent file:text-sm file:font-medium",
        error && "border-red-500 dark:border-red-400 focus:border-red-500 dark:focus:border-red-400 focus:ring-red-100 dark:focus:ring-red-900",
        className
      )}
      {...props}
    />
  )
}

export { Input }