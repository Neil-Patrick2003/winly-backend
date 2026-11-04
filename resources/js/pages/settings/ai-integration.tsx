import { Form, Head, router } from '@inertiajs/react';
import {
    Check,
    Clock,
    Copy,
    Eye,
    KeyRound,
    MessageSquareText,
    ShieldCheck,
    Sparkles,
    Terminal,
    Trash2,
    TriangleAlert,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AiIntegrationController from '@/actions/App/Http/Controllers/Settings/AiIntegrationController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { SettingsSection } from '@/components/settings-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/ai-integration';

type AiToken = {
    id: number;
    name: string;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
};

type Props = {
    /** The MCP endpoint, for apps that send the token as a header. */
    mcpUrl: string;
    /** The signed-in person's AI integration tokens. */
    tokens: AiToken[];
    /** Plain-text token, present only right after creating one. */
    newToken: string | null;
    /** Whether this person runs any circle — the tools only answer about those. */
    runsCircles: boolean;
};

const GUARANTEES: { icon: LucideIcon; label: string }[] = [
    { icon: Eye, label: 'Read-only' },
    { icon: UsersRound, label: 'Only circles you own' },
    { icon: Clock, label: 'Expires in 90 days' },
];

const STEPS = [
    {
        title: 'Generate a token',
        description: 'Name it after the app or device you will use it on.',
    },
    {
        title: 'Connect your AI app',
        description:
            'Run the Claude Code command, or add the token to any MCP app.',
    },
    {
        title: 'Ask about your circles',
        description: 'The AI looks up the answer from your circles as you.',
    },
];

const EXAMPLE_QUESTIONS = [
    "Who didn't post yesterday in my circle?",
    'Which members have been quiet this week?',
    'Who posted the most in the last 30 days?',
];

const listStyles =
    'divide-y divide-border overflow-hidden rounded-card border border-border shadow-card';

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

const isExpired = (token: AiToken) =>
    token.expires_at !== null && new Date(token.expires_at) < new Date();

export default function AiIntegration({
    mcpUrl,
    tokens,
    newToken,
    runsCircles,
}: Props) {
    const [copiedText, copy] = useClipboard();

    const claudeCodeCommand = newToken
        ? `claude mcp add --transport http welle ${mcpUrl} --header "Authorization: Bearer ${newToken}"`
        : '';

    const revoke = (token: AiToken) => {
        router.delete(AiIntegrationController.destroy(token.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="AI integration" />

            <h1 className="sr-only">AI integration</h1>

            <div className="space-y-10">
                {/* Overview */}
                <section className="overflow-hidden rounded-card border border-border shadow-card">
                    <div className="flex items-start gap-4 bg-gradient-to-br from-primary/10 via-primary/5 to-transparent p-5">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm">
                            <Sparkles className="size-5" />
                        </div>
                        <div className="min-w-0 space-y-1">
                            <h2 className="text-base font-semibold">
                                Connect an AI assistant
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Let Claude or any MCP app answer questions about
                                your circles, like who has been posting and who
                                has gone quiet.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2 border-t border-border px-5 py-3">
                        {GUARANTEES.map(({ icon: Icon, label }) => (
                            <span
                                key={label}
                                className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                            >
                                <Icon className="size-3.5" />
                                {label}
                            </span>
                        ))}
                    </div>
                </section>

                {!runsCircles && (
                    <div className="flex items-start gap-2.5 rounded-card border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            You don't own any circles yet, so the AI won't have
                            anything to look at until you do.
                        </p>
                    </div>
                )}

                {/* Freshly made token */}
                {newToken && (
                    <section className="space-y-4 rounded-card border-2 border-primary/40 bg-primary/5 p-5">
                        <div className="flex items-start gap-3">
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                <Check className="size-4" />
                            </div>
                            <div className="space-y-0.5">
                                <h2 className="text-sm font-semibold">
                                    Your token is ready
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Copy it now. It won't be shown again, and
                                    anyone with it can read your circles.
                                </p>
                            </div>
                        </div>

                        <CopyField
                            icon={KeyRound}
                            label="Token"
                            value={newToken}
                            copied={copiedText === newToken}
                            onCopy={copy}
                        />

                        <CopyField
                            icon={Terminal}
                            label="Claude Code: run this in your terminal"
                            value={claudeCodeCommand}
                            copied={copiedText === claudeCodeCommand}
                            onCopy={copy}
                            multiline
                        />

                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Other apps (Cursor, ChatGPT, etc.): use the server
                            URL{' '}
                            <code className="rounded bg-muted px-1 py-0.5 font-mono">
                                {mcpUrl}
                            </code>{' '}
                            with the header{' '}
                            <code className="rounded bg-muted px-1 py-0.5 font-mono">
                                Authorization: Bearer &lt;token&gt;
                            </code>
                            .
                        </p>
                    </section>
                )}

                {/* How it works */}
                <SettingsSection title="How it works">
                    <ol className="grid gap-3 sm:grid-cols-3">
                        {STEPS.map((step, index) => (
                            <li
                                key={step.title}
                                className="rounded-card border border-border p-4"
                            >
                                <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                    {index + 1}
                                </span>
                                <p className="mt-3 text-sm font-medium">
                                    {step.title}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {step.description}
                                </p>
                            </li>
                        ))}
                    </ol>
                </SettingsSection>

                {/* Generate */}
                <SettingsSection
                    title="Generate a token"
                    description="Tokens are personal: each one works only as you."
                >
                    <Form
                        {...AiIntegrationController.store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                    >
                        {({ processing, errors }) => (
                            <div className="grid gap-2">
                                <Label htmlFor="name" className="sr-only">
                                    Token name
                                </Label>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Input
                                        id="name"
                                        name="name"
                                        className="block w-full"
                                        autoComplete="off"
                                        placeholder="e.g. Claude on my laptop"
                                        maxLength={100}
                                        required
                                    />
                                    <Button
                                        disabled={processing}
                                        className="shrink-0"
                                    >
                                        <KeyRound className="size-4" />
                                        Generate token
                                    </Button>
                                </div>
                                <InputError message={errors.name} />
                            </div>
                        )}
                    </Form>
                </SettingsSection>

                {/* Existing tokens */}
                <SettingsSection
                    title="Your tokens"
                    description={
                        tokens.length > 0
                            ? `${tokens.length} of 10 used`
                            : undefined
                    }
                >
                    {tokens.length === 0 ? (
                        <EmptyState
                            icon={KeyRound}
                            title="No tokens yet"
                            description="Generate one above to connect your first AI app."
                        />
                    ) : (
                        <ul className={listStyles}>
                            {tokens.map((token) => (
                                <li
                                    key={token.id}
                                    className="flex items-center gap-3 px-4 py-3"
                                >
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                        <KeyRound className="size-4" />
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <p className="truncate text-sm font-medium">
                                                {token.name}
                                            </p>
                                            {isExpired(token) ? (
                                                <Badge variant="destructive">
                                                    Expired
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Active
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {token.last_used_at
                                                ? `Last used ${formatDate(token.last_used_at)}`
                                                : 'Never used'}
                                            {token.expires_at &&
                                                ` · ${isExpired(token) ? 'Expired' : 'Expires'} ${formatDate(token.expires_at)}`}
                                        </p>
                                    </div>

                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-destructive"
                                                aria-label={`Revoke ${token.name}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        }
                                        tooltip="Revoke"
                                        title="Revoke token?"
                                        description={`Any AI app using “${token.name}” will stop working immediately.`}
                                        confirmLabel="Revoke"
                                        destructive
                                        onConfirm={() => revoke(token)}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </SettingsSection>

                {/* Examples */}
                <SettingsSection
                    title="Things you can ask"
                    description="Click one to copy it, then paste it into your AI app."
                >
                    <div className="flex flex-wrap gap-2">
                        {EXAMPLE_QUESTIONS.map((question) => (
                            <button
                                key={question}
                                type="button"
                                onClick={() => copy(question)}
                                className="inline-flex items-center gap-2 rounded-full border border-border px-3 py-1.5 text-left text-sm transition-colors hover:border-primary/40 hover:bg-primary/5"
                            >
                                {copiedText === question ? (
                                    <Check className="size-3.5 text-primary" />
                                ) : (
                                    <MessageSquareText className="size-3.5 text-muted-foreground" />
                                )}
                                {question}
                            </button>
                        ))}
                    </div>
                </SettingsSection>

                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <ShieldCheck className="size-3.5 shrink-0" />
                    AI tokens can't open the mobile app's API, and app sign-ins
                    can't be used here.
                </p>
            </div>
        </>
    );
}

AiIntegration.layout = {
    breadcrumbs: [
        {
            title: 'AI integration',
            href: edit(),
        },
    ],
};

function CopyField({
    icon: Icon,
    label,
    value,
    copied,
    onCopy,
    multiline = false,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    copied: boolean;
    onCopy: (text: string) => Promise<boolean>;
    /** Wraps long values instead of cutting them off. */
    multiline?: boolean;
}) {
    return (
        <div className="space-y-1.5">
            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <Icon className="size-3.5" />
                {label}
            </p>
            <div className="flex items-start gap-2 rounded-lg border border-border bg-background p-1.5 pl-3">
                <code
                    className={cn(
                        'min-w-0 flex-1 py-1 font-mono text-xs',
                        multiline ? 'break-all' : 'truncate',
                    )}
                >
                    {value}
                </code>
                <Button
                    type="button"
                    variant={copied ? 'default' : 'outline'}
                    size="sm"
                    className="shrink-0"
                    onClick={() => onCopy(value)}
                >
                    {copied ? (
                        <Check className="size-3.5" />
                    ) : (
                        <Copy className="size-3.5" />
                    )}
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </div>
        </div>
    );
}
