# Commands

## nowo:performance:set-route

Set or update route performance metrics.

### Usage

```bash
php bin/console nowo:performance:set-route <route> [options]
```

### Arguments

- `route` (required) - The route name

### Options

- `--env, -e` - Environment (dev, test, prod) (default: `dev`)
- `--request-time, -r` - Request time in seconds (float)
- `--queries` - Total number of queries (integer)
- `--query-time, -t` - Total query execution time in seconds (float)
- `--params, -p` - Route parameters as JSON string

### Description

This command sets or updates route performance metrics. If a route doesn't exist, it will be created. If it exists, it will be updated only if the new metrics are worse (higher request time or more queries).

### Examples

#### Basic Usage

```bash
# Set request time and query count
php bin/console nowo:performance:set-route app_home \
    --request-time=0.5 \
    --queries=10
```

#### Full Metrics

```bash
# Set all metrics
php bin/console nowo:performance:set-route app_user_show \
    --env=prod \
    --request-time=1.2 \
    --queries=25 \
    --query-time=0.3 \
    --params='{"id":123}'
```

#### Update with Worse Metrics

```bash
# Update if metrics are worse
php bin/console nowo:performance:set-route app_home \
    --request-time=0.8 \
    --queries=15
```

The command will only update if:
- Request time is higher than existing
- Query count is higher than existing
- No existing data exists

### Output

The command displays a table with the saved metrics:

```
Route metrics saved successfully!
+----------------+-------+
| Metric         | Value |
+----------------+-------+
| Route Name     | app_home |
| Environment    | dev   |
| Request Time   | 0.5000 s |
| Total Queries  | 10    |
| Query Time     | 0.2000 s |
| Updated At     | 2025-01-23 10:30:00 |
+----------------+-------+
```

### Error Handling

- If JSON in `--params` is invalid, the command will fail with an error message
- If no metrics are provided, the command will fail
- If database errors occur, the command will display an error message
