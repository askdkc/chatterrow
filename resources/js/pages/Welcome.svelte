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
    import { toUrl } from '@/lib/utils';
    import { login } from '@/routes';
    import { register } from '@/routes';
    import { index as serversIndex } from '@/routes/servers';

    const auth = $derived(page.props.auth);
</script>

<AppHead title="茶多楼 chatterrow" />

<div class="min-h-screen bg-[#1e1f22] text-[#dbdee1]">
    <!-- ── Header ─────────────────────────────────────────── -->
    <header
        class="sticky top-0 z-20 border-b border-black/40 bg-[#1e1f22]/90 backdrop-blur"
    >
        <div
            class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#5865f2] shadow-lg shadow-[#5865f2]/30"
                >
                    <MessagesSquare class="h-5 w-5 text-white" />
                </div>
                <div class="leading-tight">
                    <div class="text-[15px] font-bold tracking-wide text-white">
                        茶多楼
                    </div>
                    <div class="text-[11px] text-[#80848e]">chatterrow</div>
                </div>
            </div>
            <nav class="flex items-center gap-2">
                {#if auth.user}
                    <Link
                        href={toUrl(serversIndex())}
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#5865f2] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#4752c4]"
                    >
                        <Users class="h-4 w-4" />
                        サーバー一覧へ
                    </Link>
                {:else}
                    <Link
                        href={toUrl(login())}
                        class="rounded-lg px-4 py-2 text-sm font-medium text-[#dbdee1] transition hover:bg-white/5"
                    >
                        ログイン
                    </Link>
                    <Link
                        href={toUrl(register())}
                        class="rounded-lg bg-[#5865f2] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#4752c4]"
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
            class="pointer-events-none absolute -top-40 left-1/2 h-[480px] w-[900px] -translate-x-1/2 rounded-full bg-[#5865f2]/15 blur-3xl"
        ></div>
        <div
            class="pointer-events-none absolute top-40 right-0 h-72 w-72 rounded-full bg-[#eb459e]/10 blur-3xl"
        ></div>

        <div
            class="relative mx-auto grid max-w-6xl gap-12 px-4 pt-16 pb-20 sm:px-6 lg:grid-cols-2 lg:items-center lg:pt-24 lg:pb-28"
        >
            <div>
                <div
                    class="mb-5 inline-flex items-center gap-2 rounded-full border border-[#5865f2]/40 bg-[#5865f2]/10 px-3 py-1 text-xs font-medium text-[#8b95f7]"
                >
                    <Sparkles class="h-3.5 w-3.5" />
                    チャットでつながる一元管理グループウェア
                </div>
                <h1
                    class="text-4xl leading-tight font-extrabold tracking-tight text-white sm:text-5xl"
                >
                    業務の「経緯」を
                    <span
                        class="bg-gradient-to-r from-[#8b95f7] to-[#eb459e] bg-clip-text text-transparent"
                    >
                        ひとつの場
                    </span>
                    に集める
                </h1>
                <p
                    class="mt-5 max-w-md text-[15px] leading-relaxed text-[#b5bac1]"
                >
                    メールの往復や散らばったファイル、個人チャットの行き来。
                    チャッターロウは会話・決定・ファイル・タスクを同じチャネルに集約し、
                    「誰が・何を・なぜ」がいつでも追えるチームの土台を作ります。
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    {#if auth.user}
                        <Link
                            href={toUrl(serversIndex())}
                            class="inline-flex items-center gap-2 rounded-lg bg-[#5865f2] px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-[#5865f2]/30 transition hover:bg-[#4752c4]"
                        >
                            マイサーバーへ
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    {:else}
                        <Link
                            href={toUrl(register())}
                            class="inline-flex items-center gap-2 rounded-lg bg-[#5865f2] px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-[#5865f2]/30 transition hover:bg-[#4752c4]"
                        >
                            アカウントを作成
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <Link
                            href={toUrl(login())}
                            class="rounded-lg border border-white/10 px-6 py-3 text-sm font-semibold text-[#dbdee1] transition hover:bg-white/5"
                        >
                            ログイン
                        </Link>
                    {/if}
                </div>
                <div
                    class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-xs text-[#80848e]"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <CheckCircle2 class="h-3.5 w-3.5 text-[#23a559]" />
                        チャンネル = 業務 = タスク
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <CheckCircle2 class="h-3.5 w-3.5 text-[#23a559]" />
                        期限とリマインダー
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <CheckCircle2 class="h-3.5 w-3.5 text-[#23a559]" />
                        ファイルプレビュー
                    </span>
                </div>
            </div>

            <!-- mock UI -->
            <div class="relative">
                <div
                    class="overflow-hidden rounded-2xl border border-white/10 bg-[#313338] shadow-2xl shadow-black/50"
                >
                    <div class="flex h-[380px]">
                        <!-- rail -->
                        <div
                            class="flex w-16 flex-col items-center gap-2 bg-[#1e1f22] py-3"
                        >
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#5865f2] text-[13px] font-bold text-white"
                            >
                                茶
                            </div>
                            <div class="h-8 w-0.5 rounded bg-white/10"></div>
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#2b2d31] text-[13px] font-bold text-[#dbdee1] transition hover:rounded-lg hover:bg-[#5865f2]"
                            >
                                総
                            </div>
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#2b2d31] text-[13px] font-bold text-[#dbdee1]"
                            >
                                企
                            </div>
                        </div>
                        <!-- channel list -->
                        <div class="w-52 shrink-0 bg-[#2b2d31] p-3">
                            <div
                                class="mb-3 text-[11px] font-bold tracking-wide text-white/90"
                            >
                                総務部
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 text-xs font-medium text-[#80848e]"
                            >
                                <Hash class="h-3.5 w-3.5" />
                                全体連絡
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 rounded bg-[#404249] px-1 py-0.5 text-xs font-medium text-white"
                            >
                                <Hash class="h-3.5 w-3.5" />
                                採用スケジュール
                                <span
                                    class="ml-auto rounded-full bg-[#f23f43] px-1.5 text-[9px] font-bold text-white"
                                >
                                    2
                                </span>
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 text-xs font-medium text-[#80848e]"
                            >
                                <Hash class="h-3.5 w-3.5" />
                                稟議・決裁
                            </div>
                            <div
                                class="mb-2 flex items-center gap-1.5 text-xs font-medium text-[#80848e]"
                            >
                                <Hash class="h-3.5 w-3.5" />
                                雑談
                            </div>
                            <div
                                class="mt-4 mb-2 text-[11px] font-bold tracking-wide text-white/90"
                            >
                                タスク
                            </div>
                            <div class="space-y-1.5">
                                <div class="rounded-md bg-[#1e1f22] p-2">
                                    <div
                                        class="mb-1.5 flex items-center gap-1.5"
                                    >
                                        <CheckCircle2
                                            class="h-3 w-3 text-[#23a559]"
                                        />
                                        <span
                                            class="text-[10px] text-[#b5bac1]"
                                        >
                                            一次面接の日程調整
                                        </span>
                                    </div>
                                    <div
                                        class="ml-4 h-1 w-full rounded bg-[#2b2d31]"
                                    >
                                        <div
                                            class="h-1 w-3/4 rounded bg-[#23a559]"
                                        ></div>
                                    </div>
                                </div>
                                <div class="rounded-md bg-[#1e1f22] p-2">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="h-3 w-3 rounded-full border-2 border-[#80848e]"
                                        ></span>
                                        <span
                                            class="text-[10px] text-[#b5bac1]"
                                        >
                                            求人票の更新
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- messages -->
                        <div class="flex min-w-0 flex-1 flex-col bg-[#313338]">
                            <div
                                class="flex items-center gap-1.5 border-b border-black/20 px-4 py-2.5 text-sm font-bold text-white"
                            >
                                <Hash class="h-4 w-4 text-[#80848e]" />
                                採用スケジュール
                            </div>
                            <div class="flex-1 space-y-3 overflow-hidden p-4">
                                <div class="flex gap-2.5">
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#eb459e] text-xs font-bold text-white"
                                    >
                                        佐
                                    </div>
                                    <div>
                                        <div
                                            class="flex items-center gap-2 text-xs"
                                        >
                                            <span
                                                class="font-semibold text-white"
                                                >佐久間</span
                                            >
                                            <span
                                                class="text-[10px] text-[#80848e]"
                                                >10:24</span
                                            >
                                        </div>
                                        <p
                                            class="mt-0.5 text-[13px] text-[#dbdee1]"
                                        >
                                            面接日程、火曜に変更しました
                                            <span class="text-[#5865f2]">
                                                @採用チーム</span
                                            >
                                        </p>
                                        <div
                                            class="mt-1.5 flex items-center gap-1.5 rounded-lg bg-[#2b2d31] px-2.5 py-1.5"
                                        >
                                            <FileText
                                                class="h-3.5 w-3.5 text-[#5865f2]"
                                            />
                                            <span
                                                class="text-[10px] text-[#b5bac1]"
                                                >面接スケジュール.xlsx</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2.5">
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#5865f2] text-xs font-bold text-white"
                                    >
                                        高
                                    </div>
                                    <div>
                                        <div
                                            class="flex items-center gap-2 text-xs"
                                        >
                                            <span
                                                class="font-semibold text-white"
                                                >高橋</span
                                            >
                                            <span
                                                class="text-[10px] text-[#80848e]"
                                                >10:31</span
                                            >
                                        </div>
                                        <p
                                            class="mt-0.5 text-[13px] text-[#dbdee1]"
                                        >
                                            確認しました。候補者への連絡は明日までに送ります。
                                        </p>
                                        <div
                                            class="mt-1.5 inline-flex items-center gap-1 rounded bg-[#5865f2]/15 px-1.5 py-0.5 text-[10px] text-[#8b95f7]"
                                        >
                                            <CheckCircle2 class="h-3 w-3" />
                                            タスク「一次面接の日程調整」完了
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="rounded-lg border border-[#5865f2]/30 bg-[#5865f2]/10 p-2.5"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-[11px] font-semibold text-[#8b95f7]"
                                    >
                                        <BellRing class="h-3.5 w-3.5" />
                                        期限リマインダー
                                    </div>
                                    <p class="mt-1 text-[11px] text-[#b5bac1]">
                                        「求人票の更新」の期限は 8/5（水）です
                                    </p>
                                </div>
                            </div>
                            <div class="border-t border-black/20 p-3">
                                <div
                                    class="rounded-lg bg-[#383a40] px-3 py-2.5 text-[13px] text-[#80848e]"
                                >
                                    メッセージを送信
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="pointer-events-none absolute -bottom-6 -left-6 -z-10 h-40 w-40 rounded-2xl bg-[#eb459e]/10 blur-2xl"
                ></div>
            </div>
        </div>
    </section>

    <!-- ── 3つの考え方（記事の思想を反映） ─────────────── -->
    <section class="border-t border-white/5 bg-[#2b2d31]/60">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
            <div class="mb-12 max-w-2xl">
                <div
                    class="mb-2 text-xs font-bold tracking-widest text-[#8b95f7] uppercase"
                >
                    WHY CHATTERROW
                </div>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">
                    チャットは「連絡の場」から<br class="hidden sm:block" />
                    「業務の経緯を共有する場」へ
                </h2>
                <p class="mt-4 text-sm leading-relaxed text-[#b5bac1]">
                    情報が分散すると、業務の効率は下がり、経緯は失われます。
                    チャッターロウは、チームの知識を組織の財産にするための3つの原則を
                    機能として組み込んでいます。
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <!-- 1 -->
                <div
                    class="group rounded-2xl border border-white/10 bg-[#1e1f22] p-6 transition hover:border-[#5865f2]/50 hover:bg-[#1e1f22]/80"
                >
                    <div
                        class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-[#5865f2]/15 text-[#8b95f7]"
                    >
                        <FolderOpen class="h-5 w-5" />
                    </div>
                    <div class="mb-1 text-[11px] font-bold text-[#8b95f7]">
                        01
                    </div>
                    <h3 class="mb-2 text-[15px] font-bold text-white">
                        情報を1か所に集める
                    </h3>
                    <p class="text-[13px] leading-relaxed text-[#b5bac1]">
                        サーバーを組織・プロジェクト単位で作り、チャンネルを業務テーマ単位で運用。
                        相談・進捗・資料が「業務Aチャネル」に集約され、担当者チャットで
                        完結するもったいない運用を防ぎます。
                    </p>
                </div>
                <!-- 2 -->
                <div
                    class="group rounded-2xl border border-white/10 bg-[#1e1f22] p-6 transition hover:border-[#eb459e]/50 hover:bg-[#1e1f22]/80"
                >
                    <div
                        class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-[#eb459e]/15 text-[#f47eb8]"
                    >
                        <MessageSquareText class="h-5 w-5" />
                    </div>
                    <div class="mb-1 text-[11px] font-bold text-[#f47eb8]">
                        02
                    </div>
                    <h3 class="mb-2 text-[15px] font-bold text-white">
                        会話・決定・ファイルを同じ場に
                    </h3>
                    <p class="text-[13px] leading-relaxed text-[#b5bac1]">
                        会話の流れの中でファイルを共有し、その場でプレビュー。
                        「最終版」「修正版」の増殖を防ぎ、スレッドで細かい調整も
                        同じチャネル内に完結させます。
                    </p>
                </div>
                <!-- 3 -->
                <div
                    class="group rounded-2xl border border-white/10 bg-[#1e1f22] p-6 transition hover:border-[#23a559]/50 hover:bg-[#1e1f22]/80"
                >
                    <div
                        class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-[#23a559]/15 text-[#23a559]"
                    >
                        <ListTodo class="h-5 w-5" />
                    </div>
                    <div class="mb-1 text-[11px] font-bold text-[#23a559]">
                        03
                    </div>
                    <h3 class="mb-2 text-[15px] font-bold text-white">
                        「誰が・何を・なぜ」を残す
                    </h3>
                    <p class="text-[13px] leading-relaxed text-[#b5bac1]">
                        チャンネル＝タスクとして期限・担当者を明示し、
                        メンションで宛先を明確に。後から見ても判断の理由が
                        追える記録を自動的に残します。
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── 機能 ─────────────────────────────────────────── -->
    <section class="border-t border-white/5">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
            <div class="mb-12 max-w-2xl">
                <div
                    class="mb-2 text-xs font-bold tracking-widest text-[#8b95f7] uppercase"
                >
                    FEATURES
                </div>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">
                    一元管理を支える機能
                </h2>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    class="rounded-2xl border border-white/10 bg-[#2b2d31]/40 p-6"
                >
                    <div
                        class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-white/5"
                    >
                        <MessagesSquare class="h-4.5 w-4.5 text-[#8b95f7]" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-white">
                        スレッド化チャット
                    </h3>
                    <p class="text-[12px] leading-relaxed text-[#b5bac1]">
                        本題の流れを遮らず、細かい議論はスレッドで整理。
                        会話の経緯がチャネルに残ります。
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-[#2b2d31]/40 p-6"
                >
                    <div
                        class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-white/5"
                    >
                        <ListTodo class="h-4.5 w-4.5 text-[#8b95f7]" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-white">
                        チャンネル = タスク
                    </h3>
                    <p class="text-[12px] leading-relaxed text-[#b5bac1]">
                        チャンネル自体が業務タスク。開始日・終了期限と ToDo
                        を内包し、進捗が会話から見えます。
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-[#2b2d31]/40 p-6"
                >
                    <div
                        class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-white/5"
                    >
                        <GanttChartSquare class="h-4.5 w-4.5 text-[#8b95f7]" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-white">
                        ガントチャート
                    </h3>
                    <p class="text-[12px] leading-relaxed text-[#b5bac1]">
                        全業務の期間・期限をタイムラインで可視化。
                        チーム全体の負荷と締切をひと目で把握。
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-[#2b2d31]/40 p-6"
                >
                    <div
                        class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-white/5"
                    >
                        <FileText class="h-4.5 w-4.5 text-[#8b95f7]" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-white">
                        ファイルプレビュー
                    </h3>
                    <p class="text-[12px] leading-relaxed text-[#b5bac1]">
                        画像・動画・PDFはその場で表示。Office 文書は OnlyOffice
                        でブラウザ内プレビュー、ダウンロード不要。
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-[#2b2d31]/40 p-6"
                >
                    <div
                        class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-white/5"
                    >
                        <BellRing class="h-4.5 w-4.5 text-[#8b95f7]" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-white">
                        期限リマインダー
                    </h3>
                    <p class="text-[12px] leading-relaxed text-[#b5bac1]">
                        期限が近づいた業務を自動通知。
                        伝言メモの付箋から卒業して、締切の見落としを防ぎます。
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-[#2b2d31]/40 p-6"
                >
                    <div
                        class="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-white/5"
                    >
                        <ShieldCheck class="h-4.5 w-4.5 text-[#8b95f7]" />
                    </div>
                    <h3 class="mb-1.5 text-sm font-bold text-white">
                        権限とメンション
                    </h3>
                    <p class="text-[12px] leading-relaxed text-[#b5bac1]">
                        メンバーは閲覧・投稿、作成者は管理。
                        メンションとタグで「誰宛か」を明確にし、
                        限定的な範囲に情報を閉じません。
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── CTA ──────────────────────────────────────────── -->
    <section class="border-t border-white/5 bg-[#2b2d31]/60">
        <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6 lg:py-24">
            <Zap class="mx-auto mb-5 h-10 w-10 text-[#8b95f7]" />
            <h2 class="text-2xl font-bold text-white sm:text-3xl">
                まずは、電話の伝言メモを
                <br class="hidden sm:block" />
                チャットのチャネルに変えるだけでも
            </h2>
            <p
                class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-[#b5bac1]"
            >
                迷ったらこの3点。 情報を1か所に / 会話・決定・ファイルを同じ場に
                / 誰が・何を・なぜを残す。
                チャッターロウはそのすべてをひとつの画面にしています。
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                {#if auth.user}
                    <Link
                        href={toUrl(serversIndex())}
                        class="inline-flex items-center gap-2 rounded-lg bg-[#5865f2] px-7 py-3 text-sm font-semibold text-white shadow-lg shadow-[#5865f2]/30 transition hover:bg-[#4752c4]"
                    >
                        サーバー一覧を開く
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                {:else}
                    <Link
                        href={toUrl(register())}
                        class="inline-flex items-center gap-2 rounded-lg bg-[#5865f2] px-7 py-3 text-sm font-semibold text-white shadow-lg shadow-[#5865f2]/30 transition hover:bg-[#4752c4]"
                    >
                        アカウントを作成
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                    <Link
                        href={toUrl(login())}
                        class="rounded-lg border border-white/10 px-7 py-3 text-sm font-semibold text-[#dbdee1] transition hover:bg-white/5"
                    >
                        ログイン
                    </Link>
                {/if}
            </div>
        </div>
    </section>

    <!-- ── Footer ───────────────────────────────────────── -->
    <footer class="border-t border-white/5">
        <div
            class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-8 sm:flex-row sm:px-6"
        >
            <div class="flex items-center gap-2">
                <div
                    class="flex h-6 w-6 items-center justify-center rounded-md bg-[#5865f2]"
                >
                    <MessagesSquare class="h-3.5 w-3.5 text-white" />
                </div>
                <span class="text-xs text-[#80848e]">茶多楼 chatterrow</span>
            </div>
            <p class="text-[11px] text-[#80848e]">
                © 2026 chatterrow — チャットを通じた一元管理グループウェア
            </p>
        </div>
    </footer>
</div>
