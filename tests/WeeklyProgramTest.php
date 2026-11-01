<?php

declare(strict_types=1);

require_once __DIR__ . '/HomematicModuleTestCase.php';
require_once __DIR__ . '/../libs/HMWeeklyProgram.php';
require_once __DIR__ . '/../WeeklyProgramHmIP/module.php';

use HMExtended\WeeklyProgram;

/**
 * Prüft das Wochenprogramm der HmIP-Aktoren: Umwandlung (Referenzwerte eines HmIP-Schaltaktors, abgeglichen mit der CCU-Oberfläche)
 * und das Modul HomeMatic IP Weekly Program.
 */
class WeeklyProgramTest extends HomematicModuleTestCase
{
    private const Address = '002560C99B959A:6';

    public function testDecodeReturnsOnlyUsedEntries(): void
    {
        $Entries = WeeklyProgram::Decode(self::master() + ['SOMETHING_ELSE' => 1]);
        $this->assertSame([1, 2, 3], array_keys($Entries));
        $this->assertSame(71, $Entries[1]['WEEKDAY']);
        $this->assertSame(10, count($Entries[1]));
    }

    public function testWeekdayMask(): void
    {
        $this->assertSame(['Sunday', 'Monday', 'Tuesday', 'Saturday'], WeeklyProgram::WeekdaysFromMask(71));
        $this->assertSame(['Sunday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], WeeklyProgram::WeekdaysFromMask(121));
        $this->assertSame(126, WeeklyProgram::WeekdaysToMask(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']));
        $this->assertSame(0, WeeklyProgram::WeekdaysToMask(['Nothing']));
    }

    public function testChannelsAndDuration(): void
    {
        $this->assertSame([0, 1, 2], WeeklyProgram::ChannelsFromMask(7));
        $this->assertSame([1], WeeklyProgram::ChannelsFromMask(2));
        $this->assertSame(1200.0, WeeklyProgram::DurationSeconds(6, 2));
        $this->assertNull(WeeklyProgram::DurationSeconds(7, 31));
        $this->assertSame(180.0, WeeklyProgram::DurationSeconds(4, 3));
    }

    public function testEncodeWritesEntriesAndClearsRemoved(): void
    {
        $Entries = WeeklyProgram::Decode(self::master());
        $Set = WeeklyProgram::Encode(array_slice($Entries, 0, 1), 3);
        $this->assertIsArray($Set);
        $this->assertSame(71, $Set['01_WP_WEEKDAY']);
        $this->assertSame(1.0, $Set['01_WP_LEVEL']);
        $this->assertSame(0, $Set['02_WP_WEEKDAY']);
        $this->assertSame(0.0, $Set['03_WP_LEVEL']);
        $this->assertArrayNotHasKey('04_WP_WEEKDAY', $Set);
    }

    public function testEncodeRejectsInvalidEntries(): void
    {
        $this->assertSame('Weekly program entry without weekday.', WeeklyProgram::Encode([['WEEKDAY' => 0]]));
        $this->assertSame('Value out of range in weekly program entry.', WeeklyProgram::Encode([['WEEKDAY' => 1, 'FIXED_HOUR' => 24]]));
        $this->assertSame('Value out of range in weekly program entry.', WeeklyProgram::Encode([['WEEKDAY' => 1, 'ASTRO_OFFSET' => -129]]));
        $this->assertSame('Invalid value in weekly program entry.', WeeklyProgram::Encode([['WEEKDAY' => 1, 'LEVEL' => 'x']]));
        $this->assertSame('Too many weekly program entries.', WeeklyProgram::Encode([['WEEKDAY' => 1], ['WEEKDAY' => 1]], 0, 1));
        $this->assertSame('Invalid weekly program entry.', WeeklyProgram::Encode(['x']));
    }

    public function testEncodeKeepsOtherFieldsWithTheirTypes(): void
    {
        $Set = WeeklyProgram::Encode([['WEEKDAY' => 2, 'LEVEL' => 1, 'LEVEL_2' => 1, 'RAMP_TIME_BASE' => '3']]);
        $this->assertSame(1.0, $Set['01_WP_LEVEL']);
        $this->assertSame(1.0, $Set['01_WP_LEVEL_2']);
        $this->assertSame(3, $Set['01_WP_RAMP_TIME_BASE']);
    }

    public function testProgramIsRead(): void
    {
        $Id = $this->createModule();
        $Program = json_decode(HM_ReadWeeklyProgram($Id), true);
        $this->assertSame([1, 2, 3], array_keys($Program));
        $this->assertSame(126, $Program[3]['WEEKDAY']);
        $this->assertSame(IS_ACTIVE, IPS_GetInstance($Id)['InstanceStatus']);
    }

    public function testWriteSendsParamsetAndClearsRemovedEntries(): void
    {
        $Id = $this->createModule();
        FakeHomematicIO::$Requests = [];
        $this->assertTrue(HM_WriteWeeklyProgram($Id, json_encode([['WEEKDAY' => 10, 'FIXED_HOUR' => 12, 'FIXED_MINUTE' => 34, 'LEVEL' => 1.0, 'TARGET_CHANNELS' => 4]])));
        $Put = array_values(array_filter(FakeHomematicIO::$Requests, fn ($Request) => ($Request['MethodName'] ?? '') == 'putParamset'));
        $this->assertCount(1, $Put);
        $this->assertSame(['002560C99B959A:6', 'MASTER'], array_slice($Put[0]['Data'], 0, 2));
        $Values = json_decode($Put[0]['Data'][2], true);
        $this->assertSame(10, $Values['01_WP_WEEKDAY']);
        $this->assertSame(0, $Values['02_WP_WEEKDAY']);
        $this->assertSame(0, $Values['03_WP_WEEKDAY']);
        $this->assertStringContainsString('"01_WP_LEVEL":1.0', $Put[0]['Data'][2]);
    }

    public function testInvalidProgramIsRejected(): void
    {
        $Id = $this->createModule();
        FakeHomematicIO::$Requests = [];
        ob_start();
        $Result = HM_WriteWeeklyProgram($Id, json_encode([['WEEKDAY' => 0]]));
        $Output = ob_get_clean();
        $this->assertFalse($Result);
        $this->assertStringContainsString('without weekday', $Output);
        $Put = array_filter(FakeHomematicIO::$Requests, fn ($Request) => ($Request['MethodName'] ?? '') == 'putParamset');
        $this->assertSame([], array_values($Put));
    }

    public function testTileContainsProgramAndTexts(): void
    {
        $Id = $this->createModule();
        $Tile = HM_GetVisualizationTile($Id);
        foreach (['/*CONFIG*/', '/*STYLE*/', '/*CORE*/'] as $Placeholder) {
            $this->assertStringNotContainsString($Placeholder, $Tile);
        }
        $this->assertStringContainsString('window.WP_CONFIG = {', $Tile);
        $this->assertStringContainsString('"WEEKDAY":126', $Tile);
        $this->assertStringContainsString('--accent-color', $Tile);
        $this->assertStringContainsString('margintop', $Tile);
        $this->assertStringContainsString('"Condition 4":"Condition 4"', $Tile);
    }

    public function testTileIsUpdatedAfterReload(): void
    {
        $Id = $this->createModule();
        FakeHomematicIO::$Requests = [];
        IPS_RequestAction($Id, 'Reload', '');
        $this->assertCount(1, $this->masterReads());
    }

    public function testTileSaveWritesProgramAndReadsItBack(): void
    {
        $Id = $this->createModule();
        FakeHomematicIO::$Requests = [];
        IPS_RequestAction($Id, 'Save', json_encode([['WEEKDAY' => 6, 'CONDITION' => 0, 'FIXED_HOUR' => 8, 'FIXED_MINUTE' => 15, 'LEVEL' => 1.0, 'TARGET_CHANNELS' => 1]]));
        $this->assertCount(1, $this->putRequests());
        $this->assertCount(1, $this->masterReads());
    }

    public function testTileSaveIsRejectedWhenEditingIsNotAllowed(): void
    {
        $Id = $this->createModule();
        IPS_SetProperty($Id, 'AllowEdit', false);
        IPS_ApplyChanges($Id);
        $this->assertStringContainsString('"editable":false', HM_GetVisualizationTile($Id));
        FakeHomematicIO::$Requests = [];
        ob_start();
        IPS_RequestAction($Id, 'Save', json_encode([['WEEKDAY' => 6, 'FIXED_HOUR' => 8]]));
        $Output = ob_get_clean();
        $this->assertStringContainsString('not allowed', $Output);
        $this->assertSame([], $this->putRequests());
    }

    public function testTileKnowsEditableFieldsAndLimit(): void
    {
        $Id = $this->createModule();
        $Tile = HM_GetVisualizationTile($Id);
        $this->assertStringContainsString('"editable":true', $Tile);
        $this->assertStringContainsString('"maxEntries":75', $Tile);
        $this->assertStringContainsString('"fields":["ASTRO_OFFSET"', $Tile);
    }

    public function testDescriptionIsReducedToTheFieldsOfOneEntry(): void
    {
        $Info = WeeklyProgram::ParseDescription(self::description());
        $this->assertSame(['LEVEL', 'RAMP_TIME_BASE', 'RAMP_TIME_FACTOR', 'LEVEL_2'], array_keys($Info));
        $this->assertSame('ENUM', $Info['RAMP_TIME_BASE']['Type']);
        $this->assertCount(8, $Info['RAMP_TIME_BASE']['List']);
        $this->assertSame([0, 7], WeeklyProgram::InfoRange($Info['RAMP_TIME_BASE']));
        $this->assertSame([0, 31], WeeklyProgram::InfoRange($Info['RAMP_TIME_FACTOR']));
    }

    public function testEncodeChecksDeviceSpecificFieldsWithTheDescription(): void
    {
        $Info = WeeklyProgram::ParseDescription(self::description());
        $Entry = ['WEEKDAY' => 2, 'RAMP_TIME_BASE' => 3, 'RAMP_TIME_FACTOR' => 5, 'LEVEL_2' => 1];
        $Set = WeeklyProgram::Encode([$Entry], 0, 75, $Info);
        $this->assertSame(3, $Set['01_WP_RAMP_TIME_BASE']);
        $this->assertSame(1.0, $Set['01_WP_LEVEL_2']);
        $this->assertSame('Value out of range in weekly program entry.', WeeklyProgram::Encode([['WEEKDAY' => 2, 'RAMP_TIME_BASE' => 8]], 0, 75, $Info));
        $this->assertSame('Value out of range in weekly program entry.', WeeklyProgram::Encode([['WEEKDAY' => 2, 'RAMP_TIME_FACTOR' => 32]], 0, 75, $Info));
        $this->assertSame('Invalid value in weekly program entry.', WeeklyProgram::Encode([['WEEKDAY' => 2, 'RAMP_TIME_FACTOR' => 'x']], 0, 75, $Info));
    }

    public function testChannelModes(): void
    {
        // Belegt am HmIP-Schaltaktor: Ruhewert 2, Kanal 1/2/3 manuell = +1/+8/+64
        $this->assertSame([false, false, false], WeeklyProgram::ManualChannels(2, 3));
        $this->assertSame([true, false, false], WeeklyProgram::ManualChannels(3, 3));
        $this->assertSame([false, true, false], WeeklyProgram::ManualChannels(10, 3));
        $this->assertSame([true, false, true], WeeklyProgram::ManualChannels(67, 3));
        $this->assertSame(1, WeeklyProgram::LockMask(1));
        $this->assertSame(8, WeeklyProgram::LockMask(2));
        $this->assertSame(73, WeeklyProgram::LockMask(7));
    }

    public function testModeIsWrittenToValuesParamset(): void
    {
        $Id = $this->createModule();
        FakeHomematicIO::$Requests = [];
        $this->assertTrue(HM_SetWeeklyProgramMode($Id, 2, 'MANU_MODE'));
        $Put = $this->putRequests();
        $this->assertCount(1, $Put);
        $this->assertSame(['002560C99B959A:6', 'VALUES'], array_slice($Put[0]['Data'], 0, 2));
        $this->assertSame(['WEEK_PROGRAM_TARGET_CHANNEL_LOCK' => 'MANU_MODE', 'WEEK_PROGRAM_TARGET_CHANNEL_LOCKS' => 8], json_decode($Put[0]['Data'][2], true));
    }

    public function testInvalidModeIsRejected(): void
    {
        $Id = $this->createModule();
        FakeHomematicIO::$Requests = [];
        ob_start();
        $First = HM_SetWeeklyProgramMode($Id, 1, 'SOMETHING');
        $Second = HM_SetWeeklyProgramMode($Id, 0, 'MANU_MODE');
        ob_end_clean();
        $this->assertFalse($First);
        $this->assertFalse($Second);
        $this->assertSame([], $this->putRequests());
    }

    public function testModesAreReadAndUpdatedByEvents(): void
    {
        $Id = $this->createModule();
        FakeHomematicIO::$RpcResponses['getParamset:VALUES'] = ['WEEK_PROGRAM_CHANNEL_LOCKS' => 3];
        $this->assertSame([true], json_decode(HM_ReadWeeklyProgramModes($Id), true));
        $Instance = IPS\InstanceManager::getInstanceInterface($Id);
        $Instance->ReceiveData(json_encode(['DeviceID' => self::Address, 'VariableName' => 'WEEK_PROGRAM_CHANNEL_LOCKS', 'VariableValue' => 2]));
        $Tile = HM_GetVisualizationTile($Id);
        $this->assertMatchesRegularExpression('/"modes":\[false\]/', $Tile);
    }

    public function testModeFromTileIsRejectedWhenEditingIsNotAllowed(): void
    {
        $Id = $this->createModule();
        IPS_SetProperty($Id, 'AllowEdit', false);
        IPS_ApplyChanges($Id);
        FakeHomematicIO::$Requests = [];
        ob_start();
        IPS_RequestAction($Id, 'Mode', json_encode(['channel' => 0, 'mode' => 'MANU_MODE']));
        ob_end_clean();
        $this->assertSame([], $this->putRequests());
    }

    /**
     * MASTER von Kanal 6, drei belegte Schaltzeitpunkte wie in der CCU-Oberfläche (gelesen am 2026-11-01).
     */
    private static function master(): array
    {
        $Master = [];
        $Defaults = ['ASTRO_OFFSET' => 0, 'ASTRO_TYPE' => 0, 'CONDITION' => 0, 'DURATION_BASE' => 0, 'DURATION_FACTOR' => 0, 'FIXED_HOUR' => 0, 'FIXED_MINUTE' => 0, 'LEVEL' => 0.0, 'TARGET_CHANNELS' => 0, 'WEEKDAY' => 0];
        for ($Index = 1; $Index <= 75; $Index++) {
            foreach ($Defaults as $Field => $Value) {
                $Master[sprintf('%02d_WP_%s', $Index, $Field)] = $Value;
            }
        }
        $Entries = [
            1 => ['WEEKDAY' => 71, 'CONDITION' => 0, 'FIXED_HOUR' => 7, 'FIXED_MINUTE' => 0, 'LEVEL' => 1.0, 'DURATION_BASE' => 6, 'DURATION_FACTOR' => 2, 'TARGET_CHANNELS' => 1],
            2 => ['WEEKDAY' => 121, 'CONDITION' => 1, 'ASTRO_TYPE' => 1, 'LEVEL' => 0.0, 'TARGET_CHANNELS' => 7],
            3 => ['WEEKDAY' => 126, 'CONDITION' => 4, 'ASTRO_TYPE' => 0, 'FIXED_HOUR' => 10, 'LEVEL' => 1.0, 'DURATION_BASE' => 7, 'DURATION_FACTOR' => 31, 'TARGET_CHANNELS' => 2]
        ];
        foreach ($Entries as $Index => $Fields) {
            foreach ($Fields as $Field => $Value) {
                $Master[sprintf('%02d_WP_%s', $Index, $Field)] = $Value;
            }
        }
        return $Master;
    }

    private function createModule(): int
    {
        $IoId = $this->createFakeIo();
        FakeHomematicIO::$RpcResponses = ['getParamset' => self::master(), 'putParamset' => []];
        $Id = $this->createDevice('HomeMaticIPWeeklyProgram', \HMExtended\GUID::WeeklyProgramHmIP, 'HM', $IoId);
        IPS_SetProperty($Id, \HMExtended\Device\Property::Address, self::Address);
        IPS_ApplyChanges($Id);
        return $Id;
    }

    private function masterReads(): array
    {
        return array_values(array_filter(FakeHomematicIO::$Requests, fn ($Request) => (($Request['MethodName'] ?? '') == 'getParamset') && (($Request['Data'][1] ?? '') == 'MASTER')));
    }

    private function putRequests(): array
    {
        return array_values(array_filter(FakeHomematicIO::$Requests, fn ($Request) => ($Request['MethodName'] ?? '') == 'putParamset'));
    }

    private static function description(): array
    {
        return [
            '01_WP_LEVEL'            => ['ID' => '01_WP_LEVEL', 'TYPE' => 'FLOAT', 'MIN' => 0.0, 'MAX' => 1.01, 'DEFAULT' => 0.0],
            '01_WP_RAMP_TIME_BASE'   => ['ID' => '01_WP_RAMP_TIME_BASE', 'TYPE' => 'ENUM', 'MIN' => 'BASE_100_MS', 'MAX' => 'BASE_1_H', 'DEFAULT' => 'BASE_1_H', 'VALUE_LIST' => ['BASE_100_MS', 'BASE_1_S', 'BASE_5_S', 'BASE_10_S', 'BASE_1_M', 'BASE_5_M', 'BASE_10_M', 'BASE_1_H']],
            '01_WP_RAMP_TIME_FACTOR' => ['ID' => '01_WP_RAMP_TIME_FACTOR', 'TYPE' => 'INTEGER', 'MIN' => 0, 'MAX' => 31, 'DEFAULT' => 0],
            '01_WP_LEVEL_2'          => ['ID' => '01_WP_LEVEL_2', 'TYPE' => 'FLOAT', 'MIN' => 0.0, 'MAX' => 1.01, 'DEFAULT' => 1.01],
            '02_WP_LEVEL'            => ['ID' => '02_WP_LEVEL', 'TYPE' => 'FLOAT', 'MIN' => 0.0, 'MAX' => 1.01, 'DEFAULT' => 0.0],
            'SOMETHING'              => ['ID' => 'SOMETHING', 'TYPE' => 'INTEGER']
        ];
    }
}
