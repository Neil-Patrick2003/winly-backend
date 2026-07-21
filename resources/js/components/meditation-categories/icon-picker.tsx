import { useState } from 'react';
import { iconLabel, meditationIcon } from '@/lib/meditation-icons';
import { cn } from '@/lib/utils';

export default function IconPicker({
    name = 'icon',
    options,
    defaultValue,
}: {
    name?: string;
    options: string[];
    defaultValue?: string;
}) {
    const [selected, setSelected] = useState(defaultValue ?? options[0]);

    return (
        <div>
            <input type="hidden" name={name} value={selected} />

            <div
                role="radiogroup"
                aria-label="Icon"
                className="grid grid-cols-7 gap-1.5"
            >
                {options.map((option) => {
                    const IconComponent = meditationIcon(option);
                    const isSelected = option === selected;

                    return (
                        <button
                            key={option}
                            type="button"
                            role="radio"
                            aria-checked={isSelected}
                            aria-label={iconLabel(option)}
                            title={iconLabel(option)}
                            onClick={() => setSelected(option)}
                            className={cn(
                                'flex aspect-square items-center justify-center rounded-md border transition-colors',
                                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                isSelected
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-input text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )}
                        >
                            <IconComponent className="size-4" />
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
