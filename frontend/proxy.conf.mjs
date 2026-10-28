// Dev server proxy: the browser only talks to the dev server, which forwards the API calls.
const api = process.env['API_URL'] ?? 'http://127.0.0.1:8002';

// Path under which the app is served: "/" by default, "/absproxy/4400/" behind
// code-server (see the "code-server" configuration in angular.json)
const prefixes = ['', '/absproxy/4400'];

export default Object.fromEntries(
  prefixes.map((prefix) => [
    `${prefix}/api`,
    {
      target: api,
      secure: false,
      changeOrigin: true,
      rewrite: (path) => path.slice(prefix.length),
    },
  ]),
);
