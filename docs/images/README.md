# Screenshots

Screenshots used in the main [README](../../README.md):

| File | Description |
|------|-------------|
| `demo/dashboard-main.webp` | Performance Metrics Dashboard (main view with KPIs, top routes, trends, routes table) |
| `demo/dashboard-advanced-statistics.webp` | Advanced Performance Statistics (correlations, efficiency, distributions) |
| `demo/dashboard-access-statistics.webp` | Access Statistics by Hour (temporal charts, heatmap, filters) |

Regenerate them from the running demo with `make -C demo/symfony8 demo-screenshots` (Playwright `screenshots` project, captured as PNG and converted to WebP by `e2e/scripts/to-webp.mjs`).
