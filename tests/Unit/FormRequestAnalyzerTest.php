<?php

namespace LaravelSpectrum\Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Tests\Fixtures\StoreUserRequest;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FormRequestAnalyzerTest extends TestCase
{
    protected FormRequestAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock cache that always calls the callback
        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberFormRequest')
            ->willReturnCallback(function ($class, $callback) {
                return $callback();
            });

        $this->analyzer = new FormRequestAnalyzer(new TypeInference, $cache);
    }

    #[Test]
    public function it_extracts_validation_rules_from_form_request()
    {
        // Act
        $parameters = $this->analyzer->analyze(StoreUserRequest::class);

        // Assert
        $parameterNames = array_column($parameters, 'name');
        $this->assertContains('name', $parameterNames);
        $this->assertContains('email', $parameterNames);

        $nameParam = $this->findParameterByName($parameters, 'name');
        $this->assertTrue($nameParam['required']);
        $this->assertEquals('string', $nameParam['type']);
    }

    #[Test]
    public function it_infers_types_from_validation_rules()
    {
        // Arrange - Create a test FormRequest
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'age' => 'required|integer|min:0|max:150',
                    'price' => 'required|numeric|min:0',
                    'is_active' => 'required|boolean',
                    'tags' => 'required|array',
                    'email' => 'required|email',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $this->assertEquals('integer', $this->findParameterByName($parameters, 'age')['type']);
        $this->assertEquals('number', $this->findParameterByName($parameters, 'price')['type']);
        $this->assertEquals('boolean', $this->findParameterByName($parameters, 'is_active')['type']);
        $this->assertEquals('array', $this->findParameterByName($parameters, 'tags')['type']);
        $this->assertEquals('string', $this->findParameterByName($parameters, 'email')['type']);
    }

    #[Test]
    public function it_extracts_descriptions_from_attributes_method()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return ['name' => 'required|string'];
            }

            public function attributes(): array
            {
                return ['name' => 'ユーザー名'];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $nameParam = $this->findParameterByName($parameters, 'name');
        $this->assertEquals('ユーザー名', $nameParam['description']);
    }

    #[Test]
    public function it_handles_array_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email'],
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $nameParam = $this->findParameterByName($parameters, 'name');
        $this->assertTrue($nameParam['required']);
        $this->assertEquals('string', $nameParam['type']);
        $this->assertContains('required', $nameParam['validation']);
        $this->assertContains('string', $nameParam['validation']);
        $this->assertContains('max:255', $nameParam['validation']);
    }

    #[Test]
    public function it_returns_empty_array_for_non_form_request()
    {
        // Act
        $parameters = $this->analyzer->analyze(\stdClass::class);

        // Assert
        $this->assertIsArray($parameters);
        $this->assertEmpty($parameters);
    }

    #[Test]
    public function it_detects_optional_fields()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'required_field' => 'required|string',
                    'optional_field' => 'sometimes|string',
                    'nullable_field' => 'nullable|string',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $this->assertTrue($this->findParameterByName($parameters, 'required_field')['required']);
        $this->assertFalse($this->findParameterByName($parameters, 'optional_field')['required']);
        $this->assertFalse($this->findParameterByName($parameters, 'nullable_field')['required']);
    }

    #[Test]
    public function it_handles_rule_objects()
    {
        // Skip test if using file-based analyzer since it can't instantiate Rule objects
        if (method_exists($this->analyzer, 'extractRules') && ! class_exists('LaravelSpectrum\Analyzers\AST\Visitors\RulesExtractorVisitor')) {
            $this->markTestSkipped('Current implementation cannot handle Rule objects');
        }

        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'email' => [
                        'required',
                        'email',
                        Rule::unique('users')->ignore(1),
                    ],
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $emailParam = $this->findParameterByName($parameters, 'email');
        $this->assertNotNull($emailParam);
        $this->assertTrue($emailParam['required']);
        $this->assertEquals('string', $emailParam['type']);

        $statusParam = $this->findParameterByName($parameters, 'status');
        $this->assertNotNull($statusParam);
        $this->assertTrue($statusParam['required']);
    }

    #[Test]
    public function it_handles_dynamic_rules()
    {
        // Skip test if using file-based analyzer
        if (method_exists($this->analyzer, 'extractRules') && ! class_exists('LaravelSpectrum\Analyzers\AST\Visitors\RulesExtractorVisitor')) {
            $this->markTestSkipped('Current implementation cannot handle dynamic rules');
        }

        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                $baseRules = ['name' => 'required|string'];

                if ($this->isMethod('POST')) {
                    $baseRules['email'] = 'required|email|unique:users';
                }

                return array_merge($baseRules, $this->additionalRules());
            }

            private function additionalRules(): array
            {
                return ['age' => 'integer|min:0'];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $nameParam = $this->findParameterByName($parameters, 'name');
        $this->assertNotNull($nameParam);
        $this->assertTrue($nameParam['required']);
    }

    #[Test]
    public function it_handles_php8_match_expression()
    {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('PHP 8.0+ required');
        }

        // Skip test if using file-based analyzer
        if (method_exists($this->analyzer, 'extractRules') && ! class_exists('LaravelSpectrum\Analyzers\AST\Visitors\RulesExtractorVisitor')) {
            $this->markTestSkipped('Current implementation cannot handle match expressions');
        }

        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return match ($this->method()) {
                    'POST' => ['name' => 'required|string', 'email' => 'required|email'],
                    'PUT' => ['name' => 'sometimes|required|string'],
                    default => []
                };
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $this->assertNotEmpty($parameters);
    }

    #[Test]
    public function it_handles_nested_array_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'user.name' => 'required|string',
                    'user.email' => 'required|email',
                    'address.street' => 'required|string',
                    'address.city' => 'required|string',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $userNameParam = $this->findParameterByName($parameters, 'user.name');
        $this->assertNotNull($userNameParam);
        $this->assertTrue($userNameParam['required']);
        $this->assertEquals('string', $userNameParam['type']);
    }

    #[Test]
    public function it_handles_wildcard_array_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'items' => 'required|array',
                    'items.*.name' => 'required|string',
                    'items.*.quantity' => 'required|integer|min:1',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $itemsParam = $this->findParameterByName($parameters, 'items');
        $this->assertNotNull($itemsParam);
        $this->assertTrue($itemsParam['required']);
        $this->assertEquals('array', $itemsParam['type']);

        $itemNameParam = $this->findParameterByName($parameters, 'items.*.name');
        $this->assertNotNull($itemNameParam);
        $this->assertTrue($itemNameParam['required']);
        $this->assertEquals('string', $itemNameParam['type']);
    }

    private function findParameterByName(array $parameters, string $name): ?array
    {
        foreach ($parameters as $parameter) {
            if ($parameter['name'] === $name) {
                return $parameter;
            }
        }

        return null;
    }
}
