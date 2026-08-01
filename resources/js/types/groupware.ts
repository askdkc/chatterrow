export interface ServerResource {
    id: number;
    name: string;
    description: string | null;
    starts_on: string | null;
    ends_on: string | null;
    created_by: number | null;
    created_at?: string;
    channels_count?: number;
    members_count?: number;
    channels?: ChannelResource[];
    members?: UserResource[];
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
}

export interface UserResource {
    id: number;
    name: string;
    email: string;
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
    reply_count?: number;
    user?: UserResource | null;
    attachments?: StoredFileResource[];
}

export interface TodoResource {
    id: number;
    channel_id: number;
    assignee_id: number | null;
    created_by: number | null;
    title: string;
    details: string | null;
    due_on: string | null;
    completed_at: string | null;
    completed_by: number | null;
    position: number;
    created_at?: string;
    assignee?: UserResource | null;
    creator?: UserResource | null;
    channel?: ChannelResource | null;
}

export interface GanttTask {
    id: string;
    type: 'channel' | 'todo';
    title: string;
    start: string | null;
    end: string | null;
    channel_id: number;
    channel_name: string | null;
    completed?: boolean;
}
