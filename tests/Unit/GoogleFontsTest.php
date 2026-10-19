<?php

namespace bensomething\placard\tests\Unit;

use bensomething\placard\services\GoogleFonts;
use PHPUnit\Framework\TestCase;

class GoogleFontsTest extends TestCase
{
    public function testParsesAFamilyWithAndWithoutAWeight(): void
    {
        $this->assertSame(['Fraunces', null], GoogleFonts::parse('Fraunces'));
        $this->assertSame(['Playfair Display', 700], GoogleFonts::parse(' Playfair  Display:700 '));
    }

    public function testRejectsAnythingButAFamilyAndWeight(): void
    {
        $this->assertNull(GoogleFonts::parse(''));
        $this->assertNull(GoogleFonts::parse('Fraunces:bold'));
        $this->assertNull(GoogleFonts::parse('Fraunces:ital,wght@1,700'));
        $this->assertNull(GoogleFonts::parse('Fraunces&text=Hi'));
    }

    public function testFindsTheTtfInAStylesheet(): void
    {
        $css = <<<CSS
            @font-face {
              font-family: 'Fraunces';
              font-style: normal;
              font-weight: 700;
              src: url(https://fonts.gstatic.com/s/fraunces/v38/6NUh8FyLNQ.ttf) format('truetype');
            }
            CSS;

        $this->assertSame('https://fonts.gstatic.com/s/fraunces/v38/6NUh8FyLNQ.ttf', GoogleFonts::fontUrl($css));
    }

    public function testIgnoresFontsFromOtherHostsAndFormats(): void
    {
        $this->assertNull(GoogleFonts::fontUrl("src: url(https://example.com/font.ttf) format('truetype');"));
        $this->assertNull(GoogleFonts::fontUrl("src: url(https://fonts.gstatic.com/s/x/v1/a.woff2) format('woff2');"));
        // What Google sends for a family it won't give as one file.
        $this->assertNull(GoogleFonts::fontUrl(''));
    }
}
