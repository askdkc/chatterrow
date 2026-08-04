import type { ServerResource, UserResource } from '@/types';

export function isProjectAdministrator(
    server: Pick<ServerResource, 'created_by'>,
    members: UserResource[],
    userId: number | null | undefined,
): boolean {
    if (userId === null || userId === undefined) {
        return false;
    }

    return (
        server.created_by === userId ||
        members.some(
            (member) => member.id === userId && member.pivot?.role === 'admin',
        )
    );
}
