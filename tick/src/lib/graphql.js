import { createClient, cacheExchange, fetchExchange } from 'urql';

const APP_SECRET = import.meta.env.VITE_APP_SECRET ?? '';
const GRAPHQL_ENDPOINT = import.meta.env.VITE_GRAPHQL_URL || '/graphql';

/** Read the stored JWT at request time — called on every fetch, not once at boot */
const getAuthHeaders = () => {
  const headers = {
    'Content-Type': 'application/json',
    'X-Maljani-App-Secret': APP_SECRET,
  };

  try {
    const saved = localStorage.getItem('maljani_auth');
    if (saved) {
      const { token } = JSON.parse(saved);
      if (token) headers['Authorization'] = `Bearer ${token}`;
    }
  } catch (_) {
    // localStorage unavailable or corrupted — continue without auth header
  }

  return headers;
};

export const client = createClient({
  url: GRAPHQL_ENDPOINT,
  exchanges: [cacheExchange, fetchExchange],
  fetchOptions: () => ({ headers: getAuthHeaders() }),
});
