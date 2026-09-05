import { type ReactNode, useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/feedback/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

type Common = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
};

type Confirmable = Common & {
    blocked?: false;
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

/**
 * The same dialog explaining why the action is not available, rather than offering it:
 * no destructive button, and the way out says Close rather than Cancel, because there
 * is nothing to cancel.
 *
 * A union rather than a `blocked` flag beside optional labels, so the compiler refuses
 * a confirmable dialog with no button and a blocked one carrying an `onConfirm` that
 * can never run.
 */
type Blocked = Common & {
    blocked: true;
    /**
     * Somewhere to go instead. A blocked dialog states a problem and then offers no
     * way to work on it, which is the one thing it can improve on: the row that
     * caused the block is on another screen, and this is the shortest path to it.
     *
     * Only on the blocked variant. A confirmable dialog already has the action
     * someone opened it for, and a second one beside it competes for the press.
     */
    children?: ReactNode;
};

type Props = Confirmable | Blocked;

export function ConfirmDialog(props: Props) {
    const { open, onOpenChange, title, description } = props;
    const blocked = props.blocked === true;
    // Nothing is in flight when there is nothing to send, so a blocked dialog can
    // always be dismissed.
    const processing = props.blocked === true ? false : props.processing;
    const confirmPhrase =
        props.blocked === true ? undefined : props.confirmPhrase;

    const { t } = useTranslation();
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
            {/*
                A blocked dialog drops the corner ✕: its footer button already says
                Close, and two controls with the same accessible name doing the same
                thing is a thing a screen reader has to disambiguate for no reason.
                Escape still dismisses it either way.
            */}
            <DialogContent showCloseButton={!blocked}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                {props.blocked === true && props.children !== undefined && (
                    <div className="text-sm">{props.children}</div>
                )}

                {confirmPhrase !== undefined && (
                    <div className="space-y-2">
                        <Label htmlFor="confirm-phrase">
                            {t('common.confirm.type_to_confirm', {
                                phrase: confirmPhrase,
                            })}
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
                        {t(
                            blocked
                                ? 'common.actions.close'
                                : 'common.actions.cancel',
                        )}
                    </Button>
                    {props.blocked !== true && (
                        <Button
                            type="button"
                            variant={props.variant ?? 'default'}
                            disabled={processing || !armed}
                            onClick={props.onConfirm}
                        >
                            {processing && <Spinner />}
                            {processing ? props.busyLabel : props.confirmLabel}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
