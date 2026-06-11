import express, { type Request, type Response, type NextFunction } from 'express';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { SSEServerTransport } from '@modelcontextprotocol/sdk/server/sse.js';
import { registerActionTools } from './tools/actions.js';
import { registerAlbumTools } from './tools/albums.js';
import { registerPlaylistTools } from './tools/playlists.js';
import { registerSearchTools } from './tools/search.js';
import { registerStatsTools } from './tools/stats.js';
import { registerTrackTools } from './tools/tracks.js';

const PORT       = parseInt(process.env.PORT ?? '3001', 10);
// MCP_ACCESS_KEY secures the SSE endpoint; falls back to the API key so the
// same Sanctum token can be used in the Claude Desktop config.
const ACCESS_KEY = process.env.MCP_ACCESS_KEY ?? process.env.GRINMUZIK_API_KEY;

if (!ACCESS_KEY) {
  throw new Error('Set MCP_ACCESS_KEY (or GRINMUZIK_API_KEY) to secure the SSE endpoint');
}

// Create a fresh McpServer per SSE connection so each client gets isolated state.
function buildServer(): McpServer {
  const server = new McpServer({ name: 'dson-music', version: '1.0.0' });
  registerSearchTools(server);
  registerTrackTools(server);
  registerAlbumTools(server);
  registerPlaylistTools(server);
  registerStatsTools(server);
  registerActionTools(server);
  return server;
}

const app = express();
app.use(express.json());

// Active transports keyed by sessionId so POST /messages can route correctly.
const sessions = new Map<string, SSEServerTransport>();

function requireBearer(req: Request, res: Response, next: NextFunction): void {
  const auth = req.headers.authorization;
  if (!auth || auth !== `Bearer ${ACCESS_KEY}`) {
    res.status(401).json({ error: 'Unauthorized' });
    return;
  }
  next();
}

// GET /sse — Claude Desktop connects here to establish the SSE stream.
// The server sends an `endpoint` event pointing to POST /messages.
app.get('/sse', requireBearer, async (req: Request, res: Response) => {
  // /mcp/messages is the nginx-visible path; the trailing sessionId is appended by the transport.
  const transport = new SSEServerTransport('/mcp/messages', res);
  const server    = buildServer();

  sessions.set(transport.sessionId, transport);
  res.on('close', () => sessions.delete(transport.sessionId));

  await server.connect(transport);
});

// POST /messages — receives JSON-RPC messages from the client.
// No bearer check here; the sessionId in the query string acts as the session token.
app.post('/messages', async (req: Request, res: Response) => {
  const sessionId  = req.query.sessionId as string | undefined;
  const transport  = sessionId ? sessions.get(sessionId) : undefined;

  if (!transport) {
    res.status(404).json({ error: 'Session not found or expired' });
    return;
  }

  await transport.handlePostMessage(req, res);
});

app.listen(PORT, () => {
  console.log(`MCP SSE server listening on port ${PORT}`);
});
