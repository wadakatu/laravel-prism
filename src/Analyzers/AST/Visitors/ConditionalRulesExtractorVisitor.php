<?php

namespace LaravelSpectrum\Analyzers\AST\Visitors;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter;

class ConditionalRulesExtractorVisitor extends NodeVisitorAbstract
{
    private PrettyPrinter\Standard $printer;

    /** @var array<string, mixed> Current variable scope */
    private array $variableScope = [];

    /** @var array<array{conditions: array, rules: array}> */
    private array $ruleSets = [];

    /** @var array<array{type: string, ...}> Current condition path */
    private array $currentPath = [];

    /** @var bool Whether we found a return statement */
    private bool $foundReturn = false;

    /** @var array<string, array> Method return values cache */
    private array $methodReturns = [];

    public function __construct(PrettyPrinter\Standard $printer)
    {
        $this->printer = $printer;
    }

    public function enterNode(Node $node): ?int
    {
        // Variable assignments
        if ($node instanceof Node\Expr\Assign) {
            $this->handleAssignment($node);
        }

        // Method calls that might return rules
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->analyzeMethod($node);
        }

        // If statements
        if ($node instanceof Node\Stmt\If_) {
            $this->handleIfStatement($node);

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        // Return statements
        if ($node instanceof Node\Stmt\Return_ && $node->expr) {
            $this->handleReturn($node->expr);

            return NodeTraverser::STOP_TRAVERSAL;
        }

        return null;
    }

    /**
     * Handle variable assignments
     */
    private function handleAssignment(Node\Expr\Assign $node): void
    {
        if (! $node->var instanceof Node\Expr\Variable) {
            return;
        }

        $varName = $node->var->name;

        // Store array assignments
        if ($node->expr instanceof Node\Expr\Array_) {
            $this->variableScope[$varName] = $this->extractArrayRules($node->expr);
        }
        // Store method call results
        elseif ($node->expr instanceof Node\Expr\MethodCall ||
                $node->expr instanceof Node\Expr\FuncCall) {
            $result = $this->evaluateExpression($node->expr);
            if ($result !== null) {
                $this->variableScope[$varName] = $result;
            }
        }
    }

    /**
     * Analyze method and cache its return value
     */
    private function analyzeMethod(Node\Stmt\ClassMethod $node): void
    {
        if (! in_array($node->name->toString(), ['additionalRules', 'baseRules', 'commonRules'])) {
            return;
        }

        $methodName = $node->name->toString();

        // Find return statements in the method
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr instanceof Node\Expr\Array_) {
                $this->methodReturns[$methodName] = $this->extractArrayRules($stmt->expr);
                break;
            }
        }
    }

    /**
     * Handle if statements with enhanced condition analysis
     */
    private function handleIfStatement(Node\Stmt\If_ $node): void
    {
        $condition = $this->analyzeCondition($node->cond);

        // Process if block
        $this->currentPath[] = $condition;
        $this->traverseStatements($node->stmts);
        array_pop($this->currentPath);

        // Process elseif blocks
        foreach ($node->elseifs as $elseif) {
            $elseifCondition = $this->analyzeCondition($elseif->cond);
            $this->currentPath[] = $elseifCondition;
            $this->traverseStatements($elseif->stmts);
            array_pop($this->currentPath);
        }

        // Process else block
        if ($node->else) {
            $this->currentPath[] = ['type' => 'else', 'description' => 'Default case'];
            $this->traverseStatements($node->else->stmts);
            array_pop($this->currentPath);
        }
    }

    /**
     * Handle return statements with complex expressions
     */
    private function handleReturn(Node\Expr $expr): void
    {
        $this->foundReturn = true;
        $rules = $this->evaluateExpression($expr);

        if ($rules !== null) {
            $this->ruleSets[] = [
                'conditions' => $this->currentPath,
                'rules' => $rules,
                'probability' => $this->calculateProbability(),
            ];
        }
    }

    /**
     * Evaluate complex expressions
     */
    private function evaluateExpression(Node\Expr $expr): ?array
    {
        // Direct array
        if ($expr instanceof Node\Expr\Array_) {
            return $this->extractArrayRules($expr);
        }

        // Variable reference
        if ($expr instanceof Node\Expr\Variable) {
            return $this->variableScope[$expr->name] ?? null;
        }

        // array_merge() calls
        if ($expr instanceof Node\Expr\FuncCall &&
            $expr->name instanceof Node\Name &&
            $expr->name->toString() === 'array_merge') {
            return $this->evaluateArrayMerge($expr);
        }

        // Method calls
        if ($expr instanceof Node\Expr\MethodCall) {
            return $this->evaluateMethodCall($expr);
        }

        // Ternary operator
        if ($expr instanceof Node\Expr\Ternary) {
            return $this->evaluateTernary($expr);
        }

        // Binary operations (array + array)
        if ($expr instanceof Node\Expr\BinaryOp\Plus) {
            return $this->evaluateArrayAddition($expr);
        }

        return null;
    }

    /**
     * Evaluate array_merge() calls
     */
    private function evaluateArrayMerge(Node\Expr\FuncCall $call): array
    {
        $merged = [];

        foreach ($call->args as $arg) {
            $value = $this->evaluateExpression($arg->value);
            if (is_array($value)) {
                $merged = array_merge($merged, $value);
            }
        }

        return $merged;
    }

    /**
     * Evaluate method calls
     */
    private function evaluateMethodCall(Node\Expr\MethodCall $call): ?array
    {
        // $this->additionalRules() pattern
        if ($call->var instanceof Node\Expr\Variable &&
            $call->var->name === 'this' &&
            $call->name instanceof Node\Identifier) {

            $methodName = $call->name->toString();

            // Check cached method returns
            if (isset($this->methodReturns[$methodName])) {
                return $this->methodReturns[$methodName];
            }

            // Common method patterns
            return match ($methodName) {
                'baseRules', 'commonRules' => ['_notice' => "Method {$methodName}() - implement in subclass"],
                default => null
            };
        }

        return null;
    }

    /**
     * Evaluate ternary expressions
     */
    private function evaluateTernary(Node\Expr\Ternary $expr): ?array
    {
        // For now, evaluate the 'if' branch (more likely to have validation)
        if ($expr->if !== null) {
            return $this->evaluateExpression($expr->if);
        }

        return $this->evaluateExpression($expr->else);
    }

    /**
     * Evaluate array addition (array + array)
     */
    private function evaluateArrayAddition(Node\Expr\BinaryOp\Plus $expr): array
    {
        $left = $this->evaluateExpression($expr->left) ?? [];
        $right = $this->evaluateExpression($expr->right) ?? [];

        // Array + preserves keys from left, adds non-existing from right
        return $left + $right;
    }

    /**
     * Enhanced condition analysis
     */
    private function analyzeCondition(Node\Expr $condition): array
    {
        // HTTP method checks
        if ($this->isHttpMethodCheck($condition)) {
            return $this->extractHttpMethodCondition($condition);
        }

        // User role/permission checks
        if ($this->isUserCheck($condition)) {
            return $this->extractUserCondition($condition);
        }

        // Request field checks
        if ($this->isRequestFieldCheck($condition)) {
            return $this->extractRequestFieldCondition($condition);
        }

        // Rule::when() pattern
        if ($this->isRuleWhenPattern($condition)) {
            return $this->extractRuleWhenCondition($condition);
        }

        // Generic condition
        return [
            'type' => 'custom',
            'expression' => $this->printer->prettyPrintExpr($condition),
        ];
    }

    /**
     * Check if condition is HTTP method check
     */
    private function isHttpMethodCheck(Node\Expr $expr): bool
    {
        if (! $expr instanceof Node\Expr\MethodCall) {
            return false;
        }

        $method = $expr->name instanceof Node\Identifier ? $expr->name->toString() : '';

        return in_array($method, ['isMethod', 'method']);
    }

    /**
     * Extract HTTP method from condition
     */
    private function extractHttpMethodCondition(Node\Expr $call): array
    {
        if (! $call instanceof Node\Expr\MethodCall) {
            return [
                'type' => 'http_method',
                'method' => null,
                'expression' => '',
            ];
        }

        $method = null;

        if (isset($call->args[0]) &&
            $call->args[0]->value instanceof Node\Scalar\String_) {
            $method = $call->args[0]->value->value;
        }

        return [
            'type' => 'http_method',
            'method' => $method,
            'expression' => $this->printer->prettyPrintExpr($call),
        ];
    }

    /**
     * Check if condition is user-related
     */
    private function isUserCheck(Node\Expr $expr): bool
    {
        if (! $expr instanceof Node\Expr\MethodCall) {
            return false;
        }

        // Check for $this->user()->method() pattern
        if ($expr->var instanceof Node\Expr\MethodCall &&
            $expr->var->var instanceof Node\Expr\Variable &&
            $expr->var->var->name === 'this' &&
            $expr->var->name instanceof Node\Identifier &&
            $expr->var->name->toString() === 'user') {
            return true;
        }

        return false;
    }

    /**
     * Extract user condition details
     */
    private function extractUserCondition(Node\Expr $call): array
    {
        if (! $call instanceof Node\Expr\MethodCall) {
            return [
                'type' => 'user_check',
                'method' => 'unknown',
                'expression' => '',
            ];
        }

        $method = $call->name instanceof Node\Identifier ? $call->name->toString() : 'unknown';

        return [
            'type' => 'user_check',
            'method' => $method,
            'expression' => $this->printer->prettyPrintExpr($call),
        ];
    }

    /**
     * Check if condition is request field check
     */
    private function isRequestFieldCheck(Node\Expr $expr): bool
    {
        // $this->has('field') or $this->filled('field')
        if ($expr instanceof Node\Expr\MethodCall &&
            $expr->name instanceof Node\Identifier &&
            in_array($expr->name->toString(), ['has', 'filled', 'missing'])) {
            return true;
        }

        // $this->input('field') == 'value'
        if ($expr instanceof Node\Expr\BinaryOp &&
            $expr->left instanceof Node\Expr\MethodCall &&
            $expr->left->name instanceof Node\Identifier &&
            $expr->left->name->toString() === 'input') {
            return true;
        }

        return false;
    }

    /**
     * Extract request field condition
     */
    private function extractRequestFieldCondition(Node\Expr $expr): array
    {
        if ($expr instanceof Node\Expr\MethodCall) {
            $field = null;
            if (isset($expr->args[0]) &&
                $expr->args[0]->value instanceof Node\Scalar\String_) {
                $field = $expr->args[0]->value->value;
            }

            return [
                'type' => 'request_field',
                'check' => $expr->name->toString(),
                'field' => $field,
                'expression' => $this->printer->prettyPrintExpr($expr),
            ];
        }

        return [
            'type' => 'request_field',
            'expression' => $this->printer->prettyPrintExpr($expr),
        ];
    }

    /**
     * Check for Rule::when() pattern
     */
    private function isRuleWhenPattern(Node\Expr $expr): bool
    {
        return $expr instanceof Node\Expr\StaticCall &&
               $expr->class instanceof Node\Name &&
               $expr->class->toString() === 'Rule' &&
               $expr->name instanceof Node\Identifier &&
               $expr->name->toString() === 'when';
    }

    /**
     * Extract Rule::when condition
     */
    private function extractRuleWhenCondition(Node\Expr $call): array
    {
        if (! $call instanceof Node\Expr\StaticCall) {
            return [
                'type' => 'rule_when',
                'expression' => '',
            ];
        }

        return [
            'type' => 'rule_when',
            'expression' => $this->printer->prettyPrintExpr($call),
        ];
    }

    private function traverseStatements(array $statements): void
    {
        $traverser = new NodeTraverser;
        $traverser->addVisitor($this);
        $traverser->traverse($statements);
    }

    /**
     * Extract rules from array node with enhanced handling
     */
    private function extractArrayRules(Node\Expr\Array_ $array): array
    {
        $rules = [];

        foreach ($array->items as $item) {
            if (! $item || ! isset($item->key)) {
                continue;
            }

            $key = $this->evaluateKey($item->key);
            $value = $this->evaluateRuleValue($item->value);

            if ($key !== null && $value !== null) {
                $rules[$key] = $value;
            }
        }

        return $rules;
    }

    private function evaluateKey(Node $expr): ?string
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Node\Scalar\LNumber) {
            return (string) $expr->value;
        }

        // Handle other expressions
        if ($expr instanceof Node\Expr) {
            return $this->printer->prettyPrintExpr($expr);
        }

        return null;
    }

    /**
     * Evaluate rule value with Rule class support
     */
    private function evaluateRuleValue(Node\Expr $expr)
    {
        // String rules
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        // Array of rules
        if ($expr instanceof Node\Expr\Array_) {
            $rules = [];
            foreach ($expr->items as $item) {
                if (! $item) {
                    continue;
                }

                $value = $this->evaluateSingleRule($item->value);
                $rules[] = $value;
            }

            return $rules;
        }

        // Single rule
        return $this->evaluateSingleRule($expr);
    }

    /**
     * Evaluate single rule with Rule:: support
     */
    private function evaluateSingleRule(Node\Expr $expr): string
    {
        // String rule
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }

        // Rule::in(['a', 'b'])
        if ($expr instanceof Node\Expr\StaticCall &&
            $expr->class instanceof Node\Name &&
            $expr->class->toString() === 'Rule') {
            return $this->evaluateRuleStaticCall($expr);
        }

        // Rule::when($condition, 'required')
        if ($expr instanceof Node\Expr\MethodCall &&
            $this->isRuleMethodChain($expr)) {
            return $this->evaluateRuleMethodChain($expr);
        }

        // new Enum(StatusEnum::class)
        if ($expr instanceof Node\Expr\New_) {
            return $this->printer->prettyPrintExpr($expr);
        }

        // Concatenation
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            $left = $this->evaluateSingleRule($expr->left);
            $right = $this->evaluateSingleRule($expr->right);

            return $left.$right;
        }

        // Default: convert to string
        return $this->printer->prettyPrintExpr($expr);
    }

    /**
     * Evaluate Rule:: static calls
     */
    private function evaluateRuleStaticCall(Node\Expr\StaticCall $call): string
    {
        $method = $call->name instanceof Node\Identifier ? $call->name->toString() : '';

        switch ($method) {
            case 'in':
                return $this->extractRuleIn($call);
            case 'exists':
                return $this->extractRuleExists($call);
            case 'unique':
                return $this->extractRuleUnique($call);
            case 'requiredIf':
                return $this->extractRuleRequiredIf($call);
            case 'when':
                return 'sometimes'; // Simplified for now
            default:
                return $this->printer->prettyPrintExpr($call);
        }
    }

    /**
     * Extract Rule::in() values
     */
    private function extractRuleIn(Node\Expr\StaticCall $call): string
    {
        if (! isset($call->args[0])) {
            return 'in:';
        }

        $arg = $call->args[0]->value;

        // Array of values
        if ($arg instanceof Node\Expr\Array_) {
            $values = [];
            foreach ($arg->items as $item) {
                if ($item && isset($item->value) && $item->value instanceof Node\Scalar\String_) {
                    $values[] = $item->value->value;
                }
            }

            return 'in:'.implode(',', $values);
        }

        return 'in:...';
    }

    /**
     * Extract Rule::exists()
     */
    private function extractRuleExists(Node\Expr\StaticCall $call): string
    {
        if (isset($call->args[0]) &&
            $call->args[0]->value instanceof Node\Scalar\String_) {
            return 'exists:'.$call->args[0]->value->value;
        }

        return 'exists:...';
    }

    /**
     * Extract Rule::unique()
     */
    private function extractRuleUnique(Node\Expr\StaticCall $call): string
    {
        if (isset($call->args[0]) &&
            $call->args[0]->value instanceof Node\Scalar\String_) {
            return 'unique:'.$call->args[0]->value->value;
        }

        return 'unique:...';
    }

    /**
     * Extract Rule::requiredIf()
     */
    private function extractRuleRequiredIf(Node\Expr\StaticCall $call): string
    {
        $parts = ['required_if'];

        foreach ($call->args as $i => $arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                $parts[] = $arg->value->value;
            }
        }

        return implode(':', $parts);
    }

    /**
     * Check if expression is Rule method chain
     */
    private function isRuleMethodChain(Node\Expr $expr): bool
    {
        // TODO: Implement method chain detection
        return false;
    }

    /**
     * Evaluate Rule method chain
     */
    private function evaluateRuleMethodChain(Node\Expr\MethodCall $call): string
    {
        // TODO: Implement method chain evaluation
        return $this->printer->prettyPrintExpr($call);
    }

    private function calculateProbability(): float
    {
        // Simple probability calculation based on condition depth
        return 1.0 / pow(2, count($this->currentPath));
    }

    public function getRuleSets(): array
    {
        return [
            'rules_sets' => $this->ruleSets,
            'merged_rules' => $this->mergeAllRules(),
            'has_conditions' => $this->hasConditionalRules(),
        ];
    }

    private function mergeAllRules(): array
    {
        $merged = [];

        foreach ($this->ruleSets as $set) {
            foreach ($set['rules'] as $field => $rules) {
                if (! isset($merged[$field])) {
                    $merged[$field] = [];
                }

                $rulesList = is_string($rules) ? explode('|', $rules) : $rules;
                $merged[$field] = array_unique(array_merge($merged[$field], $rulesList));
            }
        }

        return $merged;
    }

    /**
     * Check if conditional rules were found
     */
    public function hasConditionalRules(): bool
    {
        return $this->foundReturn && ! empty($this->currentPath);
    }
}
