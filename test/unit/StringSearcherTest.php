<?php

class StringSearcherTest extends AbstractTest
{
    /**
     * @test
     */
    public function escapeNeedle()
    {
        $string = "Ciao, ho comprato un cane per 300USD$.";
        $expected = "\bCiao, ho comprato un cane per 300USD\b\\$\\.";
        $expected_not_esc = "Ciao, ho comprato un cane per 300USD\\$\\.";

        $this->assertEquals(StringSearcher::escapeNeedle($string), $expected);
        $this->assertEquals(StringSearcher::escapeNeedle($string, false), $expected_not_esc);

        $string = "Ciao, ho comprato un cane per 300USD$ e un'altro cane per 200USD$.   Posta elettronica:mauro@yahoo.it";
        $expected = "\bCiao, ho comprato un cane per 300USD\b\\$ \be un'altro cane per 200USD\b\\$\\.   \bPosta elettronica:mauro@yahoo\b\\.\bit\b";
        $expected_not_esc = "Ciao, ho comprato un cane per 300USD\\$ e un'altro cane per 200USD\\$\\.   Posta elettronica:mauro@yahoo\\.it";

        $this->assertEquals(StringSearcher::escapeNeedle($string), $expected);
        $this->assertEquals(StringSearcher::escapeNeedle($string, false), $expected_not_esc);
    }

    /**
     * @test
     */
    public function search_in_texts()
    {
        $haystack  = "PHP PHP is #1 the web scripting PHP language of choice.";

        $needle = "php";
        $matches = StringSearcher::search($haystack, $needle);
        $this->assertCount(3, $matches);

        $needle = "php";
        $matches = StringSearcher::search($haystack, $needle, true,true, true);
        $this->assertCount(0, $matches);

        $needle = "choice";
        $matches = StringSearcher::search($haystack, $needle, true,true, true);
        $this->assertCount(1, $matches);

        $needle = "#1";
        $matches = StringSearcher::search($haystack, $needle, true,true, false);
        $this->assertCount(1, $matches);
    }

    /**
     * @test
     */
    public function search_in_texts_with_html_entities()
    {
        $haystack  = "I bought this doll for 300USD$!";

        $needle = "$";
        $matches = StringSearcher::search($haystack, $needle, true);
        $this->assertCount(1, $matches);

        $needle = "300USD$";
        $matches = StringSearcher::search($haystack, $needle, true,false, true);
        $this->assertCount(1, $matches);

        $haystack  = "&lt;a href='##'/&gt;This is a string&lt;/a&gt; with HTML entities;&#13;&#13;They must be skipped!";

        $needle = "$";
        $matches = StringSearcher::search($haystack, $needle, true);
        $this->assertCount(0, $matches);

        $needle = "&";
        $matches = StringSearcher::search($haystack, $needle, true);
        $this->assertCount(0, $matches);

        $needle = ";";
        $matches = StringSearcher::search($haystack, $needle, true);
        $this->assertCount(1, $matches);

        $needle = "&lt;a";
        $matches = StringSearcher::search($haystack, $needle, false);
        $this->assertCount(1, $matches);

        $needle = "<a";
        $matches = StringSearcher::search($haystack, $needle, true);
        $this->assertCount(1, $matches);

        $needle = "<A";
        $matches = StringSearcher::search($haystack, $needle, true, false, true);
        $this->assertCount(0, $matches);

        $needle = "<a";
        $matches = StringSearcher::search($haystack, $needle, true, true);
        $this->assertCount(1, $matches);

        $haystack  = "&quot;This is a quotation&quot; - says the donkey.";

        $needle = "quot";
        $matches = StringSearcher::search($haystack, $needle, true);
        $this->assertCount(1, $matches);
        $matches = StringSearcher::search($haystack, $needle, true, true);
        $this->assertCount(0, $matches);

        $needle = ";";
        $matches = StringSearcher::search($haystack, $needle, true);
        $this->assertCount(0, $matches);

        $haystack  = "&quot;This is a quotation&quot; - says the donkey.";

        $needle = "&quot;";
        $matches = StringSearcher::search($haystack, $needle, false);
        $this->assertCount(2, $matches);
    }

    /**
     * @test
     */
    public function search_in_texts_with_japanese_ideograms()
    {
        $haystack = '「ハッスルの日」開催について';
        $needle = "ハッスルの日";

        $matches = StringSearcher::search($haystack, $needle, true, true);
        $this->assertCount(1, $matches);
    }

    /**
     * @test
     */
    public function search_in_texts_with_arabic_words()
    {
        $haystack = '. سعدت بلقائك.';
        $needle = "سعدت";

        $matches = StringSearcher::search($haystack, $needle, true, true);
        $this->assertCount(1, $matches);
    }
}
