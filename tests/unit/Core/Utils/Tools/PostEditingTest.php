<?php

namespace Matecat\Core\Utils\Tools;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Tools\PostEditing;

class PostEditingTest extends AbstractTest
{
    // -------------------------------------------------------------------------
    // getPee
    // -------------------------------------------------------------------------

    #[Test]
    public function getPee_identical_segments_returns_zero(): void
    {
        $result = PostEditing::getPee('Hello world', 'Hello world');
        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function getPee_completely_different_returns_100(): void
    {
        $result = PostEditing::getPee('Hello world', 'Foo bar baz qux quux');
        $this->assertSame(100.0, $result);
    }

    #[Test]
    public function getPee_partially_different_returns_value_in_range(): void
    {
        $result = PostEditing::getPee('Hello world', 'Hello earth');
        $this->assertGreaterThan(0.0, $result);
        $this->assertLessThan(100.0, $result);
    }

    #[Test]
    public function getPee_result_is_never_negative(): void
    {
        $result = PostEditing::getPee('', 'Hello world');
        $this->assertGreaterThanOrEqual(0.0, $result);
    }

    #[Test]
    public function getPee_result_is_never_above_100(): void
    {
        $result = PostEditing::getPee('Hello world', '');
        $this->assertLessThanOrEqual(100.0, $result);
    }

    #[Test]
    public function getPee_decodes_html_entities_before_comparison(): void
    {
        // &quot; decodes to " — identical segments after decode → 0
        $result = PostEditing::getPee('say &quot;hello&quot;', 'say "hello"');
        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function getPee_uses_fallback_language_when_null(): void
    {
        // Should not throw; null targetLang is handled via ?? 'en-US'
        $result = PostEditing::getPee('Hello world', 'Hello world', null);
        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function getPee_segments_with_tags_penalizes_mismatch(): void
    {
        $result1 = PostEditing::getPee('Hello world', 'Hello world');
        $result2 = PostEditing::getPee('<b>Hello</b> world', 'Hello world');
        // Adding a tag that is absent in the other segment adds penalty
        $this->assertGreaterThan($result1, $result2);
    }

    #[Test]
    public function getPee_case_difference_only_applies_small_penalty(): void
    {
        $result = PostEditing::getPee('Hello World', 'hello world');
        // Should be a very small PEE (near 0) — only formatting penalty
        $this->assertLessThan(10.0, $result);
    }

    // -------------------------------------------------------------------------
    // get() — exercised indirectly via getPee()
    // -------------------------------------------------------------------------

    #[Test]
    public function getPee_punctuation_mismatch_adds_penalty(): void
    {
        $baseline = PostEditing::getPee('Hello world', 'Hello world');
        $withPunct = PostEditing::getPee('Hello, world!', 'Hello world');
        $this->assertGreaterThan($baseline, $withPunct);
    }

    #[Test]
    public function getPee_number_mismatch_adds_penalty(): void
    {
        $baseline = PostEditing::getPee('Hello world', 'Hello world');
        $withNum  = PostEditing::getPee('Hello 123 world', 'Hello world');
        $this->assertGreaterThan($baseline, $withNum);
    }

    #[Test]
    public function getPee_cjk_language_tokenizes_differently(): void
    {
        $result = PostEditing::getPee('日本語テスト', '日本語テスト', 'ja');
        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function getPee_cjk_partial_match_returns_value_in_range(): void
    {
        $result = PostEditing::getPee('日本語テスト', '日本語サンプル', 'ja');
        $this->assertGreaterThan(0.0, $result);
        $this->assertLessThan(100.0, $result);
    }

    // -------------------------------------------------------------------------
    // array_xor (via reflection — protected static)
    // -------------------------------------------------------------------------

    #[Test]
    public function array_xor_returns_symmetric_difference(): void
    {
        $ref    = new \ReflectionClass(PostEditing::class);
        $method = $ref->getMethod('array_xor');
        $method->setAccessible(true);

        $result = $method->invoke(null, ['a', 'b', 'c'], ['b', 'c', 'd']);
        $this->assertEqualsCanonicalizing(['a', 'd'], $result);
    }

    #[Test]
    public function array_xor_identical_arrays_returns_empty(): void
    {
        $ref    = new \ReflectionClass(PostEditing::class);
        $method = $ref->getMethod('array_xor');
        $method->setAccessible(true);

        $result = $method->invoke(null, ['a', 'b'], ['a', 'b']);
        $this->assertSame([], $result);
    }

    #[Test]
    public function array_xor_disjoint_arrays_returns_union(): void
    {
        $ref    = new \ReflectionClass(PostEditing::class);
        $method = $ref->getMethod('array_xor');
        $method->setAccessible(true);

        $result = $method->invoke(null, ['a', 'b'], ['c', 'd']);
        $this->assertEqualsCanonicalizing(['a', 'b', 'c', 'd'], $result);
    }

    // -------------------------------------------------------------------------
    // compute_bigram (via reflection — protected static)
    // -------------------------------------------------------------------------

    #[Test]
    public function compute_bigram_single_char_returns_itself(): void
    {
        $ref    = new \ReflectionClass(PostEditing::class);
        $method = $ref->getMethod('compute_bigram');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'a');
        $this->assertSame(['a'], $result);
    }

    #[Test]
    public function compute_bigram_two_chars_returns_one_bigram(): void
    {
        $ref    = new \ReflectionClass(PostEditing::class);
        $method = $ref->getMethod('compute_bigram');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'ab');
        $this->assertSame(['ab'], $result);
    }

    #[Test]
    public function compute_bigram_three_chars_returns_two_bigrams(): void
    {
        $ref    = new \ReflectionClass(PostEditing::class);
        $method = $ref->getMethod('compute_bigram');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'abc');
        $this->assertSame(['ab', 'bc'], $result);
    }

    #[Test]
    public function compute_bigram_empty_string_returns_empty_array(): void
    {
        $ref    = new \ReflectionClass(PostEditing::class);
        $method = $ref->getMethod('compute_bigram');
        $method->setAccessible(true);

        $result = $method->invoke(null, '');
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // CJK_tokenizer (via reflection — protected static)
    // -------------------------------------------------------------------------

    #[Test]
    public function CJK_tokenizer_latin_text_splits_by_space(): void
    {
        $ref    = new \ReflectionClass(PostEditing::class);
        $method = $ref->getMethod('CJK_tokenizer');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'hello world');
        $this->assertSame(['hello', 'world'], $result);
    }

    #[Test]
    public function CJK_tokenizer_cjk_text_uses_bigrams(): void
    {
        $ref    = new \ReflectionClass(PostEditing::class);
        $method = $ref->getMethod('CJK_tokenizer');
        $method->setAccessible(true);

        // 三文字 → bigrams: 三文, 文字
        $result = $method->invoke(null, '三文字');
        $this->assertContains('三文', $result);
        $this->assertContains('文字', $result);
    }
}
