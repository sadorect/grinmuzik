import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import api, { apiCall } from '../api.js';

export function registerIntelligenceTools(server: McpServer): void {
  server.tool(
    'get_platform_health',
    'Get a platform health digest: new artists/tracks, plays, failed jobs, data quality issues',
    {},
    async () => apiCall(() => api.get('/admin/health')),
  );

  server.tool(
    'get_underperforming_tracks',
    'List tracks older than 14 days with play counts below a threshold',
    {
      threshold_plays: z.number().int().min(0).optional().default(100)
        .describe('Tracks with fewer plays than this are flagged (default: 100)'),
    },
    async ({ threshold_plays }) =>
      apiCall(() => api.get('/admin/tracks/underperforming', { params: { threshold: threshold_plays } })),
  );

  server.tool(
    'get_upload_queue_status',
    'Get counts of pending and failed jobs grouped by queue name',
    {},
    async () => apiCall(() => api.get('/admin/queue/status')),
  );

  server.tool(
    'get_play_spikes',
    'Find tracks that received more than 2× their average hourly play rate in a given window',
    {
      hours: z.number().int().min(1).max(168).optional().default(24)
        .describe('Window size in hours to check for spikes (default: 24)'),
    },
    async ({ hours }) =>
      apiCall(() => api.get('/admin/tracks/spikes', { params: { hours } })),
  );

  server.tool(
    'get_trending',
    'Get tracks ranked by play velocity (plays per hour) over the last 48 hours',
    {
      limit: z.number().int().min(1).max(50).optional().default(10)
        .describe('Number of trending tracks to return (default: 10)'),
    },
    async ({ limit }) =>
      apiCall(() => api.get('/tracks/trending', { params: { limit } })),
  );

  server.tool(
    'get_revenue_snapshot',
    'Get revenue totals from donations grouped by period',
    {
      period: z.enum(['day', 'week', 'month']).optional().default('month')
        .describe('Time period for the snapshot (default: month)'),
    },
    async ({ period }) =>
      apiCall(() => api.get('/admin/revenue', { params: { period } })),
  );
}
