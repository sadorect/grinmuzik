import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import api, { apiCall } from '../api.js';

export function registerActionTools(server: McpServer): void {
  server.tool(
    'record_play',
    'Record that a track was played by the authenticated user',
    {
      track_slug:     z.string().describe('Track slug'),
      seconds_played: z.number().int().min(0).optional().describe('How many seconds were played'),
    },
    async ({ track_slug, seconds_played }) =>
      apiCall(() => api.post(`/tracks/${track_slug}/play`, { seconds_played })),
  );

  server.tool(
    'toggle_like',
    'Like or unlike a track for the authenticated user. Returns the new liked state.',
    {
      track_slug: z.string().describe('Track slug'),
    },
    async ({ track_slug }) => apiCall(() => api.post(`/tracks/${track_slug}/like`)),
  );

  server.tool(
    'create_playlist',
    'Create a new playlist for the authenticated user',
    {
      name:        z.string().min(1).max(100).describe('Playlist name'),
      description: z.string().max(500).optional().describe('Optional description'),
      is_public:   z.boolean().optional().default(false).describe('Whether the playlist is publicly visible'),
    },
    async (params) => apiCall(() => api.post('/playlists', params)),
  );

  server.tool(
    'add_track_to_playlist',
    "Add a track to one of the authenticated user's playlists",
    {
      playlist_slug: z.string().describe('Playlist slug'),
      track_slug:    z.string().describe('Track slug'),
    },
    async ({ playlist_slug, track_slug }) =>
      apiCall(() => api.post(`/playlists/${playlist_slug}/tracks/${track_slug}`)),
  );

  server.tool(
    'remove_track_from_playlist',
    "Remove a track from one of the authenticated user's playlists",
    {
      playlist_slug: z.string().describe('Playlist slug'),
      track_slug:    z.string().describe('Track slug'),
    },
    async ({ playlist_slug, track_slug }) =>
      apiCall(() => api.delete(`/playlists/${playlist_slug}/tracks/${track_slug}`)),
  );
}
