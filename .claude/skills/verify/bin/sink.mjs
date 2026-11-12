// Webhook sink: a test-only stand-in for the merchant's server container.
// Records every request it receives under /hook/, so specs can look up the
// order webhooks Magento sent. verify-env runs it in a node:22-alpine
// container next to Magento (no dependencies, Node only). Adapted from the
// Shopware extension's tests/e2e/webhook-sink/server.mjs.
//
//   POST /hook/<label>[/delay/<seconds>][/status/<code>]
//                                         record on arrival; answer <code>
//                                         (default 200), after <seconds> if
//                                         given; any other method: 405, not
//                                         recorded
//   GET  /_sink/requests?label=&transactionId=   recorded requests, oldest first
//   GET  /_sink/health
//
// The status code and the delay are part of the URL, so a spec picks the
// answer through the server container URL it configures, with no shared state
// to reset. A delay stands in for a server container that hangs: the request
// is recorded at once, so a spec can tell it was sent even when the sender
// gave up waiting for the answer.
import http from 'node:http';

const PORT = Number(process.env.PORT ?? 8080);
const requests = [];

function send(res, status, body) {
    res.writeHead(status, { 'content-type': 'application/json' });
    res.end(JSON.stringify(body));
}

const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://sink');

    if (url.pathname === '/_sink/health') {
        return send(res, 200, { ok: true });
    }

    if (url.pathname === '/_sink/requests' && req.method === 'GET') {
        const label = url.searchParams.get('label');
        const transactionId = url.searchParams.get('transactionId');

        return send(res, 200, requests.filter((request) =>
            (!label || request.label === label)
            && (!transactionId || request.json?.ecommerce?.transaction_id === transactionId),
        ));
    }

    const hook = url.pathname.match(/^\/hook\/([^/]+)(?:\/delay\/(\d+))?(?:\/status\/(\d{3}))?\/?$/);
    if (!hook) {
        return send(res, 404, { error: `No such sink route: ${req.method} ${url.pathname}` });
    }

    // Order webhooks are POSTs. Anything else is refused and not recorded, so
    // a webhook sent with another method shows up as one that never arrived.
    if (req.method !== 'POST') {
        res.setHeader('allow', 'POST');
        return send(res, 405, { error: `The sink only accepts POST, got ${req.method}` });
    }

    const chunks = [];
    req.on('data', (chunk) => chunks.push(chunk));
    req.on('end', () => {
        const body = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try {
            json = JSON.parse(body);
        } catch {
            // Recorded as raw text only.
        }

        requests.push({
            receivedAt: new Date().toISOString(),
            method: req.method,
            path: url.pathname,
            label: hook[1],
            headers: req.headers,
            body,
            json,
        });

        const status = Number(hook[3] ?? 200);
        const answer = () => {
            // The sender may have hung up while the sink was waiting.
            if (res.destroyed) {
                return;
            }
            if (status === 204) {
                res.writeHead(204);
                return res.end();
            }

            return send(res, status, { received: true });
        };

        if (hook[2]) {
            return setTimeout(answer, Number(hook[2]) * 1000);
        }

        return answer();
    });
});

server.listen(PORT, () => {
    console.log(`webhook sink listening on ${PORT}`);
});
