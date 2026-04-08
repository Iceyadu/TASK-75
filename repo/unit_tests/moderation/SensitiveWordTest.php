<?php
declare(strict_types=1);

namespace unit_tests\moderation;

use PHPUnit\Framework\TestCase;

/**
 * Tests for sensitive word detection.
 *
 * The detection must:
 *   - Match exact words (case-insensitive)
 *   - Respect word boundaries (no false positives like "assessment" matching "ass")
 *   - Return all matches found
 */
class SensitiveWordTest extends TestCase
{
    /** Simulated sensitive word list */
    private const SENSITIVE_WORDS = ['spam', 'scam', 'ass', 'damn', 'fraud'];

    /**
     * Detect sensitive words in text using word-boundary-aware matching.
     *
     * @return string[] Array of matched sensitive words
     */
    private function detectSensitiveWords(string $text): array
    {
        $matches = [];

        foreach (self::SENSITIVE_WORDS as $word) {
            // Use word boundary regex for accurate matching
            $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            if (preg_match($pattern, $text)) {
                $matches[] = $word;
            }
        }

        return $matches;
    }

    public function test_exact_word_match_detected(): void
    {
        $matches = $this->detectSensitiveWords('This is a scam listing');
        $this->assertContains('scam', $matches);
    }

    public function test_case_insensitive_match(): void
    {
        $matches = $this->detectSensitiveWords('SPAM offer in this listing');
        $this->assertContains('spam', $matches);

        $matches = $this->detectSensitiveWords('This is Fraud');
        $this->assertContains('fraud', $matches);
    }

    public function test_word_boundary_respected(): void
    {
        // "assessment" contains "ass" but should NOT trigger a match
        $matches = $this->detectSensitiveWords('This is an assessment of the ride quality');
        $this->assertNotContains('ass', $matches);

        // "damnation" contains "damn" but should NOT trigger (damn is followed by more letters)
        // Actually \b matches between "damn" and "ation" because of word character boundaries
        // Let's test with "class" containing "ass"
        $matches = $this->detectSensitiveWords('This is a class act');
        $this->assertNotContains('ass', $matches);

        // But standalone "ass" should match
        $matches = $this->detectSensitiveWords('What an ass');
        $this->assertContains('ass', $matches);
    }

    public function test_clean_text_passes(): void
    {
        $matches = $this->detectSensitiveWords('This is a perfectly clean and friendly ride listing');
        $this->assertEmpty($matches);
    }

    public function test_multiple_matches_returned(): void
    {
        $matches = $this->detectSensitiveWords('This spam fraud listing is a scam');
        $this->assertCount(3, $matches);
        $this->assertContains('spam', $matches);
        $this->assertContains('fraud', $matches);
        $this->assertContains('scam', $matches);
    }
}
