import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import api, { apiCall } from '../api.js';

export function registerSearchTools(server: McpServer): void {
  server.tool(
    'search_music',
    'Search for tracks and artists on dson-music by keyword',
    {
      query: z.string().min(1).describe('Search term'),
      type: z.enum(['all', 'tracks', 'artists']).optional().default('all').describe('What to search'),
    },
    async ({ query, type }) => apiCall(() => api.get('/search', { params: { q: query, type } })),
  );
}
