export interface ServerResource {
    id: number;
    name: string;
    description: string | null;
    icon_url?: string | null;
    starts_on: string | null;
    ends_on: string | null;
    archived_at?: string | null;
    project_folder_id?: number | null;
    created_by: number | null;
    created_at?: string;
    channels_count?: number;
    members_count?: number;
    channels?: ChannelResource[];
    members?: UserResource[];
}

export interface ProjectFolderResource {
    id: number;
    name: string;
    color?: string | null;
    icon_url?: string | null;
    position: number;
}

export type ProjectInvitationStatus = 'pending' | 'accepted' | 'declined';

export interface ProjectInvitationResource {
    id: number;
    email: string;
    status: ProjectInvitationStatus;
    registered: boolean;
    sent_at: string | null;
    responded_at: string | null;
    user?: UserResource | null;
    server?: Pick<ServerResource, 'id' | 'created_by' | 'name' | 'description'>;
    inviter?: UserResource | null;
}

export interface ChannelResource {
    id: number;
    server_id: number;
    name: string;
    description: string | null;
    starts_on: string | null;
    ends_on: string | null;
    created_by: number | null;
    created_at?: string;
    open_todos_count?: number;
    todos_count?: number;
    todos?: TodoResource[];
}

export type ChannelSummaryResource = Pick<ChannelResource, 'id' | 'name'>;

export interface UserResource {
    id: number;
    name: string;
    email: string;
    pivot?: {
        role?: ServerMemberRole;
    };
}

export type ServerMemberRole = 'admin' | 'member';

export type MentionKind = 'direct' | 'everyone';

export interface MentionResource {
    id: number;
    name: string;
    kind: MentionKind;
}

export interface MessageReactionResource {
    emoji: string;
    count: number;
    user_ids: number[];
    user_names: string[];
}

export interface StoredFileResource {
    id: number;
    server_id: number;
    path: string;
    original_name: string;
    mime_type: string | null;
    size: number;
    preview_status: string | null;
    preview_path?: string | null;
    created_at?: string | null;
    uploader?: UserResource | null;
    attachable_type?: string | null;
    attachable_id?: number | null;
    stream_url?: string;
    download_url?: string;
    thumbnail_url?: string | null;
}

export interface MessageResource {
    id: number;
    server_id: number;
    channel_id: number;
    user_id: number | null;
    parent_id: number | null;
    body: string;
    created_at: string;
    updated_at?: string;
    reply_count?: number;
    user?: UserResource | null;
    attachments?: StoredFileResource[];
    mentions?: MentionResource[];
    reactions?: MessageReactionResource[];
    is_reminder?: boolean;
}

export interface NotificationResource {
    id: number;
    kind: MentionKind;
    message_id: number;
    parent_message_id: number | null;
    parent_id: number | null;
    server_id: number;
    server_name: string;
    channel_id: number;
    channel_name: string;
    author: UserResource | null;
    excerpt: string;
    created_at: string;
    read_at: string | null;
}

export interface NotificationCounts {
    total: number;
    unread: number;
    servers: Record<string, number>;
    channels: Record<string, number>;
}

export interface NotificationIndexResource {
    items?: NotificationResource[];
    notifications?: NotificationResource[];
    next_cursor?: string | null;
    previous_cursor?: string | null;
    total?: number;
    unread?: number;
    server_counts?: Record<string, number>;
    channel_counts?: Record<string, number>;
    counts?: NotificationCounts;
}

export interface TodoResource {
    id: number;
    channel_id: number;
    assignee_id: number | null;
    created_by: number | null;
    title: string;
    details: string | null;
    starts_at: string | null;
    due_at: string | null;
    due_timezone: string;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    completed_at: string | null;
    completed_by: number | null;
    position: number;
    created_at?: string;
    assignee?: UserResource | null;
    creator?: UserResource | null;
    channel?: ChannelResource | ChannelSummaryResource | null;
}

export type TodoWithChannelSummaryResource = Omit<TodoResource, 'channel'> & {
    channel: ChannelSummaryResource;
};

export interface GanttTask {
    id: string;
    type: 'channel' | 'todo';
    title: string;
    start: string | null;
    end: string | null;
    channel_id: number;
    channel_name: string;
    completed?: boolean;
}
