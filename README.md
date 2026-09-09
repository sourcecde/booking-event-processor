# Booking Event Processor

An asynchronous booking event processor built with PHP and Amazon SQS.

## Architecture Design

![new_architecture_design](storage/images/finance-task-new-architecture.jpg)

## Key Decisions

- SQS decouples incoming requests from booking processing and absorbs traffic spikes.
- PHP consumers process messages asynchronously and can scale horizontally.
- Transient failures are retried through SQS redelivery after the visibility timeout.
- Repeated failures are moved to a DLQ. CloudWatch provides the queue metrics, which are monitored and alerted on through Grafana.
- Configuration values shown in the diagram are proposed starting values.
- A message is only deleted after both API calls succeed.
- If the failure is permanent, like a 404 (the booking doesn't exist), the message is deleted right away instead — retrying it would never fix a booking that doesn't exist.
- SQS can deliver the same message more than once, so duplicate processing should be handled separately, for example by assigning each message a unique ID and having the receiving service ignore IDs it has already processed.
- For cases where events for the same booking must be processed in order, an SQS FIFO queue can be used with the booking ID as the `MessageGroupId`. This keeps events for each booking ordered while allowing different bookings to be processed in parallel.

## Implementation

The implementation is split into two main services:

- `SqsService` handles receiving messages from SQS and deleting processed messages.
- `Processor` validates each message, retrieves the booking from the external API, sends the event to the internal API, and decides whether the message should be deleted or left for redelivery.

The AWS SDK is constrained to a pre-JSON-protocol version because the provided Fake SQS service expects the legacy SQS Query protocol.

Messages are deleted after successful processing. Permanent failures, such as invalid messages or a missing booking, are also deleted because retrying would not resolve them. Temporary failures, such as API timeouts or server errors, are left in the queue and become available again after the visibility timeout.

### Running the Consumer

The SQS endpoint is configured using the Docker service hostname
`finance_task_sqs`, so run the consumer within the application container:

```bash
docker compose exec finance_task php artisan consumer:run
```

## Tests

Unit tests cover the main consumer scenarios, including successful message processing, malformed messages, API failures and timeouts, SQS deletion failures, and unexpected exceptions.

The current implementation achieves 100% class, method, and line coverage for the covered application code.

Run all tests:

```bash
composer phpunit:test
```

Run tests with coverage:

```bash
composer phpunit:test:coverage
```

Generate the HTML coverage report:

```bash
composer phpunit:test:coverage:html
```

The HTML coverage report is generated at:

```text
test-reports/coverage-html/index.html
```
