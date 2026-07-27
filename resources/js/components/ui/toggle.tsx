import * as React from "react"
import * as TogglePrimitive from "@radix-ui/react-toggle"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

const toggleVariants = cva(
  "relative inline-block w-12 h-6 bg-gray-300 dark:bg-gray-600 rounded-full cursor-pointer transition-colors duration-200",
  {
    variants: {
      variant: {
        default: "data-[state=on]:bg-indigo-600 dark:data-[state=on]:bg-indigo-500",
        outline: "border-2 border-gray-300 dark:border-gray-600 data-[state=on]:border-indigo-600 dark:data-[state=on]:border-indigo-500 data-[state=on]:bg-indigo-600 dark:data-[state=on]:bg-indigo-500",
      },
      size: {
        default: "w-12 h-6",
        sm: "w-10 h-5",
        lg: "w-14 h-7",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Toggle({
  className,
  variant,
  size,
  ...props
}: React.ComponentProps<typeof TogglePrimitive.Root> &
  VariantProps<typeof toggleVariants>) {
  return (
    <TogglePrimitive.Root
      className={cn(toggleVariants({ variant, size }), className)}
      {...props}
    >
      <span className={cn(
        "absolute top-0.5 left-0.5 bg-white dark:bg-gray-200 rounded-full transition-transform duration-200",
        "data-[state=on]:translate-x-6",
        size === "sm" && "h-4 w-4 data-[state=on]:translate-x-5",
        size === "default" && "h-5 w-5 data-[state=on]:translate-x-6",
        size === "lg" && "h-6 w-6 data-[state=on]:translate-x-7"
      )} />
    </TogglePrimitive.Root>
  )
}

export { Toggle, toggleVariants }