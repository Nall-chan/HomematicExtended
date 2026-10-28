<?php

declare(strict_types=1);
/**
 * @addtogroup HomeMaticExtended
 * @{
 *
 * @file          module.php
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2015-2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       3.75
 */
require_once __DIR__ . '/../libs/HMBase.php';  // HMBase Klasse
require_once __DIR__ . '/../libs/HMTypes.php';  // HMTypes Data
require_once __DIR__ . '/../libs/HMSystemVariables.php';  // Scripte und Parser für Systemvariablen

/**
 * HomeMaticExtendedConfigurator ist die Klasse für das IPS-Modul 'HomeMatic Extended Configurator'.
 * Erweitert HMBase.
 */
class HomeMaticExtendedConfigurator extends HMBase
{
    protected const SummaryShowsHost = true;
    private const IORFOpen = 'RFOpen';
    private const IOWROpen = 'WROpen';
    private const IOGROpen = 'GROpen';
    private const IOIPOpen = 'IPOpen';
    /**
     * Protokolle des Sockets, welche dieser Konfigurator nutzt (Property des Sockets => Beschriftung).
     */
    private const ProtocolCaptions = [
        self::IORFOpen => 'HomeMatic BidCos-RF',
        self::IOIPOpen => 'HomeMatic IP',
        self::IOGROpen => 'HomeMatic groups'
    ];

    /**
     * Namen und Räume der CCU-Geräte, je Interface und Adresse.
     * Gilt nur für einen Aufruf von GetConfigurationForm und liegt deshalb nicht im Buffer der Instanz (Buffer sind auf 256 kB begrenzt).
     *
     * @var array
     */
    private array $DeviceData = [];

    /**
     * Adresse und Typ aller Geräte und Kanäle aus listDevices, je Protokoll. Gilt nur für einen Aufruf von GetConfigurationForm.
     *
     * @var array
     */
    private array $listDevices = [];
    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterHMPropertys('XXX9999994');
        $this->RegisterPropertyBoolean(\HMExtended\Device\Property::EmulateStatus, false);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
    }
    //################# PUBLIC

    /**
     * Interne Funktion des SDK.
     * Meldungen beim Lesen der CCU würden das Formular zerstören. Sie werden abgefangen, per Debug ausgegeben und im Formular angezeigt.
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        ob_start();
        try {
            $Form = $this->BuildForm($Form);
        } finally {
            $Errors = trim((string) ob_get_clean());
        }
        if ($Errors !== '') {
            $this->SendDebug('FORM errors', $Errors, 0);
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => $Errors
                    ]]
                ]
            ];
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    /**
     * Liefert die Instanzen eines Moduls, die am Homematic Socket dieses Konfigurators hängen.
     *
     * @param string $GUID GUID des Moduls.
     * @param string|null $ConfigParam Name einer Eigenschaft, deren Wert statt der Zahl geliefert wird.
     *
     * @return array Instanz-ID => Wert der Eigenschaft.
     */
    protected function GetInstanceList(string $GUID, ?string $ConfigParam = null): array
    {
        $InstanceIDList = array_filter(IPS_GetInstanceListByModuleID($GUID), [$this, 'FilterInstances']);
        if ($ConfigParam != null) {
            $InstanceIDList = array_flip(array_values($InstanceIDList));
            array_walk($InstanceIDList, [$this, 'GetConfigParam'], $ConfigParam);
        }
        return $InstanceIDList;
    }

    /**
     * Filter für array_filter: Instanzen mit demselben Homematic Socket wie dieser Konfigurator.
     *
     * @param int $InstanceID Instanz-ID.
     *
     * @return bool true, wenn die Instanz zum selben Socket gehört.
     */
    protected function FilterInstances(int $InstanceID): bool
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
    }

    /**
     * Callback für array_walk: ersetzt den Eintrag durch den Wert einer Eigenschaft der Instanz.
     *
     * @param mixed $item1 Wert, wird ersetzt.
     * @param int $InstanceID Instanz-ID.
     * @param string $ConfigParam Name der Eigenschaft.
     */
    protected function GetConfigParam(mixed &$item1, int $InstanceID, string $ConfigParam): void
    {
        $item1 = IPS_GetProperty($InstanceID, $ConfigParam);
    }

    //################# protected
    /**
     * Füllt das Formular mit den Geräten der CCU und den vorhandenen Instanzen.
     *
     * @param array $Form Das Formular aus form.json.
     *
     * @return array Das gefüllte Formular.
     */
    private function BuildForm(array $Form): array
    {
        $ParentId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ((!$this->HasActiveParent()) || ($ParentId == 0)) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Instance has no active parent instance!'
                    ]]
                ]
            ];
            return $Form;
        }
        // Bei jedem Aufruf neu von der CCU lesen, sonst fehlen neu angelernte Geräte.
        $this->listDevices = [];
        $this->DeviceData = $this->LoadDeviceData();
        $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigInstance(\HMExtended\GUID::Programme));
        $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigInstance(\HMExtended\GUID::RemoteScript));
        $Gateways = $this->LoadGatewayConfig();
        $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRFInterfaces($ParentId, $Gateways['rfd'] ?? []));
        $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigSystemVariables());
        $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigAlarms($this->LoadSystemVariables()));
        // Module mit Geräten auf mehreren Protokollen werden in einem Durchgang gelesen, damit Instanzen ohne Gerät nur einmal erscheinen.
        $RadioAndIp = [];
        if (@IPS_GetProperty($ParentId, self::IORFOpen)) {
            $RadioAndIp[] = \HMExtended\CCU::ProtocolBidCosRF;
        }
        if (@IPS_GetProperty($ParentId, self::IOIPOpen)) {
            $RadioAndIp[] = \HMExtended\CCU::ProtocolHmIP;
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(\HMExtended\CCU::ProtocolHmIP, \HMExtended\GUID::Weather));
        }
        if (count($RadioAndIp)) {
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows($RadioAndIp, \HMExtended\GUID::Powermeter));
        }
        if (@IPS_GetProperty($ParentId, self::IORFOpen)) {
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(0, \HMExtended\GUID::Dis_WM55));
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(0, \HMExtended\GUID::Dis_EP_WM55));
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(0, \HMExtended\GUID::HeatingDevice));
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(0, \HMExtended\GUID::ClimacontrolRegulator));
        }
        if (@IPS_GetProperty($ParentId, self::IOWROpen)) {
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigInstance(\HMExtended\GUID::WR_Interface, $Gateways['hs485d'] ?? []));
        }
        if (@IPS_GetProperty($ParentId, self::IOGROpen)) {
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(3, \HMExtended\GUID::HeatingGroupHmIP));
            $Form['actions'][0]['values'] = array_merge($Form['actions'][0]['values'], $this->GetConfigRows(3, \HMExtended\GUID::HeatingGroup));
        }
        $DisabledProtocols = [];
        foreach (self::ProtocolCaptions as $Property => $Caption) {
            if (!@IPS_GetProperty($ParentId, $Property)) {
                $DisabledProtocols[] = '- ' . $this->Translate($Caption);
            }
        }
        if (count($DisabledProtocols)) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [
                        [
                            'type'    => 'Label',
                            'caption' => 'These protocols are not enabled in the I/O, the matching devices cannot be listed:'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => implode("\n", $DisabledProtocols)
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'caption'  => 'Open I/O instance',
                            'objectID' => $ParentId
                        ]
                    ]
                ]
            ];
        }
        $Form['actions'][0]['rowCount'] = count($Form['actions'][0]['values']) + 1;
        return $Form;
    }

    /**
     * Liefert die Zeilen für Module mit fester Adresse (Programme, RemoteScript, WR-Interface). Ist keine Instanz vorhanden, wird eine Zeile zum Anlegen geliefert.
     *
     * @param string $GUID GUID des Moduls.
     *
     * @return array Zeilen für das Element Konfigurator.
     */
    private function GetConfigInstance(string $GUID, array $Gateways = []): array
    {
        if (!array_key_exists($GUID, \HMExtended\Device\Address::$GuidToStaticAddress)) {
            return [];
        }
        $IPSDevices = $this->GetInstanceList($GUID, \HMExtended\Device\Property::Address);
        $Devices = [];
        foreach ($IPSDevices as $InstanceID => $Address) {
            $Devices[] = [
                'instanceID'      => $InstanceID,
                'address'         => '',
                'name'            => IPS_GetName($InstanceID),
                'longname'        => IPS_GetName($InstanceID),
                'type'            => IPS_GetModule($GUID)['ModuleName'],
                'create'          => [
                    'moduleID'      => $GUID,
                    'location'      => [],
                    'configuration' => [
                        \HMExtended\Device\Property::Address      => \HMExtended\Device\Address::$GuidToStaticAddress[$GUID],
                        \HMExtended\Device\Property::Protocol     => 0,
                        \HMExtended\Device\Property::EmulateStatus=> false
                    ]
                ]
            ];
        }
        if (!count($Devices)) {
            $Devices[] = [
                'instanceID'      => 0,
                'address'         => '',
                'name'            => IPS_GetModule($GUID)['ModuleName'],
                'longname'        => IPS_GetModule($GUID)['ModuleName'],
                'type'            => IPS_GetModule($GUID)['ModuleName'],
                'create'          => [
                    'moduleID'      => $GUID,
                    'location'      => [],
                    'configuration' => [
                        \HMExtended\Device\Property::Address      => \HMExtended\Device\Address::$GuidToStaticAddress[$GUID],
                        \HMExtended\Device\Property::Protocol     => 0,
                        \HMExtended\Device\Property::EmulateStatus=> false
                    ]
                ]
            ];

        }
        // Das WR-Interface ist eine Instanz je CCU. Ist ein Wired-Gateway eingerichtet, werden dessen Daten angezeigt.
        if (($GUID == \HMExtended\GUID::WR_Interface) && count($Gateways)) {
            $Gateway = $Gateways[0];
            $Label = $Gateway['Name'] != '' ? $Gateway['Name'] : $Gateway['Type'];
            foreach ($Devices as &$Device) {
                $Device['address'] = $Gateway['Serial Number'];
                $Device['type'] = $Device['type'] . ' (' . $Gateway['Type'] . ', ' . $Gateway['IP Address'] . ')';
                if ($Device['instanceID'] == 0) {
                    $Device['name'] = $Label;
                    $Device['longname'] = $Label;
                }
            }
        }
        return $Devices;
    }

    /**
     * Liest die eingerichteten Gateways aus den Konfigurationsdateien der CCU (`rfd.conf` für Funk, `hs485d.conf` für Wired).
     * Die RPC-Anfrage `listBidcosInterfaces` meldet nur erreichbare Interfaces, hier erscheinen auch Gateways, die gerade offline sind.
     * Das Script liest nur die Felder Typ, Name, Seriennummer, IP-Adresse und Beschreibung, der Sicherheitsschlüssel wird nie gelesen.
     * Ist die Datei nicht lesbar (z.B. andere Firmware), bleibt die Liste leer, es erfolgt nur eine Debug-Ausgabe.
     *
     * @return array[] Dateiname (`rfd`, `hs485d`) => Gateways.
     */
    private function LoadGatewayConfig(): array
    {
        $Fields = "'^.Interface|^Type|^Name|^Serial Number|^IP Address|^Description'";
        $Script = 'string o; string e;
            system.Exec("echo @@rfd; grep -E ' . $Fields . ' /etc/config/rfd.conf; echo @@hs485d; grep -E ' . $Fields . ' /etc/config/hs485d.conf", &o, &e);
            WriteLine(o); o = 0; e = 0;';
        // Optionale Zusatzabfrage: Fehler nur per Debug.
        ob_start();
        $Result = $this->LoadHMScript($Script);
        $Errors = (string) ob_get_clean();
        if ($Result === false) {
            $this->SendDebug('Gateway config', 'not available ' . $Errors, 0);
            return [];
        }
        $Gateways = \HMExtended\ScriptParser::ParseGatewayConfig(\HMExtended\ScriptParser::SplitOutput($Result)[0]);
        $this->SendDebug('Gateway config', $Gateways, 0);
        return $Gateways;
    }

    /**
     * Liefert die Zeilen der RF-Interfaces (Gateways) der CCU.
     * Quellen sind die RPC-Anfrage `listBidcosInterfaces` (erreichbare Interfaces) und `rfd.conf` (eingerichtete Interfaces, auch offline).
     * Das Interface wird mit einer Kette angelegt: RF Interface, RF Interface Splitter, Homematic Socket.
     * Die Kette ist bei allen Interfaces gleich, Symcon ordnet bereits vorhandene Instanzen beim Erzeugen automatisch zu.
     *
     * @param int     $ParentId Instanz-ID des Homematic Sockets.
     * @param array[] $Configured Die eingerichteten Funk-Gateways aus `rfd.conf`.
     */
    private function GetConfigRFInterfaces(int $ParentId, array $Configured = []): array
    {
        $Existing = [];
        foreach (IPS_GetInstanceListByModuleID(\HMExtended\GUID::RF_Interface) as $InstanceID) {
            $SplitterId = IPS_GetInstance($InstanceID)['ConnectionID'];
            if (($SplitterId > 0) && (IPS_GetInstance($SplitterId)['ConnectionID'] == $ParentId)) {
                $Existing[$InstanceID] = IPS_GetProperty($InstanceID, \HMExtended\Device\Property::Address);
            }
        }
        $IoConfiguration = json_decode(IPS_GetConfiguration($ParentId)) ?? new stdClass();
        $SplitterConfiguration = new stdClass();
        foreach (IPS_GetInstanceListByModuleID(\HMExtended\GUID::RF_Interface_Splitter) as $SplitterId) {
            if (IPS_GetInstance($SplitterId)['ConnectionID'] == $ParentId) {
                $SplitterConfiguration = json_decode(IPS_GetConfiguration($SplitterId)) ?? new stdClass();
                break;
            }
        }
        $ConfiguredBySerial = [];
        foreach ($Configured as $Gateway) {
            $ConfiguredBySerial[$Gateway['Serial Number']] = $Gateway;
        }
        // Adresse => [Name, Typtext]
        $Interfaces = [];
        $Types = [
            \HMExtended\CCU::ProtocolBidCosRF => [self::IORFOpen, 'RF interface (radio)'],
            \HMExtended\CCU::ProtocolHmIP     => [self::IOIPOpen, 'RF interface (HmIP)']
        ];
        foreach ($Types as $Protocol => [$Property, $Type]) {
            if (!@IPS_GetProperty($ParentId, $Property)) {
                continue;
            }
            $Result = $this->SendRPC('listBidcosInterfaces', $Protocol, []);
            if (!is_array($Result)) {
                continue;
            }
            foreach ($Result as $Interface) {
                if (!isset($Interface['ADDRESS'])) {
                    continue;
                }
                $Gateway = $ConfiguredBySerial[$Interface['ADDRESS']] ?? null;
                $Name = ($Gateway !== null) && ($Gateway['Name'] != '') ? $Gateway['Name'] : ($Interface['TYPE'] ?? $Interface['ADDRESS']);
                $Interfaces[$Interface['ADDRESS']] = [$Name, $this->Translate($Type)];
            }
        }
        // Eingerichtete, aber nicht erreichbare Funk-Gateways
        if (@IPS_GetProperty($ParentId, self::IORFOpen)) {
            foreach ($ConfiguredBySerial as $Serial => $Gateway) {
                if (array_key_exists($Serial, $Interfaces)) {
                    continue;
                }
                $Name = $Gateway['Name'] != '' ? $Gateway['Name'] : $Gateway['Type'];
                $Interfaces[$Serial] = [$Name, $this->Translate('RF interface (radio, not connected)') . ' ' . $Gateway['Type'] . ', ' . $Gateway['IP Address']];
            }
        }
        $Rows = [];
        foreach ($Interfaces as $Address => [$Name, $TypeText]) {
            $InstanceID = array_search($Address, $Existing);
            if ($InstanceID !== false) {
                unset($Existing[$InstanceID]);
                $Name = IPS_GetName($InstanceID);
            }
            $Rows[] = [
                'instanceID' => $InstanceID === false ? 0 : $InstanceID,
                'address'    => $Address,
                'name'       => $Name,
                'longname'   => $Name,
                'type'       => $TypeText,
                'create'     => [
                    [
                        'moduleID'      => \HMExtended\GUID::RF_Interface,
                        'configuration' => [\HMExtended\Device\Property::Address => $Address]
                    ],
                    // Jedes Element der Kette braucht ein Objekt `configuration`, ein leeres Array wäre der falsche Typ.
                    // Splitter und Socket tragen die Konfiguration der vorhandenen Instanzen, damit Symcon sie wiederverwendet.
                    [
                        'moduleID'      => \HMExtended\GUID::RF_Interface_Splitter,
                        'configuration' => $SplitterConfiguration
                    ],
                    [
                        'moduleID'      => \HMExtended\GUID::HmIO,
                        'configuration' => $IoConfiguration
                    ]
                ]
            ];
        }
        foreach ($Existing as $InstanceID => $Address) {
            $Rows[] = [
                'instanceID' => $InstanceID,
                'address'    => $Address,
                'name'       => IPS_GetName($InstanceID),
                'longname'   => IPS_GetName($InstanceID),
                'type'       => $this->Translate('Device not found on the CCU')
            ];
        }
        return $Rows;
    }

    /**
     * Liefert die Zeilen für alle Geräte eines Moduls, die auf der CCU gefunden wurden, sowie Zeilen für Instanzen ohne Gerät.
     *
     * @param int|int[] $Protocols Das System der CCU, bei Modulen für mehrere Systeme eine Liste.
     * @param string $GUID GUID des Moduls.
     *
     * @return array Zeilen für das Element Konfigurator.
     */
    private function GetConfigRows(int|array $Protocols, string $GUID): array
    {
        $CreateParams = [
            'moduleID'      => $GUID,
            'configuration' => [],
            'location'      => []
        ];

        $IPSDevices = $this->GetInstanceList($GUID, \HMExtended\Device\Property::Address);
        // Hat die Instanz einen Ausgabe-Kanal in der Adresse, so wird über die Geräteadresse (ohne Kanal) verglichen.
        $DefaultChannel = \HMExtended\DeviceType::$GuidToDefaultChannel[$GUID] ?? '';
        $IPSCompareAddresses = $DefaultChannel == '' ? $IPSDevices : array_map(function (string $Address)
        {
            $Pos = strpos($Address, ':');
            return $Pos === false ? $Address : substr($Address, 0, $Pos);
        }, $IPSDevices);
        $Rows = [];
        foreach ((array) $Protocols as $Protocol) {
            $Devices = $this->GetDevices($Protocol, \HMExtended\DeviceType::$GuidToType[$GUID]);
            $this->SendDebug('Devices', $Devices, 0);
            foreach ($Devices as $Device) {
                $Device = array_change_key_case($Device);
                unset($Device['parent']);
                $Device['create'] = $CreateParams;
                $Device['create']['configuration'] = [
                    \HMExtended\Device\Property::Address      => $Device['address'] . $DefaultChannel,
                    \HMExtended\Device\Property::Protocol     => $Protocol,
                    \HMExtended\Device\Property::EmulateStatus=> false
                ];

                $InstanceID = array_search($Device['address'], $IPSCompareAddresses);

                if ($InstanceID !== false) {
                    unset($IPSDevices[$InstanceID]);
                    $Device['instanceID'] = $InstanceID;
                    $Device['name'] = IPS_GetName($InstanceID);
                    $Device['longname'] = IPS_GetName($InstanceID);
                } else {
                    $Device['instanceID'] = 0;
                    $this->GetDeviceData($Protocol, $Device);
                }
                $Rows[] = $Device;
            }
        }
        foreach ($IPSDevices as $InstanceID => $Address) {
            $Rows[] = [
                'instanceID'      => $InstanceID,
                'address'         => $Address,
                'name'            => IPS_GetName($InstanceID),
                'longname'        => IPS_GetName($InstanceID),
                'type'            => $this->GetUnmatchedInstanceHint($Address)
            ];
        }
        return $Rows;
    }

    /**
     * Liest alle Systemvariablen der CCU mit einem HM-Script.
     *
     * @return array[] CCU-ID => Eigenschaften (siehe SystemVariables::ParseList), bei einem Fehler leer.
     */
    private function LoadSystemVariables(): array
    {
        $Result = $this->LoadHMScript(\HMExtended\SystemVariables::ListScript());
        if ($Result === false) {
            return [];
        }
        return \HMExtended\SystemVariables::ParseList(\HMExtended\ScriptParser::SplitOutput($Result)[0]);
    }

    /**
     * Liefert die Zeilen der Instanzen des Moduls Systemvariablen. Ist keine Instanz vorhanden, wird eine Zeile zum Anlegen geliefert.
     * Die Systemvariablen werden in der Instanz gewählt, mehrere Instanzen sind möglich.
     *
     * @return array Zeilen für das Element Konfigurator.
     */
    private function GetConfigSystemVariables(): array
    {
        $GUID = \HMExtended\GUID::Systemvariablen;
        $Create = [
            'moduleID'      => $GUID,
            'location'      => [],
            'configuration' => [
                \HMExtended\Device\Property::Protocol      => 0,
                \HMExtended\Device\Property::EmulateStatus => false
            ]
        ];
        $Rows = [];
        foreach ($this->GetInstanceList($GUID, \HMExtended\Device\Property::Address) as $InstanceID => $Address) {
            $Rows[] = [
                'instanceID' => $InstanceID,
                'address'    => (\HMExtended\SystemVariables::ProtocolOfKey($Address) !== false) ? $Address : '',
                'name'       => IPS_GetName($InstanceID),
                'longname'   => IPS_GetName($InstanceID),
                'type'       => IPS_GetModule($GUID)['ModuleName'],
                'create'     => $Create
            ];
        }
        if (!count($Rows)) {
            $Rows[] = [
                'instanceID' => 0,
                'address'    => '',
                'name'       => IPS_GetModule($GUID)['ModuleName'],
                'longname'   => IPS_GetModule($GUID)['ModuleName'],
                'type'       => IPS_GetModule($GUID)['ModuleName'],
                'create'     => $Create
            ];
        }
        return $Rows;
    }

    /**
     * Liefert je Alarmvariable der CCU eine Zeile (Modul Alarmvariable) sowie Zeilen für Instanzen, deren Alarmvariable fehlt.
     *
     * @param array[] $SystemVariables Alle Systemvariablen der CCU.
     *
     * @return array Zeilen für das Element Konfigurator.
     */
    private function GetConfigAlarms(array $SystemVariables): array
    {
        $GUID = \HMExtended\GUID::Alarmvariable;
        $Instances = $this->GetInstanceList($GUID, \HMExtended\Device\Property::AlarmID);
        $Type = IPS_GetModule($GUID)['ModuleName'];
        $Rows = [];
        foreach ($SystemVariables as $Id => $Variable) {
            if (!\HMExtended\SystemVariables::IsAlarm($Variable)) {
                continue;
            }
            // Die Spalte Adresse zeigt den Namen auf der CCU, die Instanz kann anders heißen. Interne Alarme (z.B. WatchDog) werden gekennzeichnet.
            $Row = [
                'instanceID' => 0,
                'address'    => $Variable['Name'],
                'name'       => $Variable['Name'],
                'longname'   => $Variable['Name'],
                'type'       => $Variable['Internal'] ? $Type . ' (' . $this->Translate('internal') . ')' : $Type,
                'create'     => [
                    'moduleID'      => $GUID,
                    'location'      => [],
                    'configuration' => [
                        \HMExtended\Device\Property::AlarmID       => $Id,
                        \HMExtended\Device\Property::Protocol      => 0,
                        \HMExtended\Device\Property::EmulateStatus => false
                    ]
                ]
            ];
            $InstanceID = array_search($Id, $Instances);
            if ($InstanceID !== false) {
                unset($Instances[$InstanceID]);
                $Row['instanceID'] = $InstanceID;
                $Row['name'] = IPS_GetName($InstanceID);
                $Row['longname'] = IPS_GetName($InstanceID);
            }
            $Rows[] = $Row;
        }
        foreach ($Instances as $InstanceID => $AlarmId) {
            $Rows[] = [
                'instanceID' => $InstanceID,
                'address'    => '',
                'name'       => IPS_GetName($InstanceID),
                'longname'   => IPS_GetName($InstanceID),
                'type'       => $this->Translate('Alarm variable not found on the CCU')
            ];
        }
        return $Rows;
    }

    /**
     * Liefert einen Hinweis für Instanzen, zu denen kein Gerät auf der CCU gefunden wurde.
     * Das betrifft auch Instanzen, welche nicht auf die neue Adresse migriert werden konnten.
     */
    private function GetUnmatchedInstanceHint(string $Address): string
    {
        if (($Address == '') || str_starts_with($Address, 'XXX')) {
            return $this->Translate('No device address configured (migration required)');
        }
        return $this->Translate('Device not found on the CCU');
    }

    /**
     * Trägt Name und Raum aus der CCU in die Zeile ein. Der Raum bestimmt den Ort der neuen Instanz.
     *
     * @param int $Protocol Das System der CCU.
     * @param array $Device Die Zeile, wird direkt geändert.
     */
    private function GetDeviceData(int $Protocol, array &$Device): void
    {
        $InterfaceString = \HMExtended\CCU::$Interfaces[$Protocol];
        if (isset($this->DeviceData[$InterfaceString][$Device['address']])) {
            $DeviceData = $this->DeviceData[$InterfaceString][$Device['address']];
            $Device['name'] = $DeviceData['Name'];
            if ($DeviceData['Room'] != '') {
                $Device['create']['location'] = [$DeviceData['Room']];
                $Device['longname'] = $DeviceData['Name'] .
        '(' . $DeviceData['Room'] . ')';
            } else {
                $Device['longname'] = $DeviceData['Name'];
            }
        } else {
            $Device['longname'] = $Device['type'];
            $Device['name'] = $Device['type'];
        }
    }

    /**
     * Liefert die Geräte der CCU mit den gewünschten Typen. Das Ergebnis von listDevices wird je Protokoll zwischengespeichert.
     *
     * @param int $Protocol Das System der CCU.
     * @param array $Types Gerätetypen, z.B. `HM-Dis-WM55`.
     *
     * @return array Die Geräte, bei einem Fehler ein leeres Array.
     */
    private function GetDevices(int $Protocol, array $Types): array
    {
        if (!array_key_exists($Protocol, $this->listDevices)) {
            $Devices = $this->SendRPC('listDevices', $Protocol, []);
            if (!is_array($Devices)) {
                return [];
            }
            // Für die Zeilen werden nur Adresse und Typ gebraucht, die übrigen Felder (CHILDREN, PARAMSETS, …) blähen das Formular auf.
            $Devices = array_map(fn ($Device) => ['ADDRESS' => $Device['ADDRESS'] ?? '', 'TYPE' => $Device['TYPE'] ?? ''], $Devices);
            $this->listDevices[$Protocol] = $Devices;
        } else {
            $Devices = $this->listDevices[$Protocol];
        }
        $Result = [];
        foreach ($Types as $Type) {
            $Result = array_merge(
                $Result,
                array_filter($Devices, function ($Device) use ($Type)
                {
                    return $Device['TYPE'] == $Type;
                })
            );
        }
        return array_values($Result);
    }

    /**
     * Liest Namen und Räume aller Geräte und Kanäle per HM-Script von der CCU.
     *
     * @return array Interface => Adresse => [Name, Raum].
     */
    private function LoadDeviceData(): array
    {
        $Values = [];
        $Script = 'string did; string cid; string rid;
		foreach (did, dom.GetObject(ID_DEVICES).EnumUsedIDs())
		{
		  object o = dom.GetObject(did);
          string i = dom.GetObject(o.Interface());
          WriteLine(i # "\t" # o.Address() # "\t" # o.Name());
		  foreach (cid, o.Channels())
		  {
			object c = dom.GetObject(cid);
			Write(i # "\t" # c.Address() # "\t" # c.Name());
			foreach (rid, c.ChnRoom())
			{
			  Write("\t" # dom.GetObject(rid).Name());
			}
			WriteLine("");
		  }
		}
		did = 0; cid = 0; rid = 0; o = 0; c = 0; i = 0;';
        $ret = $this->LoadHMScript($Script);
        if ($ret === false) {
            return $Values;
        }
        // Spalten: Interface, Adresse, Name, Raum (Raum nur bei Kanälen)
        foreach (\HMExtended\ScriptParser::ParseTable(\HMExtended\ScriptParser::SplitOutput($ret)[0]) as $data) {
            if (count($data) < 3) {
                continue;
            }
            $Values[$data[0]][$data[1]] = [
                'Name' => $data[2],
                'Room' => $data[3] ?? ''
            ];
        }
        return $Values;
    }
}

/* @} */
