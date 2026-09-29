<?php
declare(strict_types=1);

namespace App\Auth;

final class DeviceAgent
{
    public static function parse(string $agent): array
    {
        $browser = 'Unknown';
        foreach (['Edg/'=>'Edge','OPR/'=>'Opera','Firefox/'=>'Firefox','Chrome/'=>'Chrome','Safari/'=>'Safari'] as $needle=>$name) {
            if (str_contains($agent,$needle)) { $browser=$name; break; }
        }
        $os = 'Unknown';
        foreach (['Android'=>'Android','iPhone'=>'iOS','iPad'=>'iPadOS','Windows'=>'Windows','Macintosh'=>'macOS','Linux'=>'Linux'] as $needle=>$name) {
            if (str_contains($agent,$needle)) { $os=$name; break; }
        }
        return ['browser'=>$browser,'os'=>$os];
    }
}
