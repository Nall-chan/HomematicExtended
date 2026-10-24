<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/HMScriptParser.php';

use HMExtended\ScriptParser;
use PHPUnit\Framework\TestCase;

/**
 * Prüft den Parser der CCU-Antworten ohne Symcon-Abhängigkeit, mit Antworten im Format der CCU.
 */
class ScriptParserTest extends TestCase
{
    /** Antwort der CCU auf ein Script mit Variablen (Mitschnitt, anonymisiert). */
    private const Response = '<xml><exec>/symcon.exe</exec><sessionId></sessionId><httpUserAgent>user-agent: Symcon/9.1 Revision/rust-796 Platform/Windows</httpUserAgent><DisplayKeySubmit>20518</DisplayKeySubmit><State>false</State></xml>';

    public function testResponseWithoutOutput(): void
    {
        [$Output, $Xml] = ScriptParser::SplitOutput(self::Response);
        $this->assertSame('', $Output);
        $this->assertSame(self::Response, $Xml);
        $Parsed = new SimpleXMLElement($Xml);
        $this->assertSame('false', (string) $Parsed->State);
        $this->assertSame('20518', (string) $Parsed->DisplayKeySubmit);
    }

    public function testOutputIsSeparatedFromXml(): void
    {
        [$Output, $Xml] = ScriptParser::SplitOutput("1234\tProgramm 1\r\n5678\tProgramm 2\r\n" . self::Response);
        $this->assertSame("1234\tProgramm 1\r\n5678\tProgramm 2\r\n", $Output);
        $this->assertSame(self::Response, $Xml);
    }

    public function testResponseWithoutXml(): void
    {
        $this->assertSame(['nur Text', ''], ScriptParser::SplitOutput('nur Text'));
        $this->assertSame(['', ''], ScriptParser::SplitOutput(''));
    }

    public function testTableOfPrograms(): void
    {
        $Rows = ScriptParser::ParseTable("1234\tHaustürklingel\r\n5678\tDummy für Tasten\r\n");
        $this->assertSame([['1234', 'Haustürklingel'], ['5678', 'Dummy für Tasten']], $Rows);
    }

    public function testTableOfDevicesWithAndWithoutRoom(): void
    {
        $Output = "BidCos-RF\tLEQ1323301\tMein Display\r\n" .
            "BidCos-RF\tLEQ1323301:1\tMein Display:1\tKüche\r\n" .
            "BidCos-RF\tLEQ1323301:2\tMein Display:2\tKüche\tFlur\r\n";
        $Rows = ScriptParser::ParseTable($Output);
        $this->assertCount(3, $Rows);
        $this->assertSame(['BidCos-RF', 'LEQ1323301', 'Mein Display'], $Rows[0]);
        $this->assertSame('Küche', $Rows[1][3]);
        $this->assertSame(5, count($Rows[2]));
    }

    public function testEmptyLinesAndLineBreaksAreIgnored(): void
    {
        $this->assertSame([], ScriptParser::ParseTable(''));
        $this->assertSame([], ScriptParser::ParseTable("\r\n\r\n"));
        $this->assertSame([['a', 'b'], ['c']], ScriptParser::ParseTable("a\tb\n\nc\r\n"));
    }

    public function testEmptyColumnsAreKept(): void
    {
        $this->assertSame([['1', '', 'x']], ScriptParser::ParseTable("1\t\tx\r\n"));
    }

    public function testGatewayConfigIsParsedPerFile(): void
    {
        $Output = "@@rfd\r\n[Interface 0]\r\nType = CCU2\r\nDescription = CCU2-Coprocessor\r\n[Interface 1]\r\nType = HMLGW2\r\nName = Schlafzimmer\r\nSerial Number = MEQ0460859\r\nIP Address = 192.168.201.203\r\n\r\n@@hs485d\r\n[Interface 0]\r\nType = HMWLGW\r\nName = \r\nSerial Number = KEQ0112340\r\nIP Address = 192.168.201.201\r\n";
        $Result = \HMExtended\ScriptParser::ParseGatewayConfig($Output);
        $this->assertSame(['rfd', 'hs485d'], array_keys($Result));
        $this->assertCount(1, $Result['rfd'], 'The coprocessor has no serial number and is ignored');
        $this->assertSame('MEQ0460859', $Result['rfd'][0]['Serial Number']);
        $this->assertSame('Schlafzimmer', $Result['rfd'][0]['Name']);
        $this->assertSame('192.168.201.203', $Result['rfd'][0]['IP Address']);
        $this->assertSame('HMWLGW', $Result['hs485d'][0]['Type']);
        $this->assertSame('', $Result['hs485d'][0]['Name']);
    }

    public function testGatewayConfigIgnoresKeysAndUnknownFields(): void
    {
        $Output = "@@rfd\n[Interface 1]\nType = HMLGW2\nSerial Number = MEQ0000001\nEncryption Key = SECRET\nAES Key = SECRET2\nIP Address = 10.0.0.1\n";
        $Result = \HMExtended\ScriptParser::ParseGatewayConfig($Output);
        $this->assertStringNotContainsString('SECRET', json_encode($Result));
        $this->assertSame(['Type', 'Name', 'Serial Number', 'IP Address', 'Description'], array_keys($Result['rfd'][0]));
    }

    public function testGatewayConfigWithoutDataIsEmpty(): void
    {
        $this->assertSame([], \HMExtended\ScriptParser::ParseGatewayConfig("Fehler\r\n"));
        $this->assertSame(['rfd' => []], \HMExtended\ScriptParser::ParseGatewayConfig("@@rfd\n"));
    }
}
