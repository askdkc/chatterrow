<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import {
        ArrowRight,
        BellRing,
        CheckCircle2,
        FileText,
        FolderOpen,
        GanttChartSquare,
        Hash,
        ListTodo,
        MessageSquareText,
        MessagesSquare,
        ShieldCheck,
        Sparkles,
        Users,
        Zap,
    } from 'lucide-svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import LanguageSwitcher from '@/components/LanguageSwitcher.svelte';
    import { t } from '@/lib/i18n';
    import { toUrl } from '@/lib/utils';
    import { login } from '@/routes';
    import { register } from '@/routes';
    import { index as serversIndex } from '@/routes/servers';

    const auth = $derived(page.props.auth);
    const appName = $derived(page.props.name ?? 'Chatterrow');
</script>

<AppHead title={appName} />

<div class="min-h-screen bg-background text-foreground">
    <!-- ── Header ─────────────────────────────────────────── -->
    <header
        class="sticky top-0 z-20 border-b border-border bg-background/90 backdrop-blur"
    >
        <div
            class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6"
        >
            <div class="flex items-center gap-2.5">
                <AppLogoIcon
                    class="size-9 rounded-xl shadow-lg shadow-brand/30"
                />
                <div class="leading-tight">
                    <div
                        class="text-[15px] font-bold tracking-wide text-foreground"
                    >
                        {appName}
                    </div>
                    <div class="text-[11px] text-foreground/80">chatterrow</div>
                </div>
            </div>
            <nav class="flex min-w-0 items-center gap-2">
                <LanguageSwitcher
                    class="w-36 max-w-[38vw] shrink-0 sm:w-40 sm:max-w-none"
                />
                {#if auth.user}
                    <Link
                        href={toUrl(serversIndex())}
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground transition hover:bg-brand-hover"
                    >
                        <Users class="size-4" />
                        {t('Go to project list')}
                    </Link>
                {:else}
                    <Link
                        href={toUrl(login())}
                        class="rounded-lg px-4 py-2 text-sm font-medium text-foreground transition hover:bg-accent"
                    >
                        {t('Log in')}
                    </Link>
                    <Link
                        href={toUrl(register())}
                        class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground transition hover:bg-brand-hover"
                    >
                        {t('Get started for free')}
                    </Link>
                {/if}
            </nav>
        </div>
    </header>

    <!-- ── Hero ───────────────────────────────────────────── -->
    <section class="relative overflow-hidden">
        <!-- glow -->
        <div
            class="pointer-events-none absolute -top-40 left-1/2 h-[480px] w-[900px] -translate-x-1/2 rounded-full bg-brand/15 blur-3xl"
        ></div>
        <div
            class="pointer-events-none absolute top-40 right-0 size-72 rounded-full bg-brand-pink/10 blur-3xl"
        ></div>

        <div
            class="relative mx-auto grid max-w-6xl gap-12 px-4 pt-16 pb-20 sm:px-6 lg:grid-cols-2 lg:items-center lg:pt-24 lg:pb-28"
        >
            <div>
                <div
                    class="mb-5 inline-flex items-center gap-2 rounded-full border border-brand/40 bg-brand/10 px-3 py-1 text-xs font-medium text-brand-accent"
                >
                    <Sparkles class="size-3.5" />
                    {t('A unified team workspace connected by chat')}
                </div>
                <h1
                    class="text-4xl leading-tight font-extrabold tracking-tight text-foreground sm:text-5xl"
                >
                    {t('Bring the context behind your work')}
                    <span
                        class="bg-gradient-to-r from-brand-accent to-brand-pink bg-clip-text text-transparent"
                    >
                        {t('into one place')}
                    </span>
                </h1>
                <p
                    class="mt-5 max-w-md text-[15px] leading-relaxed font-medium text-foreground/80"
                >
                    {t(
                        'Email threads, scattered files, and private chats. Chatterrow brings conversations, decisions, files, and tasks into the same channels, creating a foundation where teams can always trace who did what and why.',
                    )}
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    {#if auth.user}
                        <Link
                            href={toUrl(serversIndex())}
                            class="inline-flex items-center gap-2 rounded-lg bg-brand px-6 py-3 text-sm font-semibold text-brand-foreground shadow-lg shadow-brand/30 transition hover:bg-brand-hover"
                        >
                            {t('Go to my projects')}
                            <ArrowRight class="size-4" />
                        </Link>
                    {:else}
                        <Link
                            href={toUrl(register())}
                            class="inline-flex items-center gap-2 rounded-lg bg-brand px-6 py-3 text-sm font-semibold text-brand-foreground shadow-lg shadow-brand/30 transition hover:bg-brand-hover"
                        >
                            {t('Create an account')}
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            href={toUrl(login())}
                            class="rounded-lg border border-border px-6 py-3 text-sm font-semibold text-foreground transition hover:bg-accent"
                        >
                            {t('Log in')}
                        </Link>
                    {/if}
                </div>
                <div
                    class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-xs font-medium text-foreground/80"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <CheckCircle2 class="size-3.5 text-success" />
                        {t('Channel = work = task')}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <CheckCircle2 class="size-3.5 text-success" />
                        {t('Deadlines and reminders')}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <CheckCircle2 class="size-3.5 text-success" />
                        {t('File previews')}
                    </span>
                </div>
            </div>

            <!-- mock UI -->
            <div class="relative">
                <div
                    class="overflow-hidden rounded-2xl border border-border bg-card shadow-2xl shadow-foreground/15"
                >
                    <div class="flex h-[380px]">
                        <!-- rail -->
                        <div
                            class="flex w-16 flex-col items-center gap-2 bg-muted py-3"
                        >
                            <div
                                class="flex size-11 items-center justify-center rounded-2xl bg-brand text-[13px] font-bold text-brand-foreground"
                            >
                                {t('C')}
                            </div>
                            <div class="h-8 w-0.5 rounded bg-border"></div>
                            <div
                                class="flex size-11 items-center justify-center rounded-2xl bg-background text-[13px] font-bold text-foreground transition hover:rounded-lg hover:bg-brand hover:text-brand-foreground"
                            >
                                {t('G')}
                            </div>
                            <div
                                class="flex size-11 items-center justify-center rounded-2xl bg-background text-[13px] font-bold text-foreground"
                            >
                                {t('P')}
                            </div>
                        </div>
                        <!-- channel list -->
                        <div class="w-52 shrink-0 bg-secondary/70 p-3">
                            <div
                                class="mb-3 text-[11px] font-bold tracking-wide text-foreground"
                            >
                                {t('General Affairs')}
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground/80"
                            >
                                <Hash class="size-3.5" />
                                {t('Company-wide updates')}
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 rounded bg-accent px-1 py-0.5 text-xs font-medium text-accent-foreground"
                            >
                                <Hash class="size-3.5" />
                                {t('Hiring schedule')}
                                <span
                                    class="ml-auto rounded-full bg-danger px-1.5 text-[9px] font-bold text-brand-foreground"
                                >
                                    2
                                </span>
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground/80"
                            >
                                <Hash class="size-3.5" />
                                {t('Approvals and decisions')}
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground/80"
                            >
                                <Hash class="size-3.5" />
                                {t('Watercooler')}
                            </div>
                            <div
                                class="mt-4 mb-2 text-[11px] font-bold tracking-wide text-foreground"
                            >
                                {t('Tasks')}
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <div class="rounded-md bg-background p-2">
                                    <div
                                        class="mb-1.5 flex items-center gap-1.5"
                                    >
                                        <CheckCircle2
                                            class="size-3 text-success"
                                        />
                                        <span
                                            class="text-[10px] font-medium text-foreground/80"
                                        >
                                            {t(
                                                'Schedule first-round interviews',
                                            )}
                                        </span>
                                    </div>
                                    <div
                                        class="ml-4 h-1 w-full rounded bg-muted"
                                    >
                                        <div
                                            class="h-1 w-3/4 rounded bg-success"
                                        ></div>
                                    </div>
                                </div>
                                <div class="rounded-md bg-background p-2">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="size-3 rounded-full border-2 border-muted-foreground"
                                        ></span>
                                        <span
                                            class="text-[10px] font-medium text-foreground/80"
                                        >
                                            {t('Update job posting')}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- messages -->
                        <div class="flex min-w-0 flex-1 flex-col bg-card">
                            <div
                                class="flex items-center gap-1.5 border-b border-border px-4 py-2.5 text-sm font-bold text-card-foreground"
                            >
                                <Hash class="size-4 text-foreground/80" />
                                {t('Hiring schedule')}
                            </div>
                            <div
                                class="flex flex-1 flex-col gap-3 overflow-hidden p-4"
                            >
                                <div class="flex gap-2.5">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-pink-solid text-xs font-bold text-brand-foreground"
                                    >
                                        {t('S')}
                                    </div>
                                    <div>
                                        <div
                                            class="flex items-center gap-2 text-xs"
                                        >
                                            <span
                                                class="font-semibold text-card-foreground"
                                                >{t('Sakuma')}</span
                                            >
                                            <span
                                                class="text-[10px] font-medium text-foreground/80"
                                                >10:24</span
                                            >
                                        </div>
                                        <p
                                            class="mt-0.5 text-[13px] text-card-foreground"
                                        >
                                            {t(
                                                'The interview schedule was moved to Tuesday',
                                            )}
                                            <span class="text-brand-accent">
                                                @{t('Recruiting team')}</span
                                            >
                                        </p>
                                        <div
                                            class="mt-1.5 flex items-center gap-1.5 rounded-lg bg-muted px-2.5 py-1.5"
                                        >
                                            <FileText
                                                class="size-3.5 text-brand-accent"
                                            />
                                            <span
                                                class="text-[10px] font-medium text-foreground/80"
                                                >{t(
                                                    'Interview schedule.xlsx',
                                                )}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2.5">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-brand-foreground"
                                    >
                                        {t('T')}
                                    </div>
                                    <div>
                                        <div
                                            class="flex items-center gap-2 text-xs"
                                        >
                                            <span
                                                class="font-semibold text-card-foreground"
                                                >{t('Takahashi')}</span
                                            >
                                            <span
                                                class="text-[10px] font-medium text-foreground/80"
                                                >10:31</span
                                            >
                                        </div>
                                        <p
                                            class="mt-0.5 text-[13px] text-card-foreground"
                                        >
                                            {t(
                                                'Confirmed. I will contact the candidates by tomorrow.',
                                            )}
                                        </p>
                                        <div
                                            class="mt-1.5 inline-flex items-center gap-1 rounded bg-brand/15 px-1.5 py-0.5 text-[10px] text-brand-accent"
                                        >
                                            <CheckCircle2 class="size-3" />
                                            {t(
                                                'Task "Schedule first-round interviews" completed',
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="rounded-lg border border-brand/30 bg-brand/10 p-2.5"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-[11px] font-semibold text-brand-accent"
                                    >
                                        <BellRing class="size-3.5" />
                                        {t('Deadline reminder')}
                                    </div>
                                    <p
                                        class="mt-1 text-[11px] font-medium text-foreground/80"
                                    >
                                        {t(
                                            'The deadline for "Update job posting" is Wednesday, August 5.',
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div class="border-t border-border p-3">
                                <div
                                    class="rounded-lg bg-muted px-3 py-2.5 text-[13px] font-medium text-foreground/80"
                                >
                                    {t('Send a message')}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="pointer-events-none absolute -bottom-6 -left-6 -z-10 size-40 rounded-2xl bg-brand-pink/10 blur-2xl"
                ></div>
            </div>
        </div>
    </section>

    <!-- ── Principles ───────────────────────────────────── -->
    <section class="border-t border-border bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
            <div class="mb-12 max-w-2xl">
                <div
                    class="mb-2 text-xs font-bold tracking-widest text-brand-accent uppercase"
                >
                    {t('WHY CHATTERROW')}
                </div>
                <h2 class="text-2xl font-bold text-foreground sm:text-3xl">
                    {t('Chat moves beyond simple communication')}<br
                        class="hidden sm:block"
                    />
                    {t('to a place where work context is shared')}
                </h2>
                <p
                    class="mt-4 text-sm leading-relaxed font-medium text-foreground/80"
                >
                    {t(
                        'When information is scattered, work becomes less efficient and context gets lost. Chatterrow builds three principles into the product so team knowledge becomes an organizational asset.',
                    )}
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <!-- 1 -->
                <div
                    class="group rounded-2xl border border-border bg-card p-6 transition hover:border-brand/50 hover:bg-accent/50"
                >
                    <div
                        class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand/15 text-brand-accent"
                    >
                        <FolderOpen class="size-5" />
                    </div>
                    <div class="mb-1 text-[11px] font-bold text-brand-accent">
                        01
                    </div>
                    <h3 class="mb-2 text-[15px] font-bold text-card-foreground">
                        {t('Gather information in one place')}
                    </h3>
                    <p
                        class="text-[13px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        {t(
                            'Build projects around organizations and work, and channels around work topics. Advice, progress, and materials gather in the "Work A" channel, preventing valuable context from ending in private chats.',
                        )}
                    </p>
                </div>
                <!-- 2 -->
                <div
                    class="group rounded-2xl border border-border bg-card p-6 transition hover:border-brand-pink/50 hover:bg-accent/50"
                >
                    <div
                        class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-pink/15 text-brand-pink"
                    >
                        <MessageSquareText class="size-5" />
                    </div>
                    <div class="mb-1 text-[11px] font-bold text-brand-pink">
                        02
                    </div>
                    <h3 class="mb-2 text-[15px] font-bold text-card-foreground">
                        {t('Keep conversations, decisions, and files together')}
                    </h3>
                    <p
                        class="text-[13px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        {t(
                            'Share files in the flow of conversation and preview them immediately. Prevent "final" and "revised" versions from multiplying, and keep detailed adjustments in threads within the same channel.',
                        )}
                    </p>
                </div>
                <!-- 3 -->
                <div
                    class="group rounded-2xl border border-border bg-card p-6 transition hover:border-success/50 hover:bg-accent/50"
                >
                    <div
                        class="mb-4 flex size-11 items-center justify-center rounded-xl bg-success/15 text-success"
                    >
                        <ListTodo class="size-5" />
                    </div>
                    <div class="mb-1 text-[11px] font-bold text-success">
                        03
                    </div>
                    <h3 class="mb-2 text-[15px] font-bold text-card-foreground">
                        {t('Preserve who did what and why')}
                    </h3>
                    <p
                        class="text-[13px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        {t(
                            'Make deadlines and owners explicit through channel-based tasks, and clarify recipients with mentions. Automatically preserve a record of the reasoning that can be followed later.',
                        )}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── Features ─────────────────────────────────────── -->
    <section class="border-t border-border">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
            <div class="mb-12 max-w-2xl">
                <div
                    class="mb-2 text-xs font-bold tracking-widest text-brand-accent uppercase"
                >
                    {t('FEATURES')}
                </div>
                <h2 class="text-2xl font-bold text-foreground sm:text-3xl">
                    {t('Features that support unified work management')}
                </h2>
            </div>

            <div class="mb-12 flex flex-col gap-6">
                <figure
                    class="grid overflow-hidden rounded-2xl border border-border bg-card shadow-sm lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]"
                >
                    <img
                        src="/images/welcome/chat-screen.png"
                        alt={t(
                            'Example chat screen based on a fictional new product project',
                        )}
                        width="1667"
                        height="943"
                        loading="lazy"
                        decoding="async"
                        class="block aspect-video w-full object-cover object-top lg:border-r lg:border-border"
                    />
                    <figcaption
                        class="flex flex-col justify-center gap-3 border-t border-border p-6 lg:border-t-0 lg:p-8"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-brand/10 text-brand-accent"
                        >
                            <MessagesSquare class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-card-foreground">
                                {t('Chat view')}
                            </h3>
                            <p
                                class="mt-2 text-sm leading-relaxed font-medium text-card-foreground/80"
                            >
                                {t(
                                    'Review conversations, shared files, and task progress in one flow.',
                                )}
                            </p>
                        </div>
                    </figcaption>
                </figure>

                <figure
                    class="grid overflow-hidden rounded-2xl border border-border bg-card shadow-sm lg:grid-cols-[minmax(16rem,1fr)_minmax(0,2fr)]"
                >
                    <figcaption
                        class="flex flex-col justify-center gap-3 border-b border-border p-6 lg:border-r lg:border-b-0 lg:p-8"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-brand-pink/10 text-brand-pink"
                        >
                            <GanttChartSquare class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-card-foreground">
                                {t('Gantt chart view')}
                            </h3>
                            <p
                                class="mt-2 text-sm leading-relaxed font-medium text-card-foreground/80"
                            >
                                {t(
                                    'See work durations, progress, and dependencies on a single timeline.',
                                )}
                            </p>
                        </div>
                    </figcaption>
                    <img
                        src="/images/welcome/gantt-screen.png"
                        alt={t(
                            'Example Gantt chart screen based on a fictional new product project',
                        )}
                        width="1668"
                        height="943"
                        loading="lazy"
                        decoding="async"
                        class="block aspect-video w-full object-cover object-top"
                    />
                </figure>

                <figure
                    class="grid overflow-hidden rounded-2xl border border-border bg-card shadow-sm lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]"
                >
                    <img
                        src="/images/welcome/file-preview-screen.png"
                        alt={t(
                            'Example file preview screen showing a fictional proposal',
                        )}
                        width="1665"
                        height="945"
                        loading="lazy"
                        decoding="async"
                        class="block aspect-video w-full object-cover object-top lg:border-r lg:border-border"
                    />
                    <figcaption
                        class="flex flex-col justify-center gap-3 border-t border-border p-6 lg:border-t-0 lg:p-8"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-success/10 text-success"
                        >
                            <FileText class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-card-foreground">
                                {t('File preview view')}
                            </h3>
                            <p
                                class="mt-2 text-sm leading-relaxed font-medium text-card-foreground/80"
                            >
                                {t(
                                    'Review shared materials without leaving the conversation or downloading them.',
                                )}
                            </p>
                        </div>
                    </figcaption>
                </figure>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <MessagesSquare class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        {t('Threaded chat')}
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        {t(
                            'Keep the main conversation moving while organizing detailed discussions in threads. The conversation history stays in the channel.',
                        )}
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <ListTodo class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        {t('Channel = task')}
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        {t(
                            'Each channel is a work task. It contains start and end dates plus to-dos, so progress remains visible in the conversation.',
                        )}
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <GanttChartSquare class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        {t('Gantt chart')}
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        {t(
                            'Visualize each task duration and deadline on a timeline. See the workload and deadlines of the whole team at a glance.',
                        )}
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <FileText class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        {t('File preview')}
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        {t(
                            'Images, videos, and PDFs appear in place. Office documents preview in the browser with OnlyOffice, so downloading is unnecessary.',
                        )}
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <BellRing class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        {t('Deadline reminders')}
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        {t(
                            'Automatically notify the team as work deadlines approach. Leave sticky-note reminders behind and avoid missed deadlines.',
                        )}
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <ShieldCheck class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        {t('Permissions and mentions')}
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        {t(
                            'Members can view and post, while administrators manage projects. Mentions and tags make the intended recipient clear without locking information into a narrow circle.',
                        )}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── CTA ──────────────────────────────────────────── -->
    <section class="border-t border-border bg-muted/40">
        <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6 lg:py-24">
            <Zap class="mx-auto mb-5 size-10 text-brand-accent" />
            <h2 class="text-2xl font-bold text-foreground sm:text-3xl">
                {t('Start by turning phone messages')}
                <br class="hidden sm:block" />
                {t('into chat channels')}
            </h2>
            <p
                class="mx-auto mt-4 max-w-md text-sm leading-relaxed font-medium text-foreground/80"
            >
                {t(
                    'When in doubt, start with these three: keep information in one place, keep conversations, decisions, and files together, and preserve who did what and why. Chatterrow brings all of it into one screen.',
                )}
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                {#if auth.user}
                    <Link
                        href={toUrl(serversIndex())}
                        class="inline-flex items-center gap-2 rounded-lg bg-brand px-7 py-3 text-sm font-semibold text-brand-foreground shadow-lg shadow-brand/30 transition hover:bg-brand-hover"
                    >
                        {t('Open project list')}
                        <ArrowRight class="size-4" />
                    </Link>
                {:else}
                    <Link
                        href={toUrl(register())}
                        class="inline-flex items-center gap-2 rounded-lg bg-brand px-7 py-3 text-sm font-semibold text-brand-foreground shadow-lg shadow-brand/30 transition hover:bg-brand-hover"
                    >
                        {t('Create an account')}
                        <ArrowRight class="size-4" />
                    </Link>
                    <Link
                        href={toUrl(login())}
                        class="rounded-lg border border-border px-7 py-3 text-sm font-semibold text-foreground transition hover:bg-accent"
                    >
                        {t('Log in')}
                    </Link>
                {/if}
            </div>
        </div>
    </section>

    <!-- ── Footer ───────────────────────────────────────── -->
    <footer class="border-t border-border">
        <div
            class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-8 sm:flex-row sm:px-6"
        >
            <div class="flex items-center gap-2">
                <AppLogoIcon class="size-6 rounded-md" />
                <span class="text-xs font-medium text-foreground/80"
                    >{appName}</span
                >
            </div>
            <p class="text-[11px] font-medium text-foreground/80">
                {t('© 2026 chatterrow - A unified team workspace through chat')}
            </p>
        </div>
    </footer>
</div>
