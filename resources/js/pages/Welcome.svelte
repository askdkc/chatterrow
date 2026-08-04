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
    import { toUrl } from '@/lib/utils';
    import { login } from '@/routes';
    import { register } from '@/routes';
    import { index as serversIndex } from '@/routes/servers';

    const auth = $derived(page.props.auth);
</script>

<AppHead title="茶多楼 chatterrow" />

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
                        茶多楼
                    </div>
                    <div class="text-[11px] text-foreground/80">chatterrow</div>
                </div>
            </div>
            <nav class="flex items-center gap-2">
                {#if auth.user}
                    <Link
                        href={toUrl(serversIndex())}
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground transition hover:bg-brand-hover"
                    >
                        <Users class="size-4" />
                        プロジェクト一覧へ
                    </Link>
                {:else}
                    <Link
                        href={toUrl(login())}
                        class="rounded-lg px-4 py-2 text-sm font-medium text-foreground transition hover:bg-accent"
                    >
                        ログイン
                    </Link>
                    <Link
                        href={toUrl(register())}
                        class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground transition hover:bg-brand-hover"
                    >
                        無料で始める
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
                    チャットでつながる一元管理グループウェア
                </div>
                <h1
                    class="text-4xl leading-tight font-extrabold tracking-tight text-foreground sm:text-5xl"
                >
                    業務の「経緯」を
                    <span
                        class="bg-gradient-to-r from-brand-accent to-brand-pink bg-clip-text text-transparent"
                    >
                        ひとつの場
                    </span>
                    に集める
                </h1>
                <p
                    class="mt-5 max-w-md text-[15px] leading-relaxed font-medium text-foreground/80"
                >
                    メールの往復や散らばったファイル、個人チャットの行き来。
                    茶多楼は会話・決定・ファイル・タスクを同じチャネルに集約し、
                    「誰が・何を・なぜ」がいつでも追えるチームの土台を作ります。
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    {#if auth.user}
                        <Link
                            href={toUrl(serversIndex())}
                            class="inline-flex items-center gap-2 rounded-lg bg-brand px-6 py-3 text-sm font-semibold text-brand-foreground shadow-lg shadow-brand/30 transition hover:bg-brand-hover"
                        >
                            マイプロジェクトへ
                            <ArrowRight class="size-4" />
                        </Link>
                    {:else}
                        <Link
                            href={toUrl(register())}
                            class="inline-flex items-center gap-2 rounded-lg bg-brand px-6 py-3 text-sm font-semibold text-brand-foreground shadow-lg shadow-brand/30 transition hover:bg-brand-hover"
                        >
                            アカウントを作成
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            href={toUrl(login())}
                            class="rounded-lg border border-border px-6 py-3 text-sm font-semibold text-foreground transition hover:bg-accent"
                        >
                            ログイン
                        </Link>
                    {/if}
                </div>
                <div
                    class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-xs font-medium text-foreground/80"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <CheckCircle2 class="size-3.5 text-success" />
                        チャンネル = 業務 = タスク
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <CheckCircle2 class="size-3.5 text-success" />
                        期限とリマインダー
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <CheckCircle2 class="size-3.5 text-success" />
                        ファイルプレビュー
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
                                茶
                            </div>
                            <div class="h-8 w-0.5 rounded bg-border"></div>
                            <div
                                class="flex size-11 items-center justify-center rounded-2xl bg-background text-[13px] font-bold text-foreground transition hover:rounded-lg hover:bg-brand hover:text-brand-foreground"
                            >
                                総
                            </div>
                            <div
                                class="flex size-11 items-center justify-center rounded-2xl bg-background text-[13px] font-bold text-foreground"
                            >
                                企
                            </div>
                        </div>
                        <!-- channel list -->
                        <div class="w-52 shrink-0 bg-secondary/70 p-3">
                            <div
                                class="mb-3 text-[11px] font-bold tracking-wide text-foreground"
                            >
                                総務部
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground/80"
                            >
                                <Hash class="size-3.5" />
                                全体連絡
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 rounded bg-accent px-1 py-0.5 text-xs font-medium text-accent-foreground"
                            >
                                <Hash class="size-3.5" />
                                採用スケジュール
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
                                稟議・決裁
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground/80"
                            >
                                <Hash class="size-3.5" />
                                雑談
                            </div>
                            <div
                                class="mt-4 mb-2 text-[11px] font-bold tracking-wide text-foreground"
                            >
                                タスク
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
                                            一次面接の日程調整
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
                                            求人票の更新
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
                                採用スケジュール
                            </div>
                            <div
                                class="flex flex-1 flex-col gap-3 overflow-hidden p-4"
                            >
                                <div class="flex gap-2.5">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-pink-solid text-xs font-bold text-brand-foreground"
                                    >
                                        佐
                                    </div>
                                    <div>
                                        <div
                                            class="flex items-center gap-2 text-xs"
                                        >
                                            <span
                                                class="font-semibold text-card-foreground"
                                                >佐久間</span
                                            >
                                            <span
                                                class="text-[10px] font-medium text-foreground/80"
                                                >10:24</span
                                            >
                                        </div>
                                        <p
                                            class="mt-0.5 text-[13px] text-card-foreground"
                                        >
                                            面接日程、火曜に変更しました
                                            <span class="text-brand-accent">
                                                @採用チーム</span
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
                                                >面接スケジュール.xlsx</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2.5">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-brand-foreground"
                                    >
                                        高
                                    </div>
                                    <div>
                                        <div
                                            class="flex items-center gap-2 text-xs"
                                        >
                                            <span
                                                class="font-semibold text-card-foreground"
                                                >高橋</span
                                            >
                                            <span
                                                class="text-[10px] font-medium text-foreground/80"
                                                >10:31</span
                                            >
                                        </div>
                                        <p
                                            class="mt-0.5 text-[13px] text-card-foreground"
                                        >
                                            確認しました。候補者への連絡は明日までに送ります。
                                        </p>
                                        <div
                                            class="mt-1.5 inline-flex items-center gap-1 rounded bg-brand/15 px-1.5 py-0.5 text-[10px] text-brand-accent"
                                        >
                                            <CheckCircle2 class="size-3" />
                                            タスク「一次面接の日程調整」完了
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
                                        期限リマインダー
                                    </div>
                                    <p
                                        class="mt-1 text-[11px] font-medium text-foreground/80"
                                    >
                                        「求人票の更新」の期限は 8/5（水）です
                                    </p>
                                </div>
                            </div>
                            <div class="border-t border-border p-3">
                                <div
                                    class="rounded-lg bg-muted px-3 py-2.5 text-[13px] font-medium text-foreground/80"
                                >
                                    メッセージを送信
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

    <!-- ── 3つの考え方（記事の思想を反映） ─────────────── -->
    <section class="border-t border-border bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
            <div class="mb-12 max-w-2xl">
                <div
                    class="mb-2 text-xs font-bold tracking-widest text-brand-accent uppercase"
                >
                    WHY CHATTERROW
                </div>
                <h2 class="text-2xl font-bold text-foreground sm:text-3xl">
                    チャットは「連絡の場」から<br class="hidden sm:block" />
                    「業務の経緯を共有する場」へ
                </h2>
                <p
                    class="mt-4 text-sm leading-relaxed font-medium text-foreground/80"
                >
                    情報が分散すると、業務の効率は下がり、経緯は失われます。
                    茶多楼は、チームの知識を組織の財産にするための3つの原則を
                    機能として組み込んでいます。
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
                        情報を1か所に集める
                    </h3>
                    <p
                        class="text-[13px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        プロジェクトを組織・業務単位で作り、チャンネルを業務テーマ単位で運用。
                        相談・進捗・資料が「業務Aチャネル」に集約され、担当者チャットで
                        完結するもったいない運用を防ぎます。
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
                        会話・決定・ファイルを同じ場に
                    </h3>
                    <p
                        class="text-[13px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        会話の流れの中でファイルを共有し、その場でプレビュー。
                        「最終版」「修正版」の増殖を防ぎ、スレッドで細かい調整も
                        同じチャネル内に完結させます。
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
                        「誰が・何を・なぜ」を残す
                    </h3>
                    <p
                        class="text-[13px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        チャンネル＝タスクとして期限・担当者を明示し、
                        メンションで宛先を明確に。後から見ても判断の理由が
                        追える記録を自動的に残します。
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── 機能 ─────────────────────────────────────────── -->
    <section class="border-t border-border">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
            <div class="mb-12 max-w-2xl">
                <div
                    class="mb-2 text-xs font-bold tracking-widest text-brand-accent uppercase"
                >
                    FEATURES
                </div>
                <h2 class="text-2xl font-bold text-foreground sm:text-3xl">
                    一元管理を支える機能
                </h2>
            </div>

            <div class="mb-12 flex flex-col gap-6">
                <figure
                    class="grid overflow-hidden rounded-2xl border border-border bg-card shadow-sm lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]"
                >
                    <img
                        src="/images/welcome/chat-screen.png"
                        alt="架空の新商品プロジェクトを題材にしたチャット画面のサンプル"
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
                                チャット画面
                            </h3>
                            <p
                                class="mt-2 text-sm leading-relaxed font-medium text-card-foreground/80"
                            >
                                会話、共有ファイル、タスクの進捗を同じ流れで確認できます。
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
                                ガントチャート画面
                            </h3>
                            <p
                                class="mt-2 text-sm leading-relaxed font-medium text-card-foreground/80"
                            >
                                業務の期間、進捗、依存関係をひとつの時間軸で把握できます。
                            </p>
                        </div>
                    </figcaption>
                    <img
                        src="/images/welcome/gantt-screen.png"
                        alt="架空の新商品プロジェクトを題材にしたガントチャート画面のサンプル"
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
                        alt="架空の提案資料を表示したファイルプレビュー画面のサンプル"
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
                                ファイルプレビュー画面
                            </h3>
                            <p
                                class="mt-2 text-sm leading-relaxed font-medium text-card-foreground/80"
                            >
                                会話を離れず、共有された資料をダウンロードせずに確認できます。
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
                        スレッド化チャット
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        本題の流れを遮らず、細かい議論はスレッドで整理。
                        会話の経緯がチャネルに残ります。
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <ListTodo class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        チャンネル = タスク
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        チャンネル自体が業務タスク。開始日・終了期限と ToDo
                        を内包し、進捗が会話から見えます。
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <GanttChartSquare class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        ガントチャート
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        全業務の期間・期限をタイムラインで可視化。
                        チーム全体の負荷と締切をひと目で把握。
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <FileText class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        ファイルプレビュー
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        画像・動画・PDFはその場で表示。Office 文書は OnlyOffice
                        でブラウザ内プレビュー、ダウンロード不要。
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <BellRing class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        期限リマインダー
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        期限が近づいた業務を自動通知。
                        伝言メモの付箋から卒業して、締切の見落としを防ぎます。
                    </p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-muted"
                    >
                        <ShieldCheck class="size-4.5 text-brand-accent" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-card-foreground">
                        権限とメンション
                    </h3>
                    <p
                        class="text-[12px] leading-relaxed font-medium text-card-foreground/80"
                    >
                        メンバーは閲覧・投稿、管理者はプロジェクトを管理。
                        メンションとタグで「誰宛か」を明確にし、
                        限定的な範囲に情報を閉じません。
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
                まずは、電話の伝言メモを
                <br class="hidden sm:block" />
                チャットのチャネルに変えるだけでも
            </h2>
            <p
                class="mx-auto mt-4 max-w-md text-sm leading-relaxed font-medium text-foreground/80"
            >
                迷ったらこの3点。 情報を1か所に / 会話・決定・ファイルを同じ場に
                / 誰が・何を・なぜを残す。
                茶多楼はそのすべてをひとつの画面にしています。
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                {#if auth.user}
                    <Link
                        href={toUrl(serversIndex())}
                        class="inline-flex items-center gap-2 rounded-lg bg-brand px-7 py-3 text-sm font-semibold text-brand-foreground shadow-lg shadow-brand/30 transition hover:bg-brand-hover"
                    >
                        プロジェクト一覧を開く
                        <ArrowRight class="size-4" />
                    </Link>
                {:else}
                    <Link
                        href={toUrl(register())}
                        class="inline-flex items-center gap-2 rounded-lg bg-brand px-7 py-3 text-sm font-semibold text-brand-foreground shadow-lg shadow-brand/30 transition hover:bg-brand-hover"
                    >
                        アカウントを作成
                        <ArrowRight class="size-4" />
                    </Link>
                    <Link
                        href={toUrl(login())}
                        class="rounded-lg border border-border px-7 py-3 text-sm font-semibold text-foreground transition hover:bg-accent"
                    >
                        ログイン
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
                    >茶多楼 chatterrow</span
                >
            </div>
            <p class="text-[11px] font-medium text-foreground/80">
                © 2026 chatterrow — チャットを通じた一元管理グループウェア
            </p>
        </div>
    </footer>
</div>
