import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { myanniversaryApi, apiCall } from '../api.js';

export function registerMyAnniversaryTools(server: McpServer): void {
  server.tool(
    'myanniversary_get_stats',
    'Get MyAnniversary platform stats: active subscribers, emails sent this month, upcoming anniversaries in 7 days',
    {},
    async () => apiCall(() => myanniversaryApi.get('/stats')),
  );

  server.tool(
    'myanniversary_get_subscribers',
    'List MyAnniversary subscribers with optional status filter',
    {
      status: z.enum(['active', 'trial', 'expired']).optional()
        .describe('Filter by subscription status'),
      limit: z.number().int().min(1).max(100).optional().default(25)
        .describe('Number of subscribers to return'),
    },
    async (params) =>
      apiCall(() => myanniversaryApi.get('/subscribers', { params })),
  );

  server.tool(
    'myanniversary_get_upcoming',
    'Get upcoming anniversary/birthday events in the next N days',
    {
      days: z.number().int().min(1).max(90).optional().default(7)
        .describe('Number of days to look ahead (default: 7)'),
    },
    async ({ days }) =>
      apiCall(() => myanniversaryApi.get('/upcoming', { params: { days } })),
  );
}
