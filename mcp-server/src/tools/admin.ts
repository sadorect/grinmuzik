import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import api, { apiCall } from '../api.js';

export function registerAdminTools(server: McpServer): void {
  server.tool(
    'feature_track',
    'Toggle the featured flag on a track',
    {
      slug:     z.string().describe('Track slug'),
      featured: z.boolean().describe('true to feature, false to un-feature'),
    },
    async ({ slug, featured }) =>
      apiCall(() => api.patch(`/admin/tracks/${slug}/feature`, { featured })),
  );

  server.tool(
    'update_track_metadata',
    'Partially update track metadata (title, mood, genre, is_free)',
    {
      slug:       z.string().describe('Track slug'),
      title:      z.string().optional().describe('New title'),
      mood:       z.string().optional().describe('Mood slug'),
      genre_slug: z.string().optional().describe('Genre slug'),
      is_free:    z.boolean().optional().describe('Whether the track is free'),
    },
    async ({ slug, ...fields }) =>
      apiCall(() => api.patch(`/admin/tracks/${slug}`, fields)),
  );

  server.tool(
    'publish_track',
    'Publish a track (set is_published=true and record published_at)',
    {
      slug: z.string().describe('Track slug'),
    },
    async ({ slug }) =>
      apiCall(() => api.patch(`/admin/tracks/${slug}/publish`)),
  );

  server.tool(
    'unpublish_track',
    'Unpublish a track (revert to draft)',
    {
      slug: z.string().describe('Track slug'),
    },
    async ({ slug }) =>
      apiCall(() => api.patch(`/admin/tracks/${slug}/unpublish`)),
  );

  server.tool(
    'delete_artist',
    'Soft-delete an artist and all their tracks. Requires confirm=true to proceed.',
    {
      slug:    z.string().describe('Artist slug'),
      confirm: z.boolean().describe('Must be true to execute the deletion'),
    },
    async ({ slug, confirm }) =>
      apiCall(() => api.delete(`/admin/artists/${slug}`, { data: { confirm } })),
  );

  server.tool(
    'approve_artist',
    'Verify (approve) an artist account',
    {
      slug: z.string().describe('Artist slug'),
    },
    async ({ slug }) =>
      apiCall(() => api.patch(`/admin/artists/${slug}/verify`)),
  );

  server.tool(
    'send_artist_notification',
    'Send an email notification to an artist',
    {
      slug:    z.string().describe('Artist slug'),
      subject: z.string().describe('Email subject'),
      message: z.string().describe('Email body text'),
    },
    async ({ slug, subject, message }) =>
      apiCall(() => api.post(`/admin/artists/${slug}/notify`, { subject, message })),
  );
}
