<?php

namespace LaravelSpectrum\Generators;

class ErrorResponseGenerator
{
    protected ValidationMessageGenerator $messageGenerator;

    public function __construct(ValidationMessageGenerator $messageGenerator)
    {
        $this->messageGenerator = $messageGenerator;
    }

    /**
     * エラーレスポンスのスキーマを生成
     */
    public function generateErrorResponses(?array $formRequestData = null): array
    {
        $responses = [];

        // 401 Unauthorized
        $responses['401'] = $this->generateUnauthorizedResponse();

        // 403 Forbidden
        $responses['403'] = $this->generateForbiddenResponse();

        // 404 Not Found
        $responses['404'] = $this->generateNotFoundResponse();

        // 422 Validation Error（FormRequestがある場合のみ）
        if ($formRequestData && isset($formRequestData['rules'])) {
            $responses['422'] = $this->generateValidationErrorResponse(
                $formRequestData['rules'],
                $formRequestData['messages'] ?? []
            );
        }

        // 500 Internal Server Error
        $responses['500'] = $this->generateInternalServerErrorResponse();

        return $responses;
    }

    /**
     * 401 Unauthorized レスポンス
     */
    protected function generateUnauthorizedResponse(): array
    {
        return [
            'description' => 'Unauthorized',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'example' => 'Unauthenticated.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 403 Forbidden レスポンス
     */
    protected function generateForbiddenResponse(): array
    {
        return [
            'description' => 'Forbidden',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'example' => 'This action is unauthorized.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 404 Not Found レスポンス
     */
    protected function generateNotFoundResponse(): array
    {
        return [
            'description' => 'Not Found',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'example' => 'Resource not found.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 422 Validation Error レスポンス
     */
    protected function generateValidationErrorResponse(array $rules, array $customMessages = []): array
    {
        // 各フィールドのエラーメッセージを生成
        $fieldErrors = [];
        $errorExamples = [];

        foreach ($rules as $field => $fieldRules) {
            // 特殊なフィールド（_noticeなど）はスキップ
            if (str_starts_with($field, '_')) {
                continue;
            }

            $fieldErrors[$field] = [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                ],
                'description' => 'Validation errors for the '.$field.' field',
            ];

            // サンプルメッセージを生成
            $sampleMessage = $this->messageGenerator->generateSampleMessage($field, $fieldRules);
            $errorExamples[$field] = [$sampleMessage];
        }

        return [
            'description' => 'Validation Error',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'example' => 'The given data was invalid.',
                            ],
                            'errors' => [
                                'type' => 'object',
                                'properties' => $fieldErrors,
                                'example' => $errorExamples,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 500 Internal Server Error レスポンス
     */
    protected function generateInternalServerErrorResponse(): array
    {
        return [
            'description' => 'Internal Server Error',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'example' => 'Server Error',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 特定のHTTPメソッドに基づいてデフォルトのエラーレスポンスを選択
     */
    public function getDefaultErrorResponses(string $method, bool $requiresAuth = false, bool $hasValidation = false): array
    {
        $responses = [];

        // 認証が必要な場合
        if ($requiresAuth) {
            $responses['401'] = $this->generateUnauthorizedResponse();
            $responses['403'] = $this->generateForbiddenResponse();
        }

        // バリデーションがある場合（POST, PUT, PATCH）
        if ($hasValidation && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            // 422はFormRequestから生成されるので、ここでは含めない
        }

        // GETリクエストや特定リソースへのアクセス
        if (in_array(strtoupper($method), ['GET', 'PUT', 'PATCH', 'DELETE'])) {
            $responses['404'] = $this->generateNotFoundResponse();
        }

        // 全てのリクエストで可能性のあるエラー
        $responses['500'] = $this->generateInternalServerErrorResponse();

        return $responses;
    }
}
