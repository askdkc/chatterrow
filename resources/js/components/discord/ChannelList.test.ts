import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ChannelList from './ChannelList.svelte';

afterEach(cleanup);

describe('ChannelList', () => {
    it('hides the add button when channel creation is unavailable', () => {
        render(ChannelList, {
            props: {
                server: {
                    id: 1,
                    name: 'Test Server',
                    description: null,
                    starts_on: null,
                    ends_on: null,
                    created_by: 1,
                },
                channels: [],
                members: [],
                activeChannelId: null,
                onManageMembers: vi.fn(),
            },
        });

        expect(
            screen.queryByRole('button', { name: 'チャンネルを作成' }),
        ).toBeNull();
    });

    it('opens channel settings without navigating to the channel', async () => {
        const onEditChannel = vi.fn();
        const channel = {
            id: 2,
            server_id: 1,
            name: '実装',
            description: null,
            starts_on: null,
            ends_on: null,
            created_by: 1,
        };

        render(ChannelList, {
            props: {
                server: {
                    id: 1,
                    name: 'Test Server',
                    description: null,
                    starts_on: null,
                    ends_on: null,
                    created_by: 1,
                },
                channels: [channel],
                members: [],
                activeChannelId: 2,
                onAddChannel: vi.fn(),
                onEditChannel,
                onManageMembers: vi.fn(),
            },
        });

        const channelLink = screen.getByRole('link', { name: '実装' });
        const settingsButton = screen.getByRole('button', {
            name: '実装の設定',
        });

        expect(channelLink.classList.contains('group-hover:pr-8')).toBe(true);
        expect(settingsButton.classList.contains('absolute')).toBe(true);

        await fireEvent.click(settingsButton);

        expect(onEditChannel).toHaveBeenCalledWith(channel);
    });
});
