<?php

declare(strict_types=1);

namespace Gtt\SynchronizationPlugin\ExceptionListener;

use Gtt\SynchronizationPlugin\Api\Handler\ReceiveOperationHandler;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class ErrorsInRequestListener
{
    public const METHOD = 'onKernelResponse';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $this->requestStack->getMainRequest();
        $response = $event->getResponse();

        if (
            $request === null
            || !str_starts_with($request->get('_route', ''), 'api_synchronizations_')
        ) {
            return;
        }

        $errors = $request->attributes->get(ReceiveOperationHandler::ERRORS_REQUEST_ATTRIBUTE, []);
        $contentAsArray = json_decode($response->getContent() === false ? '' : $response->getContent(), true) ?? [];
        $contentAsArray['code'] = $response->getStatusCode();

        if ($errors !== []) {
            [$code, $message] = $this->prepareCodeAndMessage($errors);
            $response->setStatusCode((int) $code);
            $contentAsArray['code'] = $code;
            $contentAsArray['message'] = $message;
        }

        $content = json_encode($contentAsArray);
        $response->setContent($content === false ? null : $content);
    }

    /**
     * @param array<int|string, array<string, string>> $errors
     *
     * @return array<int, int|string>
     */
    private function prepareCodeAndMessage(array $errors): array
    {
        if ($errors === []) {
            return [Response::HTTP_NO_CONTENT, null];
        }

        $codes = [];
        $messages = [];

        foreach ($errors as $code => $errorData) {
            if ((int) $code >= Response::HTTP_INTERNAL_SERVER_ERROR) {
                return [Response::HTTP_INTERNAL_SERVER_ERROR, 'Internal server error.'];
            }

            foreach ($errorData as $className => $message) {
                $messages[] = $message;

                if (is_a($className, UnexpectedValueException::class, true)) {
                    $code = Response::HTTP_BAD_REQUEST;
                }

                $codes[] = (int)$code;
            }
        }

        return [$this->getFinalCode($codes), implode('; ', $messages)];
    }

    /**
     * @param array<int> $codes
     */
    private function getFinalCode(array $codes): int
    {
        if ($codes === []) {
            return Response::HTTP_NO_CONTENT;
        }

        $groupedCodes = [];

        foreach ($codes as $code) {
            // 412 = 4
            $genericCode = (int) floor((int) $code / 100);
            $groupedCodes[$genericCode][] = (int) $code;
            $groupedCodes[$genericCode] = array_unique($groupedCodes[$genericCode]);
        }

        $maxGenericCode = max(array_keys($groupedCodes));
        $maxCodes = $groupedCodes[$maxGenericCode];

        if (count($maxCodes) === 1) {
            // 412 for example
            return reset($maxCodes);
        }

        // 400 for example
        return $maxGenericCode * 100;
    }
}
