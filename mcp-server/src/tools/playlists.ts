import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import api, { apiCall } from '../api.js';

export function registerPlaylistTools(server: McpServer): void {
  server.tool(
    'list_playlists',
    'List public playlists',
    {
      page:     z.number().int().positive().optional().default(1),
      per_page: z.number().int().min(1).max(50).optional().default(20),
    },
    async (params) => apiCall(() => api.get('/playlists', { params })),
  );

  server.tool(
    'get_playlist',
    'Get a playlist with its full track listing by slug',
    {
      slug: z.string().describe('Playlist slug'),
    },
    async ({ slug }) => apiCall(() => api.get(`/playlists/${slug}`)),
  );

  server.tool(
    'list_genres',
    'List all active genres with their published track counts',
    {},
    async () => apiCall(() => api.get('/genres')),
  );
}
