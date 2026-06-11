import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import api, { apiCall } from '../api.js';

export function registerTrackTools(server: McpServer): void {
  server.tool(
    'list_tracks',
    'List published tracks with optional genre, artist, or mood filters',
    {
      genre:    z.string().optional().describe('Filter by genre slug'),
      artist:   z.string().optional().describe('Filter by artist slug'),
      mood:     z.string().optional().describe('Filter by mood (e.g. energetic, melancholic)'),
      page:     z.number().int().positive().optional().default(1),
      per_page: z.number().int().min(1).max(50).optional().default(20),
    },
    async (params) => apiCall(() => api.get('/tracks', { params })),
  );

  server.tool(
    'get_track',
    'Get full details for a single track by its slug',
    {
      slug: z.string().describe('Track slug (from URL or list_tracks result)'),
    },
    async ({ slug }) => apiCall(() => api.get(`/tracks/${slug}`)),
  );

  server.tool(
    'list_artists',
    'List publicly discoverable artists, ordered by total plays',
    {
      page:     z.number().int().positive().optional().default(1),
      per_page: z.number().int().min(1).max(50).optional().default(20),
    },
    async (params) => apiCall(() => api.get('/artists', { params })),
  );

  server.tool(
    'get_artist',
    'Get an artist profile and their top tracks by slug',
    {
      slug: z.string().describe('Artist slug (from URL or list_artists result)'),
    },
    async ({ slug }) => apiCall(() => api.get(`/artists/${slug}`)),
  );
}
