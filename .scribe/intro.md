# Introduction

REST API v1 for FacilityDesk: work orders, technicians' jobs, sites, assets and dashboards.

<aside>
    <strong>Base URL</strong>: <code>http://facilitydesk.test</code>
</aside>

    All endpoints live on the central domain under `/api/v1`. Your workspace is taken from your token, never from the URL or body,
    so a record from another workspace is always a `404`.

    - **Auth:** `POST /auth/login` with your workspace slug, email, password and a device name returns a bearer token (30 days).
    - **Errors:** `{ "message": "...", "errors": { "field": ["..."] } }`. `401` no/invalid token, `403` not allowed, `404` not found, `422` validation or an illegal status change, `429` rate limited.
    - **Limits:** 60 requests a minute per token; 5 login attempts a minute per IP.
    - **Times** are ISO 8601 in UTC; show them in the work order's `site.timezone`.

