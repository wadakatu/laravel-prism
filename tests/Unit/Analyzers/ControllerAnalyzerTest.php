<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ControllerAnalyzerTest extends TestCase
{
    private ControllerAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = app(ControllerAnalyzer::class);
    }

    #[Test]
    public function it_detects_fractal_item_usage()
    {
        $controller = TestFractalController::class;
        $result = $this->analyzer->analyze($controller, 'show');

        $this->assertArrayHasKey('fractal', $result);
        $this->assertEquals('LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer', $result['fractal']['transformer']);
        $this->assertFalse($result['fractal']['collection']);
        $this->assertEquals('item', $result['fractal']['type']);
    }

    #[Test]
    public function it_detects_fractal_collection_usage()
    {
        $controller = TestFractalController::class;
        $result = $this->analyzer->analyze($controller, 'index');

        $this->assertArrayHasKey('fractal', $result);
        $this->assertEquals('LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer', $result['fractal']['transformer']);
        $this->assertTrue($result['fractal']['collection']);
        $this->assertEquals('collection', $result['fractal']['type']);
    }

    #[Test]
    public function it_detects_fractal_with_includes()
    {
        $controller = TestFractalController::class;
        $result = $this->analyzer->analyze($controller, 'withIncludes');

        $this->assertArrayHasKey('fractal', $result);
        $this->assertEquals('LaravelSpectrum\Tests\Fixtures\Transformers\PostTransformer', $result['fractal']['transformer']);
        $this->assertTrue($result['fractal']['hasIncludes']);
    }

    #[Test]
    public function it_detects_both_resource_and_fractal()
    {
        $controller = TestMixedController::class;
        $result = $this->analyzer->analyze($controller, 'mixed');

        // 既存のResource検出
        $this->assertArrayHasKey('resource', $result);

        // Fractal検出も動作する
        $this->assertArrayHasKey('fractal', $result);
    }
}

// テスト用のコントローラークラス
class TestFractalController
{
    public function show($id)
    {
        $user = User::find($id);

        return fractal()->item($user, new \LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer);
    }

    public function index()
    {
        $users = User::all();

        return fractal()->collection($users, new \LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer);
    }

    public function withIncludes()
    {
        $posts = Post::all();

        return fractal()
            ->collection($posts, new \LaravelSpectrum\Tests\Fixtures\Transformers\PostTransformer)
            ->parseIncludes(request()->get('include', ''))
            ->respond();
    }
}

class TestMixedController
{
    public function mixed()
    {
        if (request()->wantsJson()) {
            return fractal()->item($user, new \LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer);
        }

        return new UserResource($user);
    }
}
