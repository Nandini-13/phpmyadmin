<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Tests for methods in Sanitize class
 *
 * @package PhpMyAdmin-test
 */
/*
 * Include to test
 */
use PMA\libraries\Sanitize;

/**
 * Tests for methods in Sanitize class
 *
 * @package PhpMyAdmin-test
 */
class SanitizeTest extends PHPUnit_Framework_TestCase
{
    /**
     * Setup various pre conditions
     *
     * @return void
     */
    function setUp()
    {
    }

    /**
     * Tests for proper escaping of XSS.
     *
     * @return void
     */
    public function testXssInHref()
    {
        $this->assertEquals(
            '[a@javascript:alert(\'XSS\');@target]link</a>',
            Sanitize::sanitize('[a@javascript:alert(\'XSS\');@target]link[/a]')
        );
    }

    /**
     * Tests correct generating of link redirector.
     *
     * @return void
     */
    public function testLink()
    {
        $lang = $GLOBALS['lang'];
        $collation_connection = $GLOBALS['collation_connection'];

        unset($GLOBALS['server']);
        unset($GLOBALS['lang']);
        unset($GLOBALS['collation_connection']);
        $this->assertEquals(
            '<a href="./url.php?url=http%3A%2F%2Fwww.phpmyadmin.net%2F" target="target">link</a>',
            Sanitize::sanitize('[a@http://www.phpmyadmin.net/@target]link[/a]')
        );

        $GLOBALS['lang'] = $lang;
        $GLOBALS['collation_connection'] = $collation_connection;
    }

    /**
     * Tests links to documentation.
     *
     * @return void
     */
    public function testDoc()
    {
        $this->assertEquals(
            '<a href="./url.php?url=http%3A%2F%2Fdocs.phpmyadmin.net%2Fen%2Flatest%2Fsetup.html%23foo" target="documentation">doclink</a>',
            Sanitize::sanitize('[doc@foo]doclink[/doc]')
        );
    }

    /**
     * Tests link target validation.
     *
     * @return void
     */
    public function testInvalidTarget()
    {
        $this->assertEquals(
            '[a@./Documentation.html@INVALID9]doc</a>',
            Sanitize::sanitize('[a@./Documentation.html@INVALID9]doc[/a]')
        );
    }

    /**
     * Tests XSS escaping after valid link.
     *
     * @return void
     */
    public function testLinkDocXss()
    {
        $this->assertEquals(
            '[a@./Documentation.html" onmouseover="alert(foo)"]doc</a>',
            Sanitize::sanitize('[a@./Documentation.html" onmouseover="alert(foo)"]doc[/a]')
        );
    }

    /**
     * Tests proper handling of multi link code.
     *
     * @return void
     */
    public function testLinkAndXssInHref()
    {
        $this->assertEquals(
            '<a href="./url.php?url=http%3A%2F%2Fdocs.phpmyadmin.net%2F">doc</a>[a@javascript:alert(\'XSS\');@target]link</a>',
            Sanitize::sanitize('[a@http://docs.phpmyadmin.net/]doc[/a][a@javascript:alert(\'XSS\');@target]link[/a]')
        );
    }

    /**
     * Test escaping of HTML tags
     *
     * @return void
     */
    public function testHtmlTags()
    {
        $this->assertEquals(
            '&lt;div onclick=""&gt;',
            Sanitize::sanitize('<div onclick="">')
        );
    }

    /**
     * Tests basic BB code.
     *
     * @return void
     */
    public function testBBCode()
    {
        $this->assertEquals(
            '<strong>strong</strong>',
            Sanitize::sanitize('[strong]strong[/strong]')
        );
    }

    /**
     * Tests output escaping.
     *
     * @return void
     */
    public function testEscape()
    {
        $this->assertEquals(
            '&lt;strong&gt;strong&lt;/strong&gt;',
            Sanitize::sanitize('[strong]strong[/strong]', true)
        );
    }

    /**
     * Test for Sanitize::sanitizeFilename
     *
     * @return void
     */
    public function testSanitizeFilename()
    {
        $this->assertEquals(
            'File_name_123',
            Sanitize::sanitizeFilename('File_name 123')
        );
    }

    /**
     * Test for Sanitize::getJsValue
     *
     * @param string $key      Key
     * @param string $value    Value
     * @param string $expected Expected output
     *
     * @dataProvider variables
     *
     * @return void
     */
    public function testGetJsValue($key, $value, $expected)
    {
        $this->assertEquals($expected, Sanitize::getJsValue($key, $value));
        $this->assertEquals('foo = 100', Sanitize::getJsValue('foo', '100', false));
        $array = array('1','2','3');
        $this->assertEquals(
            "foo = [\"1\",\"2\",\"3\",];\n",
            Sanitize::getJsValue('foo', $array)
        );
        $this->assertEquals(
            "foo = \"bar\\\"baz\";\n",
            Sanitize::getJsValue('foo', 'bar"baz')
        );
    }

    /**
     * Test for Sanitize::jsFormat
     *
     * @return void
     */
    public function testJsFormat()
    {
        $this->assertEquals("`foo`", Sanitize::jsFormat('foo'));
    }

    /**
     * Provider for testFormat
     *
     * @return array
     */
    public function variables()
    {
        return array(
            array('foo', true, "foo = true;\n"),
            array('foo', false, "foo = false;\n"),
            array('foo', 100, "foo = 100;\n"),
            array('foo', 0, "foo = 0;\n"),
            array('foo', 'text', "foo = \"text\";\n"),
            array('foo', 'quote"', "foo = \"quote\\\"\";\n"),
            array('foo', 'apostroph\'', "foo = \"apostroph\\'\";\n"),
        );
    }

    /**
     * Sanitize::escapeJsString tests
     *
     * @param string $target expected output
     * @param string $source string to be escaped
     *
     * @return void
     *
     * @dataProvider escapeDataProvider
     */
    public function testEscapeJsString($target, $source)
    {
        $this->assertEquals($target, Sanitize::escapeJsString($source));
    }

    /**
     * Data provider for testEscape
     *
     * @return array data for testEscape test case
     */
    public function escapeDataProvider()
    {
        return array(
            array('\\\';', '\';'),
            array('\r\n\\\'<scrIpt></\' + \'script>', "\r\n'<scrIpt></sCRIPT>"),
            array('\\\';[XSS]', '\';[XSS]'),
            array(
                    '</\' + \'script></head><body>[HTML]',
                    '</SCRIPT></head><body>[HTML]'
            ),
            array('\"\\\'\\\\\\\'\"', '"\'\\\'"'),
            array("\\\\\'\'\'\'\'\'\'\'\'\'\'\'\\\\", "\\''''''''''''\\")
        );
    }
}
