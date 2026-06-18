<?php

namespace App\Libraries\Drivers;

use App\Libraries\TelnetService;

/**
 * Driver untuk OLT Fiberhome (AN5516, AN5006, dll)
 * TODO: Implement command set Fiberhome
 */
class FiberhomeDriver implements OltDriverInterface
{
    private TelnetService $telnet;
    private array $config;

    private array $rootPrompt = ['User#', 'FH#', '#'];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->telnet = new TelnetService();
    }

    public function connect(): void
    {
        $this->telnet->connect(
            $this->config['ip'],
            (int)($this->config['telnet_port'] ?? 23),
            $this->config['telnet_user'],
            $this->config['telnet_pass']
        );
    }

    public function disconnect(): void
    {
        $this->telnet->disconnect();
    }

    public function getUnconfiguredOnus(): array
    {
        // FH command: show pon-onu auto-find
        $output = $this->telnet->execute('show pon-onu auto-find all', $this->rootPrompt, 15);
        return $this->parseUncfgOutput($output);
    }

    private function parseUncfgOutput(string $output): array
    {
        $onus  = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            // Fiberhome format: slot/port/id  SERIAL  type
            if (preg_match('/(\d+)\/(\d+)\/(\d+)\s+([A-Za-z0-9]{8,20})\s+(\S+)/', $line, $m)) {
                $onus[] = [
                    'board'     => '1',
                    'slot'      => $m[1],
                    'port'      => $m[2],
                    'onu_index' => $m[3],
                    'sn'        => strtoupper($m[4]),
                    'state'     => 'auto-found',
                ];
            }
        }
        return $onus;
    }

    public function getRegisteredOnus(): array
    {
        // TODO: implement untuk Fiberhome
        return [];
    }

    public function registerOnu(array $params): array
    {
        // TODO: implement command set Fiberhome
        // Fiberhome menggunakan CLI yang berbeda dari ZTE
        throw new \Exception("Fiberhome driver belum diimplementasikan. Coming soon.");
    }

    public function deleteOnu(string $board, string $slot, string $port, string $onuIndex): bool
    {
        throw new \Exception("Fiberhome driver belum diimplementasikan.");
    }

    public function getOnuSignal(string $board, string $slot, string $port, string $onuIndex): array
    {
        return ['rx' => null, 'tx' => null];
    }

    public function getTcontProfiles(): array { return []; }
    public function getTrafficProfiles(): array { return []; }
    public function getVlanProfiles(): array { return []; }
    public function applyPonMng(string $board, string $slot, string $port, string $onuIndex, int $vlanAcs, string $acsUrl, int $vlanInternet = 0, string $pppoeUser = '', string $pppoePass = ''): array { return ['success' => true, 'log' => ['Fiberhome: pon-onu-mng not applicable']]; }
    public function getSnAtIndex(string $board, string $slot, string $port, string $onuIndex): ?string { return null; }
    public function getOnuConfig(string $board, string $slot, string $port, string $onuIndex): array
    {
        return ['tcont_profile' => '', 'traffic_profile' => '', 'vlan_internet' => 0, 'vlan_acs' => 0, 'service_ports' => []];
    }

    public function getBrand(): string { return 'Fiberhome'; }
    public function getModel(): string { return $this->config['model'] ?? 'AN5516'; }
}
