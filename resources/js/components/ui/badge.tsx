import * as React from "react"
import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-block px-2 py-0.5 rounded-full text-xs font-semibold",
  {
    variants: {
      variant: {
        default: "bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200",
        secondary: "bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300",
        destructive: "bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200",
        success: "bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200",
        warning: "bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200",
        purple: "bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-200",
        pink: "bg-pink-100 dark:bg-pink-900 text-pink-700 dark:text-pink-200",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }