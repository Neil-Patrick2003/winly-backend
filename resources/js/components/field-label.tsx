import { Label } from '@/components/ui/label';

/**
 * A form label that states whether the field must be filled in.
 *
 * Required fields carry an asterisk with a screen-reader-only "required";
 * everything else is explicitly marked optional, so a blank field is never
 * ambiguous.
 */
export default function FieldLabel({
    htmlFor,
    children,
    required = false,
}: {
    htmlFor: string;
    children: React.ReactNode;
    required?: boolean;
}) {
    return (
        <Label htmlFor={htmlFor} className="gap-1.5 text-[13px]">
            {children}

            {required ? (
                <>
                    <span aria-hidden="true" className="text-destructive">
                        *
                    </span>
                    <span className="sr-only">(required)</span>
                </>
            ) : (
                <span className="text-[11px] font-normal text-muted-foreground">
                    Optional
                </span>
            )}
        </Label>
    );
}
