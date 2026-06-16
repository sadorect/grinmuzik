import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import api, { apiCall } from '../api.js';

async function sample(server: McpServer, prompt: string): Promise<string | null> {
  try {
    const srv = server as unknown as { server?: { createMessage?: (opts: unknown) => Promise<unknown> } };
    const result = await srv.server?.createMessage?.({
      messages: [{ role: 'user', content: { type: 'text', text: prompt } }],
      maxTokens: 300,
    }) as { content?: { type?: string; text?: string } } | undefined;
    if (result?.content?.type === 'text') return result.content.text ?? null;
    return null;
  } catch {
    return null;
  }
}

export function registerSamplingTools(server: McpServer): void {
  server.tool(
    'generate_track_description',
    'Generate a short streaming-platform description for a track using AI sampling',
    {
      slug: z.string().describe('Track slug'),
    },
    async ({ slug }) => {
      const trackRes = await apiCall(() => api.get(`/tracks/${slug}`));
      if (trackRes.isError) return trackRes;

      const track = JSON.parse(trackRes.content[0].text);
      const prompt = `Write a 2-sentence streaming platform description for this track: "${track.title}" by ${track.artist?.name ?? 'Unknown'}, genre: ${track.genre?.name ?? 'Unknown'}, mood: ${track.mood ?? 'Unknown'}. Keep it engaging and under 50 words.`;

      const description = await sample(server, prompt);

      if (!description) {
        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({ note: 'Sampling not supported by client. Raw track data returned.', track }),
          }],
        };
      }

      return { content: [{ type: 'text' as const, text: JSON.stringify({ slug, description }) }] };
    },
  );

  server.tool(
    'suggest_mood_tag',
    'Use AI sampling to suggest a mood tag for a track based on its metadata',
    {
      slug: z.string().describe('Track slug'),
    },
    async ({ slug }) => {
      const trackRes = await apiCall(() => api.get(`/tracks/${slug}`));
      if (trackRes.isError) return trackRes;

      const track = JSON.parse(trackRes.content[0].text);
      const prompt = `Given a music track titled "${track.title}" in the ${track.genre?.name ?? 'Unknown'} genre, suggest a single mood tag from this list: energetic, melancholic, romantic, uplifting, chill, aggressive, nostalgic, peaceful, sad, happy. Reply with only the mood word.`;

      const mood = await sample(server, prompt);

      if (!mood) {
        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({ note: 'Sampling not supported. Use genre/title to infer mood manually.', track }),
          }],
        };
      }

      return { content: [{ type: 'text' as const, text: JSON.stringify({ slug, suggested_mood: mood.trim().toLowerCase() }) }] };
    },
  );

  server.tool(
    'generate_artist_bio',
    'Draft an artist bio using their profile, top tracks, and play stats via AI sampling',
    {
      slug: z.string().describe('Artist slug'),
    },
    async ({ slug }) => {
      const artistRes = await apiCall(() => api.get(`/artists/${slug}`));
      if (artistRes.isError) return artistRes;

      const artist = JSON.parse(artistRes.content[0].text);
      const topTracks = (artist.top_tracks ?? []).slice(0, 3).map((t: { title: string }) => t.title).join(', ');
      const prompt = `Write a compelling 3-sentence artist bio for a music streaming platform. Artist name: ${artist.stage_name ?? artist.name}. Genre: ${artist.genre ?? 'Various'}. Notable tracks: ${topTracks || 'none listed'}. Total plays: ${artist.total_plays ?? 0}. Keep it third-person, engaging, and under 80 words.`;

      const bio = await sample(server, prompt);

      if (!bio) {
        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({ note: 'Sampling not supported. Raw artist data returned for manual bio writing.', artist }),
          }],
        };
      }

      return { content: [{ type: 'text' as const, text: JSON.stringify({ slug, draft_bio: bio.trim(), note: 'Review before saving' }) }] };
    },
  );

  server.tool(
    'generate_weekly_newsletter',
    'Generate a weekly newsletter using charts, platform health, and new releases via AI sampling',
    {
      week: z.string().optional().describe('ISO week string, e.g. "2026-W24". Defaults to current week.'),
    },
    async ({ week }) => {
      const [chartsRes, statsRes] = await Promise.all([
        apiCall(() => api.get('/charts', { params: { period: 'week', limit: 5 } })),
        apiCall(() => api.get('/stats')),
      ]);

      const charts = JSON.parse(chartsRes.content[0].text);
      const stats  = JSON.parse(statsRes.content[0].text);
      const weekLabel = week ?? `Week of ${new Date().toDateString()}`;

      const prompt = `You are a music platform newsletter writer. Generate a concise weekly newsletter for GrinMuzik (an independent African music platform) for ${weekLabel}.\n\nPlatform stats: ${JSON.stringify(stats)}\nTop charts this week: ${JSON.stringify(charts?.data ?? charts)}\n\nInclude: a brief intro, top 3 tracks of the week, a platform highlight, and a closing call-to-action. Keep it under 250 words. Format with markdown headings.`;

      const newsletter = await sample(server, prompt);

      if (!newsletter) {
        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              note: 'Sampling not supported. Raw data returned for manual newsletter writing.',
              week: weekLabel,
              charts,
              stats,
            }),
          }],
        };
      }

      return { content: [{ type: 'text' as const, text: JSON.stringify({ week: weekLabel, newsletter }) }] };
    },
  );
}
