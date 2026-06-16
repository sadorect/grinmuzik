import axios from 'axios';

const token = process.env.GRINMUZIK_API_KEY;
if (!token) throw new Error('GRINMUZIK_API_KEY environment variable is required');

const api = axios.create({
  baseURL: process.env.GRINMUZIK_API_URL ?? 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
  },
  timeout: 10_000,
});

export const myanniversaryApi = axios.create({
  baseURL: process.env.MYANNIVERSARY_API_URL ?? 'https://myanniversary.com.ng/api',
  headers: {
    Accept: 'application/json',
    Authorization: `Bearer ${process.env.MYANNIVERSARY_API_KEY ?? ''}`,
  },
  timeout: 10_000,
});

type McpContent = { content: [{ type: 'text'; text: string }]; isError?: true };

export async function apiCall(fn: () => Promise<{ data: unknown }>): Promise<McpContent> {
  try {
    const { data } = await fn();
    return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
  } catch (e) {
    if (axios.isAxiosError(e)) {
      const status = e.response?.status ?? 0;
      const msg = (e.response?.data as Record<string, string>)?.message ?? e.message;
      return { content: [{ type: 'text', text: `API error ${status}: ${msg}` }], isError: true };
    }
    const msg = e instanceof Error ? e.message : String(e);
    return { content: [{ type: 'text', text: msg }], isError: true };
  }
}

export default api;
