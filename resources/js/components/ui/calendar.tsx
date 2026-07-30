import { ChevronLeft, ChevronRight } from "lucide-react"
import * as React from "react"
import { DayPicker } from "react-day-picker"

import { buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

function Calendar({
  className,
  classNames,
  showOutsideDays = true,
  ...props
}: React.ComponentProps<typeof DayPicker>) {
  return (
    <DayPicker
      showOutsideDays={showOutsideDays}
      className={cn("p-3", className)}
      classNames={{
        months: "relative flex flex-col gap-4 sm:flex-row",
        month: "flex flex-col gap-4",
        month_caption: "flex h-7 items-center justify-center",
        caption_label: "text-sm font-medium",
        nav: "absolute inset-x-0 top-0 flex items-center justify-between",
        button_previous: cn(
          buttonVariants({ variant: "ghost" }),
          "size-7 p-0 opacity-50 hover:opacity-100 disabled:opacity-25"
        ),
        button_next: cn(
          buttonVariants({ variant: "ghost" }),
          "size-7 p-0 opacity-50 hover:opacity-100 disabled:opacity-25"
        ),
        month_grid: "w-full border-collapse",
        weekdays: "flex",
        weekday:
          "text-muted-foreground w-8 text-[0.8rem] font-normal select-none",
        week: "mt-2 flex w-full",
        // The range background lives on the cell so it meets its neighbours
        // edge to edge; the button inside keeps its own rounding.
        day: "relative size-8 p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([aria-selected])]:bg-accent [&:has([aria-selected].day-range-end)]:rounded-r-md [&:has([aria-selected].day-range-start)]:rounded-l-md",
        day_button: cn(
          buttonVariants({ variant: "ghost" }),
          "size-8 p-0 font-normal aria-selected:opacity-100"
        ),
        range_start:
          "day-range-start rounded-l-md [&>button]:bg-primary [&>button]:text-primary-foreground [&>button:hover]:bg-primary [&>button:hover]:text-primary-foreground",
        range_end:
          "day-range-end rounded-r-md [&>button]:bg-primary [&>button]:text-primary-foreground [&>button:hover]:bg-primary [&>button:hover]:text-primary-foreground",
        range_middle:
          "rounded-none [&>button]:bg-transparent [&>button]:text-accent-foreground [&>button:hover]:bg-transparent",
        selected: "",
        today: "[&>button]:border [&>button]:border-ring",
        outside: "text-muted-foreground opacity-50",
        disabled: "text-muted-foreground opacity-50",
        hidden: "invisible",
        ...classNames,
      }}
      components={{
        Chevron: ({ orientation, className: chevronClassName, ...rest }) => {
          const Icon = orientation === "left" ? ChevronLeft : ChevronRight

          return <Icon className={cn("size-4", chevronClassName)} {...rest} />
        },
      }}
      {...props}
    />
  )
}

export { Calendar }
