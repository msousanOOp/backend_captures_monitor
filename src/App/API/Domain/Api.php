<?php

namespace Monitor\App\API\Domain;

use Sohris\Core\Utils;

final class Api
{
    private string $key;
    private string $api_url;
    private string $jwt_token;
    private string $version;

    public function __construct()
    {
        $this->key = Utils::getConfigFiles('system')['key'];
        $this->api_url = Utils::getConfigFiles('system')['api_url'];
        $this->jwt_token = Utils::getConfigFiles('system')['jwt_token'];
        $this->version = \Composer\InstalledVersions::getRootPackage()['pretty_version'];
    }

    public function key()
    {
        return $this->key;
    }
    public function url()
    {
        return $this->api_url;
    }
    public function version()
    {
        return $this->version;
    }
    public function token()
    {
        return $this->jwt_token;
    }
}
