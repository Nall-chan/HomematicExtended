<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateDisplayStatusAnzeige(): void
    {
        $this->validateModule(__DIR__ . '/../DisplayStatusAnzeige');
    }

    public function testValidateePaperStatusAnzeige(): void
    {
        $this->validateModule(__DIR__ . '/../ePaperStatusAnzeige');
    }

    public function testValidateHomeMaticScript(): void
    {
        $this->validateModule(__DIR__ . '/../HomeMaticScript');
    }

    public function testValidatePowerMeter(): void
    {
        $this->validateModule(__DIR__ . '/../PowerMeter');
    }
    public function testValidateProgramme(): void
    {
        $this->validateModule(__DIR__ . '/../Programme');
    }
    public function testValidateRFInterface(): void
    {
        $this->validateModule(__DIR__ . '/../RFInterface');
    }
    public function testValidateRFInterfaceConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../RFInterfaceConfigurator');
    }
    public function testValidateRFInterfaceSplitter(): void
    {
        $this->validateModule(__DIR__ . '/../RFInterfaceSplitter');
    }
    public function testValidateSystemvariablen(): void
    {
        $this->validateModule(__DIR__ . '/../Systemvariablen');
    }
    public function testValidateWRInterface(): void
    {
        $this->validateModule(__DIR__ . '/../WRInterface');
    }
}