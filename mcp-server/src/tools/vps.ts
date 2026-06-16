import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { exec } from 'child_process';
import { promisify } from 'util';

const execAsync = promisify(exec);

const SSH_HOST    = process.env.VPS_SSH_HOST ?? '';
const SSH_USER    = process.env.VPS_SSH_USER ?? 'root';
const SSH_KEY     = process.env.VPS_SSH_KEY_PATH ?? '';

function sshBase(): string {
  const keyOpt = SSH_KEY ? `-i ${SSH_KEY} ` : '';
  return `ssh ${keyOpt}-o StrictHostKeyChecking=no -o ConnectTimeout=10 ${SSH_USER}@${SSH_HOST}`;
}

async function ssh(cmd: string): Promise<{ content: [{ type: 'text'; text: string }] }> {
  if (!SSH_HOST) {
    return { content: [{ type: 'text', text: 'VPS_SSH_HOST is not configured' }] };
  }
  try {
    const { stdout, stderr } = await execAsync(`${sshBase()} "${cmd}"`, { timeout: 30_000 });
    return { content: [{ type: 'text', text: (stdout || stderr).trim() }] };
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : String(e);
    return { content: [{ type: 'text', text: `SSH error: ${msg}` }] };
  }
}

const APP_PATHS: Record<string, string> = {
  grinmuzik:     '/var/www/grinmuzik',
  myanniversary: '/var/www/myanniversary',
  rentify:       '/var/www/rentify',
};

const KNOWN_DOMAINS = [
  'grinmuzik.com',
  'myanniversary.com.ng',
];

export function registerVpsTools(server: McpServer): void {
  server.tool(
    'vps_get_disk_usage',
    'Get disk usage per partition on the VPS (excluding tmpfs)',
    {},
    async () => ssh("df -h --output=source,size,used,avail,pcent | grep -v tmpfs"),
  );

  server.tool(
    'vps_get_mail_queue',
    'Get the number of messages in the Postfix mail queue',
    {},
    async () => ssh("mailq | tail -1"),
  );

  server.tool(
    'vps_get_failed_jobs_all',
    'Get failed job counts from all Laravel apps on the VPS',
    {},
    async () => {
      const dbUser = process.env.VPS_DB_USER ?? 'root';
      const dbPass = process.env.VPS_DB_PASS ?? '';
      const passFlag = dbPass ? `-p'${dbPass}'` : '';
      const queries = Object.entries(APP_PATHS).map(([app, _]) =>
        `echo "${app}: $(mysql -u${dbUser} ${passFlag} -se "SELECT COUNT(*) FROM ${app.replace(/[^a-z]/g, '_')}.failed_jobs" 2>/dev/null || echo 'N/A')"`
      ).join(' && ');
      return ssh(queries);
    },
  );

  server.tool(
    'vps_check_ssl_expiry',
    'Check SSL certificate expiry for one or more domains, flagging anything under 30 days',
    {
      domains: z.array(z.string()).optional()
        .describe('Domains to check. Defaults to all known platform domains.'),
    },
    async ({ domains }) => {
      const targets = (domains && domains.length > 0) ? domains : KNOWN_DOMAINS;
      const cmd = targets
        .map(d =>
          `echo "${d}: $(echo | openssl s_client -connect ${d}:443 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)"`
        )
        .join(' && ');
      return ssh(cmd);
    },
  );

  server.tool(
    'vps_restart_queue',
    'Restart the Laravel queue worker for a given app',
    {
      app: z.enum(['grinmuzik', 'myanniversary', 'rentify'])
        .describe('Which app queue to restart'),
    },
    async ({ app }) => {
      const path = APP_PATHS[app];
      return ssh(`cd ${path} && php artisan queue:restart`);
    },
  );
}
