import { randomUUID } from 'crypto';
import express, { type Request, type Response, type NextFunction } from 'express';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';
import { registerActionTools } from './tools/actions.js';
import { registerAlbumTools } from './tools/albums.js';
import { registerPlaylistTools } from './tools/playlists.js';
import { registerSearchTools } from './tools/search.js';
import { registerStatsTools } from './tools/stats.js';
import { registerTrackTools } from './tools/tracks.js';

const PORT       = parseInt(process.env.PORT ?? '3001', 10);
const ACCESS_KEY = process.env.MCP_ACCESS_KEY ?? process.env.GRINMUZIK_API_KEY;

if (!ACCESS_KEY) {
  throw new Error('Set MCP_ACCESS_KEY (or GRINMUZIK_API_KEY) to secure the endpoint');
}

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

const sessions = new Map<string, StreamableHTTPServerTransport>();

function requireBearer(req: Request, res: Response, next: NextFunction): void {
  const auth = req.headers.authorization;
  if (!auth || auth !== `Bearer ${ACCESS_KEY}`) {
    res.status(401).json({ error: 'Unauthorized' });
    return;
  }
  next();
}

// Single endpoint handles all MCP traffic (POST to init/call, GET for SSE stream, DELETE to close)
app.all('/mcp', requireBearer, async (req: Request, res: Response) => {
  const sessionId = req.headers['mcp-session-id'] as string | undefined;

  if (req.method === 'POST' && !sessionId) {
    let transport!: StreamableHTTPServerTransport;
    transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: () => randomUUID(),
      onsessioninitialized: (id) => { sessions.set(id, transport); },
    });
    transport.onclose = () => {
      if (transport.sessionId) sessions.delete(transport.sessionId);
    };
    await buildServer().connect(transport);
    await transport.handleRequest(req, res, req.body);
    return;
  }

  if (!sessionId || !sessions.has(sessionId)) {
    res.status(sessionId ? 404 : 400).json({ error: 'Session not found or missing mcp-session-id' });
    return;
  }

  await sessions.get(sessionId)!.handleRequest(req, res, req.body);
});

app.listen(PORT, () => {
  console.log(`MCP HTTP server listening on port ${PORT}`);
});
