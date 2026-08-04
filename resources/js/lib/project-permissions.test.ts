import { describe, expect, it } from 'vitest';
import { isProjectAdministrator } from './project-permissions';

const server = { created_by: 1 };
const members = [
    {
        id: 1,
        name: 'Creator',
        email: 'creator@example.com',
        pivot: { role: 'admin' as const },
    },
    {
        id: 2,
        name: 'Second Admin',
        email: 'admin@example.com',
        pivot: { role: 'admin' as const },
    },
    {
        id: 3,
        name: 'Member',
        email: 'member@example.com',
        pivot: { role: 'member' as const },
    },
];

describe('project administrator permissions', () => {
    it('recognises both the creator and additional administrators', () => {
        expect(isProjectAdministrator(server, members, 1)).toBe(true);
        expect(isProjectAdministrator(server, members, 2)).toBe(true);
    });

    it('rejects regular, missing, and unauthenticated users', () => {
        expect(isProjectAdministrator(server, members, 3)).toBe(false);
        expect(isProjectAdministrator(server, members, 999)).toBe(false);
        expect(isProjectAdministrator(server, members, null)).toBe(false);
    });
});
