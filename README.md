# OpenMage CrUX Data

An OpenMage extension that integrates Chrome User Experience Report (CrUX) data into the admin backend.

## Features

- Display Core Web Vitals data (LCP, INP, CLS, FCP) from Chrome User Experience Report
- Support for different form factors (desktop, mobile, tablet)
- Show form factor distribution for the site
- Rate metrics according to Google standards (good, average, poor)
- Simple configuration through the admin panel

## Installation

### Manual

1. Download the repository
2. Copy the files to your OpenMage root directory
3. Clear the cache

### Composer

```bash
composer require mm/openmage-cruxdata
```

### Modman

```bash
modman clone https://github.com/empiricompany/openmage-cruxdata.git
```

## Configuration

1. Log in to your OpenMage admin panel
2. Go to System > Configuration > CrUX Data > General Settings
3. Enter your Google API key with CrUX API access
4. (Optional) Enter a custom API endpoint domain if you're using a proxy (e.g., https://your-worker.workers.dev)
5. Save the configuration

To obtain a Google API key:
1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing project
3. Enable the Chrome UX Report API
4. Create an API key in the credentials section

## Usage

After configuring the extension, you can access the CrUX data by going to:

Report > CrUX Data > Report

## Using a Custom API Endpoint

You can use a custom API endpoint (like a CloudFlare Workers proxy) to:

1. Hide your API key from the client
2. Add caching at the proxy level
3. Implement custom rate limiting
4. Add additional security measures

To set up a CloudFlare Workers proxy:

1. Create a new CloudFlare Worker
2. Implement a proxy that forwards requests to the CrUX API with your API key
3. Enter the Worker domain (e.g., https://your-worker.workers.dev) in the Custom API Endpoint field in the extension configuration

Example CloudFlare Worker code with caching:

```javascript
export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // Build URL with API key (stored in Worker environment)
    const APIkey = await env.curxdata_api_key.get()
    const upstreamUrl = `https://chromeuxreport.googleapis.com${url.pathname}?key=${APIkey}`;

    const method = request.method.toUpperCase();

    // Only POST and OPTIONS are supported
    if (method === 'OPTIONS') {
      return new Response(null, {
        status: 204,
        headers: {
          'Access-Control-Allow-Origin': '*',
          'Access-Control-Allow-Methods': 'POST, OPTIONS',
          'Access-Control-Allow-Headers': 'Content-Type',
        },
      });
    }

    if (method !== 'POST') {
      return new Response('Only POST supported', { status: 405 });
    }

    // Get body as text for content-based caching
    const body = await request.text();

    // Daily cache key
    const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
    const cacheKey = new Request(`${upstreamUrl}|${body}|${today}`, { method: 'GET' });
    const cache = caches.default;

    let response = await cache.match(cacheKey);
    if (response) {
      return new Response(response.body, {
        ...response,
        headers: {
          ...Object.fromEntries(response.headers),
          'Access-Control-Allow-Origin': '*',
          'X-Worker-Cache': 'HIT',
        },
      });
    }

    // Call the actual API
    response = await fetch(upstreamUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body,
    });

    // Cache only if status is 200
    if (response.status === 200) {
      ctx.waitUntil(cache.put(cacheKey, response.clone()));
    }

    return new Response(response.body, {
      ...response,
      headers: {
        ...Object.fromEntries(response.headers),
        'Access-Control-Allow-Origin': '*',
        'X-Worker-Cache': 'MISS',
      },
    });
  },
};
```

## License

MIT
