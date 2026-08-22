import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    busyLabel: string;
    processing: boolean;
    onConfirm: () => void;
    variant?: 'default' | 'destructive';
    /**
     * When set, the action stays disabled until this exact text is typed. For the
     * irreversible ones only — asking on every archive would train people to type
     * without reading.
     */
    confirmPhrase?: string;
};

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    busyLabel,
    processing,
    onConfirm,
    variant = 'default',
    confirmPhrase,
}: Props) {
    const [typed, setTyped] = useState('');

    // Clear the phrase whenever the dialog opens, so a previous confirmation can
    // never carry over and pre-arm the button.
    useEffect(() => {
        if (open) {
            setTyped('');
        }
    }, [open]);

    const armed = confirmPhrase === undefined || typed === confirmPhrase;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                // Never let a click-away abandon an in-flight request.
                if (!processing) {
                    onOpenChange(next);
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                {confirmPhrase !== undefined && (
                    <div className="space-y-2">
                        <Label htmlFor="confirm-phrase">
                            Type{' '}
                            <span className="font-mono font-semibold">
                                {confirmPhrase}
                            </span>{' '}
                            to confirm
                        </Label>
                        <Input
                            id="confirm-phrase"
                            value={typed}
                            onChange={(event) => setTyped(event.target.value)}
                            autoComplete="off"
                            autoCapitalize="none"
                            spellCheck={false}
                        />
                    </div>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant={variant}
                        disabled={processing || !armed}
                        onClick={onConfirm}
                    >
                        {processing && <Spinner />}
                        {processing ? busyLabel : confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
