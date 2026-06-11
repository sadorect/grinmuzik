import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { registerActionTools } from './tools/actions.js';
import { registerAlbumTools } from './tools/albums.js';
import { registerPlaylistTools } from './tools/playlists.js';
import { registerSearchTools } from './tools/search.js';
import { registerStatsTools } from './tools/stats.js';
import { registerTrackTools } from './tools/tracks.js';

const server = new McpServer({
  name: 'dson-music',
  version: '1.0.0',
});

registerSearchTools(server);
registerTrackTools(server);
registerAlbumTools(server);
registerPlaylistTools(server);
registerStatsTools(server);
registerActionTools(server);

const transport = new StdioServerTransport();
await server.connect(transport);
