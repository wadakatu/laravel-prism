<?php

namespace LaravelSpectrum\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class QueryParameterDetector
{
    private array $detectedParams = [];

    private array $variableAssignments = [];

    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * Parse method and detect request calls
     */
    public function parseMethod(\ReflectionMethod $method): ?array
    {
        try {
            // Get method source code directly
            $fileName = $method->getFileName();
            $startLine = $method->getStartLine() - 1;
            $endLine = $method->getEndLine();
            $length = $endLine - $startLine;

            if (! $fileName) {
                return null;
            }

            $source = file($fileName);
            $methodSource = implode('', array_slice($source, $startLine, $length));

            // Wrap in a class for parsing
            $code = "<?php\nclass TempClass {\n".$methodSource."\n}";

            $ast = $this->parser->parse($code);
            if (! $ast) {
                return null;
            }

            // Find the method node in our temporary class
            foreach ($ast as $node) {
                if ($node instanceof Node\Stmt\Class_) {
                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof Node\Stmt\ClassMethod &&
                            $stmt->name->toString() === $method->getName()) {
                            return [$stmt];
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }


    /**
     * Detect Request method calls from AST
     */
    public function detectRequestCalls(array $ast): array
    {
        $this->detectedParams = [];
        $this->variableAssignments = [];

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new class($this) extends NodeVisitorAbstract
        {
            public function __construct(
                private QueryParameterDetector $detector
            ) {}

            public function enterNode(Node $node)
            {
                // Track variable assignments
                if ($node instanceof Node\Expr\Assign) {
                    $this->detector->trackVariableAssignment($node);
                }

                // Detect method calls on $request variable
                if ($node instanceof MethodCall &&
                    $node->var instanceof Variable &&
                    $node->var->name === 'request') {
                    $this->detector->processMethodCall($node);
                }

                // Detect static calls to Request
                if ($node instanceof StaticCall &&
                    $this->isRequestClass($node->class)) {
                    $this->detector->processStaticCall($node);
                }

                // Detect property fetch (magic access)
                if ($node instanceof PropertyFetch &&
                    $node->var instanceof Variable &&
                    $node->var->name === 'request') {
                    $this->detector->processMagicAccess($node);
                }

                // Detect null coalescing with request property
                if ($node instanceof Node\Expr\BinaryOp\Coalesce &&
                    $node->left instanceof PropertyFetch &&
                    $node->left->var instanceof Variable &&
                    $node->left->var->name === 'request') {
                    $this->detector->processCoalesceWithRequest($node);
                }

                // Track context for enum detection
                if ($node instanceof FuncCall &&
                    $node->name instanceof Node\Name &&
                    $node->name->toString() === 'in_array') {
                    $this->detector->processInArrayCall($node);
                }

                // Track if/switch for context
                if ($node instanceof If_ || $node instanceof Switch_) {
                    $this->detector->analyzeConditionalContext($node);
                }

                return null;
            }

            private function isRequestClass($node): bool
            {
                if ($node instanceof Node\Name) {
                    $name = $node->toString();

                    return in_array($name, ['Request', 'Illuminate\\Http\\Request', '\\Illuminate\\Http\\Request']);
                }

                return false;
            }
        });

        $traverser->traverse($ast);

        return $this->consolidateParameters();
    }

    /**
     * Process method call on $request
     */
    public function processMethodCall(MethodCall $node): void
    {
        $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if (! $methodName || ! $this->isRequestMethod($methodName)) {
            return;
        }

        $args = $node->args;
        if (empty($args)) {
            return;
        }

        $paramName = $this->extractStringValue($args[0]->value);
        if (! $paramName) {
            return;
        }

        $param = [
            'name' => $paramName,
            'method' => $methodName,
            'default' => isset($args[1]) ? $this->extractValue($args[1]->value) : null,
            'context' => [],
        ];

        // Special handling for has() and filled()
        if (in_array($methodName, ['has', 'filled'])) {
            $param['context'][$methodName.'_check'] = true;
        }

        $this->detectedParams[] = $param;
    }

    /**
     * Process static call to Request
     */
    public function processStaticCall(StaticCall $node): void
    {
        $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if (! $methodName || ! $this->isRequestMethod($methodName)) {
            return;
        }

        $args = $node->args;
        if (empty($args)) {
            return;
        }

        $paramName = $this->extractStringValue($args[0]->value);
        if (! $paramName) {
            return;
        }

        $this->detectedParams[] = [
            'name' => $paramName,
            'method' => $methodName,
            'default' => isset($args[1]) ? $this->extractValue($args[1]->value) : null,
            'context' => [],
        ];
    }

    /**
     * Process magic property access
     */
    public function processMagicAccess(PropertyFetch $node): void
    {
        $propName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if (! $propName) {
            return;
        }

        $this->detectedParams[] = [
            'name' => $propName,
            'method' => 'magic',
            'default' => null,
            'context' => [],
        ];
    }

    /**
     * Process null coalescing with request property
     */
    public function processCoalesceWithRequest(Node\Expr\BinaryOp\Coalesce $node): void
    {
        if ($node->left instanceof PropertyFetch &&
            $node->left->name instanceof Node\Identifier) {

            $propName = $node->left->name->toString();
            $default = $this->extractValue($node->right);

            // Check if we already have this parameter
            $found = false;
            foreach ($this->detectedParams as &$param) {
                if ($param['name'] === $propName && $param['method'] === 'magic') {
                    $param['default'] = $default;
                    $found = true;
                    break;
                }
            }

            // If not found, add it
            if (! $found) {
                $this->detectedParams[] = [
                    'name' => $propName,
                    'method' => 'magic',
                    'default' => $default,
                    'context' => [],
                ];
            }
        }
    }

    /**
     * Process in_array calls for enum detection
     */
    public function processInArrayCall(FuncCall $node): void
    {
        if (count($node->args) < 2) {
            return;
        }

        // Check if first argument references a request parameter
        $needle = $node->args[0]->value;
        $paramName = null;

        if ($needle instanceof MethodCall &&
            $needle->var instanceof Variable &&
            $needle->var->name === 'request') {
            $paramName = $this->extractStringValue($needle->args[0]->value ?? null);
        } elseif ($needle instanceof Variable) {
            // Try to trace back the variable to a request call
            $paramName = $this->traceVariableToRequest($needle->name);
        }

        if (! $paramName) {
            return;
        }

        // Extract enum values from array
        $enumValues = $this->extractArrayValues($node->args[1]->value);

        if ($enumValues) {
            $this->updateParameterContext($paramName, ['enum_values' => $enumValues]);
        }
    }

    /**
     * Analyze conditional context for parameter usage
     */
    public function analyzeConditionalContext(Node $node): void
    {
        if ($node instanceof If_) {
            // Check if condition involves request has/filled
            if ($node->cond instanceof MethodCall &&
                $node->cond->var instanceof Variable &&
                $node->cond->var->name === 'request') {
                $method = $node->cond->name instanceof Node\Identifier ? $node->cond->name->toString() : null;
                if (in_array($method, ['has', 'filled']) && ! empty($node->cond->args)) {
                    $paramName = $this->extractStringValue($node->cond->args[0]->value);
                    if ($paramName) {
                        $this->updateParameterContext($paramName, [$method.'_check' => true]);
                    }
                }
            }
        } elseif ($node instanceof Switch_ && $node->cond instanceof Variable) {
            // Check if switch is on a request parameter
            $varName = $node->cond->name;
            $paramName = $this->traceVariableToRequest($varName);

            if ($paramName) {
                // Get existing enum values if any
                $existingEnumValues = [];
                foreach ($this->detectedParams as $param) {
                    if ($param['name'] === $paramName && isset($param['context']['enum_values'])) {
                        $existingEnumValues = $param['context']['enum_values'];
                        break;
                    }
                }

                $enumValues = $existingEnumValues;
                foreach ($node->cases as $case) {
                    if ($case->cond) {
                        $value = $this->extractValue($case->cond);
                        if ($value !== null && ! in_array($value, $enumValues)) {
                            $enumValues[] = $value;
                        }
                    }
                }
                if (! empty($enumValues)) {
                    $this->updateParameterContext($paramName, ['enum_values' => $enumValues]);
                }
            }
        }
    }

    /**
     * Check if method name is a Request method we're interested in
     */
    private function isRequestMethod(string $method): bool
    {
        return in_array($method, [
            'input', 'query', 'get', 'post',
            'has', 'filled', 'missing',
            'boolean', 'bool',
            'integer', 'int',
            'float', 'double',
            'string', 'array', 'date',
            'only', 'except', 'all',
        ]);
    }

    /**
     * Extract string value from node
     */
    private function extractStringValue(?Node $node): ?string
    {
        if (! $node) {
            return null;
        }

        if ($node instanceof String_) {
            return $node->value;
        }

        return null;
    }

    /**
     * Extract value from node (string, number, bool, array)
     */
    private function extractValue(?Node $node)
    {
        if (! $node) {
            return null;
        }

        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof LNumber) {
            return $node->value;
        }

        if ($node instanceof DNumber) {
            return $node->value;
        }

        if ($node instanceof ConstFetch) {
            $name = $node->name->toString();
            if ($name === 'true') {
                return true;
            }
            if ($name === 'false') {
                return false;
            }
            if ($name === 'null') {
                return null;
            }
        }

        if ($node instanceof Array_) {
            return $this->extractArrayValues($node);
        }

        return null;
    }

    /**
     * Extract array values
     */
    private function extractArrayValues(Node $node): ?array
    {
        if (! $node instanceof Array_) {
            return null;
        }

        $values = [];
        /** @var (?ArrayItem)[] $items */
        $items = $node->items;
        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }
            $value = $this->extractValue($item->value);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }


    /**
     * Track variable assignments
     */
    public function trackVariableAssignment(Node\Expr\Assign $node): void
    {
        if (! ($node->var instanceof Variable)) {
            return;
        }

        $varName = $node->var->name;

        // Check if assignment is from a request method
        if ($node->expr instanceof MethodCall &&
            $node->expr->var instanceof Variable &&
            $node->expr->var->name === 'request') {

            $methodName = $node->expr->name instanceof Node\Identifier ? $node->expr->name->toString() : null;
            if ($methodName && $this->isRequestMethod($methodName) && ! empty($node->expr->args)) {
                $paramName = $this->extractStringValue($node->expr->args[0]->value);
                if ($paramName) {
                    $this->variableAssignments[$varName] = $paramName;
                }
            }
        }

        // Check if assignment is from a request property (magic access)
        if ($node->expr instanceof PropertyFetch &&
            $node->expr->var instanceof Variable &&
            $node->expr->var->name === 'request' &&
            $node->expr->name instanceof Node\Identifier) {

            $this->variableAssignments[$varName] = $node->expr->name->toString();
        }
    }

    /**
     * Trace variable back to request call
     */
    private function traceVariableToRequest(string $varName): ?string
    {
        // Check our tracked assignments
        if (isset($this->variableAssignments[$varName])) {
            return $this->variableAssignments[$varName];
        }

        return null;
    }

    /**
     * Update parameter context
     */
    private function updateParameterContext(string $paramName, array $context): void
    {
        foreach ($this->detectedParams as &$param) {
            if ($param['name'] === $paramName) {
                $param['context'] = array_merge($param['context'], $context);
            }
        }
    }

    /**
     * Consolidate detected parameters
     */
    private function consolidateParameters(): array
    {
        $consolidated = [];
        $seen = [];

        foreach ($this->detectedParams as $param) {
            $key = $param['name'];

            if (! isset($seen[$key])) {
                $seen[$key] = $param;
            } else {
                // Merge contexts and keep most specific type
                $seen[$key]['context'] = array_merge($seen[$key]['context'], $param['context']);

                // Prefer typed methods over generic ones
                if ($this->isTypedMethod($param['method']) && ! $this->isTypedMethod($seen[$key]['method'])) {
                    $seen[$key]['method'] = $param['method'];
                }

                // Keep default value if one exists
                if ($param['default'] !== null && $seen[$key]['default'] === null) {
                    $seen[$key]['default'] = $param['default'];
                }
            }
        }

        return array_values($seen);
    }

    /**
     * Check if method is a typed method (returns specific type)
     */
    private function isTypedMethod(string $method): bool
    {
        return in_array($method, ['boolean', 'bool', 'integer', 'int', 'float', 'double', 'string', 'array', 'date']);
    }
}
