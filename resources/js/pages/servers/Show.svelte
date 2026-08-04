<script lang="ts">
    import { router, usePage } from '@inertiajs/svelte';
    import {
        Plus,
        Users,
        CalendarRange,
        ListTodo,
        FileText,
    } from 'lucide-svelte';
    import { onMount } from 'svelte';
    import ChannelDialog from '@/components/discord/ChannelDialog.svelte';
    import ChannelList from '@/components/discord/ChannelList.svelte';
    import MemberDialog from '@/components/discord/MemberDialog.svelte';
    import ProjectIcon from '@/components/discord/ProjectIcon.svelte';
    import ServerDialog from '@/components/discord/ServerDialog.svelte';
    import ServerRail from '@/components/discord/ServerRail.svelte';
    import { isProjectAdministrator } from '@/lib/project-permissions';
    import type {
        ServerResource,
        ChannelResource,
        UserResource,
    } from '@/types';

    let {
        server,
        members,
    }: {
        server: ServerResource;
        members: UserResource[];
    } = $props();

    const page = usePage();

    const authServers: ServerResource[] = $derived(
        page.props.auth?.servers ?? [],
    );

    let showChannelDialog = $state(false);
    let showMemberDialog = $state(false);
    let showServerDialog = $state(false);

    const channels: ChannelResource[] = $derived(server.channels ?? []);

    onMount(() => {
        // Auto-enter the first channel, like Discord opens the default channel.
        if (channels.length > 0) {
            window.location.href = `/servers/${server.id}/channels/${channels[0].id}`;
        }
    });

    function onAddServer() {
        showServerDialog = true;
    }

    function onBrowse() {
        router.visit('/servers');
    }
</script>

<div class="flex h-screen w-full overflow-hidden bg-[#313338] text-[#dbdee1]">
    <ServerRail
        servers={authServers}
        activeServerId={server.id}
        {onAddServer}
        {onBrowse}
    />

    <ChannelList
        {server}
        {channels}
        {members}
        activeChannelId={null}
        onAddChannel={() => (showChannelDialog = true)}
        onManageMembers={() => (showMemberDialog = true)}
    />

    <main class="flex min-w-0 flex-1 flex-col items-center justify-center p-8">
        <div class="text-center">
            <ProjectIcon {server} size="hero" class="mx-auto mb-4" />
            <h1 class="text-xl font-bold text-[#dbdee1]">{server.name}</h1>
            {#if server.description}
                <p class="mt-2 max-w-md text-sm text-[#80848e]">
                    {server.description}
                </p>
            {/if}
            <div
                class="mt-4 flex flex-wrap items-center justify-center gap-4 text-xs text-[#80848e]"
            >
                <span class="flex items-center gap-1">
                    <Users class="h-3.5 w-3.5" />
                    メンバー {members.length} 人
                </span>
                {#if server.starts_on || server.ends_on}
                    <span class="flex items-center gap-1">
                        <CalendarRange class="h-3.5 w-3.5" />
                        {server.starts_on ?? '開始日未定'} 〜 {server.ends_on ??
                            '期限未定'}
                    </span>
                {/if}
            </div>

            {#if channels.length === 0}
                <div
                    class="mx-auto mt-8 max-w-sm rounded-xl bg-[#2b2d31] p-6 text-left"
                >
                    <h2 class="mb-1 font-semibold">
                        最初のチャンネルを作成しましょう
                    </h2>
                    <p class="text-sm text-[#80848e]">
                        チャンネルはタスクとしても機能します。開始日と終了期限を設定できます。
                    </p>
                    <button
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-md bg-[#5865f2] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#4752c4]"
                        onclick={() => (showChannelDialog = true)}
                    >
                        <Plus class="h-4 w-4" />
                        チャンネルを作成
                    </button>
                </div>
            {/if}

            <div class="mt-8 flex items-center justify-center gap-3">
                <a
                    href={`/servers/${server.id}/tasks`}
                    class="flex items-center gap-2 rounded-md bg-[#2b2d31] px-4 py-2 text-sm font-medium transition hover:bg-[#383a40]"
                >
                    <ListTodo class="h-4 w-4" />
                    タスク一覧
                </a>
                <a
                    href={`/servers/${server.id}/gantt`}
                    class="flex items-center gap-2 rounded-md bg-[#2b2d31] px-4 py-2 text-sm font-medium transition hover:bg-[#383a40]"
                >
                    <CalendarRange class="h-4 w-4" />
                    ガントチャート
                </a>
                <a
                    href={`/servers/${server.id}/files`}
                    class="flex items-center gap-2 rounded-md bg-[#2b2d31] px-4 py-2 text-sm font-medium transition hover:bg-[#383a40]"
                >
                    <FileText class="h-4 w-4" />
                    ファイル
                </a>
            </div>
        </div>
    </main>
</div>

{#if showChannelDialog}
    <ChannelDialog {server} onClose={() => (showChannelDialog = false)} />
{/if}

{#if showMemberDialog}
    <MemberDialog
        {server}
        {members}
        canManage={isProjectAdministrator(
            server,
            members,
            page.props.auth?.user?.id,
        )}
        onUpdated={(updated) => (server = { ...server, ...updated })}
        onMembersUpdated={(updated) => (members = updated)}
        onClose={() => (showMemberDialog = false)}
    />
{/if}

{#if showServerDialog}
    <ServerDialog onClose={() => (showServerDialog = false)} />
{/if}
