import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { registerActionTools } from './tools/actions.js';
import { registerAdminTools } from './tools/admin.js';
import { registerAlbumTools } from './tools/albums.js';
import { registerIntelligenceTools } from './tools/intelligence.js';
import { registerMyAnniversaryTools } from './tools/myanniversary.js';
import { registerPlaylistTools } from './tools/playlists.js';
import { registerSamplingTools } from './tools/sampling.js';
import { registerSearchTools } from './tools/search.js';
import { registerStatsTools } from './tools/stats.js';
import { registerTrackTools } from './tools/tracks.js';
import { registerVpsTools } from './tools/vps.js';
import api from './api.js';
import { z } from 'zod';
const server = new McpServer({
    name: 'dson-music',
    version: '2.0.0',
});
// ── Existing tools ─────────────────────────────────────────────────────────────
registerSearchTools(server);
registerTrackTools(server);
registerAlbumTools(server);
registerPlaylistTools(server);
registerStatsTools(server);
registerActionTools(server);
// ── Phase 1: Admin / write tools ──────────────────────────────────────────────
registerAdminTools(server);
// ── Phase 2: Intelligence tools ───────────────────────────────────────────────
registerIntelligenceTools(server);
// ── Phase 4: Cross-app tools ──────────────────────────────────────────────────
registerMyAnniversaryTools(server);
registerVpsTools(server);
// ── Phase 5: Sampling / AI-augmented tools ────────────────────────────────────
registerSamplingTools(server);
// ── Phase 3: Resources ────────────────────────────────────────────────────────
server.resource('platform_guidelines', 'grinmuzik://guidelines', async (uri) => ({
    contents: [{
            uri: uri.href,
            text: await api.get('/resources/guidelines').then((r) => {
                const d = r.data;
                return d.content ?? JSON.stringify(d);
            }),
        }],
}));
server.resource('genre_taxonomy', 'grinmuzik://genres', async (uri) => ({
    contents: [{
            uri: uri.href,
            text: await api.get('/resources/genres').then((r) => JSON.stringify(r.data, null, 2)),
        }],
}));
server.resource('artist_contract', 'grinmuzik://contract', async (uri) => ({
    contents: [{
            uri: uri.href,
            text: await api.get('/resources/contract').then((r) => {
                const d = r.data;
                return d.content ?? JSON.stringify(d);
            }),
        }],
}));
// ── Phase 3: Prompts ──────────────────────────────────────────────────────────
server.prompt('weekly_report', 'Generate a weekly platform performance digest', {}, async () => ({
    messages: [{
            role: 'user',
            content: {
                type: 'text',
                text: `You are a music platform analyst. Using the get_site_stats, get_charts, get_platform_health, and get_trending tools, generate a structured weekly report for GrinMuzik covering: top tracks, artist performance, platform health issues, and 3 actionable recommendations.`,
            },
        }],
}));
server.prompt('artist_brief', 'Summarise an artist\'s full profile and performance', { slug: z.string().describe('Artist slug') }, async ({ slug }) => ({
    messages: [{
            role: 'user',
            content: {
                type: 'text',
                text: `Use get_artist with slug "${slug}" to retrieve the artist profile and their top tracks. Then summarise: bio, total plays, top 5 tracks, genre spread, and whether they have any metadata gaps (missing cover, bio, genre). Finish with a 2-sentence recommendation for how to grow their presence on GrinMuzik.`,
            },
        }],
}));
server.prompt('content_audit', 'Flag all tracks and artists with incomplete metadata', {}, async () => ({
    messages: [{
            role: 'user',
            content: {
                type: 'text',
                text: `Use get_platform_health to get counts of tracks missing cover art, duration, or genre, and unverified artists. Then use get_underperforming_tracks with the default threshold. Produce a structured audit report with: (1) metadata gaps table, (2) underperforming tracks list, (3) recommended actions ranked by impact.`,
            },
        }],
}));
server.prompt('onboarding_checklist', 'Check what is missing before a new artist can go live', { slug: z.string().describe('Artist slug') }, async ({ slug }) => ({
    messages: [{
            role: 'user',
            content: {
                type: 'text',
                text: `Use get_artist with slug "${slug}" to fetch their profile. Then check the following onboarding requirements and report pass/fail for each: (1) profile photo/avatar set, (2) bio written, (3) at least one published track, (4) genre assigned, (5) artist is verified, (6) at least one track has cover art. List what is missing and call the relevant admin tool to fix anything you can fix automatically.`,
            },
        }],
}));
// ── Transport ──────────────────────────────────────────────────────────────────
const transport = new StdioServerTransport();
await server.connect(transport);
