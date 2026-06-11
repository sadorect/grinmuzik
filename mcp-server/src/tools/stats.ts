import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import api, { apiCall } from '../api.js';

export function registerStatsTools(server: McpServer): void {
  server.tool(
    'get_charts',
    'Get the top-ranked tracks from the most recent chart snapshot',
    {
      period: z.enum(['week', 'month']).optional().default('week').describe('Chart period'),
      limit:  z.number().int().min(1).max(100).optional().default(20).describe('Number of entries to return'),
    },
    async (params) => apiCall(() => api.get('/charts', { params })),
  );

  server.tool(
    'get_site_stats',
    'Get overall platform statistics: total tracks, artists, plays, and downloads',
    {},
    async () => apiCall(() => api.get('/stats')),
  );
}
