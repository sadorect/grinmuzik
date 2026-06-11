import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import api, { apiCall } from '../api.js';

export function registerAlbumTools(server: McpServer): void {
  server.tool(
    'list_albums',
    'List publicly visible albums with optional artist, genre, or type filters',
    {
      artist:   z.string().optional().describe('Filter by artist slug'),
      genre:    z.string().optional().describe('Filter by genre slug'),
      type:     z.enum(['album', 'ep']).optional().describe('Album type'),
      page:     z.number().int().positive().optional().default(1),
      per_page: z.number().int().min(1).max(50).optional().default(20),
    },
    async (params) => apiCall(() => api.get('/albums', { params })),
  );

  server.tool(
    'get_album',
    'Get an album with its full track listing by slug',
    {
      slug: z.string().describe('Album slug'),
    },
    async ({ slug }) => apiCall(() => api.get(`/albums/${slug}`)),
  );
}
