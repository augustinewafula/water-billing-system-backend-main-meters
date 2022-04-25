<?php
namespace App\Traits;

trait SetsEnvironmentalValue
{
    public function setEnvironmentValue($envKey, $envValue): void
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);

        $oldValue = env($envKey);

        $str = str_replace("$envKey=$oldValue", "$envKey=$envValue", $str);

        $fp = fopen($envFile, 'wb');
        fwrite($fp, $str);
        fclose($fp);
    }
}
