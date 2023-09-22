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

        if (empty($this->key))
            $this->key = getenv("SNOOP_KEY");
        if (empty($this->api_url))
            $this->api_url = getenv("SNOOP_API");
        if (empty($this->jwt_token))
            $this->jwt_token = getenv("SNOOP_TOKEN");

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
