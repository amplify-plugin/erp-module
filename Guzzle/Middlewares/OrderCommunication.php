<?php

namespace Amplify\ErpApi\Guzzle\Middlewares;

use Amplify\System\Backend\Models\CustomerOrder;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderCommunication
{
    protected ?CustomerOrder $order;

    public function __construct(?int $orderId = null)
    {
        $this->order = CustomerOrder::find($orderId);
    }

    public function __invoke(callable $handler): \Closure
    {
        if (!$this->order) {
            return $handler;
        }

        return function (
            RequestInterface $request,
            array            $options
        ) use ($handler): PromiseInterface {

            /*
             * Request is about to leave our application.
             */

            $requestBody = (string)$request->getBody();

            $payload = [
                'started_at' => now()->toISOString(),
                'request' => $this->decodeBody($requestBody),
                'finished_at' => null,
                'response' => null,
                'error' => null,
            ];

            $this->updateLog($payload);

            /*
             * Send the actual request.
             */
            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) {

                    /*
                     * Response received successfully.
                     */

                    $payload = $this->order?->erp_log ?? [];

                    $responseBody = (string)$response->getBody();

                    $payload['finished_at'] = now()->toISOString();
                    $payload['response'] = $this->decodeBody($responseBody);
                    $payload['error'] = null;

                    $this->updateLog($payload);

                    /*
                     * VERY IMPORTANT:
                     * Return the response so Laravel/Guzzle can
                     * continue processing it.
                     */
                    return $response;
                },
                function (\Throwable $exception) {

                    /*
                     * Request failed at transport level.
                     */

                    $payload = $this->order?->erp_log ?? [];

                    $payload['finished_at'] = now()->toISOString();
                    $payload['response'] = null;
                    $payload['error'] = $exception->getMessage();

                    $this->updateLog($payload);

                    /*
                     * Do not swallow the exception.
                     */
                    throw $exception;
                }
            );
        };
    }

    protected function decodeBody(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        if (json_validate($body)) {
            return json_decode($body, true);
        }

        return $body;
    }

    protected function updateLog(array $payload): void
    {
        if (!$this->order) {
            return;
        }

        $this->order->update([
            'erp_log' => $payload,
        ]);
    }
}