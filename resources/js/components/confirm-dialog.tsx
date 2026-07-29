import {  useState } from 'react';
import type {ReactNode} from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

type Props = {
    /** The control that opens it. */
    trigger: ReactNode;
    /**
     * Names the trigger on hover. Worth passing whenever the trigger is an icon
     * on its own, where the label is otherwise only read by screen readers.
     */
    tooltip?: string;
    title: string;
    description: string;
    confirmLabel?: string;
    /** Draws the confirm button as destructive. */
    destructive?: boolean;
    onConfirm: () => void;
};

/**
 * An in-app confirmation for anything that cannot be undone.
 *
 * The browser's own `confirm` blocks the page, cannot be styled, and is
 * suppressible per-site — so a user who has silenced it once loses the guard
 * everywhere. This one is part of the page.
 */
export function ConfirmDialog({
    trigger,
    tooltip,
    title,
    description,
    confirmLabel = 'Confirm',
    destructive = false,
    onConfirm,
}: Props) {
    const [open, setOpen] = useState(false);

    const confirm = () => {
        setOpen(false);
        onConfirm();
    };

    /*
     * Both triggers are slots, so they nest onto the one button: the tooltip
     * wraps the dialog's trigger rather than the other way round, or the
     * tooltip would be describing a wrapper instead of the control itself.
     */
    const control = <DialogTrigger asChild>{trigger}</DialogTrigger>;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {tooltip ? (
                <Tooltip>
                    <TooltipTrigger asChild>{control}</TooltipTrigger>
                    <TooltipContent>{tooltip}</TooltipContent>
                </Tooltip>
            ) : (
                control
            )}

            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={confirm}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
